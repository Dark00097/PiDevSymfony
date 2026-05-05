<?php

namespace App\Controller;

use App\Service\AdminSecuritySettingsService;
use App\Service\AuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

final class AuthController extends AbstractController
{
    private const LOGIN_FAILED_ATTEMPTS = 'nexora.login_failed_attempts';
    private const LOGIN_MATH_QUESTION = 'nexora.login_math_question';
    private const LOGIN_MATH_ANSWER = 'nexora.login_math_answer';
    private const LOGIN_FORM_OLD = 'nexora.login_form_old';
    private const LOGIN_FORM_ERRORS = 'nexora.login_form_errors';

    #[Route('/login', name: 'login', methods: ['GET', 'POST'])]
    public function login(Request $request, AuthService $authService, AdminSecuritySettingsService $adminSecuritySettingsService): Response
    {
        $session = $request->getSession();
        $authenticatedUser = $authService->getAuthenticatedUser($session);
        if ($authenticatedUser !== null) {
            $blockedReason = $authService->getLoginBlockReason($authenticatedUser);
            if ($blockedReason === null) {
                return $this->redirectToRoute($authService->getAuthenticatedRole($session) === 'ROLE_ADMIN' ? 'admin_dashboard' : 'portal_dashboard');
            }

            $authService->logoutUser($session);
            $this->addFlash('error', $blockedReason);

            return $this->redirectToRoute('login');
        }

        $failedAttempts = (int) $session->get(self::LOGIN_FAILED_ATTEMPTS, 0);
        $mathRequired = $failedAttempts >= 3;

        if ($request->isMethod('POST')) {
            $loginMode = strtolower(trim((string) $request->request->get('login_mode', 'password')));
            if (!in_array($loginMode, ['password', 'face'], true)) {
                $loginMode = 'password';
            }

            $email = trim((string) $request->request->get('email', ''));
            $password = (string) $request->request->get('password', '');
            $faceDescriptor = (string) $request->request->get('face_descriptor', '');
            $mathAnswer = trim((string) $request->request->get('math_answer', ''));

            $loginOld = [
                'email' => $email,
                'login_mode' => $loginMode,
                'math_answer' => $mathAnswer,
            ];
            $loginErrors = [];

            if ($email === '') {
                $loginErrors['email'] = 'Email is required.';
            } elseif (!$this->isValidEmail($email)) {
                $loginErrors['email'] = 'Please enter a valid email address.';
            }

            if ($loginMode === 'face') {
                if (trim($faceDescriptor) === '') {
                    $loginErrors['face_descriptor'] = 'Face template is missing. Capture your face first.';
                }
            } elseif ($password === '') {
                $loginErrors['password'] = 'Password is required.';
            }

            if ($mathRequired && !$this->verifyMathChallenge($request)) {
                $this->generateMathChallenge($request);
                $loginErrors['math_answer'] = 'Too many failed attempts. Please solve the math challenge.';
            }

            if ($loginErrors !== []) {
                $this->persistLoginFormState($session, $loginOld, $loginErrors);
                $this->addFlash('error', (string) reset($loginErrors));
            } else {
                $candidateUser = $authService->findUserByEmail($email);
                if ($candidateUser !== null) {
                    $candidateBlockedReason = $authService->getLoginBlockReason($candidateUser);
                    if ($candidateBlockedReason !== null) {
                        $this->persistLoginFormState($session, $loginOld, []);
                        $this->addFlash('error', $candidateBlockedReason);

                        return $this->redirectToRoute('login');
                    }
                }

                $user = null;
                if ($loginMode === 'face') {
                    $user = $authService->authenticateByFace($email, $faceDescriptor, $request);
                } else {
                    $user = $authService->authenticate($email, $password, $request);
                }

                if ($user === null) {
                    ++$failedAttempts;
                    $session->set(self::LOGIN_FAILED_ATTEMPTS, $failedAttempts);
                    if ($failedAttempts >= 3) {
                        $this->generateMathChallenge($request);
                    }
                    $this->persistLoginFormState($session, $loginOld, [
                        $loginMode === 'face' ? 'face_descriptor' : 'password' => $loginMode === 'face'
                            ? 'Face verification failed.'
                            : 'Invalid email or password.',
                    ]);
                    $this->addFlash('error', $loginMode === 'face' ? 'Face verification failed.' : 'Invalid email or password.');
                } else {
                    $role = strtoupper(trim((string) ($user['role'] ?? '')));
                    $adminSettings = $adminSecuritySettingsService->getSettings();
                    $faceRequired = $authService->isFaceLoginRequired(
                        $user,
                        $role === 'ROLE_ADMIN' && (bool) ($adminSettings['require_biometric_on_admin_login'] ?? false)
                    );
                    $blockedReason = $authService->getLoginBlockReason($user);

                    if ($blockedReason !== null) {
                        $this->persistLoginFormState($session, $loginOld, []);
                        $this->addFlash('error', $blockedReason);
                    } elseif ($faceRequired && $loginMode !== 'face') {
                        $this->persistLoginFormState($session, $loginOld, [
                            'password' => 'Face login is required for this account.',
                        ]);
                        $this->addFlash('error', 'Face login is required for this account. Use "Connexion par visage".');
                    } else {
                        $session->remove(self::LOGIN_FAILED_ATTEMPTS);
                        $session->remove(self::LOGIN_MATH_QUESTION);
                        $session->remove(self::LOGIN_MATH_ANSWER);
                        $this->clearLoginFormState($session);
                        $authService->loginUser($session, $user);

                        return $this->redirectToRoute($role === 'ROLE_ADMIN' ? 'admin_dashboard' : 'portal_dashboard');
                    }
                }
            }

            $failedAttempts = (int) $session->get(self::LOGIN_FAILED_ATTEMPTS, 0);
            $mathRequired = $failedAttempts >= 3;

            return $this->redirectToRoute('login');
        }

        $loginOld = $session->get(self::LOGIN_FORM_OLD, []);
        $loginErrors = $session->get(self::LOGIN_FORM_ERRORS, []);
        $session->remove(self::LOGIN_FORM_OLD);
        $session->remove(self::LOGIN_FORM_ERRORS);

        return $this->render('auth/login.html.twig', [
            'math_required' => $mathRequired,
            'math_question' => $session->get(self::LOGIN_MATH_QUESTION),
            'login_old' => is_array($loginOld) ? $loginOld : [],
            'login_errors' => is_array($loginErrors) ? $loginErrors : [],
        ]);
    }

