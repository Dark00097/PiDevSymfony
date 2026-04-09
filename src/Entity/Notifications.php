<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\NotificationsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationsRepository::class)]
#[ORM\Table(name: 'notifications')]
class Notifications
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idNotification', type: 'integer')]
    private ?int $idnotification = null;

    #[ORM\Column(name: 'recipient_user_id', type: 'integer', nullable: true)]
    private ?int $recipientUserId = null;

    #[ORM\Column(name: 'recipient_role', type: 'string', length: 20, nullable: true)]
    private ?string $recipientRole = null;

    #[ORM\Column(name: 'related_user_id', type: 'integer', nullable: true)]
    private ?int $relatedUserId = null;

    #[ORM\Column(name: 'type', type: 'string', length: 40)]
    private string $type;

    #[ORM\Column(name: 'title', type: 'string', length: 160)]
    private string $title;

    #[ORM\Column(name: 'message', type: 'text', length: 65535)]
    private string $message;

    #[ORM\Column(name: 'is_read', type: 'boolean')]
    private bool $isRead;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTimeInterface $createdAt;



}
