<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

final class PaymentEmailService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
    ) {
    }

    /**
     * Envoie un email de confirmation de paiement à l'utilisateur qui a payé
     */
    public function sendPaymentConfirmationToUser(array $transaction, array $user): void
    {
        $userEmail = trim((string) ($user['email'] ?? ''));
        
        if ($userEmail === '') {
            error_log('PaymentEmailService: Email utilisateur vide, impossible d\'envoyer la confirmation');
            return;
        }

        try {
            $htmlContent = $this->twig->render('emails/payment_confirmation_user.html.twig', [
                'user' => $user,
                'transaction' => $transaction,
            ]);

            $email = (new Email())
                ->from('noreply@nexora-bank.com')
                ->to($userEmail)
                ->subject('✅ Confirmation de paiement - Transaction #' . ($transaction['idTransaction'] ?? ''))
                ->html($htmlContent);

            $this->mailer->send($email);
            
            error_log(sprintf(
                'PaymentEmailService: Email de confirmation envoyé à l\'utilisateur %s (%s)',
                $user['nom'] ?? 'Unknown',
                $userEmail
            ));
        } catch (\Throwable $e) {
            error_log('PaymentEmailService: Erreur lors de l\'envoi de l\'email utilisateur: ' . $e->getMessage());
        }
    }

    /**
     * Envoie un email de notification de virement au destinataire
     */
    public function sendVirementNotificationToRecipient(array $transaction, array $sender): void
    {
        $recipientEmail = trim((string) ($transaction['emailDestinataire'] ?? ''));

        if ($recipientEmail === '') {
            error_log('PaymentEmailService: Email destinataire vide, notification virement non envoyée');
            return;
        }

        try {
            $htmlContent = $this->twig->render('emails/virement_notification_recipient.html.twig', [
                'sender'      => $sender,
                'transaction' => $transaction,
            ]);

            $email = (new Email())
                ->from('noreply@nexora-bank.com')
                ->to($recipientEmail)
                ->subject('💸 Vous avez reçu un virement - Nexora Bank')
                ->html($htmlContent);

            $this->mailer->send($email);

            error_log(sprintf(
                'PaymentEmailService: Email virement envoyé à %s (%s)',
                $transaction['nomDestinataire'] ?? 'Unknown',
                $recipientEmail
            ));
        } catch (\Throwable $e) {
            error_log('PaymentEmailService: Erreur envoi email virement: ' . $e->getMessage());
        }
    }

    /**
     * Envoie un email de notification de paiement reçu au destinataire
     */
    public function sendPaymentNotificationToRecipient(array $transaction, array $user): void
    {
        $recipientEmail = trim((string) ($transaction['emailDestinataire'] ?? ''));
        
        if ($recipientEmail === '') {
            error_log('PaymentEmailService: Email destinataire vide, notification non envoyée');
            return;
        }

        try {
            $htmlContent = $this->twig->render('emails/payment_notification_recipient.html.twig', [
                'user' => $user,
                'transaction' => $transaction,
            ]);

            $email = (new Email())
                ->from('noreply@nexora-bank.com')
                ->to($recipientEmail)
                ->subject('💰 Vous avez reçu un paiement - Nexora Bank')
                ->html($htmlContent);

            $this->mailer->send($email);
            
            error_log(sprintf(
                'PaymentEmailService: Email de notification envoyé au destinataire %s (%s)',
                $transaction['nomDestinataire'] ?? 'Unknown',
                $recipientEmail
            ));
        } catch (\Throwable $e) {
            error_log('PaymentEmailService: Erreur lors de l\'envoi de l\'email destinataire: ' . $e->getMessage());
        }
    }
}
