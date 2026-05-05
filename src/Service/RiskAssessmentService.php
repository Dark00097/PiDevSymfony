<?php

namespace App\Service;

final class RiskAssessmentService
{
    /**
     * @return array{level:string,decision:string,color:string}
     */
    public function assess(float $score, float $debtRatio): array
    {
        $level = 'Low';
        if ($debtRatio > 50 || $score < 45) {
            $level = 'High';
        } elseif ($debtRatio >= 35 || $score < 70) {
            $level = 'Medium';
        }

        $decision = 'Approved';
        if ($level === 'High') {
            $decision = 'Rejected';
        } elseif ($level === 'Medium') {
            $decision = 'Review Needed';
        }

        $color = $level === 'Low' ? 'green' : ($level === 'Medium' ? 'orange' : 'red');

        return ['level' => $level, 'decision' => $decision, 'color' => $color];
    }
}

