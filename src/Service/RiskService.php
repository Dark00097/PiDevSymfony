<?php

namespace App\Service;

final class RiskService
{
    /**
     * @return array{level:string,status:string,color:string,message:string}
     */
    public function evaluate(float $debtRatio, float $score): array
    {
        if ($debtRatio >= 50.0 || $score < 45.0) {
            return [
                'level' => 'Eleve',
                'status' => 'high',
                'color' => 'red',
                'message' => 'Risque eleve: la charge mensuelle est trop importante pour le profil.',
            ];
        }

        if ($debtRatio >= 35.0 || $score < 70.0) {
            return [
                'level' => 'Moyen',
                'status' => 'medium',
                'color' => 'orange',
                'message' => 'Risque moyen: dossier acceptable avec conditions de securite.',
            ];
        }

        return [
            'level' => 'Faible',
            'status' => 'low',
            'color' => 'green',
            'message' => 'Risque faible: profil sain et remboursement globalement confortable.',
        ];
    }
}

