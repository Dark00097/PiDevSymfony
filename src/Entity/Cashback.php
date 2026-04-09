<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CashbackRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CashbackRepository::class)]
#[ORM\Table(name: 'cashback')]
class Cashback
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idCashback', type: 'integer')]
    private ?int $idcashback = null;

    #[ORM\Column(name: 'montantAchat', type: 'float', precision: 22)]
    private float $montantachat;

    #[ORM\Column(name: 'tauxApplique', type: 'float', precision: 22)]
    private float $tauxapplique;

    #[ORM\Column(name: 'montantCashback', type: 'float', precision: 22)]
    private float $montantcashback;

    #[ORM\Column(name: 'dateAchat', type: 'string', length: 20)]
    private string $dateachat;

    #[ORM\Column(name: 'dateCredit', type: 'string', length: 20, nullable: true)]
    private ?string $datecredit = null;

    #[ORM\Column(name: 'dateExpiration', type: 'string', length: 20, nullable: true)]
    private ?string $dateexpiration = null;

    #[ORM\Column(name: 'statut', type: 'string', length: 30)]
    private string $statut;

    #[ORM\ManyToOne(targetEntity: Partenaire::class)]
    #[ORM\JoinColumn(name: 'idPartenaire', referencedColumnName: 'idPartenaire', nullable: false)]
    private ?Partenaire $partenaire = null;

    #[ORM\ManyToOne(targetEntity: Transactions::class)]
    #[ORM\JoinColumn(name: 'idTransaction', referencedColumnName: 'idTransaction', nullable: false)]
    private ?Transactions $transactions = null;



}
