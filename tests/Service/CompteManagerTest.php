<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Compte;
use App\Service\CompteManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour CompteManager.
 *
 * Règles métier validées :
 *  1. Le numéro de compte est obligatoire.
 *  2. Le solde ne peut pas être négatif.
 *  3. Le plafond de retrait doit être supérieur à zéro.
 *  4. Le statut doit être parmi : Actif, Inactif, Bloqué.
 *  5. Le type de compte doit être parmi : Courant, Epargne.
 */
class CompteManagerTest extends TestCase
{
    // ---------------------------------------------------------------
    // Cas nominal : compte valide
    // ---------------------------------------------------------------

    public function testCompteValide(): void
    {
        $compte = new Compte();
        $compte->setNumeroCompte('TN5910006035183598478831');
        $compte->setSolde('1500.00');
        $compte->setPlafondRetrait('500.00');
        $compte->setPlafondVirement('2000.00');
        $compte->setStatutCompte('Actif');
        $compte->setTypeCompte('Courant');
        $compte->setDateOuverture('2024-01-15');

        $manager = new CompteManager();

        $this->assertTrue($manager->validate($compte));
    }

    // ---------------------------------------------------------------
    // Règle 1 : numéro de compte obligatoire
    // ---------------------------------------------------------------

    public function testNumeroCompteVide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le numéro de compte est obligatoire.');

        $compte = new Compte();
        $compte->setNumeroCompte('');          // vide
        $compte->setSolde('500.00');
        $compte->setPlafondRetrait('200.00');
        $compte->setStatutCompte('Actif');
        $compte->setTypeCompte('Courant');

        (new CompteManager())->validate($compte);
    }

    public function testNumeroCompteEspacesSeuls(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le numéro de compte est obligatoire.');

        $compte = new Compte();
        $compte->setNumeroCompte('   ');       // espaces uniquement
        $compte->setSolde('500.00');
        $compte->setPlafondRetrait('200.00');
        $compte->setStatutCompte('Actif');
        $compte->setTypeCompte('Courant');

        (new CompteManager())->validate($compte);
    }

    // ---------------------------------------------------------------
    // Règle 2 : solde non négatif
    // ---------------------------------------------------------------

    public function testSoldeNegatif(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le solde ne peut pas être négatif.');

        $compte = new Compte();
        $compte->setNumeroCompte('TN5910006035183598478831');
        $compte->setSolde('-100.00');          // négatif
        $compte->setPlafondRetrait('200.00');
        $compte->setStatutCompte('Actif');
        $compte->setTypeCompte('Courant');

        (new CompteManager())->validate($compte);
    }

    public function testSoldeZeroEstValide(): void
    {
        $compte = new Compte();
        $compte->setNumeroCompte('TN5910006035183598478831');
        $compte->setSolde('0.00');             // zéro est autorisé
        $compte->setPlafondRetrait('200.00');
        $compte->setStatutCompte('Actif');
        $compte->setTypeCompte('Courant');

        $this->assertTrue((new CompteManager())->validate($compte));
    }

    // ---------------------------------------------------------------
    // Règle 3 : plafond de retrait > 0
    // ---------------------------------------------------------------

    public function testPlafondRetraitZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le plafond de retrait doit être supérieur à zéro.');

        $compte = new Compte();
        $compte->setNumeroCompte('TN5910006035183598478831');
        $compte->setSolde('500.00');
        $compte->setPlafondRetrait('0.00');    // zéro interdit
        $compte->setStatutCompte('Actif');
        $compte->setTypeCompte('Courant');

        (new CompteManager())->validate($compte);
    }

    public function testPlafondRetraitNegatif(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le plafond de retrait doit être supérieur à zéro.');

        $compte = new Compte();
        $compte->setNumeroCompte('TN5910006035183598478831');
        $compte->setSolde('500.00');
        $compte->setPlafondRetrait('-50.00');  // négatif interdit
        $compte->setStatutCompte('Actif');
        $compte->setTypeCompte('Courant');

        (new CompteManager())->validate($compte);
    }

    // ---------------------------------------------------------------
    // Règle 4 : statut valide
    // ---------------------------------------------------------------

    public function testStatutInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le statut "Suspendu" est invalide.');

        $compte = new Compte();
        $compte->setNumeroCompte('TN5910006035183598478831');
        $compte->setSolde('500.00');
        $compte->setPlafondRetrait('200.00');
        $compte->setStatutCompte('Suspendu');  // valeur inconnue
        $compte->setTypeCompte('Courant');

        (new CompteManager())->validate($compte);
    }

    /** @dataProvider statutsValidesProvider */
    public function testStatutsValides(string $statut): void
    {
        $compte = new Compte();
        $compte->setNumeroCompte('TN5910006035183598478831');
        $compte->setSolde('500.00');
        $compte->setPlafondRetrait('200.00');
        $compte->setStatutCompte($statut);
        $compte->setTypeCompte('Courant');

        $this->assertTrue((new CompteManager())->validate($compte));
    }

    public static function statutsValidesProvider(): array
    {
        return [
            'statut Actif'    => ['Actif'],
            'statut Inactif'  => ['Inactif'],
            'statut Bloqué'   => ['Bloqué'],
        ];
    }

    // ---------------------------------------------------------------
    // Règle 5 : type de compte valide
    // ---------------------------------------------------------------

    public function testTypeCompteInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le type de compte "Professionnel" est invalide.');

        $compte = new Compte();
        $compte->setNumeroCompte('TN5910006035183598478831');
        $compte->setSolde('500.00');
        $compte->setPlafondRetrait('200.00');
        $compte->setStatutCompte('Actif');
        $compte->setTypeCompte('Professionnel'); // valeur inconnue

        (new CompteManager())->validate($compte);
    }

    /** @dataProvider typesValidesProvider */
    public function testTypesValides(string $type): void
    {
        $compte = new Compte();
        $compte->setNumeroCompte('TN5910006035183598478831');
        $compte->setSolde('500.00');
        $compte->setPlafondRetrait('200.00');
        $compte->setStatutCompte('Actif');
        $compte->setTypeCompte($type);

        $this->assertTrue((new CompteManager())->validate($compte));
    }

    public static function typesValidesProvider(): array
    {
        return [
            'type Courant' => ['Courant'],
            'type Epargne' => ['Epargne'],
        ];
    }
}
