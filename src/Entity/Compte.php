<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CompteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CompteRepository::class)]
#[ORM\Table(name: 'compte')]
class Compte
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idCompte', type: 'integer')]
    private ?int $idcompte = null;

    #[ORM\Column(name: 'numeroCompte', type: 'string', length: 30)]
    private string $numerocompte = '';

    #[ORM\Column(name: 'solde', type: 'decimal', precision: 12, scale: 2)]
    private string $solde = '0.00';

    #[ORM\Column(name: 'dateOuverture', type: 'string', length: 10)]
    private string $dateouverture = '';

    #[ORM\Column(name: 'statutCompte', type: 'string', length: 20)]
    private string $statutcompte = 'Actif';

    #[ORM\Column(name: 'plafondRetrait', type: 'decimal', precision: 12, scale: 2)]
    private string $plafondretrait = '0.00';

    #[ORM\Column(name: 'plafondVirement', type: 'decimal', precision: 12, scale: 2)]
    private string $plafondvirement = '0.00';

    #[ORM\Column(name: 'typeCompte', type: 'string', length: 20)]
    private string $typecompte = 'Courant';

    #[ORM\ManyToOne(targetEntity: Users::class, inversedBy: 'comptes')]
    #[ORM\JoinColumn(name: 'idUser', referencedColumnName: 'idUser', nullable: true)]
    private ?Users $users = null;

    #[ORM\OneToMany(mappedBy: 'compte', targetEntity: Coffrevirtuel::class)]
    private Collection $coffrevirtuels;

    #[ORM\OneToMany(mappedBy: 'compte', targetEntity: Credit::class)]
    private Collection $credits;

    #[ORM\OneToMany(mappedBy: 'compte', targetEntity: Transactions::class)]
    private Collection $transactionses;

    public function __construct()
    {
        $this->coffrevirtuels = new ArrayCollection();
        $this->credits = new ArrayCollection();
        $this->transactionses = new ArrayCollection();
    }

    public function getIdCompte(): ?int
    {
        return $this->idcompte;
    }

    public function getNumeroCompte(): string
    {
        return $this->numerocompte;
    }

    public function setNumeroCompte(string $numeroCompte): self
    {
        $this->numerocompte = $numeroCompte;

        return $this;
    }

    public function getSolde(): string
    {
        return $this->solde;
    }

    public function setSolde(string $solde): self
    {
        $this->solde = $solde;

        return $this;
    }

    public function getDateOuverture(): string
    {
        return $this->dateouverture;
    }

    public function setDateOuverture(string $dateOuverture): self
    {
        $this->dateouverture = $dateOuverture;

        return $this;
    }

    public function getStatutCompte(): string
    {
        return $this->statutcompte;
    }

    public function setStatutCompte(string $statutCompte): self
    {
        $this->statutcompte = $statutCompte;

        return $this;
    }

    public function getPlafondRetrait(): string
    {
        return $this->plafondretrait;
    }

    public function setPlafondRetrait(string $plafondRetrait): self
    {
        $this->plafondretrait = $plafondRetrait;

        return $this;
    }

    public function getPlafondVirement(): string
    {
        return $this->plafondvirement;
    }

    public function setPlafondVirement(string $plafondVirement): self
    {
        $this->plafondvirement = $plafondVirement;

        return $this;
    }

    public function getTypeCompte(): string
    {
        return $this->typecompte;
    }

    public function setTypeCompte(string $typeCompte): self
    {
        $this->typecompte = $typeCompte;

        return $this;
    }

    public function getUsers(): ?Users
    {
        return $this->users;
    }

    public function setUsers(?Users $users): self
    {
        $this->users = $users;

        return $this;
    }

    /**
     * @return Collection<int, Coffrevirtuel>
     */
    public function getCoffrevirtuels(): Collection
    {
        return $this->coffrevirtuels;
    }

    /**
     * @return Collection<int, Credit>
     */
    public function getCredits(): Collection
    {
        return $this->credits;
    }

    /**
     * @return Collection<int, Transactions>
     */
    public function getTransactionses(): Collection
    {
        return $this->transactionses;
    }

    public function __toString(): string
    {
        return $this->numerocompte !== '' ? $this->numerocompte : 'Compte';
    }
}
