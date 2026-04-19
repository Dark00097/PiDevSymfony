<?php

namespace App\Service;

final class RestructurationService
{
    /**
     * @param array<int, array<string, mixed>> $credits
     * @return array{
     *   by_credit: array<string, array<string, mixed>>,
     *   first_eligible_credit_id: int,
     *   first_critical_credit_id: int
     * }
     */
    public function buildPortfolioScenarios(array $credits): array
    {
        $byCredit = [];
        $firstEligibleCreditId = 0;
        $firstCriticalCreditId = 0;

        foreach ($credits as $credit) {
            $scenario = $this->buildCreditScenario($credit);
            $creditId = (int) ($scenario['credit_id'] ?? 0);
            if ($creditId <= 0) {
                continue;
            }

            $byCredit[(string) $creditId] = $scenario;
            if ($firstEligibleCreditId === 0 && (bool) ($scenario['eligible'] ?? false)) {
                $firstEligibleCreditId = $creditId;
            }
            if ($firstCriticalCreditId === 0 && (bool) ($scenario['auto_open'] ?? false)) {
                $firstCriticalCreditId = $creditId;
            }
        }

        return [
            'by_credit' => $byCredit,
            'first_eligible_credit_id' => $firstEligibleCreditId,
            'first_critical_credit_id' => $firstCriticalCreditId,
        ];
    }

    /**
     * @param array<string, mixed> $credit
     * @return array<string, mixed>
     */
    public function buildCreditScenario(array $credit): array
    {
        $creditId = (int) ($credit['idCredit'] ?? 0);
        $creditType = trim((string) ($credit['typeCredit'] ?? 'Credit'));
        $amountRequested = max(0.0, (float) ($credit['montantDemande'] ?? 0));
        $approvedAmount = max(0.0, (float) ($credit['montantAccorde'] ?? 0));
        $autoFunding = max(0.0, (float) ($credit['autofinancement'] ?? 0));
        $duration = max(1, (int) ($credit['duree'] ?? 1));
        $annualRate = max(0.1, (float) ($credit['tauxInteret'] ?? 8.0));
        $salary = max(0.0, (float) ($credit['salaire'] ?? 0));
        $riskScore = $this->clamp((float) ($credit['risk_score'] ?? 0), 0.0, 100.0);
        $monthly = max(0.0, (float) ($credit['mensualite'] ?? 0));
        $principal = max(0.0, $approvedAmount > 0 ? $approvedAmount : ($amountRequested - $autoFunding));
        if ($principal <= 0.0) {
            $principal = $amountRequested;
        }

        if ($monthly <= 0.0) {
            $monthly = $this->estimateMonthlyPayment($principal, $duration, $annualRate);
        }

        $debtRatio = $salary > 0.0 ? round(($monthly / $salary) * 100, 1) : 100.0;
        $creditScore = round($this->clamp(100.0 - $riskScore, 0.0, 100.0), 1);
        $riskLevel = $riskScore >= 70.0 ? 'Eleve' : ($riskScore >= 40.0 ? 'Moyen' : 'Faible');

        $eligible = $debtRatio > 40.0 || $creditScore < 55.0 || $riskScore >= 40.0;
        $critical = $debtRatio > 55.0 || $creditScore < 45.0 || $riskScore >= 65.0;

        $durationExtended = $duration + 24;
        $monthlyExtended = $this->estimateMonthlyPayment($principal, $durationExtended, $annualRate);

        $monthlyReduced = round(max(0.0, $monthly * 0.8), 2);
        $durationReduced = max($duration + 6, $this->solveDurationForTargetMonthly($principal, $annualRate, $monthlyReduced));
        $durationReduced = min($durationReduced, $duration + 84);

        $freezeMonthly = round($monthly + ($monthly / 3), 2);

        $reasons = [];
        if ($debtRatio > 40.0) {
            $reasons[] = sprintf('taux d endettement eleve (%.1f%%)', $debtRatio);
        }
        if ($creditScore < 55.0) {
            $reasons[] = sprintf('score faible (%.1f/100)', $creditScore);
        }
        if ($riskScore >= 40.0) {
            $reasons[] = sprintf('risque %s', strtolower($riskLevel));
        }

        return [
            'credit_id' => $creditId,
            'credit_type' => $creditType,
            'eligible' => $eligible,
            'auto_open' => $critical,
            'debt_ratio' => $debtRatio,
            'debt_ratio_formatted' => number_format($debtRatio, 1, '.', ' ').'%',
            'monthly_payment' => $monthly,
            'monthly_payment_formatted' => $this->formatMoney($monthly),
            'duration' => $duration,
            'duration_formatted' => sprintf('%d mois', $duration),
            'score' => $creditScore,
            'score_formatted' => number_format($creditScore, 1, '.', ' ').'/100',
            'risk_score' => $riskScore,
            'risk_level' => $riskLevel,
            'status_reason' => $reasons !== [] ? implode(', ', $reasons) : 'aucune difficulte majeure detectee',
            'info_text' => 'Ces simulations sont indicatives et peuvent etre validees ensuite avec votre conseiller bancaire.',
            'options' => [
                [
                    'key' => 'extend_duration',
                    'theme' => 'blue',
                    'title' => 'Allonger la duree (+24 mois)',
                    'description' => 'Etalez le remboursement sur 24 mois supplementaires pour alleger immediatement la charge.',
                    'old_amount' => $this->formatMoney($monthly),
                    'new_amount' => $this->formatMoney($monthlyExtended),
                    'old_duration' => sprintf('%d mois', $duration),
                    'new_duration' => sprintf('%d mois', $durationExtended),
                    'highlight' => sprintf('%s -> %s', $this->formatMoney($monthly), $this->formatMoney($monthlyExtended)),
                ],
                [
                    'key' => 'reduce_monthly',
                    'theme' => 'green',
                    'title' => 'Reduire la mensualite (-20%)',
                    'description' => 'Abaissez la mensualite cible de 20%% avec un allongement automatique de la duree.',
                    'old_amount' => $this->formatMoney($monthly),
                    'new_amount' => $this->formatMoney($monthlyReduced),
                    'old_duration' => sprintf('%d mois', $duration),
                    'new_duration' => sprintf('%d mois', $durationReduced),
                    'highlight' => sprintf('%s -> %s', $this->formatMoney($monthly), $this->formatMoney($monthlyReduced)),
                ],
                [
                    'key' => 'freeze_one_installment',
                    'theme' => 'violet',
                    'title' => 'Geler 1 mensualite',
                    'description' => 'Reportez une echeance puis etalez-la sur les 3 mensualites suivantes.',
                    'old_amount' => $this->formatMoney($monthly),
                    'new_amount' => $this->formatMoney($freezeMonthly),
                    'old_duration' => sprintf('%d mois', $duration),
                    'new_duration' => sprintf('%d mois', $duration),
                    'highlight' => sprintf('%s -> %s pendant 3 mois', $this->formatMoney($monthly), $this->formatMoney($freezeMonthly)),
                ],
            ],
        ];
    }

