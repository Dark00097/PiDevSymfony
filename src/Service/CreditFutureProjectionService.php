<?php

namespace App\Service;

final class CreditFutureProjectionService
{
    /**
     * @param array<string, mixed> $credit
     * @return array<string, mixed>
     */
    public function buildProjection(array $credit, int $horizonMonths, float $salaryChange = 0.0, float $earlyRepayment = 0.0): array
    {
        $horizon = in_array($horizonMonths, [6, 12, 24], true) ? $horizonMonths : 12;

        $principal = max(
            0.0,
            (float) ($credit['montantAccorde'] ?? 0) > 0
                ? (float) ($credit['montantAccorde'] ?? 0)
                : (float) ($credit['montantDemande'] ?? 0)
        );
        $duration = max(1, (int) ($credit['duree'] ?? 1));
        $annualRate = max(0.0, (float) ($credit['tauxInteret'] ?? 0));
        $monthlyRate = $annualRate > 0 ? ($annualRate / 100) / 12 : 0.0;
        $monthlyPayment = max(0.0, (float) ($credit['mensualite'] ?? 0));
        if ($monthlyPayment <= 0.0) {
            $monthlyPayment = $this->estimateMonthly($principal, $duration, $monthlyRate);
        }

        $salary = max(0.0, (float) ($credit['salaire'] ?? 0));
        $futureSalary = max(1.0, $salary + $salaryChange);
        $debtRatio = max(0.0, min(100.0, ($monthlyPayment / $futureSalary) * 100));

        $monthsElapsed = $this->resolveElapsedMonths((string) ($credit['dateDemande'] ?? ''), $duration);
        $remainingNow = $this->remainingBalance($principal, $monthlyPayment, $monthlyRate, $monthsElapsed);
        $remainingNow = max(0.0, $remainingNow - max(0.0, $earlyRepayment));

        $points = [];
        $remaining = $remainingNow;
        $startScore = $this->scoreForMonth($debtRatio, $remainingNow, $principal, 0.0, $earlyRepayment);
        $endScore = $startScore;

        for ($month = 1; $month <= $horizon; $month++) {
            if ($remaining > 0.0) {
                $interest = $remaining * $monthlyRate;
                $capitalPart = max(0.0, $monthlyPayment - $interest);
                $remaining = max(0.0, $remaining - $capitalPart);
            }

            $progress = $horizon > 0 ? ($month / $horizon) : 0.0;
            $score = $this->scoreForMonth($debtRatio, $remaining, $principal, $progress, $earlyRepayment);
            $endScore = $score;

            $points[] = [
                'month' => $month,
                'remaining' => round($remaining, 2),
                'score' => round($score, 1),
            ];
        }

        $remainingFuture = $points !== [] ? (float) $points[count($points) - 1]['remaining'] : round($remainingNow, 2);
        $scoreDelta = $endScore - $startScore;
        $conclusion = $this->buildConclusion($debtRatio, $endScore, $scoreDelta);

        return [
            'credit_id' => (int) ($credit['idCredit'] ?? 0),
            'credit_type' => (string) ($credit['typeCredit'] ?? 'Credit'),
            'horizon_months' => $horizon,
            'monthly_payment' => round($monthlyPayment, 2),
            'remaining_future' => round($remainingFuture, 2),
            'debt_ratio' => round($debtRatio, 1),
            'score' => round($endScore, 1),
            'score_start' => round($startScore, 1),
            'score_delta' => round($scoreDelta, 1),
            'conclusion' => $conclusion['status'],
            'conclusion_label' => $conclusion['label'],
            'conclusion_text' => $conclusion['text'],
            'series' => $points,
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

    private function remainingBalance(float $principal, float $monthlyPayment, float $monthlyRate, int $elapsedMonths): float
    {
        if ($principal <= 0.0) {
            return 0.0;
        }

        if ($elapsedMonths <= 0) {
            return $principal;
        }

        if ($monthlyRate <= 0.0) {
            return max(0.0, $principal - ($monthlyPayment * $elapsedMonths));
        }

        $factor = pow(1 + $monthlyRate, $elapsedMonths);

        return max(0.0, ($principal * $factor) - ($monthlyPayment * (($factor - 1) / $monthlyRate)));
    }

    private function resolveElapsedMonths(string $dateDemande, int $duration): int
    {
        if ($dateDemande === '') {
            return 0;
        }

        try {
            $start = new \DateTimeImmutable($dateDemande);
            $now = new \DateTimeImmutable('today');
            if ($start > $now) {
                return 0;
            }
            $diff = $start->diff($now);
            $elapsed = ($diff->y * 12) + $diff->m;

            return max(0, min($duration, $elapsed));
        } catch (\Throwable) {
            return 0;
        }
    }

    private function scoreForMonth(float $debtRatio, float $remaining, float $principal, float $progress, float $earlyRepayment): float
    {
        $remainingRatio = $principal > 0 ? min(1.0, max(0.0, $remaining / $principal)) : 0.0;
        $debtComponent = max(0.0, 45.0 - ($debtRatio * 0.6));
        $remainingComponent = (1.0 - $remainingRatio) * 30.0;
        $progressComponent = max(0.0, min(1.0, $progress)) * 12.0;
        $earlyBonus = $principal > 0 ? min(8.0, max(0.0, ($earlyRepayment / $principal) * 100.0)) : 0.0;

        return max(0.0, min(100.0, 30.0 + $debtComponent + $remainingComponent + $progressComponent + $earlyBonus));
    }

    /**
     * @return array{status:string,label:string,text:string}
     */
    private function buildConclusion(float $debtRatio, float $endScore, float $scoreDelta): array
    {
        if ($debtRatio > 50.0 || $endScore < 45.0) {
            return [
                'status' => 'risque',
                'label' => 'Risque',
                'text' => 'Votre projection indique une situation fragile. Reduire les charges ou augmenter les revenus est recommande.',
            ];
        }

        if ($scoreDelta >= 4.0 && $debtRatio <= 35.0) {
            return [
                'status' => 'ameliore',
                'label' => 'Ameliore',
                'text' => 'Votre projection est positive: le reste a payer baisse et votre profil de credit s ameliore.',
            ];
        }

        return [
            'status' => 'stable',
            'label' => 'Stable',
            'text' => 'Votre situation reste globalement stable sur la periode selectionnee.',
        ];
    }
}
