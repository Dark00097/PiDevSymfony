<?php

namespace App\Service;

final class RecommendationEngine
{
    /**
     * @param array<string, mixed> $simulation
     * @param array<string, mixed> $score
     * @param array<string, mixed> $guarantee
     * @return array<int, string>
     */
    public function generate(array $simulation, array $score, array $guarantee): array
    {
        $items = [];

        $debtRatio = (float) ($score['debt_ratio'] ?? 0);
        if ($debtRatio > 50) {
            $items[] = 'Reduce requested amount to lower monthly pressure.';
            $items[] = 'Extend duration to decrease monthly payment.';
        } elseif ($debtRatio >= 35) {
            $items[] = 'Consider a small down payment increase to improve profile.';
        }

        $decision = (string) ($score['decision'] ?? '');
        if ($decision === 'Review Needed') {
            $items[] = 'Add co-borrower or additional income proof for stronger approval odds.';
        } elseif ($decision === 'Rejected') {
            $items[] = 'Rework application with lower amount and stronger guarantees before resubmission.';
        }

        $strength = (string) ($guarantee['guarantee_strength'] ?? 'Weak');
        if ($strength === 'Weak') {
            $items[] = 'Add stronger collateral (real estate preferred).';
        } elseif ($strength === 'Moderate') {
            $items[] = 'Increase guarantee value to reach stronger coverage.';
        }

        $missing = is_array($guarantee['missing_documents'] ?? null) ? $guarantee['missing_documents'] : [];
        if ($missing !== []) {
            $items[] = 'Complete missing legal documents to reduce legal risk.';
        }

        if ((string) ($simulation['affordability'] ?? '') === 'Risky') {
            $items[] = 'Pause and request a human advisor review before formal submission.';
        }

        return array_values(array_unique($items));
    }
}

