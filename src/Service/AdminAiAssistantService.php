<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Credit;
use App\Entity\Garantiecredit;

final class AdminAiAssistantService
{
    public function __construct(
        private readonly BankingService $bankingService,
        private readonly GeminiService $geminiService,
        private readonly CreditGarantieAssistantService $creditGarantieAssistantService,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function answer(array $payload): array
    {
        $message = trim((string) ($payload['message'] ?? ''));
        $selectedCreditId = max(0, (int) ($payload['selectedCreditId'] ?? 0));
        $selectedGarantieId = max(0, (int) ($payload['selectedGarantieId'] ?? 0));
        $intent = trim((string) ($payload['intent'] ?? 'summary'));

        $context = $this->buildContext($selectedCreditId, $selectedGarantieId);
        $local = $this->buildLocalAdvice($context, $message);

        $assistant = $this->creditGarantieAssistantService->buildResponse(
            $intent,
            is_array($context['selected_credit'] ?? null) ? $context['selected_credit'] : null,
            is_array($context['selected_garantie'] ?? null) ? $context['selected_garantie'] : null,
            [
                'message' => $message,
                'horizonMonths' => (int) ($payload['horizonMonths'] ?? 12),
                'salaryChange' => (float) ($payload['salaryChange'] ?? 0),
                'earlyRepayment' => (float) ($payload['earlyRepayment'] ?? 0),
            ]
        );

        $summary = $local['summary'];
        $summaryFromMetrics = $this->buildSummaryFromMetrics(is_array($assistant['metrics'] ?? null) ? $assistant['metrics'] : []);
        if ($summaryFromMetrics !== []) {
            $summary = array_replace($summary, $summaryFromMetrics);
        }

        $answer = trim((string) ($assistant['answer'] ?? ''));
        if ($answer === '') {
            $answer = (string) ($local['answer'] ?? '');
        }

        $decision = trim((string) ($assistant['decision'] ?? ''));
        if ($decision === '') {
            $decision = (string) ($local['recommendation'] ?? 'A etudier');
        }

        $riskLevel = trim((string) ($assistant['risk_level'] ?? ''));
        if ($riskLevel === '') {
            $riskLevel = (string) ($local['risk_level'] ?? 'Moyen');
        }

        $score = (float) ($assistant['score'] ?? $local['score'] ?? 0);
        $assistantRecommendations = is_array($assistant['recommendations'] ?? null) ? $assistant['recommendations'] : [];
        $assistantMetrics = is_array($assistant['metrics'] ?? null) ? $assistant['metrics'] : [];
        $assistantProjection = is_array($assistant['projection'] ?? null) ? $assistant['projection'] : null;

        return [
            'intent' => (string) ($assistant['intent'] ?? $intent),
            'answer' => $answer,
            'decision' => $decision,
            'risk_level' => $riskLevel,
            'score' => $score,
            'summary' => $summary,
            'metrics' => $assistantMetrics,
            'recommendations' => $assistantRecommendations,
            'projection' => $assistantProjection,
            'alerts' => array_values(array_unique(array_merge(
                is_array($local['alerts'] ?? null) ? $local['alerts'] : [],
                $intent === 'risk' ? $assistantRecommendations : []
            ))),
            'documents' => array_values(array_unique(array_merge(
                is_array($local['documents'] ?? null) ? $local['documents'] : [],
                $intent === 'documents' ? [$answer] : []
            ))),
            'recommendation' => $decision,
            'strengths' => $local['strengths'],
            'weaknesses' => $local['weaknesses'],
            'confidence' => $local['confidence'],
            'suggested_action' => $local['suggested_action'],
            'messages' => $this->buildDraftMessages($decision, $context),
            'assistant_provider' => 'RuleEngine',
            'prompt_example' => $this->buildPromptExample($context, $message),
        ];
    }

    /**
     * @param array<int, mixed> $metrics
     * @return array<string, string>
     */
    private function buildSummaryFromMetrics(array $metrics): array
    {
        $result = [];
        foreach ($metrics as $metric) {
            if (!is_array($metric)) {
                continue;
            }
            $label = strtolower(trim((string) ($metric['label'] ?? '')));
            $value = trim((string) ($metric['value'] ?? ''));
            if ($label === '' || $value === '') {
                continue;
            }

            if (str_contains($label, 'montant credit')) {
                $result['montant'] = $value;
            } elseif (str_contains($label, 'duree')) {
                $result['duree'] = $value;
            } elseif (str_contains($label, 'mensual')) {
                $result['mensualite'] = $value;
            } elseif (str_contains($label, 'taux endettement')) {
                $result['ratio_endettement'] = $value;
            } elseif (str_contains($label, 'score')) {
                $result['score'] = $value;
            } elseif (str_contains($label, 'risque')) {
                $result['niveau_risque'] = $value;
            } elseif (str_contains($label, 'statut')) {
                $result['statut_documents'] = $value;
            } elseif (str_contains($label, 'couverture')) {
                $result['couverture'] = $value;
            }
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function debugTypeLoading(): array
    {
        $credits = $this->bankingService->listCredits();
        $garanties = $this->bankingService->listGaranties();

        $creditTypes = [];
        foreach ($credits as $row) {
            $value = trim((string) ($row['typeCredit'] ?? ''));
            if ($value !== '') {
                $creditTypes[$value] = true;
            }
        }

        $garantieTypes = [];
        foreach ($garanties as $row) {
            $value = trim((string) ($row['typeGarantie'] ?? ''));
            if ($value !== '') {
                $garantieTypes[$value] = true;
            }
        }

        $invalidCreditTypes = [];
        foreach (array_keys($creditTypes) as $value) {
            if (Credit::normalizeTypeCreditValue($value) === null) {
                $invalidCreditTypes[] = $value;
            }
        }

        $invalidGarantieTypes = [];
        foreach (array_keys($garantieTypes) as $value) {
            if (Garantiecredit::normalizeTypeValue($value) === null) {
                $invalidGarantieTypes[] = $value;
            }
        }

        return [
            'credit_table_empty' => count($credits) === 0,
            'garantie_table_empty' => count($garanties) === 0,
            'credit_choice_values' => Credit::getAllowedTypeValues(),
            'garantie_choice_values' => Garantiecredit::getAllowedTypeValues(),
            'distinct_credit_types_in_db' => array_values(array_keys($creditTypes)),
            'distinct_garantie_types_in_db' => array_values(array_keys($garantieTypes)),
            'invalid_credit_types_in_db' => $invalidCreditTypes,
            'invalid_garantie_types_in_db' => $invalidGarantieTypes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContext(int $selectedCreditId, int $selectedGarantieId): array
    {
        $credits = $this->bankingService->listCredits();
        $garanties = $this->bankingService->listGaranties();
        $users = $this->bankingService->listUsers();

        $selectedCredit = null;
        foreach ($credits as $credit) {
            if ((int) ($credit['idCredit'] ?? 0) === $selectedCreditId) {
                $selectedCredit = $credit;
                break;
            }
        }

        $selectedGarantie = null;
        foreach ($garanties as $garantie) {
            if ((int) ($garantie['idGarantie'] ?? 0) === $selectedGarantieId) {
                $selectedGarantie = $garantie;
                break;
            }
        }

        if ($selectedCredit === null && $selectedGarantie !== null) {
            $linkedCreditId = (int) ($selectedGarantie['idCredit'] ?? 0);
            foreach ($credits as $credit) {
                if ((int) ($credit['idCredit'] ?? 0) === $linkedCreditId) {
                    $selectedCredit = $credit;
                    break;
                }
            }
        }

        $relatedGaranties = [];
        $selectedCreditIdResolved = (int) ($selectedCredit['idCredit'] ?? 0);
        if ($selectedCreditIdResolved > 0) {
            foreach ($garanties as $garantie) {
                if ((int) ($garantie['idCredit'] ?? 0) === $selectedCreditIdResolved) {
                    $relatedGaranties[] = $garantie;
                }
            }
        }

        $selectedUser = null;
        $selectedUserId = (int) ($selectedCredit['idUser'] ?? ($selectedGarantie['idUser'] ?? 0));
        if ($selectedUserId > 0) {
            foreach ($users as $user) {
                if ((int) ($user['idUser'] ?? 0) === $selectedUserId) {
                    $selectedUser = $user;
                    break;
                }
            }
        }

        return [
            'credits' => $credits,
            'garanties' => $garanties,
            'users' => $users,
            'selected_credit' => $selectedCredit,
            'selected_garantie' => $selectedGarantie,
            'selected_user' => $selectedUser,
            'related_garanties' => $relatedGaranties,
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return array{
     *   answer:string,
     *   recommendation:string,
     *   risk_level:string,
     *   score:float,
     *   summary:array<string,string>,
     *   alerts:array<int,string>,
     *   documents:array<int,string>,
     *   strengths:array<int,string>,
     *   weaknesses:array<int,string>,
     *   confidence:float,
     *   suggested_action:string
     * }
     */
    private function buildLocalAdvice(array $context, string $message): array
    {
        /** @var array<string, mixed>|null $credit */
        $credit = is_array($context['selected_credit'] ?? null) ? $context['selected_credit'] : null;
        /** @var array<int, array<string, mixed>> $garanties */
        $garanties = is_array($context['related_garanties'] ?? null) ? $context['related_garanties'] : [];

        $amount = (float) ($credit['montantDemande'] ?? 0);
        $monthly = (float) ($credit['mensualite'] ?? 0);
        $salary = (float) ($credit['salaire'] ?? 0);
        $duration = (int) ($credit['duree'] ?? 0);
        $ratio = $salary > 0 ? $monthly / $salary : 1.0;

        $retainedSum = 0.0;
        $estimatedSum = 0.0;
        $documents = [];
        $alerts = [];

        foreach ($garanties as $garantie) {
            $retainedSum += (float) ($garantie['valeurRetenue'] ?? 0);
            $estimatedSum += (float) ($garantie['valeurEstimee'] ?? 0);

            $docPath = trim((string) ($garantie['documentJustificatif'] ?? ''));
            $docStatus = trim((string) ($garantie['statutVerificationDocument'] ?? ''));

            if ($docPath === '') {
                $documents[] = sprintf('Garantie #%d: justificatif manquant.', (int) ($garantie['idGarantie'] ?? 0));
            }
            if (in_array($docStatus, ['suspect', 'incomplet', 'rejete'], true)) {
                $documents[] = sprintf('Garantie #%d: document %s.', (int) ($garantie['idGarantie'] ?? 0), $docStatus);
            }
        }

        $coverage = $amount > 0 ? $retainedSum / $amount : 0.0;
        $score = (float) ($credit['risk_score'] ?? 0);
        if ($score <= 0) {
            $score = 100 - min(100, ($ratio * 100 * 0.7) + (max(0, 1 - $coverage) * 100 * 0.3));
        }

        if ($ratio > 0.45) {
            $alerts[] = sprintf('Ratio d endettement eleve (%.1f%%).', $ratio * 100);
        }
        if ($salary <= 0) {
            $alerts[] = 'Revenu client non renseigne.';
        }
        if ($coverage < 0.6) {
            $alerts[] = sprintf('Couverture de garantie insuffisante (%.1f%%).', $coverage * 100);
        }
        if ($documents !== []) {
            $alerts[] = 'Documents justificatifs incomplets ou suspects.';
        }
        if ($duration > 0 && $duration > 84) {
            $alerts[] = 'Duree longue: verifier la capacite de remboursement.';
        }

        $riskLevel = $score >= 70 ? 'Faible' : ($score >= 45 ? 'Moyen' : 'Eleve');
        $recommendation = 'A etudier';
        if ($ratio <= 0.35 && $coverage >= 0.8 && $documents === []) {
            $recommendation = 'Accepte';
        } elseif ($ratio <= 0.45 && $coverage >= 0.6 && count($documents) <= 1) {
            $recommendation = 'Accepte avec conditions';
        } elseif ($ratio > 0.55 || $coverage < 0.4 || count($documents) >= 2) {
            $recommendation = 'Refuse';
        }

        // Build strengths
        $strengths = [];
        if ($ratio <= 0.35) {
            $strengths[] = 'Ratio d endettement sain ('.number_format($ratio * 100, 1).'%)';
        }
        if ($coverage >= 0.8) {
            $strengths[] = 'Couverture de garantie solide ('.number_format($coverage * 100, 1).'%)';
        }
        $contractType = strtolower(trim((string) ($credit['typeContrat'] ?? '')));
        if (str_contains($contractType, 'cdi') || str_contains($contractType, 'fonctionnaire')) {
            $strengths[] = 'Contrat stable ('.(string) ($credit['typeContrat'] ?? '-').')';
        }
        if ($salary >= 3000) {
            $strengths[] = 'Revenu confortable ('.number_format($salary, 0, '.', ' ').' DT)';
        }
        if ($documents === []) {
            $strengths[] = 'Dossier documentaire complet';
        }
        if ($duration > 0 && $duration <= 60) {
            $strengths[] = 'Duree raisonnable ('.$duration.' mois)';
        }
        if ($strengths === []) {
            $strengths[] = 'Aucun point fort significatif identifie';
        }

        // Build weaknesses
        $weaknesses = [];
        if ($ratio > 0.45) {
            $weaknesses[] = 'Ratio d endettement eleve ('.number_format($ratio * 100, 1).'%)';
        }
        if ($coverage < 0.6) {
            $weaknesses[] = 'Couverture de garantie insuffisante ('.number_format($coverage * 100, 1).'%)';
        }
        if ($salary <= 0) {
            $weaknesses[] = 'Revenu client non renseigne';
        } elseif ($salary < 1500) {
            $weaknesses[] = 'Revenu modeste ('.number_format($salary, 0, '.', ' ').' DT)';
        }
        if ($documents !== []) {
            $weaknesses[] = 'Justificatifs incomplets ou suspects ('.count($documents).' probleme(s))';
        }
        if ($duration > 84) {
            $weaknesses[] = 'Duree de credit tres longue ('.$duration.' mois)';
        }
        if (str_contains($contractType, 'cdd') || str_contains($contractType, 'interim')) {
            $weaknesses[] = 'Contrat precaire ('.(string) ($credit['typeContrat'] ?? '-').')';
        }

        // Confidence score (0-100)
        $completeness = 0;
        if ($credit !== null) $completeness += 20;
        if ($amount > 0) $completeness += 15;
        if ($salary > 0) $completeness += 15;
        if ($duration > 0) $completeness += 10;
        if ($monthly > 0) $completeness += 10;
        if (count($garanties) > 0) $completeness += 15;
        if ($documents === []) $completeness += 15;
        $confidence = min(100.0, (float) $completeness);

        // Suggested action
        $suggestedAction = 'Demander un complement';
        if ($recommendation === 'Accepte') {
            $suggestedAction = 'Valider le dossier';
        } elseif ($recommendation === 'Accepte avec conditions') {
            $suggestedAction = 'Demander une piece complementaire';
        } elseif ($recommendation === 'Refuse') {
            if ($ratio > 0.55) {
                $suggestedAction = 'Reduire le montant accorde';
            } else {
                $suggestedAction = 'Refuser le dossier';
            }
        }

        $montantAccorde = (float) ($credit['montantAccorde'] ?? 0);

        $summary = [
            'montant' => number_format($amount, 2, '.', ' ').' DT',
            'montant_accorde' => number_format($montantAccorde, 2, '.', ' ').' DT',
            'duree' => $duration > 0 ? $duration.' mois' : '-',
            'mensualite' => number_format($monthly, 2, '.', ' ').' DT',
            'salaire' => number_format($salary, 2, '.', ' ').' DT',
            'ratio_endettement' => number_format($ratio * 100, 1, '.', ' ').'%',
            'type_credit' => Credit::getTypeLabel((string) ($credit['typeCredit'] ?? '')),
            'type_contrat' => (string) ($credit['typeContrat'] ?? '-'),
            'garanties_liees' => (string) count($garanties),
            'valeur_estimee' => number_format($estimatedSum, 2, '.', ' ').' DT',
            'couverture' => number_format($coverage * 100, 1, '.', ' ').'%',
            'score' => number_format($score, 1, '.', ' ').'/100',
            'niveau_risque' => $riskLevel,
            'statut_documents' => $documents === [] ? 'Complet' : count($documents).' probleme(s)',
        ];

        $fallbackAnswer = $message === ''
            ? 'Selectionnez un dossier et posez une question pour obtenir une analyse decisionnelle.'
            : sprintf(
                'Analyse dossier: score %s, risque %s, recommandation %s.',
                $summary['score'],
                $riskLevel,
                $recommendation
            );

        return [
            'answer' => $fallbackAnswer,
            'recommendation' => $recommendation,
            'risk_level' => $riskLevel,
            'score' => round($score, 1),
            'summary' => $summary,
            'alerts' => array_values(array_unique($alerts)),
            'documents' => array_values(array_unique($documents)),
            'strengths' => array_values($strengths),
            'weaknesses' => array_values($weaknesses),
            'confidence' => round($confidence, 1),
            'suggested_action' => $suggestedAction,
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, string>
     */
    private function buildDraftMessages(string $recommendation, array $context): array
    {
        $credit = is_array($context['selected_credit'] ?? null) ? $context['selected_credit'] : [];
        $user = is_array($context['selected_user'] ?? null) ? $context['selected_user'] : [];
        $name = trim(sprintf('%s %s', (string) ($user['prenom'] ?? ''), (string) ($user['nom'] ?? '')));
        $target = $name !== '' ? $name : 'Madame, Monsieur';
        $creditId = (int) ($credit['idCredit'] ?? 0);
        $creditRef = $creditId > 0 ? '#'.$creditId : 'votre dossier';

        $complement = sprintf(
            "Bonjour %s,\n\nDans le cadre de l'etude de %s, merci de transmettre un justificatif lisible et a jour (revenus, identite, garantie).\n\nCordialement,\nService Credit",
            $target,
            $creditRef
        );

        $newDoc = sprintf(
            "Bonjour %s,\n\nLe justificatif transmis pour %s est flou, incomplet ou non conforme. Merci d'envoyer un nouveau document officiel net et complet.\n\nCordialement,\nService Credit",
            $target,
            $creditRef
        );

        $validation = sprintf(
            "Bonjour %s,\n\nVotre dossier %s est valide. La demande est %s.\n\nCordialement,\nService Credit",
            $target,
            $creditRef,
            $recommendation
        );

        $refus = sprintf(
            "Bonjour %s,\n\nApres analyse, %s ne peut pas etre accepte en l'etat. Vous pouvez deposer un nouveau dossier avec des garanties/documentations complementaires.\n\nCordialement,\nService Credit",
            $target,
            $creditRef
        );

        return [
            'demande_complement' => $complement,
            'demande_nouveau_justificatif' => $newDoc,
            'message_validation' => $validation,
            'message_refus' => $refus,
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildPromptExample(array $context, string $question): string
    {
        return "Tu es un assistant admin credit/garantie.\n"
            ."Objectif: analyser un dossier bancaire et recommander une decision.\n"
            ."Reponds en JSON avec: resume, alertes, recommandation, actions_client.\n"
            ."Question admin: ".($question !== '' ? $question : 'Resumer ce dossier et recommander une decision.')."\n"
            ."Contexte dossier: ".json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
