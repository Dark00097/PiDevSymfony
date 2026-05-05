<?php

namespace App\Service;

final class CreditAiAssistantService
{
    /**
     * @param array<string, mixed> $credit
     * @return array<string, mixed>
     */
    public function analyzeCredit(array $credit): array
    {
        $salary = $this->toFloat($credit['salaire'] ?? 0);
        $charges = $this->toFloat($credit['chargesMensuelles'] ?? $credit['charges'] ?? 0);
        $amount = $this->toFloat($credit['montantDemande'] ?? 0);
        $duration = max(0, (int) ($credit['duree'] ?? 0));
        $monthly = $this->resolveMensualite($credit, $amount, $duration);
        $employment = mb_strtolower(trim((string) ($credit['typeContrat'] ?? $credit['situationProfessionnelle'] ?? '')));
        $history = mb_strtolower(trim((string) ($credit['historiquePaiement'] ?? $credit['paymentHistory'] ?? $credit['statutPaiement'] ?? '')));
        $age = (int) ($credit['age'] ?? 0);

        $availableIncome = max(0.0, $salary - $charges);
        $debtRatio = $salary > 0 ? ($monthly / $salary) * 100 : 100.0;

        $score = 70.0;
        $anomalies = [];

        if ($debtRatio < 35) {
            $score += 15;
        } elseif ($debtRatio <= 50) {
            $score -= 8;
        } else {
            $score -= 30;
            $anomalies[] = 'Taux d endettement eleve (> 50%).';
        }

        if ($history !== '') {
            if (str_contains($history, 'mauvais') || str_contains($history, 'retard') || str_contains($history, 'incident')) {
                $score -= 20;
                $anomalies[] = 'Historique de paiement defavorable.';
            } elseif (str_contains($history, 'bon') || str_contains($history, 'excellent')) {
                $score += 6;
            }
        }

        if ($salary <= 0 || $amount <= 0 || $duration <= 0 || $monthly <= 0) {
            $anomalies[] = 'Donnees incompletes pour une analyse fiable.';
        }

        if ($availableIncome <= 0) {
            $score -= 25;
            $anomalies[] = 'Salaire disponible insuffisant apres charges.';
        }

        if ($monthly > $availableIncome && $availableIncome > 0) {
            $score -= 25;
            $anomalies[] = 'Mensualite superieure au salaire disponible.';
        }

        if ($employment !== '' && (str_contains($employment, 'cdd') || str_contains($employment, 'interim'))) {
            $score -= 8;
        }

        if ($age > 0 && ($age < 21 || $age > 70)) {
            $score -= 8;
            $anomalies[] = 'Age hors zone de confort standard du produit.';
        }

        $score = max(0.0, min(100.0, $score));

        $missing = [];
        foreach (['salaire', 'montantDemande', 'duree'] as $field) {
            if (!isset($credit[$field]) || trim((string) $credit[$field]) === '') {
                $missing[] = $field;
            }
        }

        $riskLevel = $this->riskLevelFromDebtRatio($debtRatio);
        if ($score < 45) {
            $riskLevel = 'Eleve';
        } elseif ($score < 65 && $riskLevel === 'Faible') {
            $riskLevel = 'Moyen';
        }

        $decision = 'Accepter';
        if ($missing !== []) {
            $decision = 'Reviser';
        } elseif ($monthly > $availableIncome && $availableIncome > 0) {
            $decision = 'Refuser';
        } elseif ($riskLevel === 'Eleve') {
            $decision = 'Refuser';
        } elseif ($riskLevel === 'Moyen') {
            $decision = 'Reviser';
        }

        $eligible = $decision === 'Accepter';
        $explanation = sprintf(
            'Endettement %.1f%%, score %.1f/100, revenu disponible %.2f DT.',
            $debtRatio,
            $score,
            $availableIncome
        );

        return [
            'score' => round($score, 1),
            'risk_level' => $riskLevel,
            'eligible' => $eligible,
            'anomalies' => array_values(array_unique($anomalies)),
            'decision' => $decision,
            'explanation' => $explanation,
            'debt_ratio' => round($debtRatio, 1),
            'monthly_payment' => round($monthly, 2),
            'available_income' => round($availableIncome, 2),
            'missing_data' => $missing,
        ];
    }

