<?php

namespace App\Service;

use App\Entity\Credit;

final class SimulationService
{
    public function __construct(
        private readonly CreditAnalysisFactory $creditAnalysisFactory,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function simulate(Credit $credit): array
    {
        $requestedAmount = max(0.0, $this->creditAnalysisFactory->getFloat($credit, 'montantdemande'));
        $approvedAmount = max(0.0, $this->creditAnalysisFactory->getFloat($credit, 'montantaccorde'));
        $duration = max(1, $this->creditAnalysisFactory->getInt($credit, 'duree'));
        $annualRate = max(0.0, $this->creditAnalysisFactory->getFloat($credit, 'tauxinteret'));
        $manualMonthlyPayment = max(0.0, $this->creditAnalysisFactory->getFloat($credit, 'mensualite'));
        $autoFunding = max(0.0, $this->creditAnalysisFactory->getNullableFloat($credit, 'autofinancement') ?? 0.0);

        $principal = $approvedAmount > 0 ? $approvedAmount : $requestedAmount;
        $monthlyPayment = $manualMonthlyPayment > 0
            ? $manualMonthlyPayment
            : $this->calculateMonthlyPayment($principal, $annualRate, $duration);

        $schedule = $this->buildAmortizationSchedule($principal, $annualRate, $duration, $monthlyPayment);
        $totalCost = array_reduce(
            $schedule,
            static fn (float $carry, array $row): float => $carry + (float) $row['payment'],
            0.0
        );

        return [
            'principal' => round($principal, 2),
            'requested_amount' => round($requestedAmount, 2),
            'approved_amount' => round($approvedAmount, 2),
            'autofinancement' => round($autoFunding, 2),
            'duration_months' => $duration,
            'annual_rate' => round($annualRate, 2),
            'monthly_rate' => round($annualRate / 12, 4),
            'monthly_payment' => round($monthlyPayment, 2),
            'total_cost' => round($totalCost, 2),
            'interest_cost' => round(max(0.0, $totalCost - $principal), 2),
            'schedule' => $schedule,
        ];
    }

    public function calculateMonthlyPayment(float $principal, float $annualRate, int $durationMonths): float
    {
        if ($principal <= 0 || $durationMonths <= 0) {
            return 0.0;
        }

        $monthlyRate = $annualRate / 100 / 12;
        if ($monthlyRate <= 0) {
            return round($principal / $durationMonths, 2);
        }

        $payment = ($principal * $monthlyRate) / (1 - (1 + $monthlyRate) ** (-$durationMonths));

        return round($payment, 2);
    }

    /**
     * @return array<int, array<string, float|int>>
     */
    public function buildAmortizationSchedule(
        float $principal,
        float $annualRate,
        int $durationMonths,
        ?float $monthlyPayment = null,
    ): array {
        if ($principal <= 0 || $durationMonths <= 0) {
            return [];
        }

        $balance = round($principal, 2);
        $payment = $monthlyPayment !== null && $monthlyPayment > 0
            ? round($monthlyPayment, 2)
            : $this->calculateMonthlyPayment($principal, $annualRate, $durationMonths);
        $monthlyRate = $annualRate / 100 / 12;
        $schedule = [];

        for ($month = 1; $month <= $durationMonths && $balance > 0; ++$month) {
            $openingBalance = $balance;
            $interest = round($openingBalance * $monthlyRate, 2);
            $principalPayment = round($payment - $interest, 2);

            if ($monthlyRate <= 0) {
                $interest = 0.0;
                $principalPayment = round($payment, 2);
            }

            if ($principalPayment <= 0 || $principalPayment > $openingBalance || $month === $durationMonths) {
                $principalPayment = round($openingBalance, 2);
                $payment = round($principalPayment + $interest, 2);
            }

            $balance = round(max(0.0, $openingBalance - $principalPayment), 2);

            $schedule[] = [
                'month' => $month,
                'opening_balance' => $openingBalance,
                'payment' => $payment,
                'interest' => $interest,
                'principal' => $principalPayment,
                'remaining_balance' => $balance,
            ];
        }

        return $schedule;
    }
}
