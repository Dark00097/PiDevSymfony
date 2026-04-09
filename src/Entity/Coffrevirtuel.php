<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CoffrevirtuelRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CoffrevirtuelRepository::class)]
#[ORM\Table(name: 'coffrevirtuel')]
class Coffrevirtuel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idCoffre', type: 'integer')]
    private ?int $idcoffre = null;

    #[ORM\Column(name: 'nom', type: 'string', length: 50)]
    private string $nom;

    #[ORM\Column(name: 'objectifMontant', type: 'decimal', precision: 12, scale: 2)]
    private string $objectifmontant;

    #[ORM\Column(name: 'montantActuel', type: 'decimal', precision: 12, scale: 2)]
    private string $montantactuel;

    #[ORM\Column(name: 'dateCreation', type: 'string', length: 20)]
    private string $datecreation;

    #[ORM\Column(name: 'dateObjectifs', type: 'string', length: 20, nullable: true)]
    private ?string $dateobjectifs = null;

    #[ORM\Column(name: 'status', type: 'string', length: 20)]
    private string $status;

    #[ORM\Column(name: 'estVerrouille', type: 'boolean')]
    private bool $estverrouille;

    #[ORM\ManyToOne(targetEntity: Compte::class)]
    #[ORM\JoinColumn(name: 'idCompte', referencedColumnName: 'idCompte', nullable: true)]
    private ?Compte $compte = null;

    #[ORM\ManyToOne(targetEntity: Users::class)]
    #[ORM\JoinColumn(name: 'idUser', referencedColumnName: 'idUser', nullable: true)]
    private ?Users $users = null;



}