    #[Route('/signup', name: 'signup', methods: ['GET', 'POST'])]
    public function signup(Request $request, AuthService $authService): Response
    {
        $signupOld = [];
        $signupErrors = [];

        if ($request->isMethod('POST')) {
            $action = trim((string) $request->request->get('action', ''));
            if ($action === '') {
                $action = trim((string) $request->request->get('full_name', '')) !== '' ? 'signup' : 'send_otp';
            }
            $email = trim((string) $request->request->get('email', ''));
            $phone = trim((string) $request->request->get('telephone', ''));
            $fullName = trim((string) $request->request->get('full_name', ''));
            $otp = trim((string) $request->request->get('otp', ''));
            if ($otp === '') {
                $otp = $this->collectOtpDigitsFromRequest($request);
            }
            $password = (string) $request->request->get('password', '');
            $confirmPassword = (string) $request->request->get('confirm_password', '');
            $termsAccepted = (string) $request->request->get('terms', '') !== '';

            $signupOld = [
                'full_name' => $fullName,
                'email' => $email,
                'telephone' => $phone,
                'otp' => $otp,
                'terms' => $termsAccepted ? '1' : '0',
            ];

            if ($action === 'send_otp') {
                if ($email === '') {
                    $signupErrors['email'] = 'Email is required to send OTP.';
                } elseif (!$this->isValidEmail($email)) {
                    $signupErrors['email'] = 'Please enter a valid email address.';
                }

                if ($phone === '') {
                    $signupErrors['telephone'] = 'Phone number is required.';
                } elseif (!$this->isValidPhone($phone)) {
                    $signupErrors['telephone'] = 'Please enter a valid phone number.';
                }

                if ($signupErrors === []) {
                    try {
                        $authService->sendSignupOtp($email, $request->getSession(), $phone);
                        $this->addFlash('success', 'OTP sent to your email and SMS (if SMS is configured). It expires in 5 minutes.');
                    } catch (\Throwable $exception) {
                        $this->addFlash('error', $exception->getMessage());
                    }
                } else {
                    $this->addFlash('error', (string) reset($signupErrors));
                }
            }

            if ($action === 'signup') {
                if ($fullName === '') {
                    $signupErrors['full_name'] = 'Full name is required.';
                } elseif (!$this->isValidFullName($fullName)) {
                    $signupErrors['full_name'] = 'Please enter a valid name (letters only).';
                }

                if ($email === '') {
                    $signupErrors['email'] = 'Email is required.';
                } elseif (!$this->isValidEmail($email)) {
                    $signupErrors['email'] = 'Please enter a valid email address.';
                }

                if ($phone === '') {
                    $signupErrors['telephone'] = 'Phone number is required.';
                } elseif (!$this->isValidPhone($phone)) {
                    $signupErrors['telephone'] = 'Please enter a valid phone number.';
                }

                if (!preg_match('/^\d{6}$/', $otp)) {
                    $signupErrors['otp'] = 'OTP must contain 6 digits.';
                }

                if ($password === '') {
                    $signupErrors['password'] = 'Password is required.';
                } elseif (!$this->isStrongPassword($password)) {
                    $signupErrors['password'] = 'Use 8+ chars with upper, lower and number.';
                }

                if ($confirmPassword === '') {
                    $signupErrors['confirm_password'] = 'Please confirm your password.';
                } elseif ($password !== $confirmPassword) {
                    $signupErrors['confirm_password'] = 'Passwords do not match.';
                }

                if (!$termsAccepted) {
                    $signupErrors['terms'] = 'You must accept terms and privacy policy.';
                }

                if ($signupErrors === [] && !$authService->verifySignupOtp($email, $otp, $request->getSession(), $phone)) {
                    $signupErrors['otp'] = 'Invalid or expired OTP.';
                }

                if ($signupErrors === []) {
                    [$nom, $prenom] = $this->splitName($fullName);

                    try {
                        $authService->registerUser([
                            'nom' => $nom,
                            'prenom' => $prenom,
                            'email' => $email,
                            'telephone' => $phone,
                            'password' => $password,
                        ], $request);
                        $this->addFlash('success', 'Your account was created and is pending admin approval.');

                        return $this->redirectToRoute('login');
                    } catch (\Throwable $exception) {
                        $this->addFlash('error', $exception->getMessage());
                    }
                } else {
                    $this->addFlash('error', (string) reset($signupErrors));
                }
            }
        }

        return $this->render('auth/signup.html.twig', [
            'signup_old' => $signupOld,
            'signup_errors' => $signupErrors,
        ]);
    }

