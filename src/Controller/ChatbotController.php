<?php

namespace App\Controller;

use App\Service\AuthService;
use App\Service\BankingService;
use App\Service\ChatbotNluService;
use App\Service\CreditScoringService;
use App\Service\CreditSimulationService;
use App\Service\GuaranteeAnalysisService;
use App\Service\ProjectionService;
use App\Service\RecommendationEngine;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/chatbot')]
final class ChatbotController extends AbstractController
{
    private const HISTORY_KEY = 'nexora.chatbot.history';

    #[Route('/message', name: 'api_chatbot_message', methods: ['POST'])]
    public function message(
        Request $request,
        AuthService $authService,
        BankingService $bankingService,
        ChatbotNluService $nluService,
        CreditSimulationService $simulationService,
        CreditScoringService $scoringService,
        GuaranteeAnalysisService $guaranteeAnalysisService,
        RecommendationEngine $recommendationEngine
    ): JsonResponse {
        $user = $authService->getAuthenticatedUser($request->getSession());
        if ($user === null || strtoupper((string) ($user['role'] ?? '')) !== 'ROLE_USER') {
            return new JsonResponse(['message' => 'Authentication required.'], 401);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            $payload = [];
        }

        $message = trim((string) ($payload['message'] ?? ''));
        if ($message === '') {
            return new JsonResponse(['message' => 'Please enter a message.'], 422);
        }

        $userId = (int) ($user['idUser'] ?? 0);
        $credits = $bankingService->listCredits($userId);
        $garanties = $bankingService->listGaranties($userId);
        $nlu = $nluService->parse($message);
        $amount = (float) ($payload['amount'] ?? $nlu['amount'] ?? 0);
        $duration = (int) ($payload['duration_months'] ?? $nlu['duration_months'] ?? 0);
        $rate = (float) ($payload['interest_rate'] ?? $nlu['interest_rate'] ?? 8);
        $intent = (string) ($nlu['intent'] ?? 'general');
        $isGreeting = preg_match('/^(salut|bonjour|bonsoir|hello|hi|hey|salam)\b/iu', $message) === 1;
        $hasUserCredit = is_array($credits[0] ?? null);
        $hasExplicitSimulationInput = $amount > 0 || $duration > 0;

        if (($intent === 'general' && $isGreeting) || (!$hasUserCredit && !$hasExplicitSimulationInput && $intent !== 'guarantee')) {
            $response = [
                'message' => $isGreeting
                    ? 'Bonjour. Je peux vous aider pour une simulation, vos garanties, ou votre admissibilite. Si vous n avez pas encore de dossier, commencez par une simulation avec un montant et une duree.'
                    : 'Je n ai trouve aucun dossier de credit pour votre compte. Lancez une simulation en precisant un montant et une duree.',
                'intent' => 'general',
                'hide_insights' => true,
                'recommendations' => $hasUserCredit ? [] : ['Commencez par une simulation ex: "Simuler 20000 DT sur 5 ans".'],
                'explainability' => [
                    'transparency' => 'AI-generated guidance. Final approval requires human review.',
                    'risk_factors' => [],
                ],
            ];

            $this->appendHistory($request, $userId, 'user', $this->maskSensitive($message));
            $this->appendHistory($request, $userId, 'assistant', (string) $response['message'], $response);

            return new JsonResponse($response);
        }

        $latestCredit = is_array($credits[0] ?? null) ? $credits[0] : [];
        $salary = (float) ($payload['salary'] ?? ($latestCredit['salaire'] ?? 0));
        $existingDebts = (float) ($payload['existing_debts'] ?? 0);
        $employmentType = (string) ($payload['employment_type'] ?? ($latestCredit['typeContrat'] ?? ''));
        $contribution = (float) ($payload['personal_contribution'] ?? 0);
        $latestGarantie = is_array($garanties[0] ?? null) ? $garanties[0] : [];
        $guaranteeType = (string) ($payload['guarantee_type'] ?? ($latestGarantie['typeGarantie'] ?? ''));
        $guaranteeValue = (float) ($payload['guarantee_value'] ?? ($latestGarantie['valeurRetenue'] ?? $latestGarantie['valeurEstimee'] ?? 0));
        $documents = is_array($payload['documents'] ?? null) ? $payload['documents'] : [];
        $paymentHistory = (string) ($payload['payment_history'] ?? 'neutral');

        if ($amount <= 0) {
            $amount = 20000;
        }
        if ($duration <= 0) {
            $duration = 60;
        }

        $simulation = $simulationService->simulate($amount, $duration, $rate, $salary, $existingDebts);
        $score = $scoringService->score([
            'salary' => $salary,
            'employment_type' => $employmentType,
            'existing_debts' => $existingDebts,
            'personal_contribution' => $contribution,
            'monthly_payment' => $simulation['monthly_payment'],
            'payment_history' => $paymentHistory,
        ]);
        $guarantee = $guaranteeAnalysisService->analyze([
            'guarantee_type' => $guaranteeType,
            'credit_amount' => $amount,
            'guarantee_value' => $guaranteeValue,
            'documents' => $documents,
        ]);
        $recommendations = $recommendationEngine->generate($simulation, $score, $guarantee);

        $response = [
            'message' => $this->buildResponseMessage($intent, $score['decision'], $score['risk_level']),
            'intent' => $intent,
            'hide_insights' => false,
            'amount' => $amount,
            'duration_months' => $duration,
            'score' => $score['score'],
            'decision' => $score['decision'],
            'risk_level' => $score['risk_level'],
            'monthly_payment' => $simulation['monthly_payment'],
            'total_repayment' => $simulation['total_repayment'],
            'debt_ratio' => $simulation['debt_ratio'],
            'affordability' => $simulation['affordability'],
            'guarantee_strength' => $guarantee['guarantee_strength'],
            'coverage_percent' => $guarantee['coverage_percent'],
            'legal_completeness' => $guarantee['legal_completeness'],
            'missing_documents' => $guarantee['missing_documents'],
            'recommendations' => $recommendations,
            'explainability' => [
                'transparency' => 'AI-generated guidance. Final approval requires human review.',
                'risk_factors' => $score['reasons'],
            ],
        ];

        $this->appendHistory($request, $userId, 'user', $this->maskSensitive($message));
        $this->appendHistory($request, $userId, 'assistant', (string) $response['message'], $response);

        return new JsonResponse($response);
    }

