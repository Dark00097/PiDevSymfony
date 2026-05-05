<?php

namespace App\Service;

final class ProjectionService
{
    public function __construct(
        private readonly CreditFutureProjectionService $futureProjectionService,
        private readonly RiskService $riskService,
    ) {
    }

    /**
     * @param array<string, mixed> $credit
     * @return array<string, mixed>
     */
    public function evaluate(array $credit, int $horizonMonths, float $salaryChange = 0.0, float $earlyRepayment = 0.0): array
    {
        $projection = $this->futureProjectionService->buildProjection($credit, $horizonMonths, $salaryChange, $earlyRepayment);
        $risk = $this->riskService->evaluate(
            (float) ($projection['debt_ratio'] ?? 0.0),
            (float) ($projection['score'] ?? 0.0)
        );

        $projection['risk_level'] = $risk['level'];
        $projection['risk_status'] = $risk['status'];
        $projection['risk_message'] = $risk['message'];

        return $projection;
    }
}