    #[Route('/forgot-password', name: 'forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(Request $request, AuthService $authService): Response
    {
        $session = $request->getSession();
        $sessionEmail = (string) $session->get('nexora.reset_email', '');
        $forgotOld = [
            'email' => $sessionEmail,
            'otp' => '',
        ];
        $forgotErrors = [];

        if ($request->isMethod('POST')) {
            $action = (string) $request->request->get('action', '');
            $email = trim((string) $request->request->get('email', ''));
            if ($email === '') {
                $email = $sessionEmail;
            }
            $forgotOld['email'] = $email;

            if ($action === 'send_otp') {
                if ($email === '') {
                    $forgotErrors['email'] = 'Email is required.';
                } elseif (!$this->isValidEmail($email)) {
                    $forgotErrors['email'] = 'Please enter a valid email address.';
                }

                if ($forgotErrors === []) {
                    try {
                        $authService->sendPasswordResetOtp($email, $session);
                        $session->set('nexora.reset_email', $email);
                        $forgotOld['email'] = $email;
                        $this->addFlash('success', 'OTP sent to '.$email.'.');
                    } catch (\Throwable $exception) {
                        $this->addFlash('error', $exception->getMessage());
                    }
                }
            }

            if ($action === 'verify_otp') {
                $otp = trim((string) $request->request->get('otp', ''));
                $forgotOld['otp'] = $otp;

                if ($email === '') {
                    $forgotErrors['email'] = 'Email is required before OTP verification.';
                } elseif (!$this->isValidEmail($email)) {
                    $forgotErrors['email'] = 'Please enter a valid email address.';
                }

                if ($otp === '') {
                    $forgotErrors['otp'] = 'OTP code is required.';
                } elseif (!preg_match('/^\d{6}$/', $otp)) {
                    $forgotErrors['otp'] = 'OTP must contain exactly 6 digits.';
                }

                if ($forgotErrors === []) {
                    try {
                        if ($authService->verifyPasswordResetOtp($email, $otp, $session)) {
                            $session->set('nexora.reset_email', $email);
                            $forgotOld['email'] = $email;
                            $this->addFlash('success', 'OTP verified. You can now set a new password.');
                        } else {
                            $forgotErrors['otp'] = 'Invalid or expired OTP.';
                            $this->addFlash('error', 'Invalid or expired OTP.');
                        }
                    } catch (\Throwable $exception) {
                        $this->addFlash('error', $exception->getMessage());
                    }
                }
            }

            if ($action === 'reset_password') {
                $password = (string) $request->request->get('new_password', '');
                $confirmPassword = (string) $request->request->get('confirm_password', '');
                $email = $email !== '' ? $email : (string) $session->get('nexora.reset_email', '');
                $forgotOld['email'] = $email;

                if ($email === '') {
                    $forgotErrors['email'] = 'Email is required before resetting password.';
                } elseif (!$this->isValidEmail($email)) {
                    $forgotErrors['email'] = 'Please enter a valid email address.';
                }

                if ($password === '') {
                    $forgotErrors['new_password'] = 'New password is required.';
                } elseif (strlen($password) < 8) {
                    $forgotErrors['new_password'] = 'New password must be at least 8 characters.';
                }

                if ($confirmPassword === '') {
                    $forgotErrors['confirm_password'] = 'Please confirm your new password.';
                } elseif ($password !== '' && $password !== $confirmPassword) {
                    $forgotErrors['confirm_password'] = 'Passwords do not match.';
                }

                if ($forgotErrors === []) {
                    try {
                        $authService->resetPasswordByVerifiedEmail($email, $password, $session);
                        $session->remove('nexora.reset_email');
                        $this->addFlash('success', 'Your password was updated successfully.');

                        return $this->redirectToRoute('login');
                    } catch (\Throwable $exception) {
                        $this->addFlash('error', $exception->getMessage());
                    }
                }
            }

            if ($forgotErrors !== []) {
                $this->addFlash('error', (string) reset($forgotErrors));
            }
        }

        return $this->render('auth/forgot_password.html.twig', [
            'reset_email' => (string) $session->get('nexora.reset_email', ''),
            'forgot_old' => $forgotOld,
            'forgot_errors' => $forgotErrors,
        ]);
    }