    /**
     * @param array<string, mixed> $credit
     * @return array<string, mixed>
     */
    public function analyzeGuarantee(array $credit): array
    {
        $amount = max(0.0, $this->toFloat($credit['montantDemande'] ?? 0));
        $garantieValue = max(0.0, $this->toFloat($credit['garantieValeur'] ?? $credit['valeurRetenue'] ?? $credit['valeurEstimee'] ?? 0));
        $garantieType = trim((string) ($credit['garantieType'] ?? $credit['typeGarantie'] ?? 'Non renseigne'));

        $ratio = $amount > 0 ? ($garantieValue / $amount) : 0.0;
        $coveragePercent = $ratio * 100;

        $coverageLevel = 'Insuffisante';
        if ($coveragePercent >= 120) {
            $coverageLevel = 'Suffisante';
        } elseif ($coveragePercent >= 80) {
            $coverageLevel = 'Moyenne';
        }

        $missingDocuments = $this->extractMissingDocuments($credit);
        $documentMissing = $missingDocuments !== [];
        $isValidated = $this->isGarantieValidated($credit);

        $legalRisk = 'Faible';
        if ($documentMissing || !$isValidated) {
            $legalRisk = 'Eleve';
        } elseif ($coverageLevel === 'Moyenne') {
            $legalRisk = 'Moyen';
        }

        $recommendation = 'Accepter la garantie';
        if ($coverageLevel === 'Insuffisante') {
            $recommendation = 'Reviser';
        } elseif ($documentMissing || !$isValidated) {
            $recommendation = 'Reviser';
        } elseif ($coverageLevel === 'Moyenne') {
            $recommendation = 'Accepter avec reserve';
        }

        return [
            'type' => $garantieType,
            'estimated_value' => round($garantieValue, 2),
            'credit_amount' => round($amount, 2),
            'coverage_percent' => round($coveragePercent, 1),
            'ratio' => round($ratio, 2),
            'coverage_level' => $coverageLevel,
            'legal_risk' => $legalRisk,
            'missing_documents' => $missingDocuments,
            'recommendation' => $recommendation,
            'validated' => $isValidated,
        ];
    }