    #[Route('/simulate', name: 'api_chatbot_simulate', methods: ['POST'])]
    public function simulate(
        Request $request,
        AuthService $authService,
        CreditSimulationService $simulationService
    ): JsonResponse {
        if ($authService->getAuthenticatedUser($request->getSession()) === null) {
            return new JsonResponse(['message' => 'Authentication required.'], 401);
        }

        $payload = json_decode($request->getContent(), true) ?: [];
        $result = $simulationService->simulate(
            (float) ($payload['amount'] ?? 0),
            (int) ($payload['duration_months'] ?? 0),
            (float) ($payload['interest_rate'] ?? 8),
            (float) ($payload['salary'] ?? 0),
            (float) ($payload['existing_debts'] ?? 0)
        );

        return new JsonResponse($result);
    }

    #[Route('/score', name: 'api_chatbot_score', methods: ['POST'])]
    public function score(
        Request $request,
        AuthService $authService,
        CreditScoringService $scoringService
    ): JsonResponse {
        if ($authService->getAuthenticatedUser($request->getSession()) === null) {
            return new JsonResponse(['message' => 'Authentication required.'], 401);
        }
        $payload = json_decode($request->getContent(), true) ?: [];
        return new JsonResponse($scoringService->score($payload));
    }

    #[Route('/guarantee', name: 'api_chatbot_guarantee', methods: ['POST'])]
    public function guarantee(
        Request $request,
        AuthService $authService,
        GuaranteeAnalysisService $guaranteeAnalysisService
    ): JsonResponse {
        if ($authService->getAuthenticatedUser($request->getSession()) === null) {
            return new JsonResponse(['message' => 'Authentication required.'], 401);
        }
        $payload = json_decode($request->getContent(), true) ?: [];
        return new JsonResponse($guaranteeAnalysisService->analyze($payload));
    }