    #[Route('/logout', name: 'logout', methods: ['GET'])]
    public function logout(Request $request, AuthService $authService): RedirectResponse
    {
        $authService->logoutUser($request->getSession());

        return $this->redirectToRoute('home');
    }

    private function splitName(string $fullName): array
    {
        $normalized = preg_replace('/\s+/', ' ', trim($fullName)) ?? trim($fullName);
        if ($normalized === '') {
            return ['Client', 'Nexora'];
        }

        $parts = array_values(array_filter(explode(' ', $normalized), static fn (string $part): bool => $part !== ''));
        if ($parts === []) {
            return ['Client', 'Nexora'];
        }

        $nom = $parts[0] ?? 'Client';
        $prenom = $parts[1] ?? $nom;

        return [$nom, $prenom];
    }

    private function collectOtpDigitsFromRequest(Request $request): string
    {
        $otp = '';
        for ($index = 1; $index <= 6; ++$index) {
            $digit = preg_replace('/\D+/', '', (string) $request->request->get('otp_digit_'.$index, '')) ?? '';
            if ($digit === '') {
                continue;
            }

            $otp .= substr($digit, -1);
        }

        return substr($otp, 0, 6);
    }

    private function generateMathChallenge(Request $request): void
    {
        $left = random_int(1, 9);
        $right = random_int(1, 9);
        $request->getSession()->set(self::LOGIN_MATH_QUESTION, sprintf('Security check: %d + %d = ?', $left, $right));
        $request->getSession()->set(self::LOGIN_MATH_ANSWER, $left + $right);
    }

    private function verifyMathChallenge(Request $request): bool
    {
        $answer = (int) $request->request->get('math_answer', 0);

        return $answer > 0 && $answer === (int) $request->getSession()->get(self::LOGIN_MATH_ANSWER, -1);
    }

    private function isValidEmail(string $email): bool
    {
        return (bool) filter_var(trim($email), FILTER_VALIDATE_EMAIL);
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

        $digitsOnly = preg_replace('/\D+/', '', $normalized) ?? '';

        return strlen($digitsOnly) >= 8 && strlen($digitsOnly) <= 15;
    }

    private function isStrongPassword(string $password): bool
    {
        if (strlen($password) < 8) {
            return false;
        }

        return preg_match('/[A-Z]/', $password) === 1
            && preg_match('/[a-z]/', $password) === 1
            && preg_match('/\d/', $password) === 1;
    }

    private function isValidFullName(string $fullName): bool
    {
        $normalized = preg_replace('/\s+/', ' ', trim($fullName)) ?? trim($fullName);
        if ($normalized === '') {
            return false;
        }

        $parts = explode(' ', $normalized);
        if (count($parts) < 1) {
            return false;
        }

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '' || mb_strlen($part) < 2 || mb_strlen($part) > 40) {
                return false;
            }
            if (preg_match("/^[\\p{L}][\\p{L}'-]*$/u", $part) !== 1) {
                return false;
            }
        }

        return true;
    }

    private function persistLoginFormState(SessionInterface $session, array $old, array $errors): void
    {
        $session->set(self::LOGIN_FORM_OLD, $old);
        $session->set(self::LOGIN_FORM_ERRORS, $errors);
    }

    private function clearLoginFormState(SessionInterface $session): void
    {
        $session->remove(self::LOGIN_FORM_OLD);
        $session->remove(self::LOGIN_FORM_ERRORS);
    }
}
