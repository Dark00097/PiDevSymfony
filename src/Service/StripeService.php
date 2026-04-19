<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Stripe\Checkout\Session;
use Stripe\Stripe;
use Stripe\Webhook;

final class StripeService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $secretKey = '',
        private readonly string $webhookSecret = '',
        private readonly ?PaymentEmailService $paymentEmailService = null,
    ) {
    }

    /**
     * Crée une Stripe Checkout Session pour payer le montant restant d'une transaction.
     * Retourne l'URL de redirection Stripe.
     */
    public function createCheckoutSession(array $transaction, string $successUrl, string $cancelUrl): string
    {
        Stripe::setApiKey($this->secretKey);

        $montant      = (float) ($transaction['montant_value']    ?? 0);
        $montantPaye  = (float) ($transaction['montantPaye_value'] ?? 0);
        $restantTnd   = max(0, $montant - $montantPaye);

        if ($restantTnd <= 0) {
            throw new \RuntimeException('Cette transaction est déjà entièrement payée.');
        }

        // Conversion TND → EUR (taux fixe, Stripe ne supporte pas TND)
        $tauxEur     = 0.295;
        $restantEur  = $restantTnd * $tauxEur;
        $amountCents = (int) round($restantEur * 100); // Stripe = centimes

        if ($amountCents < 50) { // minimum Stripe = 0.50 EUR
            $amountCents = 50;
        }

        $session = Session::create([
            'payment_method_types' => ['card'],
            'line_items'           => [[
                'price_data' => [
                    'currency'     => 'eur',
                    'unit_amount'  => $amountCents,
                    'product_data' => [
                        'name'        => sprintf('Transaction #%s — %s', $transaction['idTransaction'], $transaction['categorie'] ?? ''),
                        'description' => sprintf('Montant restant : %.3f TND (≈ %.2f EUR)', $restantTnd, $restantEur),
                    ],
                ],
                'quantity' => 1,
            ]],
            'mode'        => 'payment',
            'success_url' => $successUrl,
            'cancel_url'  => $cancelUrl,
            'metadata'    => [
                'idTransaction' => (string) ($transaction['idTransaction'] ?? ''),
                'montantPaye'   => (string) $montantPaye,
                'restantTnd'    => (string) $restantTnd,
            ],
        ]);

        return $session->url;
    }

    /**
     * Appelé après un paiement réussi — met à jour montantPaye et débite le compte.
     */
    public function markTransactionPaid(int $idTransaction, float $montantPaye, float $montantTotal): void
    {
        // Récupérer la transaction
        $transaction = $this->connection->fetchAssociative(
            'SELECT * FROM transactions WHERE idTransaction = ? LIMIT 1',
            [$idTransaction]
        );
        
        if (!$transaction) {
            throw new \RuntimeException('Transaction introuvable.');
        }
        
        // Récupérer le compte
        $account = $this->connection->fetchAssociative(
            'SELECT idCompte, solde FROM compte WHERE idCompte = ? LIMIT 1',
            [(int) $transaction['idCompte']]
        );
        
        if (!$account) {
            throw new \RuntimeException('Compte introuvable.');
        }
        
        // Récupérer l'utilisateur
        $user = $this->connection->fetchAssociative(
            'SELECT c.idUser, u.nom, u.prenom, u.email 
             FROM compte c 
             JOIN users u ON c.idUser = u.idUser 
             WHERE c.idCompte = ? LIMIT 1',
            [(int) $transaction['idCompte']]
        );
        
        $oldSolde = (float) $account['solde'];
        $newMontantPaye = min($montantPaye + ($montantTotal - $montantPaye), $montantTotal);
        
        // Débiter le compte du montant payé
        $newSolde = $oldSolde - $newMontantPaye;
        
        if ($newSolde < 0) {
            throw new \RuntimeException('Solde insuffisant pour finaliser le paiement.');
        }
        
        $this->connection->beginTransaction();
        try {
            // Mettre à jour la transaction
            $this->connection->update('transactions', [
                'montantPaye' => (string) $newMontantPaye,
                'soldeApres' => $newSolde,
            ], ['idTransaction' => $idTransaction]);
            
            // Débiter le compte
            $this->connection->update('compte', [
                'solde' => $newSolde,
            ], ['idCompte' => (int) $transaction['idCompte']]);
            
            $this->connection->commit();
            
            // Envoyer les emails de notification
            if ($this->paymentEmailService !== null && $user) {
                // Préparer les données de transaction avec les valeurs mises à jour
                $transactionData = array_merge($transaction, [
                    'montant_value' => $newMontantPaye,
                    'montantPaye_value' => $newMontantPaye,
                ]);
                
                // Email à l'utilisateur qui a payé
                $this->paymentEmailService->sendPaymentConfirmationToUser($transactionData, $user);
                
                // Email au destinataire (si email fourni)
                if (!empty($transaction['emailDestinataire'])) {
                    $this->paymentEmailService->sendPaymentNotificationToRecipient($transactionData, $user);
                }
            }
            
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    /**
     * Vérifie la signature du webhook Stripe et retourne l'événement.
     */
    public function constructWebhookEvent(string $payload, string $sigHeader): \Stripe\Event
    {
        Stripe::setApiKey($this->secretKey);

        return Webhook::constructEvent($payload, $sigHeader, $this->webhookSecret);
    }

    public function isConfigured(): bool
    {
        return trim($this->secretKey) !== '' && !str_contains($this->secretKey, 'REMPLACER');
    }
}
