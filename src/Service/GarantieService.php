<?php

namespace App\Service;

use App\Entity\Credit;
use App\Entity\Garantiecredit;

final class GarantieService
{
    public function __construct(
        private readonly CreditAnalysisFactory $creditAnalysisFactory,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function analyzeCoverage(Credit $credit): array
    {
        $requestedAmount = max(0.0, $this->creditAnalysisFactory->getFloat($credit, 'montantdemande'));
        $guarantees = $this->creditAnalysisFactory->getGuarantees($credit);

        $estimatedTotal = 0.0;
        $retainedTotal = 0.0;

        foreach ($guarantees as $guarantee) {
            if ($guarantee instanceof Garantiecredit) {
                $estimatedTotal += $this->readGuaranteeFloat($guarantee, 'valeurestimee');
                $retainedTotal += $this->readGuaranteeFloat($guarantee, 'valeurretenue');
                continue;
            }

            if (is_array($guarantee)) {
                $estimatedTotal += (float) ($guarantee['valeurEstimee'] ?? $guarantee['valeurestimee'] ?? 0);
                $retainedTotal += (float) ($guarantee['valeurRetenue'] ?? $guarantee['valeurretenue'] ?? 0);
            }
        }

        $coverageRatio = $requestedAmount > 0 ? $retainedTotal / $requestedAmount : 0.0;
        $covered = $requestedAmount > 0 && $retainedTotal >= $requestedAmount;

        $status = match (true) {
            $coverageRatio >= 1.0 => ['label' => 'Couverte', 'color' => '#28a745', 'badge_class' => 'text-bg-success'],
            $coverageRatio >= 0.6 => ['label' => 'Partielle', 'color' => '#ffc107', 'badge_class' => 'text-bg-warning'],
            default => ['label' => 'Insuffisante', 'color' => '#dc3545', 'badge_class' => 'text-bg-danger'],
        };

        return [
            'covered' => $covered,
            'coverage_ratio' => round($coverageRatio, 4),
            'estimated_total' => round($estimatedTotal, 2),
            'retained_total' => round($retainedTotal, 2),
            'shortfall' => round(max(0.0, $requestedAmount - $retainedTotal), 2),
            'label' => $status['label'],
            'color' => $status['color'],
            'badge_class' => $status['badge_class'],
        ];
    }

    /**
     * @param array<string, mixed> $credit
     * @param array<string, mixed>|null $garantie
     * @return array{
     *   covered: bool,
     *   coverage_ratio: float,
     *   estimated_total: float,
     *   retained_total: float,
     *   shortfall: float,
     *   label: string,
     *   message: string
     * }
     */
    public function analyzeCoverageFromRows(array $credit, ?array $garantie): array
    {
        $requestedAmount = max(0.0, (float) ($credit['montantDemande'] ?? 0));
        if ((float) ($credit['montantAccorde'] ?? 0) > 0) {
            $requestedAmount = max($requestedAmount, (float) ($credit['montantAccorde'] ?? 0));
        }

        $estimatedTotal = max(0.0, (float) ($garantie['valeurEstimee'] ?? 0));
        $retainedTotal = max(0.0, (float) ($garantie['valeurRetenue'] ?? 0));
        $coverageRatio = $requestedAmount > 0 ? $retainedTotal / $requestedAmount : 0.0;
        $covered = $requestedAmount > 0 && $retainedTotal >= $requestedAmount;

        $label = $coverageRatio >= 1.0 ? 'Suffisante' : ($coverageRatio >= 0.6 ? 'Partielle' : 'Insuffisante');
        $message = match ($label) {
            'Suffisante' => 'La garantie couvre correctement le credit.',
            'Partielle' => 'La garantie couvre une partie du credit, un renfort est conseille.',
            default => 'La garantie est insuffisante pour securiser le dossier.',
        };

        return [
            'covered' => $covered,
            'coverage_ratio' => round($coverageRatio * 100, 1),
            'estimated_total' => round($estimatedTotal, 2),
            'retained_total' => round($retainedTotal, 2),
            'shortfall' => round(max(0.0, $requestedAmount - $retainedTotal), 2),
            'label' => $label,
            'message' => $message,
        ];
    }

    private function readGuaranteeFloat(Garantiecredit $garantie, string $property): float
    {
        $reflection = new \ReflectionObject($garantie);
        if (!$reflection->hasProperty($property)) {
            return 0.0;
        }

        $objectProperty = $reflection->getProperty($property);
        $objectProperty->setAccessible(true);
        if (!$objectProperty->isInitialized($garantie)) {
            return 0.0;
        }

        $value = $objectProperty->getValue($garantie);

        return is_numeric($value) ? (float) $value : 0.0;
    }
}