    /**
     * @param array<string, mixed> $credit
     * @return array<string, mixed>
     */
    public function generateFinalDecision(array $credit): array
    {
        $creditAnalysis = $this->analyzeCredit($credit);
        $guaranteeAnalysis = $this->analyzeGuarantee($credit);

        $status = 'Accepter';
        $reason = 'Dossier coherent selon les regles metier.';

        if (($creditAnalysis['decision'] ?? '') === 'Refuser') {
            $status = 'Refuser';
            $reason = 'Le risque credit est trop eleve pour une validation.';
        } elseif (($guaranteeAnalysis['coverage_level'] ?? '') === 'Insuffisante') {
            $status = 'Reviser';
            $reason = 'La garantie ne couvre pas suffisamment le montant du credit.';
        } elseif (($guaranteeAnalysis['legal_risk'] ?? '') === 'Eleve') {
            $status = 'Reviser';
            $reason = 'Le risque juridique documentaire impose une revision.';
        } elseif (($creditAnalysis['decision'] ?? '') === 'Reviser' || ($guaranteeAnalysis['recommendation'] ?? '') === 'Reviser') {
            $status = 'Reviser';
            $reason = 'Des elements necessitent une verification complementaire.';
        }

        return [
            'credit_analysis' => [
                'score' => (float) ($creditAnalysis['score'] ?? 0),
                'risk_level' => (string) ($creditAnalysis['risk_level'] ?? ''),
                'eligible' => (bool) ($creditAnalysis['eligible'] ?? false),
                'anomalies' => is_array($creditAnalysis['anomalies'] ?? null) ? $creditAnalysis['anomalies'] : [],
                'decision' => (string) ($creditAnalysis['decision'] ?? ''),
                'explanation' => (string) ($creditAnalysis['explanation'] ?? ''),
                'debt_ratio' => (float) ($creditAnalysis['debt_ratio'] ?? 0),
                'monthly_payment' => (float) ($creditAnalysis['monthly_payment'] ?? 0),
            ],
            'guarantee_analysis' => [
                'coverage_percent' => (float) ($guaranteeAnalysis['coverage_percent'] ?? 0),
                'ratio' => (float) ($guaranteeAnalysis['ratio'] ?? 0),
                'coverage_level' => (string) ($guaranteeAnalysis['coverage_level'] ?? ''),
                'legal_risk' => (string) ($guaranteeAnalysis['legal_risk'] ?? ''),
                'missing_documents' => is_array($guaranteeAnalysis['missing_documents'] ?? null) ? $guaranteeAnalysis['missing_documents'] : [],
                'recommendation' => (string) ($guaranteeAnalysis['recommendation'] ?? ''),
                'type' => (string) ($guaranteeAnalysis['type'] ?? ''),
                'estimated_value' => (float) ($guaranteeAnalysis['estimated_value'] ?? 0),
            ],
            'final_decision' => [
                'status' => $status,
                'reason' => $reason,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $credit
     * @return array<string, mixed>
     */
    public function simulateScenario(array $credit, float $newAmount, int $newDuration): array
    {
        $scenario = $credit;
        $scenario['montantDemande'] = $newAmount;
        $scenario['duree'] = $newDuration;
        $scenario['mensualite'] = $this->computeMensualite(
            $newAmount,
            max(1, $newDuration),
            $this->toFloat($credit['tauxInteret'] ?? 8.0)
        );

        $analysis = $this->generateFinalDecision($scenario);
        $analysis['simulation'] = [
            'new_amount' => round($newAmount, 2),
            'new_duration' => $newDuration,
            'estimated_monthly' => round((float) $scenario['mensualite'], 2),
        ];

        return $analysis;
    }

    /**
     * @param array<string, mixed> $credit
     */
    private function resolveMensualite(array $credit, float $amount, int $duration): float
    {
        $monthly = $this->toFloat($credit['mensualite'] ?? 0);
        if ($monthly > 0) {
            return $monthly;
        }

        return $this->computeMensualite(
            $amount,
            max(1, $duration),
            $this->toFloat($credit['tauxInteret'] ?? 8.0)
        );
    }

    private function computeMensualite(float $amount, int $duration, float $annualRate): float
    {
        if ($amount <= 0 || $duration <= 0) {
            return 0.0;
        }

        $monthlyRate = max(0.0, $annualRate) / 12 / 100;
        if ($monthlyRate <= 0) {
            return $amount / $duration;
        }

        $factor = pow(1 + $monthlyRate, $duration);
        if ($factor <= 1) {
            return $amount / $duration;
        }

        return $amount * (($monthlyRate * $factor) / ($factor - 1));
    }

    private function riskLevelFromDebtRatio(float $debtRatio): string
    {
        if ($debtRatio < 35) {
            return 'Faible';
        }
        if ($debtRatio <= 50) {
            return 'Moyen';
        }

        return 'Eleve';
    }

    /**
     * @param array<string, mixed> $credit
     * @return array<int, string>
     */
    private function extractMissingDocuments(array $credit): array
    {
        $documents = [];
        $documentPath = trim((string) ($credit['documentJustificatif'] ?? ''));
        if ($documentPath === '') {
            $documents[] = 'Document justificatif de garantie';
        }

        $statusDoc = mb_strtolower(trim((string) ($credit['statutDocument'] ?? '')));
        if ($statusDoc === '' || str_contains($statusDoc, 'attente') || str_contains($statusDoc, 'refus')) {
            $documents[] = 'Validation documentaire';
        }

        return array_values(array_unique($documents));
    }

    /**
     * @param array<string, mixed> $credit
     */
    private function isGarantieValidated(array $credit): bool
    {
        $status = mb_strtolower(trim((string) ($credit['statutVerificationDocument'] ?? $credit['statutDocument'] ?? $credit['statutGarantie'] ?? '')));
        if ($status === '') {
            return false;
        }

        return str_contains($status, 'valid') || str_contains($status, 'approuv');
    }

    private function toFloat(mixed $value): float
    {
        if ($value === null) {
            return 0.0;
        }
        $normalized = str_replace(' ', '', str_replace(',', '.', (string) $value));
        if ($normalized === '' || !is_numeric($normalized)) {
            return 0.0;
        }

        return (float) $normalized;
    }
}

