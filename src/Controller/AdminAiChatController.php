<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AdminAiAssistantService;
use App\Service\AuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class AdminAiChatController extends AbstractController
{
    #[Route('/admin/api/ai/chat', name: 'admin_ai_chat', methods: ['POST'])]
    public function chat(
        Request $request,
        AuthService $authService,
        AdminAiAssistantService $assistantService,
    ): JsonResponse {
        $user = $authService->getAuthenticatedUser($request->getSession());
        if ($user === null) {
            return new JsonResponse(['ok' => false, 'message' => 'Non authentifie.'], 401);
        }

        if (strtoupper((string) ($user['role'] ?? '')) !== 'ROLE_ADMIN') {
            return new JsonResponse(['ok' => false, 'message' => 'Acces reserve a l admin.'], 403);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            $payload = [];
        }

        try {
            return new JsonResponse([
                'ok' => true,
                'assistant' => $assistantService->answer($payload),
            ]);
        } catch (\Throwable $exception) {
            return new JsonResponse([
                'ok' => false,
                'message' => $exception->getMessage() !== '' ? $exception->getMessage() : 'Le chatbot admin est indisponible.',
            ], 500);
        }
    }

    #[Route('/admin/api/ai/type-debug', name: 'admin_ai_type_debug', methods: ['GET'])]
    public function typeDebug(
        Request $request,
        AuthService $authService,
        AdminAiAssistantService $assistantService,
    ): JsonResponse {
        $user = $authService->getAuthenticatedUser($request->getSession());
        if ($user === null) {
            return new JsonResponse(['ok' => false, 'message' => 'Non authentifie.'], 401);
        }

        if (strtoupper((string) ($user['role'] ?? '')) !== 'ROLE_ADMIN') {
            return new JsonResponse(['ok' => false, 'message' => 'Acces reserve a l admin.'], 403);
        }

        return new JsonResponse([
            'ok' => true,
            'debug' => $assistantService->debugTypeLoading(),
        ]);
    }
}

