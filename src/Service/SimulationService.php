<?php

namespace App\Service;

final class SimulationService
{
    /**
     * @return array<string, float|int>
     */
    public function simulate(
        float $amount,
        int $durationMonths,
        float $annualRate,
        float $insuranceRate = 0.0,
        float $processingFee = 0.0
    ): array {
        $principal = max(0.0, $amount);
        $duration = max(1, $durationMonths);
        $rate = max(0.0, $annualRate);
        $insurance = max(0.0, $insuranceRate);
        $fee = max(0.0, $processingFee);

        $monthlyRate = $rate / 100 / 12;
        if ($monthlyRate <= 0.0) {
            $monthly = $principal / $duration;
        } else {
            $factor = pow(1 + $monthlyRate, $duration);
            $monthly = $principal * ($monthlyRate * $factor) / max(0.000001, ($factor - 1));
        }

        $monthlyInsurance = $insurance > 0.0 ? ($principal * ($insurance / 100)) / 12 : 0.0;
        $monthlyWithInsurance = $monthly + $monthlyInsurance;
        $totalCost = ($monthlyWithInsurance * $duration) + $fee;
        $interestCost = max(0.0, $totalCost - $principal - $fee);
        $teg = $rate + $insurance + ($principal > 0.0 ? (($fee / $principal) * 100.0) : 0.0);

        return [
            'amount' => round($principal, 2),
            'duration_months' => $duration,
            'annual_rate' => round($rate, 2),
            'insurance_rate' => round($insurance, 2),
            'processing_fee' => round($fee, 2),
            'monthly_payment' => round($monthlyWithInsurance, 2),
            'total_cost' => round($totalCost, 2),
            'interest_cost' => round($interestCost, 2),
            'teg' => round($teg, 2),
        ];
    }
}

