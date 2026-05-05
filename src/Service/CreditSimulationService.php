<?php

namespace App\Service;

final class CreditSimulationService
{
    /**
     * @return array<string, mixed>
     */
    public function simulate(
        float $amount,
        int $durationMonths,
        float $annualRatePercent,
        float $salary = 0.0,
        float $existingDebts = 0.0
    ): array {
        $amount = max(0.0, $amount);
        $durationMonths = max(1, $durationMonths);
        $annualRatePercent = max(0.0, $annualRatePercent);
        $existingDebts = max(0.0, $existingDebts);

        $monthlyRate = $annualRatePercent / 12 / 100;
        if ($monthlyRate <= 0) {
            $monthlyPayment = $amount / $durationMonths;
        } else {
            $factor = pow(1 + $monthlyRate, $durationMonths);
            $monthlyPayment = $amount * (($monthlyRate * $factor) / ($factor - 1));
        }

        $totalRepayment = $monthlyPayment * $durationMonths;
        $debtRatio = $salary > 0 ? (($monthlyPayment + $existingDebts) / $salary) * 100 : 0.0;
        $affordability = $debtRatio <= 35 ? 'Comfortable' : ($debtRatio <= 50 ? 'Tight' : 'Risky');

        return [
            'amount' => round($amount, 2),
            'duration_months' => $durationMonths,
            'annual_rate' => round($annualRatePercent, 2),
            'monthly_payment' => round($monthlyPayment, 2),
            'total_repayment' => round($totalRepayment, 2),
            'debt_ratio' => round($debtRatio, 1),
            'affordability' => $affordability,
        ];
    }
}

