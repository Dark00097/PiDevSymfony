<?php

namespace App\Service;

final class ScoringService
{
    /**
     * @param array<string, mixed> $credit
     * @param array<string, mixed>|null $garantie
     * @return array{score: float, debt_ratio: float, monthly_payment: float, message: string}
     */
    public function evaluate(array $credit, ?array $garantie = null): array
    {
        $amount = max(0.0, $this->toFloat($credit['montantAccorde'] ?? $credit['montantDemande'] ?? 0));
        $duration = max(1, (int) ($credit['duree'] ?? 12));
        $annualRate = max(0.0, $this->toFloat($credit['tauxInteret'] ?? 0));
        $monthlyRate = $annualRate > 0 ? ($annualRate / 100) / 12 : 0.0;

        $monthly = max(0.0, $this->toFloat($credit['mensualite'] ?? 0));
        if ($monthly <= 0.0) {
            $monthly = $this->estimateMonthly($amount, $duration, $monthlyRate);
        }

        $salary = max(1.0, $this->toFloat($credit['salaire'] ?? 0));
        $debtRatio = max(0.0, min(100.0, ($monthly / $salary) * 100));

        $coverage = 0.0;
        if (is_array($garantie) && $amount > 0) {
            $retained = max(0.0, $this->toFloat($garantie['valeurRetenue'] ?? 0));
            $coverage = min(150.0, ($retained / $amount) * 100);
        }

        $durationPenalty = min(18.0, max(0.0, ($duration - 12) * 0.2));
        $ratePenalty = min(14.0, $annualRate * 0.9);
        $debtPenalty = min(45.0, $debtRatio * 1.15);
        $salaryBonus = min(10.0, ($salary / 1200.0) * 3.0);
        $coverageBonus = min(12.0, $coverage * 0.08);

        $score = 78.0 - $durationPenalty - $ratePenalty - $debtPenalty + $salaryBonus + $coverageBonus;
        $score = round(max(0.0, min(100.0, $score)), 1);

        $message = $score >= 70
            ? 'Profil solide avec une bonne capacite de remboursement.'
            : ($score >= 50
                ? 'Profil moyen: quelques ajustements peuvent renforcer le dossier.'
                : 'Profil fragile: il faut reduire le risque avant validation.');

        return [
            'score' => $score,
            'debt_ratio' => round($debtRatio, 1),
            'monthly_payment' => round($monthly, 2),
            'message' => $message,
        ];
    }

    private function estimateMonthly(float $principal, int $duration, float $monthlyRate): float
    {
        if ($principal <= 0.0) {
            return 0.0;
        }
        if ($monthlyRate <= 0.0) {
            return $principal / max(1, $duration);
        }

        $factor = pow(1 + $monthlyRate, $duration);

        return $principal * ($monthlyRate * $factor) / max(0.000001, ($factor - 1));
    }

    private function toFloat(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }
}

