<?php

namespace App\Controller\Sections;

use App\Service\AuthService;
use Symfony\Component\HttpFoundation\Request;

final class AdminAccountController
{
    public function buildAdminData(): array
    {
        return [
            'items' => [],
            'support' => [],
        ];
    }

    public function handleAdminAction(
        string $action,
        Request $request,
        AuthService $authService,
        array $user,
        ?string $profileImagePath = null
    ): ?array {
        $userId = (int) $user['idUser'];

        switch ($action) {
            case 'admin_profile_save':
                $profilePayload = [
                    'nom' => (string) $request->request->get('nom', ''),
                    'prenom' => (string) $request->request->get('prenom', ''),
                    'telephone' => (string) $request->request->get('telephone', ''),
                    'email' => (string) $request->request->get('email', ''),
                ];
                if ($profileImagePath !== null) {
                    $profilePayload['profile_image_path'] = $profileImagePath;
                }
                $authService->updateProfile($userId, $profilePayload);

                return ['type' => 'success', 'message' => 'Admin profile updated.'];

            case 'admin_biometric_save':
                $authService->updateProfile($userId, [
                    'biometric_enabled' => (string) $request->request->get('biometric_enabled', '0'),
                    'biometric_face_descriptor' => (string) $request->request->get('face_descriptor', ''),
                ]);

                return ['type' => 'success', 'message' => 'Admin Face ID updated.'];

            case 'admin_biometric_clear':
                $authService->updateProfile($userId, [
                    'clear_biometric_face' => '1',
                    'biometric_enabled' => '0',
                ]);

                return ['type' => 'success', 'message' => 'Admin Face ID removed.'];

            case 'admin_password_change':
                $newPassword = (string) $request->request->get('new_password', '');
                $confirmPassword = (string) $request->request->get('confirm_password', '');
                if ($newPassword !== $confirmPassword) {
                    throw new \InvalidArgumentException('New password and confirmation do not match.');
                }
                if (strlen($newPassword) < 8) {
                    throw new \InvalidArgumentException('New password must be at least 8 characters.');
                }
                $authService->changePassword(
                    $userId,
                    (string) $request->request->get('current_password', ''),
                    $newPassword
                );

                return ['type' => 'success', 'message' => 'Admin password updated.'];
        }

        return null;
    }
}
