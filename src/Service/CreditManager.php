<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Credit;

class CreditManager
{
    private const STATUTS_VALIDES = ['En attente', 'Approuvé', 'Rejeté', 'En cours', 'Terminé'];
    private const TYPES_VALIDES   = [
        'Professionnel',
        'Immobilier',
        'Auto',
        'Consommation',
        'Etudes',
        'Travaux',
        'Personnel',
        'Hypotheque',
        'Pret auto',
        'Education',
        'Sante',
        'Autre',
    ];

    /**
     * Valide les règles métier d'un Credit.
     *
     * @throws \InvalidArgumentException si une règle métier est violée
     */
    public function validate(Credit $credit): bool
    {
        // Règle 1 : le type de crédit est obligatoire
        if (empty(trim((string) $credit->getTypeCredit()))) {
            throw new \InvalidArgumentException('Le type de crédit est obligatoire.');
        }

        // Règle 2 : le type de crédit doit être valide
        if (!in_array($credit->getTypeCredit(), self::TYPES_VALIDES, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Le type de crédit "%s" est invalide. Valeurs acceptées : %s.',
                    $credit->getTypeCredit(),
                    implode(', ', self::TYPES_VALIDES)
                )
            );
        }

        // Règle 3 : le montant demandé doit être supérieur à zéro
        if ($credit->getMontantDemande() === null || $credit->getMontantDemande() <= 0) {
            throw new \InvalidArgumentException('Le montant demandé doit être supérieur à zéro.');
        }

        // Règle 4 : la durée doit être au moins 1 mois
        if ($credit->getDuree() === null || $credit->getDuree() < 1) {
            throw new \InvalidArgumentException('La durée du crédit doit être au moins de 1 mois.');
        }

        // Règle 5 : le taux d'intérêt ne peut pas être négatif
        if ($credit->getTauxInteret() !== null && $credit->getTauxInteret() < 0) {
            throw new \InvalidArgumentException('Le taux d\'intérêt ne peut pas être négatif.');
        }

        // Règle 6 : le statut doit être valide (si défini)
        if ($credit->getStatut() !== null && !in_array($credit->getStatut(), self::STATUTS_VALIDES, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Le statut "%s" est invalide. Valeurs acceptées : %s.',
                    $credit->getStatut(),
                    implode(', ', self::STATUTS_VALIDES)
                )
            );
        }

        return true;
    }
}
