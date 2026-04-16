<?php

namespace App\Service;

use App\Entity\Credit;

final class RestructurationService
{
    public function __construct(
        private readonly CreditAnalysisFactory $creditAnalysisFactory,
        private readonly SimulationService $simulationService,
    ) {
    }

    /**
     * @param array<string, mixed> $riskData
     * @return array<string, mixed>
     */
    public function propose(Credit $credit, array $riskData): array
    {
        $riskLabel = $riskData['label'] ?? '';
        if (!in_array($riskLabel, ['Élevé', 'Eleve', 'Elevé', 'élevé', 'eleve'], true)) {
            return [
                'eligible' => false,
                'message' => 'Aucune restructuration prioritaire n est necessaire.',
                'solutions' => [],
            ];
        }

        $amount = max(0.0, $this->creditAnalysisFactory->getFloat($credit, 'montantaccorde', 'montantdemande'));
        $duration = max(1, $this->creditAnalysisFactory->getInt($credit, 'duree'));
        $rate = max(0.0, $this->creditAnalysisFactory->getFloat($credit, 'tauxinteret'));
        $currentMonthly = max(0.0, $this->creditAnalysisFactory->getFloat($credit, 'mensualite'));

        $solutions = [];

        $extendedDuration = min(120, $duration + 12);
        $extendedMonthly = $this->simulationService->calculateMonthlyPayment($amount, $rate, $extendedDuration);
        $solutions[] = [
            'title' => 'Allonger la duree',
            'description' => 'Etendre la duree du credit de 12 mois pour lisser la mensualite.',
            'duration_months' => $extendedDuration,
            'annual_rate' => round($rate, 2),
            'monthly_payment' => round($extendedMonthly, 2),
            'monthly_delta' => round($currentMonthly - $extendedMonthly, 2),
        ];

        $reducedRate = max(0.5, $rate - 0.75);
        $reducedRateMonthly = $this->simulationService->calculateMonthlyPayment($amount, $reducedRate, $duration);
        $solutions[] = [
            'title' => 'Negocier le taux',
            'description' => 'Reduire le taux annuel de 0.75 point pour diminuer le cout mensuel.',
            'duration_months' => $duration,
            'annual_rate' => round($reducedRate, 2),
            'monthly_payment' => round($reducedRateMonthly, 2),
            'monthly_delta' => round($currentMonthly - $reducedRateMonthly, 2),
        ];

        $combinedDuration = min(120, $duration + 24);
        $combinedRate = max(0.5, $rate - 1.0);
        $combinedMonthly = $this->simulationService->calculateMonthlyPayment($amount, $combinedRate, $combinedDuration);
        $solutions[] = [
            'title' => 'Scenario combine',
            'description' => 'Allonger la duree et ajuster legerement le taux pour restaurer la capacite de remboursement.',
            'duration_months' => $combinedDuration,
            'annual_rate' => round($combinedRate, 2),
            'monthly_payment' => round($combinedMonthly, 2),
            'monthly_delta' => round($currentMonthly - $combinedMonthly, 2),
        ];

        return [
            'eligible' => true,
            'message' => 'Des pistes de restructuration peuvent etre proposees pour reduire le risque.',
            'solutions' => $solutions,
        ];
    }
}
