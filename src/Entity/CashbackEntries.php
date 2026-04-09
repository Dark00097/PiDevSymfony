<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CashbackEntriesRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CashbackEntriesRepository::class)]
#[ORM\Table(name: 'cashback_entries')]
class CashbackEntries
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_cashback', type: 'integer')]
    private ?int $idCashback = null;

    #[ORM\Column(name: 'partenaire_nom', type: 'string', length: 120)]
    private string $partenaireNom;

    #[ORM\Column(name: 'montant_achat', type: 'float', precision: 22)]
    private float $montantAchat;

    #[ORM\Column(name: 'taux_applique', type: 'float', precision: 22)]
    private float $tauxApplique;

    #[ORM\Column(name: 'montant_cashback', type: 'float', precision: 22)]
    private float $montantCashback;

    #[ORM\Column(name: 'date_achat', type: 'date')]
    private \DateTimeInterface $dateAchat;

    #[ORM\Column(name: 'date_credit', type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateCredit = null;

    #[ORM\Column(name: 'date_expiration', type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateExpiration = null;

    #[ORM\Column(name: 'statut', type: 'string', length: 30)]
    private string $statut;

    #[ORM\Column(name: 'transaction_ref', type: 'string', length: 120, nullable: true)]
    private ?string $transactionRef = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(name: 'user_rating', type: 'float', precision: 22, nullable: true)]
    private ?float $userRating = null;

    #[ORM\Column(name: 'user_rating_comment', type: 'string', length: 255, nullable: true)]
    private ?string $userRatingComment = null;

    #[ORM\Column(name: 'bonus_decision', type: 'string', length: 20)]
    private string $bonusDecision;

    #[ORM\Column(name: 'bonus_note', type: 'string', length: 255, nullable: true)]
    private ?string $bonusNote = null;

    #[ORM\ManyToOne(targetEntity: Users::class)]
    #[ORM\JoinColumn(name: 'id_user', referencedColumnName: 'idUser', nullable: false)]
    private ?Users $users = null;

    #[ORM\ManyToOne(targetEntity: Partenaire::class)]
    #[ORM\JoinColumn(name: 'id_partenaire', referencedColumnName: 'idPartenaire', nullable: true)]
    private ?Partenaire $partenaire = null;



}
