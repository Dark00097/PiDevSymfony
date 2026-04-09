<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SurplusNotificationsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SurplusNotificationsRepository::class)]
#[ORM\Table(name: 'surplus_notifications')]
class SurplusNotifications
{
    #[ORM\Id]
    #[ORM\Column(name: 'moisAffiche', type: 'string', length: 7)]
    private string $moisaffiche;

    #[ORM\Column(name: 'dateCreation', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $datecreation = null;

    #[ORM\ManyToOne(targetEntity: Users::class)]
    #[ORM\JoinColumn(name: 'idUser', referencedColumnName: 'idUser', nullable: false)]
    private ?Users $users = null;



}
