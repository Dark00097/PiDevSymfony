<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RoueFortunePointsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RoueFortunePointsRepository::class)]
#[ORM\Table(name: 'roue_fortune_points')]
class RoueFortunePoints
{
    #[ORM\Column(name: 'totalPoints', type: 'integer')]
    private int $totalpoints;

    #[ORM\Column(name: 'dernierTour', type: 'string', length: 10, nullable: true)]
    private ?string $derniertour = null;

    #[ORM\Column(name: 'dernierMois', type: 'string', length: 7, nullable: true)]
    private ?string $derniermois = null;

    #[ORM\Column(name: 'pointsGagnes', type: 'integer')]
    private int $pointsgagnes;

    #[ORM\Id]
    #[ORM\OneToOne(targetEntity: Users::class, inversedBy: 'roueFortunePoints')]
    #[ORM\JoinColumn(name: 'idUser', referencedColumnName: 'idUser', nullable: false, unique: true)]
    private ?Users $users = null;



}
