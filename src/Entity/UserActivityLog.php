<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserActivityLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserActivityLogRepository::class)]
#[ORM\Table(name: 'user_activity_log')]
class UserActivityLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idAction', type: 'integer')]
    private ?int $idaction = null;

    #[ORM\Column(name: 'idUser', type: 'integer')]
    private int $iduser;

    #[ORM\Column(name: 'action_type', type: 'string', length: 50)]
    private string $actionType;

    #[ORM\Column(name: 'action_source', type: 'string', length: 180, nullable: true)]
    private ?string $actionSource = null;

    #[ORM\Column(name: 'details', type: 'string', length: 255, nullable: true)]
    private ?string $details = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTimeInterface $createdAt;



}
