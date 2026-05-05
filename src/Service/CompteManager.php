<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Compte;

class CompteManager
{
    private const STATUTS_VALIDES = ['Actif', 'Inactif', 'Bloqué'];
    private const TYPES_VALIDES   = ['Courant', 'Epargne'];

    /**
     * Valide les règles métier d'un Compte.
     *
     * @throws \InvalidArgumentException si une règle métier est violée
     */
    public function validate(Compte $compte): bool
    {
        // Règle 1 : le numéro de compte est obligatoire
        if (empty(trim($compte->getNumeroCompte()))) {
            throw new \InvalidArgumentException('Le numéro de compte est obligatoire.');
        }

        // Règle 2 : le solde ne peut pas être négatif
        if ((float) $compte->getSolde() < 0) {
            throw new \InvalidArgumentException('Le solde ne peut pas être négatif.');
        }

        // Règle 3 : le plafond de retrait doit être supérieur à zéro
        if ((float) $compte->getPlafondRetrait() <= 0) {
            throw new \InvalidArgumentException('Le plafond de retrait doit être supérieur à zéro.');
        }

        // Règle 4 : le statut doit être valide
        if (!in_array($compte->getStatutCompte(), self::STATUTS_VALIDES, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Le statut "%s" est invalide. Valeurs acceptées : %s.',
                    $compte->getStatutCompte(),
                    implode(', ', self::STATUTS_VALIDES)
                )
            );
        }

        // Règle 5 : le type de compte doit être valide
        if (!in_array($compte->getTypeCompte(), self::TYPES_VALIDES, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Le type de compte "%s" est invalide. Valeurs acceptées : %s.',
                    $compte->getTypeCompte(),
                    implode(', ', self::TYPES_VALIDES)
                )
            );
        }

        return true;
    }
}
