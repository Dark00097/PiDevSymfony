<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TransactionsRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: TransactionsRepository::class)]
#[ORM\Table(name: 'transactions')]
class Transactions
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idTransaction', type: 'integer')]
    private ?int $idtransaction = null;

    #[ORM\Column(name: 'categorie', type: 'string', length: 50)]
    private string $categorie;

    #[ORM\Column(name: 'dateTransaction', type: 'string', length: 20)]
    private string $datetransaction;

    #[ORM\Column(name: 'montant', type: 'string', length: 255, nullable: true)]
    private ?string $montant = null;

    #[ORM\Column(name: 'typeTransaction', type: 'string', length: 30)]
    private string $typetransaction;

    #[ORM\Column(name: 'statutTransaction', type: 'string', length: 30)]
    private string $statuttransaction;

    #[ORM\Column(name: 'soldeApres', type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $soldeapres = null;

    #[ORM\Column(name: 'description', type: 'string', length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'montantPaye', type: 'string', length: 255, nullable: true)]
    private ?string $montantpaye = null;

    #[ORM\ManyToOne(targetEntity: Compte::class)]
    #[ORM\JoinColumn(name: 'idCompte', referencedColumnName: 'idCompte', nullable: true)]
    private ?Compte $compte = null;

    #[ORM\ManyToOne(targetEntity: Users::class)]
    #[ORM\JoinColumn(name: 'idUser', referencedColumnName: 'idUser', nullable: true)]
    private ?Users $users = null;

    #[ORM\OneToMany(mappedBy: 'transactions', targetEntity: Cashback::class)]
    private Collection $cashbacks;

    #[ORM\OneToMany(mappedBy: 'transactions', targetEntity: Reclamation::class)]
    private Collection $reclamations;



}
