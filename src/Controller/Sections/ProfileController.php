<?php

namespace App\Controller\Sections;

use App\Service\ActivityService;
use App\Service\AuthService;
use App\Service\InsightsService;
use Symfony\Component\HttpFoundation\Request;

final class ProfileController
{
    public function buildPortalData(ActivityService $activityService, int $userId): array
    {
        return [
            'items' => [],
            'support' => [
                'activity' => $activityService->listRecent($userId, 25),
            ],
        ];
    }

    public function handlePortalAction(
        string $action,
        Request $request,
        AuthService $authService,
        InsightsService $insightsService,
        array $user,
        ?string $profileImagePath = null
    ): ?array {
        $userId = (int) $user['idUser'];

        switch ($action) {
            case 'profile_save':
                $payload = $request->request->all();
                if ($profileImagePath !== null) {
                    $payload['profile_image_path'] = $profileImagePath;
                }
                $authService->updateProfile($userId, $payload);

                return ['type' => 'success', 'message' => 'Profile updated.'];

            case 'profile_biometric_save':
                $authService->updateProfile($userId, [
                    'biometric_enabled' => (string) $request->request->get('biometric_enabled', '0'),
                    'biometric_face_descriptor' => (string) $request->request->get('face_descriptor', ''),
                ]);

                return ['type' => 'success', 'message' => 'Biometric Face ID updated.'];

            case 'profile_biometric_clear':
                $authService->updateProfile($userId, [
                    'clear_biometric_face' => '1',
                    'biometric_enabled' => '0',
                ]);

                return ['type' => 'success', 'message' => 'Biometric Face ID removed.'];

            case 'profile_ai_refresh':
                $request->getSession()->set('nexora.profile_ai_data', [
                    'generated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                    'security_analysis' => $insightsService->getAccountSecurityAnalysis($userId),
                    'prediction' => $insightsService->getSpendingPrediction($userId),
                    'account_advice' => $insightsService->getAccountAdvisor($userId),
                    'cashback_advice' => $insightsService->getCashbackAdvisor($userId),
                    'surplus' => $insightsService->detectMonthlySurplus($userId),
                ]);

                return ['type' => 'success', 'message' => 'AI profile analysis updated.'];

            case 'profile_ai_ack_surplus':
                $month = trim((string) $request->request->get('month', ''));
                if ($month !== '') {
                    $insightsService->acknowledgeMonthlySurplus($userId, $month);
                }
                $request->getSession()->set('nexora.profile_ai_data', [
                    'generated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                    'security_analysis' => $insightsService->getAccountSecurityAnalysis($userId),
                    'prediction' => $insightsService->getSpendingPrediction($userId),
                    'account_advice' => $insightsService->getAccountAdvisor($userId),
                    'cashback_advice' => $insightsService->getCashbackAdvisor($userId),
                    'surplus' => $insightsService->detectMonthlySurplus($userId),
                ]);

                return ['type' => 'success', 'message' => 'Monthly surplus suggestion acknowledged.'];

            case 'profile_password_change':
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

                return ['type' => 'success', 'message' => 'Password updated.'];

            case 'profile_send_reset_otp':
                $authService->sendPasswordResetOtp((string) ($user['email'] ?? ''), $request->getSession());

                return ['type' => 'success', 'message' => 'Reset OTP sent by email.'];

            case 'profile_reset_password':
                $newPassword = (string) $request->request->get('reset_new_password', '');
                $confirmPassword = (string) $request->request->get('reset_confirm_password', '');
                if ($newPassword !== $confirmPassword) {
                    throw new \InvalidArgumentException('Reset password and confirmation do not match.');
                }
                if (strlen($newPassword) < 8) {
                    throw new \InvalidArgumentException('Reset password must be at least 8 characters.');
                }

                $email = (string) ($user['email'] ?? '');
                $otp = (string) $request->request->get('reset_otp', '');
                if (!$authService->verifyPasswordResetOtp($email, $otp, $request->getSession())) {
                    throw new \InvalidArgumentException('Reset OTP is invalid or expired.');
                }
                $authService->resetPasswordByVerifiedEmail($email, $newPassword, $request->getSession());

                return ['type' => 'success', 'message' => 'Password reset completed.'];
        }

        return null;
    }
}
