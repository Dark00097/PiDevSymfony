<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CreditRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: CreditRepository::class)]
#[ORM\Table(name: 'credit')]
class Credit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idCredit', type: 'integer')]
    private ?int $idcredit = null;

    #[ORM\Column(name: 'typeCredit', type: 'string', length: 50)]
    private string $typecredit;

    #[ORM\Column(name: 'montantDemande', type: 'float', precision: 22)]
    private float $montantdemande;

    #[ORM\Column(name: 'autofinancement', type: 'float', precision: 22, nullable: true)]
    private ?float $autofinancement = null;

    #[ORM\Column(name: 'duree', type: 'integer')]
    private int $duree;

    #[ORM\Column(name: 'tauxInteret', type: 'float', precision: 22)]
    private float $tauxinteret;

    #[ORM\Column(name: 'mensualite', type: 'float', precision: 22)]
    private float $mensualite;

    #[ORM\Column(name: 'montantAccorde', type: 'float', precision: 22)]
    private float $montantaccorde;

    #[ORM\Column(name: 'dateDemande', type: 'string', length: 20)]
    private string $datedemande;

    #[ORM\Column(name: 'idUser', type: 'integer', nullable: true)]
    private ?int $iduser = null;

    #[ORM\Column(name: 'salaire', type: 'float', precision: 22, nullable: true)]
    private ?float $salaire = null;

    #[ORM\Column(name: 'typeContrat', type: 'string', length: 50)]
    private string $typecontrat;

    #[ORM\Column(name: 'ancienneteAnnees', type: 'integer')]
    private int $ancienneteannees;

    #[ORM\ManyToOne(targetEntity: Compte::class)]
    #[ORM\JoinColumn(name: 'idCompte', referencedColumnName: 'idCompte', nullable: false)]
    private ?Compte $compte = null;

    #[ORM\OneToMany(mappedBy: 'credit', targetEntity: Garantiecredit::class)]
    private Collection $garantiecredits;



}
