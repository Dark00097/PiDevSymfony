<?php

namespace App\Controller;

use App\Service\AuthService;
use App\Service\BankingService;
use App\Service\CreditScoringService;
use App\Service\CreditSimulationService;
use App\Service\GuaranteeAnalysisService;
use App\Service\ProjectionService;
use App\Service\RecommendationEngine;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/user')]
final class UserChatbotDataController extends AbstractController
{
    #[Route('/credits', name: 'api_user_credits_data', methods: ['GET'])]
    public function credits(Request $request, AuthService $authService, BankingService $bankingService): JsonResponse
    {
        $user = $authService->getAuthenticatedUser($request->getSession());
        if ($user === null) {
            return new JsonResponse(['message' => 'Authentication required.'], 401);
        }
        $rows = $bankingService->listCredits((int) ($user['idUser'] ?? 0));
        $items = array_map(static fn (array $row): array => [
            'id' => (int) ($row['idCredit'] ?? 0),
            'type' => (string) ($row['typeCredit'] ?? 'Credit'),
            'amount' => (float) ($row['montantDemande'] ?? 0),
            'status' => (string) ($row['statut'] ?? 'En attente'),
            'duration' => (int) ($row['duree'] ?? 0),
            'monthly' => (float) ($row['mensualite'] ?? 0),
            'salary' => (float) ($row['salaire'] ?? 0),
        ], $rows);
        return new JsonResponse(['items' => $items]);
    }

    #[Route('/guarantees', name: 'api_user_guarantees_data', methods: ['GET'])]
    public function guarantees(Request $request, AuthService $authService, BankingService $bankingService): JsonResponse
    {
        $user = $authService->getAuthenticatedUser($request->getSession());
        if ($user === null) {
            return new JsonResponse(['message' => 'Authentication required.'], 401);
        }
        $rows = $bankingService->listGaranties((int) ($user['idUser'] ?? 0));
        $items = array_map(static fn (array $row): array => [
            'id' => (int) ($row['idGarantie'] ?? 0),
            'type' => (string) ($row['typeGarantie'] ?? 'Garantie'),
            'value' => (float) ($row['valeurRetenue'] ?? $row['valeurEstimee'] ?? 0),
            'credit_id' => (int) ($row['idCredit'] ?? 0),
        ], $rows);
        return new JsonResponse(['items' => $items]);
    }

    #[Route('/simulations', name: 'api_user_simulations_data', methods: ['GET'])]
    public function simulations(
        Request $request,
        AuthService $authService,
        BankingService $bankingService,
        CreditSimulationService $creditSimulationService
    ): JsonResponse {
        $user = $authService->getAuthenticatedUser($request->getSession());
        if ($user === null) {
            return new JsonResponse(['message' => 'Authentication required.'], 401);
        }
        $credits = $bankingService->listCredits((int) ($user['idUser'] ?? 0));
        $items = [];
        foreach (array_slice($credits, 0, 5) as $credit) {
            $sim = $creditSimulationService->simulate(
                (float) ($credit['montantDemande'] ?? 0),
                (int) ($credit['duree'] ?? 0),
                (float) ($credit['tauxInteret'] ?? 8),
                (float) ($credit['salaire'] ?? 0),
                0
            );
            $items[] = [
                'amount' => $sim['amount'],
                'duration_months' => $sim['duration_months'],
                'monthly_payment' => $sim['monthly_payment'],
            ];
        }
        return new JsonResponse(['items' => $items]);
    }

    #[Route('/profile-analysis', name: 'api_user_profile_analysis_data', methods: ['GET'])]
    public function profileAnalysis(
        Request $request,
        AuthService $authService,
        BankingService $bankingService,
        CreditSimulationService $simulationService,
        CreditScoringService $scoringService,
        GuaranteeAnalysisService $guaranteeAnalysisService,
        RecommendationEngine $recommendationEngine
    ): JsonResponse {
        $user = $authService->getAuthenticatedUser($request->getSession());
        if ($user === null) {
            return new JsonResponse(['message' => 'Authentication required.'], 401);
        }
        $credits = $bankingService->listCredits((int) ($user['idUser'] ?? 0));
        $garanties = $bankingService->listGaranties((int) ($user['idUser'] ?? 0));
        $credit = is_array($credits[0] ?? null) ? $credits[0] : [];
        if ($credit === []) {
            return new JsonResponse(['message' => 'Vous n avez aucun dossier pour analyse.', 'score' => 0, 'risk_level' => 'Medium', 'decision' => 'Review Needed', 'recommendations' => []]);
        }
        $sim = $simulationService->simulate((float) ($credit['montantDemande'] ?? 0), (int) ($credit['duree'] ?? 60), (float) ($credit['tauxInteret'] ?? 8), (float) ($credit['salaire'] ?? 0), 0);
        $score = $scoringService->score([
            'salary' => $credit['salaire'] ?? 0,
            'employment_type' => $credit['typeContrat'] ?? '',
            'existing_debts' => 0,
            'personal_contribution' => $credit['autofinancement'] ?? 0,
            'monthly_payment' => $sim['monthly_payment'],
            'payment_history' => 'neutral',
        ]);
        $garantie = $guaranteeAnalysisService->analyze([
            'guarantee_type' => $garanties[0]['typeGarantie'] ?? '',
            'credit_amount' => $credit['montantDemande'] ?? 0,
            'guarantee_value' => $garanties[0]['valeurRetenue'] ?? $garanties[0]['valeurEstimee'] ?? 0,
            'documents' => [],
        ]);
        $recs = $recommendationEngine->generate($sim, $score, $garantie);

        return new JsonResponse([
            'score' => $score['score'],
            'risk_level' => $score['risk_level'],
            'decision' => $score['decision'],
            'recommendations' => $recs,
            'guarantee_strength' => $garantie['guarantee_strength'],
        ]);
    }

