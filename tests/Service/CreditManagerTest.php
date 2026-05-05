<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Credit;
use App\Service\CreditManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour CreditManager.
 *
 * Règles métier validées :
 *  1. Le type de crédit est obligatoire.
 *  2. Le type de crédit doit être valide.
 *  3. Le montant demandé doit être supérieur à zéro.
 *  4. La durée doit être au moins 1 mois.
 *  5. Le taux d'intérêt ne peut pas être négatif.
 *  6. Le statut doit être valide (si défini).
 */
class CreditManagerTest extends TestCase
{
    // ---------------------------------------------------------------
    // Cas nominal : crédit valide
    // ---------------------------------------------------------------

    public function testCreditValide(): void
    {
        $credit = new Credit();
        $credit->setTypeCredit('Immobilier');
        $credit->setMontantDemande(150000.00);
        $credit->setDuree(240); // 20 ans
        $credit->setTauxInteret(3.5);
        $credit->setStatut('En attente');

        $manager = new CreditManager();

        $this->assertTrue($manager->validate($credit));
    }

    // ---------------------------------------------------------------
    // Règle 1 : type de crédit obligatoire
    // ---------------------------------------------------------------

    public function testTypeCreditVide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le type de crédit est obligatoire.');

        $credit = new Credit();
        $credit->setTypeCredit('');
        $credit->setMontantDemande(50000.00);
        $credit->setDuree(60);
        $credit->setTauxInteret(4.0);

