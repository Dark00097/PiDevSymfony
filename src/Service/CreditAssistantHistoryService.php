<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class CreditAssistantHistoryService
{
    private const MAX_ITEMS = 24;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get(SessionInterface $session, int $userId, int $limit = 12): array
    {
        $history = $this->normalizeHistory($session->get($this->buildKey($userId), []));
        if ($limit <= 0) {
            return $history;
        }

        return array_slice($history, -$limit);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>>
     */
    public function append(SessionInterface $session, int $userId, string $role, array $payload): array
    {
        $history = $this->normalizeHistory($session->get($this->buildKey($userId), []));
        $history[] = [
            'role' => $role === 'user' ? 'user' : 'bot',
            'payload' => $payload,
            'created_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];

        if (count($history) > self::MAX_ITEMS) {
            $history = array_slice($history, -self::MAX_ITEMS);
        }

        $session->set($this->buildKey($userId), $history);

        return $history;
    }

    public function clear(SessionInterface $session, int $userId): void
    {
        $session->remove($this->buildKey($userId));
    }

    private function buildKey(int $userId): string
    {
        return 'nexora.credit_assistant.history.'.$userId;
    }

    /**
     * @param mixed $value
     * @return array<int, array<string, mixed>>
     */
    private function normalizeHistory(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $clean = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }

            $clean[] = [
                'role' => ($item['role'] ?? '') === 'user' ? 'user' : 'bot',
                'payload' => is_array($item['payload'] ?? null) ? $item['payload'] : [],
                'created_at' => is_string($item['created_at'] ?? null) ? $item['created_at'] : (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ];
        }

        return $clean;
    }
}

