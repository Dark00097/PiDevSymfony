<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AuthService;
use App\Service\SmartStrategyService;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/smart-strategy')]
final class SmartStrategyController extends AbstractController
{
    public function __construct(
        private readonly SmartStrategyService $strategyService,
        private readonly Connection $connection,
        private readonly AuthService $authService,
    ) {
    }

    #[Route('/analyze/{idCoffre}', name: 'smart_strategy_analyze', methods: ['GET'])]
    public function analyze(int $idCoffre, Request $request): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        if ($userId === null) {
            return $this->json(['error' => 'Non authentifie.'], 401);
        }

        try {
            $financialData = $this->strategyService->extractFinancialData($idCoffre, $userId);
            $result = $this->strategyService->predictStrategies($financialData);
            $activeStrategy = $this->strategyService->getActiveStrategy($idCoffre);

            return $this->json([
                'success' => true,
                'coffre_id' => $idCoffre,
                'coffre_nom' => $financialData['nomCoffre'],
                'solde_compte' => $result['solde_compte'],
                'capacite_mensuelle' => $result['capacite_mensuelle'],
                'ratio_epargne' => $result['ratio_epargne'],
                'safety_seuil' => $result['safety_seuil'],
                'strategies' => $result['strategies'],
                'recommended' => $result['recommended'],
                'prediction_source' => $result['prediction_source'] ?? 'unknown',
                'ml_confidence' => $result['ml_confidence'] ?? null,
                'ml_probabilities' => $result['ml_probabilities'] ?? null,
                'active_strategy' => $activeStrategy,
                'warning' => ($result['prediction_source'] ?? null) === 'ml'
                    ? null
                    : 'Le modele ML est indisponible. Les strategies affichees proviennent du fallback.',
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/activate', name: 'smart_strategy_activate', methods: ['POST'])]
    public function activate(Request $request): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        if ($userId === null) {
            return $this->json(['error' => 'Non authentifie.'], 401);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $idCoffre = (int) ($data['idCoffre'] ?? 0);
        $typeStrategie = strtoupper(trim((string) ($data['typeStrategie'] ?? '')));

        if ($idCoffre <= 0 || !in_array($typeStrategie, ['DOUCE', 'MODEREE', 'AGRESSIVE'], true)) {
            return $this->json(['error' => 'Parametres invalides.'], 400);
        }

        $coffre = $this->connection->fetchAssociative(
            'SELECT cv.*, c.idCompte FROM coffrevirtuel cv
             JOIN compte c ON cv.idCompte = c.idCompte
             WHERE cv.idCoffre = ? AND cv.idUser = ?
             LIMIT 1',
            [$idCoffre, $userId]
        );

        if (!$coffre) {
            return $this->json(['error' => 'Coffre introuvable ou acces refuse.'], 403);
        }

        $idCompte = (int) ($coffre['idCompte'] ?? 0);

        try {
            $financialData = $this->strategyService->extractFinancialData($idCoffre, $userId);
            $prediction = $this->strategyService->predictStrategies($financialData);

            if (($prediction['prediction_source'] ?? null) !== 'ml') {
                return $this->json([
                    'error' => 'Le modele ML est indisponible. Activation bloquee pour eviter une strategie non fiable.',
                ], 503);
            }

            $selectedStrategy = $this->strategyService->resolveStrategySelection($prediction, $typeStrategie);
            if ($selectedStrategy === null) {
                return $this->json(['error' => 'Strategie introuvable pour ce profil.'], 422);
            }

            $result = $this->strategyService->activateStrategy(
                $idCoffre,
                $idCompte,
                $userId,
                $typeStrategie,
                (float) ($selectedStrategy['montant'] ?? 0),
                (int) ($selectedStrategy['duree'] ?? 12),
                (float) ($selectedStrategy['taux_succes'] ?? 0),
                (string) ($selectedStrategy['risque'] ?? 'Faible'),
                (float) ($prediction['safety_seuil'] ?? 50)
            );

            return $this->json([
                'success' => true,
                'idStrategie' => $result['idStrategie'],
                'statut' => $result['statut'],
                'dateProchaineExecution' => $result['dateProchaineExecution'],
                'message' => $result['message'],
                'typeStrategie' => $typeStrategie,
                'montantMensuel' => (float) ($selectedStrategy['montant'] ?? 0),
                'dureeEstimee' => (int) ($selectedStrategy['duree'] ?? 12),
                'prediction_source' => $prediction['prediction_source'],
                'ml_confidence' => $prediction['ml_confidence'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    private function resolveUserId(Request $request): ?int
    {
        $session = $request->getSession();
        $userId = $session->get(AuthService::SESSION_USER_ID);
        if ($userId !== null && (int) $userId > 0) {
            return (int) $userId;
        }

        $header = $request->headers->get('X-User-Id');
        if ($header !== null && is_numeric($header) && (int) $header > 0) {
            return (int) $header;
        }

        $qUserId = $request->query->get('user_id');
        if ($qUserId !== null && is_numeric($qUserId) && (int) $qUserId > 0) {
            return (int) $qUserId;
        }

        return null;
    }
}
