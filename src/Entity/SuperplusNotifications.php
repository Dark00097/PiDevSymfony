<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SuperplusNotificationsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SuperplusNotificationsRepository::class)]
#[ORM\Table(name: 'superplus_notifications')]
class SuperplusNotifications
{
    #[ORM\Id]
    #[ORM\Column(name: 'moisAffiche', type: 'string', length: 7)]
    private string $moisaffiche;

    #[ORM\Column(name: 'dateCreation', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $datecreation = null;

    #[ORM\ManyToOne(targetEntity: Users::class, inversedBy: 'superplusNotifications')]
    #[ORM\JoinColumn(name: 'idUser', referencedColumnName: 'idUser', nullable: false)]
    private ?Users $users = null;



}
