<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TransactionsRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: TransactionsRepository::class)]
#[ORM\Table(name: 'transactions')]
class Transactions
{
    public const TYPE_DEPOT = 'DEPOT';
    public const TYPE_RETRAIT = 'RETRAIT';
    public const TYPE_VIREMENT = 'VIREMENT';
    public const TYPE_PAIEMENT = 'PAIEMENT';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idTransaction', type: 'integer')]
    private ?int $idtransaction = null;

    #[ORM\Column(name: 'categorie', type: 'string', length: 50)]
    private string $categorie;

    #[ORM\Column(name: 'dateTransaction', type: 'string', length: 20)]
    private string $datetransaction;

    #[ORM\Column(name: 'montant', type: 'decimal', precision: 12, scale: 3, nullable: true)]
    private ?string $montant = null;

    #[ORM\Column(name: 'typeTransaction', type: 'string', length: 30)]
    private string $typetransaction;

    #[ORM\Column(name: 'soldeApres', type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $soldeapres = null;

    #[ORM\Column(name: 'description', type: 'string', length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'montantPaye', type: 'decimal', precision: 12, scale: 3, nullable: true)]
    private ?string $montantpaye = null;

    #[ORM\ManyToOne(targetEntity: Compte::class, inversedBy: 'transactionses')]
    #[ORM\JoinColumn(name: 'idCompte', referencedColumnName: 'idCompte', nullable: true)]
    private ?Compte $compte = null;

    #[ORM\Column(name: 'idCompteDestinataire', type: 'string', length: 255, nullable: true)]
    private ?string $compteDestinataire = null;

    #[ORM\Column(name: 'nomDestinataire', type: 'string', length: 255, nullable: true)]
    private ?string $nomDestinataire = null;

    #[ORM\Column(name: 'emailDestinataire', type: 'string', length: 255, nullable: true)]
    private ?string $emailDestinataire = null;

    #[ORM\Column(name: 'original_amount', type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $originalAmount = null;

    #[ORM\Column(name: 'original_currency', type: 'string', length: 3, nullable: true)]
    private ?string $originalCurrency = null;

    #[ORM\Column(name: 'exchange_rate', type: 'decimal', precision: 10, scale: 6, nullable: true)]
    private ?string $exchangeRate = null;

    #[ORM\Column(name: 'conversion_fee', type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $conversionFee = null;

    #[ORM\ManyToOne(targetEntity: Users::class, inversedBy: 'transactionses')]
    #[ORM\JoinColumn(name: 'idUser', referencedColumnName: 'idUser', nullable: true)]
    private ?Users $users = null;

    #[ORM\OneToMany(mappedBy: 'transactions', targetEntity: Cashback::class)]
    private Collection $cashbacks;

    #[ORM\OneToMany(mappedBy: 'transactions', targetEntity: Reclamation::class)]
    private Collection $reclamations;

    public function __construct()
    {
        $this->cashbacks    = new ArrayCollection();
        $this->reclamations = new ArrayCollection();
    }

    public function getCashbacks(): Collection    { return $this->cashbacks; }
    public function getReclamations(): Collection { return $this->reclamations; }

    public function getIdtransaction(): ?int { return $this->idtransaction; }

    public function getCategorie(): string { return $this->categorie; }
    public function setCategorie(string $categorie): static { $this->categorie = $categorie; return $this; }

    public function getDatetransaction(): string { return $this->datetransaction; }
    public function setDatetransaction(string $datetransaction): static { $this->datetransaction = $datetransaction; return $this; }

    public function getMontant(): ?string { return $this->montant; }
    public function setMontant(?string $montant): static { $this->montant = $montant; return $this; }

    public function getTypetransaction(): string { return $this->typetransaction; }
    public function setTypetransaction(string $typetransaction): static { $this->typetransaction = $typetransaction; return $this; }

    public function getSoldeapres(): ?string { return $this->soldeapres; }
    public function setSoldeapres(?string $soldeapres): static { $this->soldeapres = $soldeapres; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getMontantpaye(): ?string { return $this->montantpaye; }
    public function setMontantpaye(?string $montantpaye): static { $this->montantpaye = $montantpaye; return $this; }

    public function getCompte(): ?Compte { return $this->compte; }
    public function setCompte(?Compte $compte): static { $this->compte = $compte; return $this; }

    public function getCompteDestinataire(): ?string { return $this->compteDestinataire; }
    public function setCompteDestinataire(?string $compteDestinataire): static { $this->compteDestinataire = $compteDestinataire; return $this; }

    public function getNomDestinataire(): ?string { return $this->nomDestinataire; }
    public function setNomDestinataire(?string $nomDestinataire): static { $this->nomDestinataire = $nomDestinataire; return $this; }

    public function getEmailDestinataire(): ?string { return $this->emailDestinataire; }
    public function setEmailDestinataire(?string $emailDestinataire): static { $this->emailDestinataire = $emailDestinataire; return $this; }

    public function getOriginalAmount(): ?string { return $this->originalAmount; }
    public function setOriginalAmount(?string $originalAmount): static { $this->originalAmount = $originalAmount; return $this; }

    public function getOriginalCurrency(): ?string { return $this->originalCurrency; }
    public function setOriginalCurrency(?string $originalCurrency): static { $this->originalCurrency = $originalCurrency; return $this; }

    public function getExchangeRate(): ?string { return $this->exchangeRate; }
    public function setExchangeRate(?string $exchangeRate): static { $this->exchangeRate = $exchangeRate; return $this; }

    public function getConversionFee(): ?string { return $this->conversionFee; }
    public function setConversionFee(?string $conversionFee): static { $this->conversionFee = $conversionFee; return $this; }

    public function getUsers(): ?Users { return $this->users; }
    public function setUsers(?Users $users): static { $this->users = $users; return $this; }
}
