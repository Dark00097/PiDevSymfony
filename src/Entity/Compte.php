<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CompteRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: CompteRepository::class)]
#[ORM\Table(name: 'compte')]
class Compte
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idCompte', type: 'integer')]
    private ?int $idcompte = null;

    #[ORM\Column(name: 'numeroCompte', type: 'string', length: 30)]
    private string $numerocompte;

    #[ORM\Column(name: 'solde', type: 'decimal', precision: 12, scale: 2)]
    private string $solde;

    #[ORM\Column(name: 'dateOuverture', type: 'string', length: 10)]
    private string $dateouverture;

    #[ORM\Column(name: 'statutCompte', type: 'string', length: 20)]
    private string $statutcompte;

    #[ORM\Column(name: 'plafondRetrait', type: 'decimal', precision: 12, scale: 2)]
    private string $plafondretrait;

    #[ORM\Column(name: 'plafondVirement', type: 'decimal', precision: 12, scale: 2)]
    private string $plafondvirement;

    #[ORM\Column(name: 'typeCompte', type: 'string', length: 20)]
    private string $typecompte;

    #[ORM\ManyToOne(targetEntity: Users::class)]
    #[ORM\JoinColumn(name: 'idUser', referencedColumnName: 'idUser', nullable: true)]
    private ?Users $users = null;

    #[ORM\OneToMany(mappedBy: 'compte', targetEntity: Coffrevirtuel::class)]
    private Collection $coffrevirtuels;

    #[ORM\OneToMany(mappedBy: 'compte', targetEntity: Credit::class)]
    private Collection $credits;

    #[ORM\OneToMany(mappedBy: 'compte', targetEntity: Transactions::class)]
    private Collection $transactionses;



}
