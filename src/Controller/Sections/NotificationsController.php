<?php

namespace App\Controller\Sections;

use App\Service\NotificationService;
use Symfony\Component\HttpFoundation\Request;

final class NotificationsController
{
    public function buildAdminData(array $notifications): array
    {
        return [
            'items' => $notifications,
            'support' => [],
        ];
    }

    public function buildPortalData(array $notifications): array
    {
        return [
            'items' => $notifications,
            'support' => [],
        ];
    }

    public function handleAction(string $action, NotificationService $notificationService, array $user, ?Request $request = null): ?array
    {
        if ($action === 'notifications_read') {
            $notificationService->markAllAsRead((int) $user['idUser'], (string) $user['role']);
            return ['type' => 'success', 'message' => 'Notifications marked as read.'];
        }

        if ($action === 'notification_delete' && $request !== null) {
            $id = (int) $request->request->get('idNotification');
            if ($id > 0) {
                $notificationService->deleteNotification($id, (int) $user['idUser'], (string) $user['role']);
                return ['type' => 'success', 'message' => 'Notification supprimée.'];
            }
        }

        return null;
    }
}