        (new CreditManager())->validate($credit);
    }

    public function testTypeCreditEspacesSeuls(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le type de crédit est obligatoire.');

        $credit = new Credit();
        $credit->setTypeCredit('   ');
        $credit->setMontantDemande(50000.00);
        $credit->setDuree(60);
        $credit->setTauxInteret(4.0);

        (new CreditManager())->validate($credit);
    }

    // ---------------------------------------------------------------
    // Règle 2 : type de crédit valide
    // ---------------------------------------------------------------

    public function testTypeCreditInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le type de crédit "Invalide" est invalide.');

        $credit = new Credit();
        $credit->setTypeCredit('Invalide');
        $credit->setMontantDemande(50000.00);
        $credit->setDuree(60);
        $credit->setTauxInteret(4.0);

        (new CreditManager())->validate($credit);
    }

    /** @dataProvider typesValidesProvider */
    public function testTypesValides(string $type): void
    {
        $credit = new Credit();
        $credit->setTypeCredit($type);
        $credit->setMontantDemande(50000.00);
        $credit->setDuree(60);
        $credit->setTauxInteret(4.0);

        $this->assertTrue((new CreditManager())->validate($credit));
    }

    public static function typesValidesProvider(): array
    {
        return [
            'type Professionnel' => ['Professionnel'],
            'type Immobilier'    => ['Immobilier'],
            'type Auto'          => ['Auto'],
            'type Consommation'  => ['Consommation'],
            'type Etudes'        => ['Etudes'],
            'type Travaux'       => ['Travaux'],
            'type Personnel'     => ['Personnel'],
            'type Hypotheque'    => ['Hypotheque'],
            'type Pret auto'     => ['Pret auto'],
            'type Education'     => ['Education'],
            'type Sante'         => ['Sante'],
            'type Autre'         => ['Autre'],
        ];
    }

    // ---------------------------------------------------------------
    // Règle 3 : montant demandé > 0
    // ---------------------------------------------------------------

    public function testMontantDemandeZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le montant demandé doit être supérieur à zéro.');

        $credit = new Credit();
        $credit->setTypeCredit('Auto');
        $credit->setMontantDemande(0.00);
        $credit->setDuree(60);
        $credit->setTauxInteret(4.0);

        (new CreditManager())->validate($credit);
    }

    public function testMontantDemandeNegatif(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le montant demandé doit être supérieur à zéro.');

        $credit = new Credit();
        $credit->setTypeCredit('Auto');
        $credit->setMontantDemande(-5000.00);
        $credit->setDuree(60);
        $credit->setTauxInteret(4.0);

        (new CreditManager())->validate($credit);
    }

    public function testMontantDemandeNull(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le montant demandé doit être supérieur à zéro.');

        $credit = new Credit();
        $credit->setTypeCredit('Auto');
        $credit->setMontantDemande(null);
        $credit->setDuree(60);
        $credit->setTauxInteret(4.0);

        (new CreditManager())->validate($credit);
    }

    // ---------------------------------------------------------------
    // Règle 4 : durée >= 1 mois
    // ---------------------------------------------------------------

    public function testDureeZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La durée du crédit doit être au moins de 1 mois.');

        $credit = new Credit();
        $credit->setTypeCredit('Personnel');
        $credit->setMontantDemande(10000.00);
        $credit->setDuree(0);
        $credit->setTauxInteret(5.0);

        (new CreditManager())->validate($credit);
    }

    public function testDureeNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La durée du crédit doit être au moins de 1 mois.');

        $credit = new Credit();
        $credit->setTypeCredit('Personnel');
        $credit->setMontantDemande(10000.00);
        $credit->setDuree(-12);
        $credit->setTauxInteret(5.0);

        (new CreditManager())->validate($credit);
    }

    public function testDureeNull(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La durée du crédit doit être au moins de 1 mois.');

        $credit = new Credit();
        $credit->setTypeCredit('Personnel');
        $credit->setMontantDemande(10000.00);
        $credit->setDuree(null);
        $credit->setTauxInteret(5.0);

        (new CreditManager())->validate($credit);
    }

    public function testDureeUnMoisEstValide(): void
    {
        $credit = new Credit();
        $credit->setTypeCredit('Personnel');
        $credit->setMontantDemande(10000.00);
        $credit->setDuree(1);
        $credit->setTauxInteret(5.0);

        $this->assertTrue((new CreditManager())->validate($credit));
    }

    // ---------------------------------------------------------------
    // Règle 5 : taux d'intérêt >= 0
    // ---------------------------------------------------------------

    public function testTauxInteretNegatif(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le taux d\'intérêt ne peut pas être négatif.');

        $credit = new Credit();
        $credit->setTypeCredit('Consommation');
        $credit->setMontantDemande(5000.00);
        $credit->setDuree(24);
        $credit->setTauxInteret(-2.5);

        (new CreditManager())->validate($credit);
    }

    public function testTauxInteretZeroEstValide(): void
    {
        $credit = new Credit();
        $credit->setTypeCredit('Consommation');
        $credit->setMontantDemande(5000.00);
        $credit->setDuree(24);
        $credit->setTauxInteret(0.0);

        $this->assertTrue((new CreditManager())->validate($credit));
    }

    public function testTauxInteretNullEstValide(): void
    {
        $credit = new Credit();
        $credit->setTypeCredit('Consommation');
        $credit->setMontantDemande(5000.00);
        $credit->setDuree(24);
        $credit->setTauxInteret(null);

        $this->assertTrue((new CreditManager())->validate($credit));
    }

    // ---------------------------------------------------------------
    // Règle 6 : statut valide (si défini)
    // ---------------------------------------------------------------

    public function testStatutInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le statut "Annulé" est invalide.');

        $credit = new Credit();
        $credit->setTypeCredit('Etudes');
        $credit->setMontantDemande(20000.00);
        $credit->setDuree(120);
        $credit->setTauxInteret(2.0);
        $credit->setStatut('Annulé');

        (new CreditManager())->validate($credit);
    }

    /** @dataProvider statutsValidesProvider */
    public function testStatutsValides(string $statut): void
    {
        $credit = new Credit();
        $credit->setTypeCredit('Etudes');
        $credit->setMontantDemande(20000.00);
        $credit->setDuree(120);
        $credit->setTauxInteret(2.0);
        $credit->setStatut($statut);

        $this->assertTrue((new CreditManager())->validate($credit));
    }

    public static function statutsValidesProvider(): array
    {
        return [
            'statut En attente' => ['En attente'],
            'statut Approuvé'   => ['Approuvé'],
            'statut Rejeté'     => ['Rejeté'],
            'statut En cours'   => ['En cours'],
            'statut Terminé'    => ['Terminé'],
        ];
    }

    public function testStatutNullEstValide(): void
    {
        $credit = new Credit();
        $credit->setTypeCredit('Etudes');
        $credit->setMontantDemande(20000.00);
        $credit->setDuree(120);
        $credit->setTauxInteret(2.0);
        $credit->setStatut(null);

        $this->assertTrue((new CreditManager())->validate($credit));
    }
}