    #[Route('/history', name: 'api_chatbot_history', methods: ['GET'])]
    public function history(Request $request, AuthService $authService): JsonResponse
    {
        $user = $authService->getAuthenticatedUser($request->getSession());
        if ($user === null) {
            return new JsonResponse(['message' => 'Authentication required.'], 401);
        }
        $session = $request->getSession();
        $all = $session->get(self::HISTORY_KEY, []);
        $history = $all[(int) ($user['idUser'] ?? 0)] ?? [];

        return new JsonResponse(['history' => $history]);
    }

    #[Route('/user/credits', name: 'api_user_credits', methods: ['GET'])]
    public function userCredits(Request $request, AuthService $authService, BankingService $bankingService): JsonResponse
    {
        $user = $authService->getAuthenticatedUser($request->getSession());
        if ($user === null) {
            return new JsonResponse(['message' => 'Authentication required.'], 401);
        }
        $userId = (int) ($user['idUser'] ?? 0);
        $rows = $bankingService->listCredits($userId);
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

    #[Route('/user/guarantees', name: 'api_user_guarantees', methods: ['GET'])]
    public function userGuarantees(Request $request, AuthService $authService, BankingService $bankingService): JsonResponse
    {
        $user = $authService->getAuthenticatedUser($request->getSession());
        if ($user === null) {
            return new JsonResponse(['message' => 'Authentication required.'], 401);
        }
        $userId = (int) ($user['idUser'] ?? 0);
        $rows = $bankingService->listGaranties($userId);
        $items = array_map(static fn (array $row): array => [
            'id' => (int) ($row['idGarantie'] ?? 0),
            'type' => (string) ($row['typeGarantie'] ?? 'Garantie'),
            'value' => (float) ($row['valeurRetenue'] ?? $row['valeurEstimee'] ?? 0),
            'credit_id' => (int) ($row['idCredit'] ?? 0),
        ], $rows);

        return new JsonResponse(['items' => $items]);
    }

    #[Route('/user/simulations', name: 'api_user_simulations', methods: ['GET'])]
    public function userSimulations(Request $request, AuthService $authService): JsonResponse
    {
        $user = $authService->getAuthenticatedUser($request->getSession());
        if ($user === null) {
            return new JsonResponse(['message' => 'Authentication required.'], 401);
        }
        $all = $request->getSession()->get(self::HISTORY_KEY, []);
        $history = is_array($all[(int) ($user['idUser'] ?? 0)] ?? null) ? $all[(int) ($user['idUser'] ?? 0)] : [];
        $items = [];
        foreach (array_reverse($history) as $entry) {
            $data = is_array($entry['data'] ?? null) ? $entry['data'] : [];
            if (!isset($data['monthly_payment'], $data['total_repayment'])) {
                continue;
            }
            $items[] = [
                'amount' => (float) ($data['amount'] ?? 0),
                'duration_months' => (int) ($data['duration_months'] ?? 0),
                'monthly_payment' => (float) ($data['monthly_payment'] ?? 0),
                'total_repayment' => (float) ($data['total_repayment'] ?? 0),
            ];
            if (count($items) >= 5) {
                break;
            }
        }
        return new JsonResponse(['items' => $items]);
    }

    #[Route('/user/profile-analysis', name: 'api_user_profile_analysis', methods: ['GET'])]
    public function userProfileAnalysis(
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
        $userId = (int) ($user['idUser'] ?? 0);
        $credits = $bankingService->listCredits($userId);
        $garanties = $bankingService->listGaranties($userId);
        $credit = is_array($credits[0] ?? null) ? $credits[0] : [];
        if ($credit === []) {
            return new JsonResponse([
                'message' => 'Je n ai pas assez de donnees pour analyser votre dossier.',
                'score' => 0,
                'decision' => 'Review Needed',
                'risk_level' => 'Medium',
                'recommendations' => ['Ajoutez un dossier de credit pour commencer.'],
            ]);
        }
        $sim = $simulationService->simulate(
            (float) ($credit['montantDemande'] ?? 0),
            (int) ($credit['duree'] ?? 60),
            (float) ($credit['tauxInteret'] ?? 8),
            (float) ($credit['salaire'] ?? 0),
            0
        );
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
            'message' => 'Analyse de votre dossier terminee.',
            'score' => $score['score'],
            'decision' => $score['decision'],
            'risk_level' => $score['risk_level'],
            'guarantee_strength' => $garantie['guarantee_strength'],
            'recommendations' => $recs,
        ]);
    }

    #[Route('/user/future-projection', name: 'api_user_future_projection', methods: ['GET'])]
    public function userFutureProjection(
        Request $request,
        AuthService $authService,
        BankingService $bankingService,
        ProjectionService $projectionService
    ): JsonResponse {
        $user = $authService->getAuthenticatedUser($request->getSession());
        if ($user === null) {
            return new JsonResponse(['message' => 'Authentication required.'], 401);
        }
        $credits = $bankingService->listCredits((int) ($user['idUser'] ?? 0));
        $credit = is_array($credits[0] ?? null) ? $credits[0] : [];
        if ($credit === []) {
            return new JsonResponse([
                'message' => 'Je n ai pas assez de donnees pour faire une projection. Faites d abord une simulation ou ajoutez un dossier de credit.',
                'timeline' => [],
            ]);
        }

        $p6 = $projectionService->evaluate($credit, 6, 0, 0);
        $p12 = $projectionService->evaluate($credit, 12, 0, 0);
        $p36 = $projectionService->evaluate($credit, 36, 0, 0);
        $p60 = $projectionService->evaluate($credit, 60, 0, 0);

        return new JsonResponse([
            'message' => 'Projection future calculee.',
            'timeline' => [
                ['horizon' => '6_months', 'label' => 'Dans 6 mois', 'status' => (string) ($p6['conclusion_label'] ?? 'Stable')],
                ['horizon' => '1_year', 'label' => 'Dans 1 an', 'score' => (float) ($p12['score'] ?? 0)],
                ['horizon' => '3_years', 'label' => 'Dans 3 ans', 'status' => (string) ($p36['conclusion_label'] ?? 'Amelioree')],
                ['horizon' => '5_years', 'label' => 'Dans 5 ans', 'status' => (string) ($p60['risk_level'] ?? 'Faible')],
            ],
            'advice' => 'Gardez un taux d endettement inferieur a 35% et ajoutez une garantie forte.',
        ]);
    }

    #[Route('/user/bank-comparison', name: 'api_user_bank_comparison', methods: ['GET'])]
    public function userBankComparison(
        Request $request,
        AuthService $authService,
        BankingService $bankingService,
        CreditSimulationService $simulationService
    ): JsonResponse {
        $user = $authService->getAuthenticatedUser($request->getSession());
        if ($user === null) {
            return new JsonResponse(['message' => 'Authentication required.'], 401);
        }
        $credits = $bankingService->listCredits((int) ($user['idUser'] ?? 0));
        $credit = is_array($credits[0] ?? null) ? $credits[0] : [];
        if ($credit === []) {
            return new JsonResponse([
                'message' => 'Je ne peux pas comparer les banques sans dossier ou simulation. Faites d abord une simulation.',
                'table' => [],
            ]);
        }

        $amount = (float) ($credit['montantDemande'] ?? 20000);
        $duration = (int) ($credit['duree'] ?? 60);
        $base = (float) ($credit['tauxInteret'] ?? 8);
        $banks = [
            ['name' => 'BIAT', 'rate' => max(4.5, $base - 0.6), 'fees' => 190, 'max_duration' => 120, 'guarantee' => 'Immobiliere'],
            ['name' => 'Attijari Bank', 'rate' => max(4.7, $base - 0.4), 'fees' => 210, 'max_duration' => 108, 'guarantee' => 'Vehicule/Immobiliere'],
            ['name' => 'Amen Bank', 'rate' => max(4.9, $base - 0.2), 'fees' => 230, 'max_duration' => 96, 'guarantee' => 'Immobiliere'],
            ['name' => 'STB', 'rate' => max(5.1, $base + 0.1), 'fees' => 170, 'max_duration' => 84, 'guarantee' => 'Depot/Garant'],
            ['name' => 'BH Bank', 'rate' => max(5.0, $base), 'fees' => 200, 'max_duration' => 96, 'guarantee' => 'Immobiliere'],
            ['name' => 'Banque Zitouna', 'rate' => max(5.3, $base + 0.2), 'fees' => 250, 'max_duration' => 84, 'guarantee' => 'Immobiliere'],
        ];

        $table = [];
        foreach ($banks as $bank) {
            $sim = $simulationService->simulate($amount, min($duration, (int) $bank['max_duration']), (float) $bank['rate']);
            $compatibility = max(50, min(95, (int) round(100 - ($sim['monthly_payment'] / max(1, (float) ($credit['salaire'] ?? 2500)) * 25))));
            $table[] = [
                'bank' => $bank['name'],
                'rate' => $bank['rate'],
                'monthly' => $sim['monthly_payment'],
                'guarantee_required' => $bank['guarantee'],
                'compatibility_score' => $compatibility,
                'ai_opinion' => $compatibility >= 80 ? 'Tres favorable' : ($compatibility >= 65 ? 'Moyenne' : 'A etudier'),
                'total_cost' => $sim['total_repayment'] + (float) $bank['fees'],
                'fees' => (float) $bank['fees'],
            ];
        }
        usort($table, static fn (array $a, array $b): int => ($b['compatibility_score'] <=> $a['compatibility_score']));
        $best = $table[0] ?? null;

        return new JsonResponse([
            'message' => $best ? ('D apres votre profil, la meilleure banque est : '.$best['bank'].'.') : 'Comparaison indisponible.',
            'best_bank' => $best,
            'table' => $table,
        ]);
    }

    /**
     * @param array<string, mixed> $structured
     */
    private function appendHistory(Request $request, int $userId, string $role, string $text, array $structured = []): void
    {
        $session = $request->getSession();
        $all = $session->get(self::HISTORY_KEY, []);
        if (!is_array($all)) {
            $all = [];
        }
        $bucket = $all[$userId] ?? [];
        if (!is_array($bucket)) {
            $bucket = [];
        }
        $bucket[] = [
            'role' => $role,
            'text' => $text,
            'at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'data' => $structured,
        ];
        $all[$userId] = array_slice($bucket, -30);
        $session->set(self::HISTORY_KEY, $all);
    }

    private function buildResponseMessage(string $intent, string $decision, string $risk): string
    {
        return match ($intent) {
            'guarantee' => sprintf('Guarantee analysis complete. Strength is %s.', $risk === 'High' ? 'sensitive' : 'under control'),
            'eligibility' => sprintf('Eligibility check complete: %s with %s risk.', $decision, strtolower($risk)),
            default => sprintf('Simulation complete: recommendation is %s (%s risk).', $decision, strtolower($risk)),
        };
    }

    private function maskSensitive(string $text): string
    {
        return preg_replace('/\b(\d{2})\d{2,}(\d{2})\b/', '$1****$2', $text) ?? $text;
    }
}
