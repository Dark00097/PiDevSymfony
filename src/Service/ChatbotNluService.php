<?php

namespace App\Service;

final class ChatbotNluService
{
    /**
     * @return array<string, mixed>
     */
    public function parse(string $message): array
    {
        $text = mb_strtolower(trim($message));

        $intent = 'general';
        if (str_contains($text, 'loan') || str_contains($text, 'credit') || str_contains($text, 'pr') || str_contains($text, 'mensual')) {
            $intent = 'simulation';
        }
        if (str_contains($text, 'salary') || str_contains($text, 'eligible') || str_contains($text, 'enough')) {
            $intent = 'eligibility';
        }
        if (str_contains($text, 'guarantee') || str_contains($text, 'collateral') || str_contains($text, 'garantie')) {
            $intent = 'guarantee';
        }

        $amount = $this->extractAmount($text);
        $durationMonths = $this->extractDurationMonths($text);
        $rate = $this->extractRate($text);

        return [
            'intent' => $intent,
            'amount' => $amount,
            'duration_months' => $durationMonths,
            'interest_rate' => $rate > 0 ? $rate : 8.0,
        ];
    }

    private function extractAmount(string $text): float
    {
        if (preg_match('/(\d[\d\s]{2,})(?:\s*(dt|tnd|dinar|€|\$))?/iu', $text, $match) === 1) {
            $digits = preg_replace('/[^\d]/', '', (string) $match[1]);
            return (float) $digits;
        }
        return 0.0;
    }

    private function extractDurationMonths(string $text): int
    {
        if (preg_match('/(\d{1,2})\s*(year|years|ans|an)/iu', $text, $match) === 1) {
            return (int) $match[1] * 12;
        }
        if (preg_match('/(\d{1,3})\s*(month|months|mois)/iu', $text, $match) === 1) {
            return (int) $match[1];
        }
        return 0;
    }

    private function extractRate(string $text): float
    {
        if (preg_match('/(\d{1,2}(?:[.,]\d{1,2})?)\s*%/u', $text, $match) === 1) {
            return (float) str_replace(',', '.', (string) $match[1]);
        }
        return 0.0;
    }
}

