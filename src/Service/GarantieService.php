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
