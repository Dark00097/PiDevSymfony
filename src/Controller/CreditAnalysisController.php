<?php

namespace App\Controller;

use App\Service\AuthService;
use App\Service\BankingService;
use App\Service\CreditAnalysisFactory;
use App\Service\GarantieService;
use App\Service\RestructurationService;
use App\Service\RiskService;
use App\Service\ScoringService;
use App\Service\SimulationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CreditAnalysisController extends AbstractController
{
    #[Route('/credit-analysis', name: 'credit_analysis_dashboard', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        AuthService $authService,
        BankingService $bankingService,
        CreditAnalysisFactory $creditAnalysisFactory,
        ScoringService $scoringService,
        SimulationService $simulationService,
        RiskService $riskService,
        GarantieService $garantieService,
        RestructurationService $restructurationService,
    ): Response {
        $session = $request->getSession();
        $user = $authService->getAuthenticatedUser($session);
        if ($user === null) {
            return $this->redirectToRoute('login');
        }

        $blockedReason = $authService->getLoginBlockReason($user);
        if ($blockedReason !== null) {
            $authService->logoutUser($session);
            $this->addFlash('error', $blockedReason);

            return $this->redirectToRoute('login');
        }

        $isAdmin = strtoupper((string) ($user['role'] ?? '')) === 'ROLE_ADMIN';
        $visibleUserId = $isAdmin ? null : (int) ($user['idUser'] ?? 0);
        $creditRows = $bankingService->listCredits($visibleUserId);
        $garantieRows = $bankingService->listGaranties($visibleUserId);

        $selectedCreditId = $this->queryInt($request, 'creditId');
        if ($selectedCreditId === null && $creditRows !== []) {
            $selectedCreditId = (int) ($creditRows[0]['idCredit'] ?? 0);
        }

        $selectedCreditRow = $this->findCreditRow($creditRows, $selectedCreditId);
        $formData = $selectedCreditRow !== null
            ? $this->buildFormDataFromCreditRow($selectedCreditRow, $garantieRows)
            : $this->defaultFormData();

        if ($request->isMethod('POST')) {
            $formData = array_replace($formData, $this->normalizePostedFormData($request));
        }

        $syntheticGaranties = $this->buildSyntheticGaranties($formData);
        $credit = $creditAnalysisFactory->createFromArray($formData, $syntheticGaranties);

        if ((float) ($formData['mensualite'] ?? 0) <= 0) {
            $formData['mensualite'] = $simulationService->calculateMonthlyPayment(
                (float) ($formData['montantAccorde'] ?: $formData['montantDemande']),
                (float) $formData['tauxInteret'],
                (int) $formData['duree']
            );
            $credit = $creditAnalysisFactory->createFromArray($formData, $syntheticGaranties);
        }

        // ── Calculs métier
        $score = $scoringService->calculateScore($credit);
        $simulation = $simulationService->simulate($credit);
        $coverage = $garantieService->analyzeCoverage($credit);
        $risk = $riskService->analyze($credit, $score);
        $restructuration = $restructurationService->propose($credit, $risk);

        // ── Scoring IA (OpenRouter)
        $aiScore = $scoringService->calculateAiScore($credit);

        return $this->render('credit/dashboard.html.twig', [
            'current_user' => $user,
            'credit_options' => array_map(
                static fn (array $row): array => [
                    'id' => (int) ($row['idCredit'] ?? 0),
                    'label' => sprintf(
                        '#%d - %s - %s',
                        (int) ($row['idCredit'] ?? 0),
                        trim((string) ($row['typeCredit'] ?? 'Credit')),
                        trim((string) ($row['user_name'] ?? 'Client'))
                    ),
                ],
                $creditRows
            ),
            'selected_credit_id' => $selectedCreditId,
            'selected_credit_row' => $selectedCreditRow,
            'form' => $formData,
            'score' => $score,
            'ai_score' => $aiScore,
            'simulation' => $simulation,
            'coverage' => $coverage,
            'risk' => $risk,
            'restructuration' => $restructuration,
            'chart_data' => $this->buildChartData($simulation['schedule']),
            'amortization_preview' => array_slice($simulation['schedule'], 0, 24),
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $creditRows
     * @return array<string, mixed>|null
     */
    private function findCreditRow(array $creditRows, ?int $selectedCreditId): ?array
    {
        if ($selectedCreditId === null || $selectedCreditId <= 0) {
            return null;
        }

        foreach ($creditRows as $creditRow) {
            if ((int) ($creditRow['idCredit'] ?? 0) === $selectedCreditId) {
                return $creditRow;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $creditRow
     * @param array<int, array<string, mixed>> $garantieRows
     * @return array<string, mixed>
     */
    private function buildFormDataFromCreditRow(array $creditRow, array $garantieRows): array
    {
        $creditId = (int) ($creditRow['idCredit'] ?? 0);
        $estimatedTotal = 0.0;
        $retainedTotal = 0.0;

        foreach ($garantieRows as $garantieRow) {
            if ((int) ($garantieRow['idCredit'] ?? 0) !== $creditId) {
                continue;
            }

            $estimatedTotal += (float) ($garantieRow['valeurEstimee'] ?? 0);
            $retainedTotal += (float) ($garantieRow['valeurRetenue'] ?? 0);
        }

        return [
            'typeCredit' => (string) ($creditRow['typeCredit'] ?? 'Consommation'),
            'montantDemande' => (float) ($creditRow['montantDemande'] ?? 0),
            'autofinancement' => (float) ($creditRow['autofinancement'] ?? 0),
            'duree' => (int) ($creditRow['duree'] ?? 12),
            'tauxInteret' => (float) ($creditRow['tauxInteret'] ?? 0),
            'mensualite' => (float) ($creditRow['mensualite'] ?? 0),
            'montantAccorde' => (float) ($creditRow['montantAccorde'] ?? $creditRow['montantDemande'] ?? 0),
            'dateDemande' => (string) ($creditRow['dateDemande'] ?? date('Y-m-d')),
            'salaire' => (float) ($creditRow['salaire'] ?? 0),
            'typeContrat' => (string) ($creditRow['typeContrat'] ?? 'Autre'),
            'ancienneteAnnees' => (int) ($creditRow['ancienneteAnnees'] ?? 0),
            'garantieEstimee' => round($estimatedTotal, 2),
            'garantieRetenue' => round($retainedTotal, 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultFormData(): array
    {
        return [
            'typeCredit' => 'Immobilier',
            'montantDemande' => 120000,
            'autofinancement' => 20000,
            'duree' => 84,
            'tauxInteret' => 7.2,
            'mensualite' => 0,
            'montantAccorde' => 120000,
            'dateDemande' => date('Y-m-d'),
            'salaire' => 4800,
            'typeContrat' => 'CDI',
            'ancienneteAnnees' => 5,
            'garantieEstimee' => 95000,
            'garantieRetenue' => 85000,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizePostedFormData(Request $request): array
    {
        return [
            'typeCredit' => trim((string) $request->request->get('typeCredit', 'Consommation')),
            'montantDemande' => (float) $request->request->get('montantDemande', 0),
            'autofinancement' => (float) $request->request->get('autofinancement', 0),
            'duree' => max(1, (int) $request->request->get('duree', 12)),
            'tauxInteret' => max(0.0, (float) $request->request->get('tauxInteret', 0)),
            'mensualite' => max(0.0, (float) $request->request->get('mensualite', 0)),
            'montantAccorde' => (float) $request->request->get('montantAccorde', $request->request->get('montantDemande', 0)),
            'dateDemande' => trim((string) $request->request->get('dateDemande', date('Y-m-d'))),
            'salaire' => max(0.0, (float) $request->request->get('salaire', 0)),
            'typeContrat' => trim((string) $request->request->get('typeContrat', 'Autre')),
            'ancienneteAnnees' => max(0, (int) $request->request->get('ancienneteAnnees', 0)),
            'garantieEstimee' => max(0.0, (float) $request->request->get('garantieEstimee', 0)),
            'garantieRetenue' => max(0.0, (float) $request->request->get('garantieRetenue', 0)),
        ];
    }

    /**
     * @param array<string, mixed> $formData
     * @return array<int, array<string, mixed>>
     */
    private function buildSyntheticGaranties(array $formData): array
    {
        $estimated = (float) ($formData['garantieEstimee'] ?? 0);
        $retained = (float) ($formData['garantieRetenue'] ?? 0);

        if ($estimated <= 0 && $retained <= 0) {
            return [];
        }

        return [[
            'typeGarantie' => 'Garantie agregee',
            'valeurEstimee' => max($estimated, $retained),
            'valeurRetenue' => $retained > 0 ? $retained : $estimated,
            'statut' => 'Active',
            'dateEvaluation' => date('Y-m-d'),
            'nomGarant' => 'Synthese',
        ]];
    }

    /**
     * @param array<int, array<string, float|int>> $schedule
     * @return array<string, mixed>
     */
    private function buildChartData(array $schedule): array
    {
        $preview = array_slice($schedule, 0, 12);

        return [
            'labels' => array_map(static fn (array $row): string => 'M'.$row['month'], $preview),
            'principal' => array_map(static fn (array $row): float => round((float) $row['principal'], 2), $preview),
            'interest' => array_map(static fn (array $row): float => round((float) $row['interest'], 2), $preview),
            'balance' => array_map(static fn (array $row): float => round((float) $row['remaining_balance'], 2), $preview),
        ];
    }

    private function queryInt(Request $request, string $key): ?int
    {
        $value = $request->query->get($key);
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = (int) $value;

        return $normalized > 0 ? $normalized : null;
    }
}
