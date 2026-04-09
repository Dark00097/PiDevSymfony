<?php

namespace App\Controller\Sections;

use App\Service\BankingService;
use App\Service\GeminiService;
use Symfony\Component\HttpFoundation\Request;

final class UsersController
{
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
                $bankingService->saveUser($payload, $this->requestInt($request, 'idUser'));

                return ['type' => 'success', 'message' => 'User saved.'];

            case 'user_status':
                $bankingService->updateUserStatus(
                    $this->requestInt($request, 'idUser') ?? 0,
                    (string) $request->request->get('status', 'PENDING')
                );

                return ['type' => 'success', 'message' => 'User status updated.'];

            case 'user_delete':
                $bankingService->deleteUser($this->requestInt($request, 'idUser') ?? 0);

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
}
