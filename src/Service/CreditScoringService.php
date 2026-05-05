<?php

namespace App\Service;

final class CreditScoringService
{
    public function __construct(private readonly RiskAssessmentService $riskAssessmentService)
    {
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function score(array $data): array
    {
        $salary = $this->toFloat($data['salary'] ?? 0);
        $employment = strtoupper(trim((string) ($data['employment_type'] ?? '')));
        $existingDebts = $this->toFloat($data['existing_debts'] ?? 0);
        $contribution = $this->toFloat($data['personal_contribution'] ?? 0);
        $monthlyPayment = $this->toFloat($data['monthly_payment'] ?? 0);
        $history = strtolower(trim((string) ($data['payment_history'] ?? 'neutral')));

        $debtRatio = $salary > 0 ? (($monthlyPayment + $existingDebts) / $salary) * 100 : 100;
        $score = 70.0;
        $reasons = [];

        if ($debtRatio < 35) {
            $score += 18;
        } elseif ($debtRatio <= 50) {
            $score -= 8;
            $reasons[] = 'Debt ratio is in the watch zone (35-50%).';
        } else {
            $score -= 28;
            $reasons[] = 'Debt ratio is high (>50%).';
        }

        if ($salary < 1200) {
            $score -= 14;
            $reasons[] = 'Declared salary is low for this request.';
        } elseif ($salary > 3000) {
            $score += 8;
        }

        if ($employment === 'CDI') {
            $score += 8;
        } elseif ($employment === 'CDD') {
            $score -= 7;
        } elseif ($employment === 'SELF-EMPLOYED') {
            $score -= 4;
        } else {
            $reasons[] = 'Employment type should be confirmed.';
        }

        if ($contribution > 0) {
            $score += min(10, $contribution / 1000);
        } else {
            $reasons[] = 'No personal contribution declared.';
        }

        if (str_contains($history, 'bad') || str_contains($history, 'late')) {
            $score -= 18;
            $reasons[] = 'Payment history indicates past incidents.';
        } elseif (str_contains($history, 'good')) {
            $score += 6;
        }

        $score = max(0.0, min(100.0, $score));
        $risk = $this->riskAssessmentService->assess($score, $debtRatio);

        return [
            'score' => round($score, 1),
            'risk_level' => $risk['level'],
            'decision' => $risk['decision'],
            'debt_ratio' => round($debtRatio, 1),
            'reasons' => $reasons,
        ];
    }

    private function toFloat(mixed $value): float
    {
        $raw = str_replace(',', '.', trim((string) $value));
        if ($raw === '' || !is_numeric($raw)) {
            return 0.0;
        }
        return (float) $raw;
    }
}

