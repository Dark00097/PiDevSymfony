<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ReclamationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReclamationRepository::class)]
#[ORM\Table(name: 'reclamation')]
class Reclamation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idReclamation', type: 'integer')]
    private ?int $idreclamation = null;

    #[ORM\Column(name: 'dateReclamation', type: 'date')]
    private \DateTimeInterface $datereclamation;

    #[ORM\Column(name: 'typeReclamation', type: 'string', length: 50)]
    private string $typereclamation;

    #[ORM\Column(name: 'description', type: 'string', length: 150)]
    private string $description;

    #[ORM\Column(name: 'status', type: 'string', length: 30)]
    private string $status;

    #[ORM\Column(name: 'is_inappropriate', type: 'boolean')]
    private bool $isInappropriate;

    #[ORM\Column(name: 'is_blurred', type: 'boolean')]
    private bool $isBlurred;

    #[ORM\ManyToOne(targetEntity: Users::class)]
    #[ORM\JoinColumn(name: 'idUser', referencedColumnName: 'idUser', nullable: true)]
    private ?Users $users = null;

    #[ORM\ManyToOne(targetEntity: Transactions::class)]
    #[ORM\JoinColumn(name: 'idTransaction', referencedColumnName: 'idTransaction', nullable: true)]
    private ?Transactions $transactions = null;



}
