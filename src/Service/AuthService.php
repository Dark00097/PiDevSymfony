<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final class AuthService
{
    public const SESSION_USER_ID = 'nexora.user_id';
    public const SESSION_USER_ROLE = 'nexora.user_role';

    private const OTP_TTL_SECONDS = 300;
    private const RESET_VERIFIED_TTL_SECONDS = 600;
    private const SESSION_SIGNUP_OTP = 'nexora.signup_otp';
    private const SESSION_RESET_OTP = 'nexora.reset_otp';
    private const SESSION_RESET_VERIFIED = 'nexora.reset_verified';
    private const FACE_DESCRIPTOR_MIN_SIZE = 64;
    private const FACE_DESCRIPTOR_MAX_SIZE = 256;

    private ?bool $biometricFaceColumnsAvailable = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly LegacyBankingSecurity $security,
        private readonly NotificationService $notificationService,
        private readonly MailerInterface $mailer,
        private readonly ActivityService $activityService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function authenticate(string $email, string $password, Request $request): ?array
    {
        $user = $this->findUserByEmail($email);
        if ($user === null) {
            return null;
        }

        if (!$this->security->verifyPassword($password, $user['password'] ?? null)) {
            return null;
        }

        $this->markUserOnline((int) $user['idUser'], $request);

        return $this->findUserById((int) $user['idUser']);
    }

    public function authenticateByFace(string $email, string $descriptorJson, Request $request): ?array
    {
        $user = $this->findUserByEmail($email);
        if ($user === null) {
            return null;
        }

        if (!$this->hasFaceBiometricEnrollment($user)) {
            return null;
        }

        $incomingDescriptor = $this->parseFaceDescriptor($descriptorJson);
        if ($incomingDescriptor === null) {
            return null;
        }

        $storedDescriptor = $this->parseFaceDescriptor((string) ($user['biometric_face_descriptor'] ?? ''));
        if ($storedDescriptor === null) {
            return null;
        }

        $distance = $this->calculateFaceDistance($incomingDescriptor, $storedDescriptor);
        if ($distance > $this->faceDistanceThreshold()) {
            return null;
        }

        $this->markUserOnline((int) $user['idUser'], $request);

        return $this->findUserById((int) $user['idUser']);
    }

    public function sendSignupOtp(string $email, SessionInterface $session, string $phone = ''): ?string
    {
        $normalizedEmail = $this->normalizeEmail($email);
        if (!filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Please enter a valid email address.');
        }
        if (trim($phone) !== '' && !$this->isValidPhone($phone)) {
            throw new \InvalidArgumentException('Please enter a valid phone number.');
        }

        if ($this->findUserByEmail($normalizedEmail) !== null) {
            throw new \InvalidArgumentException('This email is already registered.');
        }

        return $this->storeOtpAndMaybeEmail($session, self::SESSION_SIGNUP_OTP, $normalizedEmail, 'Signup verification');
    }

    public function verifySignupOtp(string $email, string $otp, SessionInterface $session, string $phone = ''): bool
    {
        return $this->verifyOtp($session, self::SESSION_SIGNUP_OTP, $email, $otp, true);
    }

    public function sendPasswordResetOtp(string $email, SessionInterface $session): ?string
    {
        $normalizedEmail = $this->normalizeEmail($email);
        if (!filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Please enter a valid email address.');
        }

        $user = $this->findUserByEmail($normalizedEmail);
        if ($user === null || strtoupper((string) ($user['role'] ?? '')) !== 'ROLE_USER') {
            throw new \InvalidArgumentException('No user account found for this email.');
        }

        $session->remove(self::SESSION_RESET_VERIFIED);

        return $this->storeOtpAndMaybeEmail($session, self::SESSION_RESET_OTP, $normalizedEmail, 'Password reset verification');
    }

    public function verifyPasswordResetOtp(string $email, string $otp, SessionInterface $session): bool
    {
        $valid = $this->verifyOtp($session, self::SESSION_RESET_OTP, $email, $otp, true);
        if ($valid) {
            $verified = $session->get(self::SESSION_RESET_VERIFIED, []);
            $verified[$this->normalizeEmail($email)] = time() + self::RESET_VERIFIED_TTL_SECONDS;
            $session->set(self::SESSION_RESET_VERIFIED, $verified);
        }

        return $valid;
    }

    public function resetPasswordByVerifiedEmail(string $email, string $newPassword, SessionInterface $session): bool
    {
        $normalizedEmail = $this->normalizeEmail($email);
        $verified = $session->get(self::SESSION_RESET_VERIFIED, []);
        $expiresAt = $verified[$normalizedEmail] ?? 0;
        if ($expiresAt < time()) {
            throw new \InvalidArgumentException('Please verify OTP first.');
        }

        $user = $this->findUserByEmail($normalizedEmail);
        if ($user === null) {
            throw new \InvalidArgumentException('No user account found for this email.');
        }

        $this->assertStrongPassword($newPassword);

        $this->connection->update('users', [
            'password' => $this->security->hashPassword($newPassword),
        ], [
            'idUser' => (int) $user['idUser'],
        ]);

        unset($verified[$normalizedEmail]);
        $session->set(self::SESSION_RESET_VERIFIED, $verified);

        return true;
    }

    public function registerUser(array $data, Request $request): array
    {
        $nom = trim((string) ($data['nom'] ?? ''));
        $prenom = trim((string) ($data['prenom'] ?? ''));
        $email = $this->normalizeEmail((string) ($data['email'] ?? ''));
        $telephone = trim((string) ($data['telephone'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if (!$this->isValidPersonName($nom)) {
            throw new \InvalidArgumentException('Please enter a valid last name.');
        }

        if (!$this->isValidPersonName($prenom)) {
            throw new \InvalidArgumentException('Please enter a valid first name.');
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Please enter a valid email address.');
        }

        if (!$this->isValidPhone($telephone)) {
            throw new \InvalidArgumentException('Please enter a valid phone number.');
        }

        $this->assertStrongPassword($password);

        if ($this->findUserByEmail($email) !== null) {
            throw new \InvalidArgumentException('This email is already registered.');
        }

        $this->connection->insert('users', [
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => $email,
            'telephone' => $telephone,
            'role' => 'ROLE_USER',
            'status' => 'PENDING',
            'password' => $this->security->hashPassword($password),
            'account_opened_from' => $this->resolveClientContext($request),
            'account_opened_location' => 'Unknown location',
            'biometric_enabled' => 0,
        ]);

        $id = (int) $this->connection->lastInsertId();
        $user = $this->findUserById($id);
        if ($user !== null) {
            $this->notificationService->notifyAdminsAboutPendingUser($user);
        }

        return $user ?? [];
    }

    public function updateProfile(int $userId, array $data): void
    {
        $existing = $this->findUserById($userId);
        if ($existing === null) {
            throw new \RuntimeException('User not found.');
        }

        $payload = [
            'nom' => trim((string) ($data['nom'] ?? $existing['nom'] ?? '')),
            'prenom' => trim((string) ($data['prenom'] ?? $existing['prenom'] ?? '')),
            'telephone' => trim((string) ($data['telephone'] ?? $existing['telephone'] ?? '')),
        ];

        if (!$this->isValidPersonName((string) $payload['nom'])) {
            throw new \InvalidArgumentException('Please enter a valid last name.');
        }

        if (!$this->isValidPersonName((string) $payload['prenom'])) {
            throw new \InvalidArgumentException('Please enter a valid first name.');
        }

        if (!$this->isValidPhone((string) $payload['telephone'])) {
            throw new \InvalidArgumentException('Please enter a valid phone number.');
        }

        if (array_key_exists('email', $data)) {
            $email = $this->normalizeEmail((string) $data['email']);
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Please enter a valid email address.');
            }

            $otherUser = $this->findUserByEmail($email);
            if ($otherUser !== null && (int) $otherUser['idUser'] !== $userId) {
                throw new \InvalidArgumentException('This email is already used by another account.');
            }

            $payload['email'] = $email;
        }

        if (array_key_exists('profile_image_path', $data)) {
            $payload['profile_image_path'] = trim((string) $data['profile_image_path']) ?: null;
        }

        if (array_key_exists('biometric_enabled', $data)) {
            $payload['biometric_enabled'] = (int) ((string) $data['biometric_enabled'] === '1');
        }

        $handlesBiometricTemplate = array_key_exists('biometric_face_descriptor', $data)
            || (string) ($data['clear_biometric_face'] ?? '0') === '1';
        if ($handlesBiometricTemplate) {
            if (!$this->ensureBiometricFaceColumns()) {
                throw new \RuntimeException('Biometric face storage is unavailable. Apply database updates first.');
            }

            if ((string) ($data['clear_biometric_face'] ?? '0') === '1') {
                $payload['biometric_face_descriptor'] = null;
                $payload['biometric_face_updated_at'] = null;
                $payload['biometric_enabled'] = 0;
            } else {
                $descriptorRaw = trim((string) ($data['biometric_face_descriptor'] ?? ''));
                if ($descriptorRaw !== '') {
                    $descriptor = $this->parseFaceDescriptor($descriptorRaw);
                    if ($descriptor === null) {
                        throw new \InvalidArgumentException('Face template is invalid. Capture your face again.');
                    }

                    $payload['biometric_face_descriptor'] = json_encode($descriptor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $payload['biometric_face_updated_at'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
                }
            }
        }

        if (($payload['biometric_enabled'] ?? (int) ($existing['biometric_enabled'] ?? 0)) === 1) {
            $storedTemplate = (string) ($payload['biometric_face_descriptor'] ?? ($existing['biometric_face_descriptor'] ?? ''));
            if (trim($storedTemplate) === '') {
                throw new \InvalidArgumentException('Capture and save your face before enabling biometric login.');
            }
        }

        $this->connection->update('users', $payload, ['idUser' => $userId]);

        $this->activityService->log($userId, 'PROFILE_UPDATE', null, 'Profile details updated.');
        if (array_key_exists('biometric_enabled', $data) && (int) ($existing['biometric_enabled'] ?? 0) !== $payload['biometric_enabled']) {
            $this->activityService->log(
                $userId,
                'BIOMETRIC_PREF',
                null,
                $payload['biometric_enabled'] === 1 ? 'Biometric login enabled.' : 'Biometric login disabled.'
            );
        }
    }

    public function markUserOnline(int $userId, Request $request): void
    {
        $context = $this->resolveClientContext($request);
        $this->connection->update('users', [
            'last_online_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'last_online_from' => $context,
        ], [
            'idUser' => $userId,
        ]);
        $this->activityService->log($userId, 'LOGIN', $context, 'User login recorded.');
    }

    public function findUserById(int $id): ?array
    {
        $user = $this->connection->fetchAssociative('SELECT * FROM users WHERE idUser = ? LIMIT 1', [$id]);

        return $user ?: null;
    }

    public function findUserByEmail(string $email): ?array
    {
        $user = $this->connection->fetchAssociative(
            'SELECT * FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1',
            [$this->normalizeEmail($email)]
        );

        return $user ?: null;
    }

    public function isFaceLoginRequired(array $user, bool $adminLoginPolicyEnabled = false): bool
    {
        if (!$this->hasFaceBiometricEnrollment($user)) {
            return false;
        }

        $isAdmin = strtoupper((string) ($user['role'] ?? '')) === 'ROLE_ADMIN';

        return $isAdmin && $adminLoginPolicyEnabled;
    }

    public function hasFaceBiometricEnrollment(array $user): bool
    {
        return trim((string) ($user['biometric_face_descriptor'] ?? '')) !== '';
    }

    public function loginUser(SessionInterface $session, array $user): void
    {
        $session->set(self::SESSION_USER_ID, (int) ($user['idUser'] ?? 0));
        $session->set(self::SESSION_USER_ROLE, strtoupper((string) ($user['role'] ?? 'ROLE_USER')));
    }

    public function logoutUser(SessionInterface $session): void
    {
        $session->remove(self::SESSION_USER_ID);
        $session->remove(self::SESSION_USER_ROLE);
    }

    public function getAuthenticatedUser(SessionInterface $session): ?array
    {
        $userId = (int) $session->get(self::SESSION_USER_ID, 0);
        if ($userId <= 0) {
            return null;
        }

        return $this->findUserById($userId);
    }

    public function getAuthenticatedRole(SessionInterface $session): string
    {
        return strtoupper((string) $session->get(self::SESSION_USER_ROLE, ''));
    }

    public function getLoginBlockReason(array $user): ?string
    {
        $role = strtoupper(trim((string) ($user['role'] ?? 'ROLE_USER')));
        $status = strtoupper(trim((string) ($user['status'] ?? 'PENDING')));

        if ($role === 'ROLE_ADMIN') {
            if ($status === 'ACTIVE') {
                return null;
            }

            if ($status === 'BANNED') {
                return 'Admin account is banned. Contact super administrator.';
            }

            return 'Admin account is inactive.';
        }

        if ($status === 'ACTIVE') {
            return null;
        }

        if ($status === 'PENDING') {
            return 'Your account is waiting for admin approval.';
        }

        if ($status === 'DECLINED') {
            return 'Your account request was declined by admin.';
        }

        if ($status === 'BANNED') {
            return 'Your account is banned. Contact support.';
        }

        return 'Your account is inactive. Contact admin.';
    }

    public function changePassword(int $userId, string $currentPassword, string $newPassword): void
    {
        $user = $this->findUserById($userId);
        if ($user === null) {
            throw new \RuntimeException('User not found.');
        }

        if (!$this->security->verifyPassword($currentPassword, (string) ($user['password'] ?? ''))) {
            throw new \InvalidArgumentException('Current password is invalid.');
        }

        $this->assertStrongPassword($newPassword);

        $this->connection->update('users', [
            'password' => $this->security->hashPassword($newPassword),
        ], [
            'idUser' => $userId,
        ]);

        $this->activityService->log($userId, 'PASSWORD_CHANGE', null, 'Password updated from the Symfony portal.');
    }

    private function storeOtpAndMaybeEmail(
        SessionInterface $session,
        string $sessionKey,
        string $email,
        string $subject
    ): ?string {
        $otp = (string) random_int(100000, 999999);
        $sent = $this->sendOtpEmail($email, $subject, $otp);
        if (!$sent) {
            throw new \RuntimeException('Unable to send OTP email. Please verify SMTP configuration and try again.');
        }

        $entries = $session->get($sessionKey, []);
        $entries[$email] = [
            'code' => $otp,
            'expires_at' => time() + self::OTP_TTL_SECONDS,
        ];
        $session->set($sessionKey, $entries);

        return null;
    }

    private function verifyOtp(
        SessionInterface $session,
        string $sessionKey,
        string $email,
        string $otp,
        bool $consume
    ): bool {
        $entries = $session->get($sessionKey, []);
        $normalizedEmail = $this->normalizeEmail($email);
        $entry = $entries[$normalizedEmail] ?? null;
        if (!is_array($entry)) {
            return false;
        }

        $valid = ((int) ($entry['expires_at'] ?? 0)) >= time()
            && (string) ($entry['code'] ?? '') === trim($otp);

        if ($consume && $valid) {
            unset($entries[$normalizedEmail]);
            $session->set($sessionKey, $entries);
        }

        if (!$valid && ((int) ($entry['expires_at'] ?? 0)) < time()) {
            unset($entries[$normalizedEmail]);
            $session->set($sessionKey, $entries);
        }

        return $valid;
    }

    private function sendOtpEmail(string $email, string $subject, string $code): bool
    {
        $senderEmail = $this->readEnv('NEXORA_SMTP_EMAIL');
        $senderName = $this->readEnv('NEXORA_SMTP_FROM_NAME', 'NEXORA Notification');
        if ($senderEmail === '') {
            $senderEmail = 'noreply@nexora.local';
        }

        try {
            $message = (new Email())
                ->from(new Address($senderEmail, $senderName))
                ->to($email)
                ->subject($subject)
                ->html(sprintf(
                    '<p>Your Nexora verification code is <strong>%s</strong>.</p><p>It expires in 5 minutes.</p>',
                    $code
                ));
            $this->mailer->send($message);
            $this->logger->info('OTP email sent.', [
                'target_email' => $email,
                'subject' => $subject,
                'sender_email' => $senderEmail,
            ]);

            return true;
        } catch (\Throwable $exception) {
            $this->logger->error('OTP email send failed.', [
                'target_email' => $email,
                'subject' => $subject,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function isValidPhone(string $phone): bool
    {
        $normalized = trim($phone);
        if ($normalized === '') {
            return false;
        }

        if (!preg_match('/^\+?[0-9][0-9\s\-]{6,19}$/', $normalized)) {
            return false;
        }

        $digits = preg_replace('/\D+/', '', $normalized) ?? '';

        return strlen($digits) >= 8 && strlen($digits) <= 15;
    }

    private function isValidPersonName(string $value): bool
    {
        $name = trim($value);
        if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 80) {
            return false;
        }

        return preg_match("/^[\\p{L}][\\p{L}'\\-\\s]*$/u", $name) === 1;
    }

    private function assertStrongPassword(string $password): void
    {
        if (
            strlen($password) < 8
            || preg_match('/[A-Z]/', $password) !== 1
            || preg_match('/[a-z]/', $password) !== 1
            || preg_match('/\d/', $password) !== 1
        ) {
            throw new \InvalidArgumentException('Password must contain at least 8 chars, upper, lower and number.');
        }
    }

    private function readEnv(string $key, string $default = ''): string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if (!is_string($value)) {
            return $default;
        }

        $value = trim($value);

        return $value !== '' ? $value : $default;
    }

    private function ensureBiometricFaceColumns(): bool
    {
        if ($this->biometricFaceColumnsAvailable !== null) {
            return $this->biometricFaceColumnsAvailable;
        }

        try {
            $columns = $this->connection->fetchFirstColumn(
                "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'"
            );
            $normalizedColumns = array_map(static fn (mixed $value): string => strtolower((string) $value), $columns);

            if (!in_array('biometric_face_descriptor', $normalizedColumns, true)) {
                $this->connection->executeStatement('ALTER TABLE users ADD biometric_face_descriptor LONGTEXT NULL AFTER biometric_enabled');
            }
            if (!in_array('biometric_face_updated_at', $normalizedColumns, true)) {
                $this->connection->executeStatement('ALTER TABLE users ADD biometric_face_updated_at DATETIME NULL AFTER biometric_face_descriptor');
            }

            $columns = $this->connection->fetchFirstColumn(
                "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'"
            );
            $normalizedColumns = array_map(static fn (mixed $value): string => strtolower((string) $value), $columns);
            $this->biometricFaceColumnsAvailable = in_array('biometric_face_descriptor', $normalizedColumns, true)
                && in_array('biometric_face_updated_at', $normalizedColumns, true);
        } catch (\Throwable) {
            $this->biometricFaceColumnsAvailable = false;
        }

        return $this->biometricFaceColumnsAvailable;
    }

    private function parseFaceDescriptor(string $descriptorJson): ?array
    {
        $trimmed = trim($descriptorJson);
        if ($trimmed === '') {
            return null;
        }

        try {
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($decoded)) {
            return null;
        }

        $normalized = [];
        foreach ($decoded as $value) {
            if (!is_numeric($value)) {
                continue;
            }

            $float = (float) $value;
            if (!is_finite($float)) {
                continue;
            }

            $normalized[] = round($float, 8);
            if (count($normalized) >= self::FACE_DESCRIPTOR_MAX_SIZE) {
                break;
            }
        }

        if (count($normalized) < self::FACE_DESCRIPTOR_MIN_SIZE) {
            return null;
        }

        return $normalized;
    }

    private function calculateFaceDistance(array $left, array $right): float
    {
        $size = min(count($left), count($right));
        if ($size < self::FACE_DESCRIPTOR_MIN_SIZE) {
            return INF;
        }

        $sum = 0.0;
        for ($index = 0; $index < $size; ++$index) {
            $diff = ((float) $left[$index]) - ((float) $right[$index]);
            $sum += $diff * $diff;
        }

        return sqrt($sum);
    }

    private function faceDistanceThreshold(): float
    {
        $raw = trim((string) ($_ENV['FACE_BIOMETRIC_THRESHOLD'] ?? $_SERVER['FACE_BIOMETRIC_THRESHOLD'] ?? '0.55'));
        $value = is_numeric($raw) ? (float) $raw : 0.55;

        return max(0.35, min(0.85, $value));
    }

    private function resolveClientContext(Request $request): string
    {
        $host = gethostname() ?: 'unknown-host';
        $agent = trim((string) $request->headers->get('User-Agent', 'Unknown client'));

        return sprintf('%s (%s)', $host, $agent !== '' ? $agent : 'Unknown client');
    }
}
