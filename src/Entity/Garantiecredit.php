<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\GarantiecreditRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GarantiecreditRepository::class)]
#[ORM\Table(name: 'garantiecredit')]
class Garantiecredit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idGarantie', type: 'integer')]
    private ?int $idgarantie = null;

    #[ORM\Column(name: 'typeGarantie', type: 'string', length: 50)]
    private string $typegarantie;

    #[ORM\Column(name: 'description', type: 'string', length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'adresseBien', type: 'string', length: 255, nullable: true)]
    private ?string $adressebien = null;

    #[ORM\Column(name: 'valeurEstimee', type: 'float', precision: 22)]
    private float $valeurestimee;

    #[ORM\Column(name: 'valeurRetenue', type: 'float', precision: 22)]
    private float $valeurretenue;

    #[ORM\Column(name: 'documentJustificatif', type: 'string', length: 255, nullable: true)]
    private ?string $documentjustificatif = null;

    #[ORM\Column(name: 'dateEvaluation', type: 'string', length: 20)]
    private string $dateevaluation;

    #[ORM\Column(name: 'nomGarant', type: 'string', length: 100, nullable: true)]
    private ?string $nomgarant = null;

    #[ORM\Column(name: 'statut', type: 'string', length: 30)]
    private string $statut;

    #[ORM\Column(name: 'idUser', type: 'integer')]
    private int $iduser;

    #[ORM\ManyToOne(targetEntity: Credit::class)]
    #[ORM\JoinColumn(name: 'idCredit', referencedColumnName: 'idCredit', nullable: false)]
    private ?Credit $credit = null;



}