    #[Route('/future-projection', name: 'api_user_future_projection_data', methods: ['GET'])]
    public function futureProjection(Request $request, AuthService $authService, BankingService $bankingService, ProjectionService $projectionService): JsonResponse
    {
        $user = $authService->getAuthenticatedUser($request->getSession());
        if ($user === null) {
            return new JsonResponse(['message' => 'Authentication required.'], 401);
        }
        $credit = $bankingService->listCredits((int) ($user['idUser'] ?? 0))[0] ?? null;
        if (!is_array($credit)) {
            return new JsonResponse(['message' => 'Je n ai pas assez de donnees pour faire une projection. Faites d abord une simulation ou ajoutez un dossier de credit.']);
        }
        $p6 = $projectionService->evaluate($credit, 6, 0, 0);
        $p12 = $projectionService->evaluate($credit, 12, 0, 0);
        $p36 = $projectionService->evaluate($credit, 36, 0, 0);
        $p60 = $projectionService->evaluate($credit, 60, 0, 0);
        return new JsonResponse([
            'timeline' => [
                ['label' => 'Dans 6 mois', 'status' => (string) ($p6['conclusion_label'] ?? 'stable')],
                ['label' => 'Dans 1 an', 'score' => (float) ($p12['score'] ?? 0)],
                ['label' => 'Dans 3 ans', 'status' => (string) ($p36['conclusion_label'] ?? 'amelioree')],
                ['label' => 'Dans 5 ans', 'status' => (string) ($p60['risk_level'] ?? 'faible')],
            ],
            'advice' => 'Gardez un taux d endettement inferieur a 35% et ajoutez une garantie forte.',
        ]);
    }

    #[Route('/bank-comparison', name: 'api_user_bank_comparison_data', methods: ['GET'])]
    public function bankComparison(Request $request, AuthService $authService, BankingService $bankingService, CreditSimulationService $simulationService): JsonResponse
    {
        $user = $authService->getAuthenticatedUser($request->getSession());
        if ($user === null) {
            return new JsonResponse(['message' => 'Authentication required.'], 401);
        }
        $credit = $bankingService->listCredits((int) ($user['idUser'] ?? 0))[0] ?? null;
        if (!is_array($credit)) {
            return new JsonResponse(['message' => 'Je ne peux pas comparer les banques sans dossier ou simulation. Faites d abord une simulation.', 'table' => []]);
        }
        $amount = (float) ($credit['montantDemande'] ?? 20000);
        $duration = (int) ($credit['duree'] ?? 60);
        $baseRate = (float) ($credit['tauxInteret'] ?? 8);
        $salary = max(1.0, (float) ($credit['salaire'] ?? 2500));
        $banks = [
            ['bank' => 'BIAT', 'rate' => max(4.5, $baseRate - 0.6), 'fees' => 190, 'max' => 120, 'garantie' => 'Immobiliere'],
            ['bank' => 'Attijari Bank', 'rate' => max(4.7, $baseRate - 0.4), 'fees' => 210, 'max' => 108, 'garantie' => 'Vehicule/Immobiliere'],
            ['bank' => 'Amen Bank', 'rate' => max(4.9, $baseRate - 0.2), 'fees' => 230, 'max' => 96, 'garantie' => 'Immobiliere'],
            ['bank' => 'STB', 'rate' => max(5.1, $baseRate + 0.1), 'fees' => 170, 'max' => 84, 'garantie' => 'Depot/Garant'],
            ['bank' => 'BH Bank', 'rate' => max(5.0, $baseRate), 'fees' => 200, 'max' => 96, 'garantie' => 'Immobiliere'],
            ['bank' => 'Banque Zitouna', 'rate' => max(5.3, $baseRate + 0.2), 'fees' => 250, 'max' => 84, 'garantie' => 'Immobiliere'],
        ];
        $table = [];
        foreach ($banks as $b) {
            $sim = $simulationService->simulate($amount, min($duration, (int) $b['max']), (float) $b['rate'], $salary, 0);
            $compat = max(50, min(95, (int) round(100 - ($sim['monthly_payment'] / $salary * 25))));
            $table[] = [
                'bank' => $b['bank'],
                'rate' => $b['rate'],
                'monthly' => $sim['monthly_payment'],
                'garantie' => $b['garantie'],
                'compatibility' => $compat,
                'opinion' => $compat >= 80 ? 'Tres favorable' : ($compat >= 65 ? 'Moyenne' : 'A etudier'),
                'total_cost' => $sim['total_repayment'] + (float) $b['fees'],
            ];
        }
        usort($table, static fn (array $a, array $b): int => ($b['compatibility'] <=> $a['compatibility']));
        return new JsonResponse(['best' => $table[0] ?? null, 'table' => $table]);
    }
}

