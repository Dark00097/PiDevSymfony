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

    #[ORM\Column(name: 'adresseComplete', type: 'string', length: 255, nullable: true)]
    private ?string $adressecomplete = null;

    #[ORM\Column(name: 'ville', type: 'string', length: 120, nullable: true)]
    private ?string $ville = null;

    #[ORM\Column(name: 'codePostal', type: 'string', length: 30, nullable: true)]
    private ?string $codepostal = null;

    #[ORM\Column(name: 'pays', type: 'string', length: 120, nullable: true)]
    private ?string $pays = null;

    #[ORM\Column(name: 'latitude', type: 'float', nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(name: 'longitude', type: 'float', nullable: true)]
    private ?float $longitude = null;

    #[ORM\Column(name: 'statutVerificationAdresse', type: 'string', length: 30, options: ['default' => 'A verifier'])]
    private string $statutverificationadresse = 'A verifier';

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
    #[ORM\JoinColumn(name: 'idCredit', referencedColumnName: 'idCredit', nullable: true)]
    private ?Credit $credit = null;



}
