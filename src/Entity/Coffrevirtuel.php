<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CoffrevirtuelRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CoffrevirtuelRepository::class)]
#[ORM\Table(name: 'coffrevirtuel')]
class Coffrevirtuel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idCoffre', type: 'integer')]
    /** @phpstan-ignore-next-line */
    private ?int $idcoffre = null;

    public function getIdCoffre(): ?int
    {
        return $this->idcoffre;
    }

    #[ORM\Column(name: 'nom', type: 'string', length: 50)]
    private string $nom;

    #[ORM\Column(name: 'objectifMontant', type: 'decimal', precision: 12, scale: 2)]
    private string $objectifmontant;

    #[ORM\Column(name: 'montantActuel', type: 'decimal', precision: 12, scale: 2)]
    private string $montantactuel;

    #[ORM\Column(name: 'dateCreation', type: 'string', length: 20)]
    private string $datecreation;

    #[ORM\Column(name: 'dateObjectifs', type: 'string', length: 20, nullable: true)]
    private ?string $dateobjectifs = null;

    #[ORM\Column(name: 'status', type: 'string', length: 20)]
    private string $status;

    #[ORM\Column(name: 'estVerrouille', type: 'boolean')]
    private bool $estverrouille;

    #[ORM\ManyToOne(targetEntity: Compte::class, inversedBy: 'coffrevirtuels')]
    #[ORM\JoinColumn(name: 'idCompte', referencedColumnName: 'idCompte', nullable: true)]
    private ?Compte $compte = null;

    #[ORM\ManyToOne(targetEntity: Users::class, inversedBy: 'coffrevirtuels')]
    #[ORM\JoinColumn(name: 'idUser', referencedColumnName: 'idUser', nullable: true)]
    private ?Users $users = null;

    // ── Getters / Setters ────────────────────────────────────────────────────

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    public function getObjectifMontant(): string
    {
        return $this->objectifmontant;
    }

    public function setObjectifMontant(string $objectifMontant): self
    {
        $this->objectifmontant = $objectifMontant;
        return $this;
    }

    public function getMontantActuel(): string
    {
        return $this->montantactuel;
    }

    public function setMontantActuel(string $montantActuel): self
    {
        $this->montantactuel = $montantActuel;
        return $this;
    }

    public function getDateCreation(): string
    {
        return $this->datecreation;
    }

    public function setDateCreation(string $dateCreation): self
    {
        $this->datecreation = $dateCreation;
        return $this;
    }

    public function getDateObjectifs(): ?string
    {
        return $this->dateobjectifs;
    }

    public function setDateObjectifs(?string $dateObjectifs): self
    {
        $this->dateobjectifs = $dateObjectifs;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function isEstVerrouille(): bool
    {
        return $this->estverrouille;
    }

    public function setEstVerrouille(bool $estVerrouille): self
    {
        $this->estverrouille = $estVerrouille;
        return $this;
    }

    public function getCompte(): ?Compte
    {
        return $this->compte;
    }

    public function setCompte(?Compte $compte): self
    {
        $this->compte = $compte;
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

}
