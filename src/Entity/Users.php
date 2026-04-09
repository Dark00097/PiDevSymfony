<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UsersRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: UsersRepository::class)]
#[ORM\Table(name: 'users')]
class Users
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idUser', type: 'integer')]
    private ?int $iduser = null;

    #[ORM\Column(name: 'nom', type: 'string', length: 80)]
    private string $nom;

    #[ORM\Column(name: 'prenom', type: 'string', length: 80)]
    private string $prenom;

    #[ORM\Column(name: 'email', type: 'string', length: 190)]
    private string $email;

    #[ORM\Column(name: 'telephone', type: 'string', length: 30)]
    private string $telephone;

    #[ORM\Column(name: 'role', type: 'string', length: 20)]
    private string $role;

    #[ORM\Column(name: 'status', type: 'string', length: 20)]
    private string $status;

    #[ORM\Column(name: 'password', type: 'string', length: 255)]
    private string $password;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    #[ORM\Column(name: 'account_opened_from', type: 'string', length: 180)]
    private string $accountOpenedFrom;

    #[ORM\Column(name: 'last_online_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastOnlineAt = null;

    #[ORM\Column(name: 'last_online_from', type: 'string', length: 180, nullable: true)]
    private ?string $lastOnlineFrom = null;

    #[ORM\Column(name: 'biometric_enabled', type: 'boolean')]
    private bool $biometricEnabled;

    #[ORM\Column(name: 'profile_image_path', type: 'string', length: 600, nullable: true)]
    private ?string $profileImagePath = null;

    #[ORM\Column(name: 'account_opened_location', type: 'string', length: 200)]
    private string $accountOpenedLocation;

    #[ORM\Column(name: 'account_opened_lat', type: 'decimal', precision: 10, scale: 7, nullable: true)]
    private ?string $accountOpenedLat = null;

    #[ORM\Column(name: 'account_opened_lng', type: 'decimal', precision: 10, scale: 7, nullable: true)]
    private ?string $accountOpenedLng = null;

    #[ORM\OneToOne(mappedBy: 'users', targetEntity: RoueFortunePoints::class)]
    private ?RoueFortunePoints $roueFortunePoints = null;

    #[ORM\OneToMany(mappedBy: 'users', targetEntity: CashbackEntries::class)]
    private Collection $cashbackEntrieses;

    #[ORM\OneToMany(mappedBy: 'users', targetEntity: Coffrevirtuel::class)]
    private Collection $coffrevirtuels;

    #[ORM\OneToMany(mappedBy: 'users', targetEntity: Compte::class)]
    private Collection $comptes;

    #[ORM\OneToMany(mappedBy: 'users', targetEntity: Reclamation::class)]
    private Collection $reclamations;

    #[ORM\OneToMany(mappedBy: 'users', targetEntity: SurplusNotifications::class)]
    private Collection $surplusNotificationses;

    #[ORM\OneToMany(mappedBy: 'users', targetEntity: Transactions::class)]
    private Collection $transactionses;



}
