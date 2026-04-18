<?php

namespace App\Controller\Sections;

use App\Service\BankingService;
use App\Service\GeminiService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class UsersController
{
    private const SESSION_FORM_OLD = 'nexora.users.form_old';
    private const SESSION_FORM_ERRORS = 'nexora.users.form_errors';
    private const ALLOWED_ROLES = ['ROLE_USER', 'ROLE_ADMIN'];
    private const ALLOWED_STATUS = ['PENDING', 'ACTIVE', 'DECLINED', 'INACTIVE', 'BANNED'];

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function buildAdminData(BankingService $bankingService, ?int $editUserId = null): array
    {
        $items = $bankingService->listUsers();
        $support = [];

        if ($editUserId !== null) {
            foreach ($items as $item) {
                if ((int) ($item['idUser'] ?? 0) === $editUserId) {
                    $support['selected_user'] = $item;
                    break;
                }
            }
        }

        $request = $this->requestStack->getCurrentRequest();
        $session = $request?->getSession();
        if ($session !== null) {
            $formOld = $session->get(self::SESSION_FORM_OLD, []);
            $formErrors = $session->get(self::SESSION_FORM_ERRORS, []);
            $session->remove(self::SESSION_FORM_OLD);
            $session->remove(self::SESSION_FORM_ERRORS);

            $support['user_form_old'] = is_array($formOld) ? $formOld : [];
            $support['user_form_errors'] = is_array($formErrors) ? $formErrors : [];
        }

        return [
            'items' => $items,
            'support' => $support,
        ];
    }

    public function buildPortalData(): array
    {
        return [
            'items' => [],
            'support' => [],
        ];
    }

    public function handleAdminAction(
        string $action,
        Request $request,
        BankingService $bankingService,
        GeminiService $geminiService,
        ?string $profileImagePath = null
    ): ?array {
        switch ($action) {
            case 'user_save':
                $payload = $request->request->all();
                if ($profileImagePath !== null) {
                    $payload['profile_image_path'] = $profileImagePath;
                }
                $userId = $this->requestInt($request, 'idUser');
                $errors = $this->validateUserPayload($payload, $userId !== null);
                if ($errors !== []) {
                    $this->storeUserFormState($request, $payload, $errors);

                    return ['type' => 'error', 'message' => (string) reset($errors)];
                }

                try {
                    $bankingService->saveUser($payload, $userId);
                } catch (\Throwable $exception) {
                    $this->storeUserFormState($request, $payload, []);

                    return ['type' => 'error', 'message' => $exception->getMessage()];
                }

                $this->clearUserFormState($request);

                return ['type' => 'success', 'message' => 'User saved.'];

            case 'user_status':
                $status = strtoupper(trim((string) $request->request->get('status', 'PENDING')));
                $userId = $this->requestInt($request, 'idUser');
                if ($userId === null) {
                    return ['type' => 'error', 'message' => 'A valid user ID is required.'];
                }
                if (!in_array($status, self::ALLOWED_STATUS, true)) {
                    return ['type' => 'error', 'message' => 'Invalid status value.'];
                }

                $bankingService->updateUserStatus(
                    $userId,
                    $status
                );

                return ['type' => 'success', 'message' => 'User status updated.'];

            case 'user_delete':
                $userId = $this->requestInt($request, 'idUser');
                if ($userId === null) {
                    return ['type' => 'error', 'message' => 'A valid user ID is required.'];
                }

                $bankingService->deleteUser($userId);

                return ['type' => 'success', 'message' => 'User deleted.'];

            case 'user_ai_assist':
                $aiResult = $geminiService->generateUserManagementAdvice([
                    'nom' => (string) $request->request->get('nom', ''),
                    'prenom' => (string) $request->request->get('prenom', ''),
                    'role' => (string) $request->request->get('role', ''),
                    'status' => (string) $request->request->get('status', ''),
                    'reason' => (string) $request->request->get('reason', ''),
                    'prompt' => (string) $request->request->get('prompt', ''),
                ]);
                $request->getSession()->set('nexora.users_ai_assistant', $aiResult);

                return ['type' => 'success', 'message' => sprintf('AI assistant (%s) updated.', $aiResult['provider'])];
        }

        return null;
    }

    private function validateUserPayload(array $payload, bool $isUpdate): array
    {
        $errors = [];

        $nom = trim((string) ($payload['nom'] ?? ''));
        $prenom = trim((string) ($payload['prenom'] ?? ''));
        $email = trim((string) ($payload['email'] ?? ''));
        $telephone = trim((string) ($payload['telephone'] ?? ''));
        $role = strtoupper(trim((string) ($payload['role'] ?? '')));
        $status = strtoupper(trim((string) ($payload['status'] ?? '')));
        $password = (string) ($payload['password'] ?? '');

        if (!$isUpdate || $nom !== '') {
            if ($nom === '') {
                $errors['nom'] = 'Last name is required.';
            } elseif (!$this->isValidName($nom)) {
                $errors['nom'] = 'Last name contains invalid characters.';
            }
        }

        if (!$isUpdate || $prenom !== '') {
            if ($prenom === '') {
                $errors['prenom'] = 'First name is required.';
            } elseif (!$this->isValidName($prenom)) {
                $errors['prenom'] = 'First name contains invalid characters.';
            }
        }

        if (!$isUpdate || $email !== '') {
            if ($email === '') {
                $errors['email'] = 'Email is required.';
            } elseif (!$this->isValidEmail($email)) {
                $errors['email'] = 'Email format is invalid.';
            }
        }

        if (!$isUpdate || $telephone !== '') {
            if ($telephone === '') {
                $errors['telephone'] = 'Phone number is required.';
            } elseif (!$this->isValidPhone($telephone)) {
                $errors['telephone'] = 'Phone format is invalid.';
            }
        }

        if ($role !== '' && !in_array($role, self::ALLOWED_ROLES, true)) {
            $errors['role'] = 'Invalid role selected.';
        }

        if ($status !== '' && !in_array($status, self::ALLOWED_STATUS, true)) {
            $errors['status'] = 'Invalid status selected.';
        }

        if (!$isUpdate && trim($password) === '') {
            $errors['password'] = 'Password is required for new users.';
        } elseif (trim($password) !== '' && !$this->isStrongPassword($password)) {
            $errors['password'] = 'Password must include upper, lower and number (8+ chars).';
        }

        return $errors;
    }

    private function storeUserFormState(Request $request, array $payload, array $errors): void
    {
        $session = $request->getSession();
        $safeOld = [
            'idUser' => trim((string) ($payload['idUser'] ?? '')),
            'nom' => trim((string) ($payload['nom'] ?? '')),
            'prenom' => trim((string) ($payload['prenom'] ?? '')),
            'email' => trim((string) ($payload['email'] ?? '')),
            'telephone' => trim((string) ($payload['telephone'] ?? '')),
            'role' => strtoupper(trim((string) ($payload['role'] ?? ''))),
            'status' => strtoupper(trim((string) ($payload['status'] ?? ''))),
        ];

        $session->set(self::SESSION_FORM_OLD, $safeOld);
        $session->set(self::SESSION_FORM_ERRORS, $errors);
    }

    private function clearUserFormState(Request $request): void
    {
        $session = $request->getSession();
        $session->remove(self::SESSION_FORM_OLD);
        $session->remove(self::SESSION_FORM_ERRORS);
    }

    private function requestInt(Request $request, string $key): ?int
    {
        $value = $request->request->get($key);
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);
        if ($normalized === '' || !preg_match('/^-?\d+$/', $normalized)) {
            return null;
        }

        $intValue = (int) $normalized;

        return $intValue > 0 ? $intValue : null;
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

        $digits = preg_replace('/\D+/', '', $normalized) ?? '';

        return strlen($digits) >= 8 && strlen($digits) <= 15;
    }

    private function isStrongPassword(string $password): bool
    {
        return strlen($password) >= 8
            && preg_match('/[A-Z]/', $password) === 1
            && preg_match('/[a-z]/', $password) === 1
            && preg_match('/\d/', $password) === 1;
    }

    private function isValidName(string $value): bool
    {
        $normalized = trim($value);
        if ($normalized === '' || mb_strlen($normalized) < 2 || mb_strlen($normalized) > 80) {
            return false;
        }

        return preg_match("/^[\\p{L}][\\p{L}'\\-\\s]*$/u", $normalized) === 1;
    }
}