    private function estimateMonthlyPayment(float $principal, int $duration, float $annualRate): float
    {
        $principal = max(0.0, $principal);
        $duration = max(1, $duration);
        $monthlyRate = max(0.0, $annualRate / 100 / 12);

        if ($principal <= 0.0) {
            return 0.0;
        }

        if ($monthlyRate <= 0.0) {
            return round($principal / $duration, 2);
        }

        $monthly = ($principal * $monthlyRate) / (1 - pow(1 + $monthlyRate, -$duration));

        return round($monthly, 2);
    }

    private function solveDurationForTargetMonthly(float $principal, float $annualRate, float $targetMonthly): int
    {
        $principal = max(0.0, $principal);
        $targetMonthly = max(0.0, $targetMonthly);
        $monthlyRate = max(0.0, $annualRate / 100 / 12);

        if ($principal <= 0.0 || $targetMonthly <= 0.0) {
            return 1;
        }

        if ($monthlyRate <= 0.0) {
            return (int) ceil($principal / $targetMonthly);
        }

        $minimumMonthly = $principal * $monthlyRate;
        if ($targetMonthly <= $minimumMonthly) {
            return 240;
        }

        $duration = -log(1 - (($principal * $monthlyRate) / $targetMonthly)) / log(1 + $monthlyRate);

        return max(1, (int) ceil($duration));
    }

    private function formatMoney(float $value): string
    {
        return number_format(max(0.0, $value), 2, '.', ' ').' DT';
    }

    private function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }
}
