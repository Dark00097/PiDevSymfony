<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class ReclamationResponseService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly GeminiService $geminiService,
    ) {
    }

    /**
     * Génère une réponse automatique pour une réclamation en utilisant l'IA
     */
    public function generateResponse(string $reclamationType, string $description, string $userName): string
    {
        $prompt = $this->buildPrompt($reclamationType, $description, $userName);
        
        try {
            $response = $this->geminiService->generateText($prompt);
            return $response;
        } catch (\Throwable $e) {
            error_log('Gemini API Error: ' . $e->getMessage());
            // Fallback: réponse générique si l'API échoue
            return $this->generateFallbackResponse($reclamationType, $userName);
        }
    }

    /**
     * Construit le prompt pour l'IA
     */
    private function buildPrompt(string $reclamationType, string $description, string $userName): string
    {
        return <<<PROMPT
Tu es un assistant bancaire professionnel et empathique. Tu dois rédiger une réponse à une réclamation client.

**Informations de la réclamation:**
- Type: {$reclamationType}
- Description: {$description}
- Client: {$userName}

**Instructions:**
1. Commence par saluer le client par son nom
2. Remercie-le d'avoir pris le temps de nous contacter
3. Montre de l'empathie et de la compréhension pour sa situation
4. Propose une solution concrète et professionnelle adaptée au type de réclamation
5. Assure-le que nous prenons sa réclamation au sérieux
6. Termine par une formule de politesse professionnelle
7. Signe "L'équipe Support Bancaire"

**Ton:** Professionnel, empathique, rassurant, orienté solution

**Format:** Email professionnel en français, maximum 200 mots

Génère maintenant la réponse:
PROMPT;
    }

    /**
     * Génère une réponse de secours si l'API échoue
     */
    private function generateFallbackResponse(string $reclamationType, string $userName): string
    {
        $solutions = [
            'Echec de montant' => 'Nous avons identifié le problème et procédons à la vérification de votre transaction. Le montant sera crédité sur votre compte sous 48 heures ouvrables.',
            'Virement non recu' => 'Nous avons lancé une enquête sur votre virement. Notre équipe technique vérifie le statut de la transaction et vous tiendra informé dans les plus brefs délais.',
            'Erreur de transaction' => 'Nous avons bien pris note de l\'erreur signalée. Notre équipe technique analyse la situation et vous contactera sous 24 heures avec une solution.',
            'Probleme de connexion au compte' => 'Nous sommes désolés pour ce désagrément. Notre équipe technique travaille à résoudre ce problème de connexion. En attendant, vous pouvez réinitialiser votre mot de passe.',
        ];

        $solution = $solutions[$reclamationType] ?? 'Nous avons bien reçu votre réclamation et notre équipe l\'examine attentivement. Nous vous contacterons sous 48 heures avec une solution adaptée.';

        return <<<RESPONSE
Bonjour {$userName},

Nous vous remercions d'avoir pris le temps de nous contacter concernant votre réclamation : {$reclamationType}.

Nous comprenons parfaitement votre préoccupation et tenons à vous assurer que nous prenons cette situation très au sérieux.

{$solution}

Nous nous excusons pour tout désagrément causé et restons à votre entière disposition pour toute question supplémentaire.

Cordialement,
L'équipe Support Bancaire
RESPONSE;
    }

    /**
     * Envoie la réponse par email à l'utilisateur
     */
    public function sendResponseEmail(
        string $userEmail,
        string $userName,
        string $reclamationType,
        string $response,
        int $reclamationId
    ): void {
        $email = (new Email())
            ->from('support@banque.com')
            ->to($userEmail)
            ->subject("Réponse à votre réclamation #{$reclamationId} - {$reclamationType}")
            ->html($this->buildEmailHTML($userName, $reclamationType, $response, $reclamationId));

        $this->mailer->send($email);
    }

    /**
     * Construit le HTML de l'email
     */
    private function buildEmailHTML(string $userName, string $reclamationType, string $response, int $reclamationId): string
    {
        // Convertir les retours à la ligne en <br>
        $responseHTML = nl2br(htmlspecialchars($response));
        
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background: #f0f4f9;
        }
        .container {
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #11b7aa, #0d9e93);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .header .icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
        .content {
            padding: 30px;
        }
        .badge {
            display: inline-block;
            background: linear-gradient(135deg, #11b7aa, #0d9e93);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin: 10px 0;
        }
        .response {
            background: #f8fbff;
            border-left: 4px solid #11b7aa;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            line-height: 1.8;
        }
        .footer {
            background: #f0f4f9;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #6b85a0;
        }
        .footer p {
            margin: 5px 0;
        }
        .cta {
            text-align: center;
            margin: 30px 0;
        }
        .cta a {
            display: inline-block;
            background: linear-gradient(135deg, #11b7aa, #0d9e93);
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="icon">🏦</div>
            <h1>Réponse à votre réclamation</h1>
        </div>
        <div class="content">
            <p><strong>Réclamation #{$reclamationId}</strong></p>
            <span class="badge">{$reclamationType}</span>
            
            <div class="response">
                {$responseHTML}
            </div>
            
            <div class="cta">
                <a href="#">Accéder à mon compte</a>
            </div>
            
            <p style="margin-top: 30px; font-size: 14px; color: #6b85a0; border-top: 1px solid #e4ecf4; padding-top: 20px;">
                <strong>Besoin d'aide supplémentaire ?</strong><br>
                Si vous avez d'autres questions ou si cette réponse ne résout pas votre problème, 
                n'hésitez pas à nous contacter à nouveau via votre espace client ou par téléphone au 
                <strong>+216 XX XXX XXX</strong>.
            </p>
        </div>
        <div class="footer">
            <p><strong>© 2026 Votre Banque - Tous droits réservés</strong></p>
            <p>Cet email a été généré automatiquement par notre système de support intelligent.</p>
            <p style="margin-top: 10px; font-size: 11px;">
                Vous recevez cet email car vous avez soumis une réclamation sur notre plateforme.
            </p>
        </div>
    </div>
</body>
</html>
HTML;
    }
}
