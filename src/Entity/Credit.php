<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'credit')]
class Credit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idCredit', type: 'integer')]
    private ?int $idCredit = null;

    #[ORM\Column(name: 'idCompte', type: 'integer', nullable: true)]
    private ?int $idCompte = null;

    #[ORM\Column(name: 'idUser', type: 'integer', nullable: true)]
    private ?int $idUser = null;

    #[ORM\Column(name: 'typeCredit', type: 'string', length: 100, nullable: true)]
    private ?string $typeCredit = null;

    #[ORM\Column(name: 'montantDemande', type: 'float', nullable: true)]
    private ?float $montantDemande = null;

    #[ORM\Column(name: 'montantAccorde', type: 'float', nullable: true)]
    private ?float $montantAccorde = null;

    #[ORM\Column(name: 'autofinancement', type: 'float', nullable: true)]
    private ?float $autofinancement = null;

    #[ORM\Column(name: 'duree', type: 'integer', nullable: true)]
    private ?int $duree = null;

    #[ORM\Column(name: 'tauxInteret', type: 'float', nullable: true)]
    private ?float $tauxInteret = null;

    #[ORM\Column(name: 'mensualite', type: 'float', nullable: true)]
    private ?float $mensualite = null;

    #[ORM\Column(name: 'dateDemande', type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateDemande = null;

    #[ORM\Column(name: 'statut', type: 'string', length: 50, nullable: true)]
    private ?string $statut = null;

    #[ORM\Column(name: 'salaire', type: 'float', nullable: true)]
    private ?float $salaire = null;

    #[ORM\Column(name: 'typeContrat', type: 'string', length: 100, nullable: true)]
    private ?string $typeContrat = null;

    #[ORM\Column(name: 'ancienneteAnnees', type: 'integer', nullable: true)]
    private ?int $ancienneteAnnees = null;

    /**
     * Champ virtuel calculé : somme des valeurs retenues des garanties associées.
     * Renseigné manuellement via setTotalGaranties() avant scoring.
     */
    private float $totalGaranties = 0.0;

    // ─────────────────────────────────────────────────────────────────────────
    // Getters / Setters
    // ─────────────────────────────────────────────────────────────────────────

    public function getIdCredit(): ?int { return $this->idCredit; }

    public function getIdCompte(): ?int { return $this->idCompte; }
    public function setIdCompte(?int $idCompte): static { $this->idCompte = $idCompte; return $this; }

    public function getIdUser(): ?int { return $this->idUser; }
    public function setIdUser(?int $idUser): static { $this->idUser = $idUser; return $this; }

    public function getTypeCredit(): ?string { return $this->typeCredit; }
    public function setTypeCredit(?string $typeCredit): static { $this->typeCredit = $typeCredit; return $this; }

    public function getMontantDemande(): ?float { return $this->montantDemande; }
    public function setMontantDemande(?float $montantDemande): static { $this->montantDemande = $montantDemande; return $this; }

    public function getMontantAccorde(): ?float { return $this->montantAccorde; }
    public function setMontantAccorde(?float $montantAccorde): static { $this->montantAccorde = $montantAccorde; return $this; }

    public function getAutofinancement(): ?float { return $this->autofinancement; }
    public function setAutofinancement(?float $autofinancement): static { $this->autofinancement = $autofinancement; return $this; }

    public function getDuree(): ?int { return $this->duree; }
    public function setDuree(?int $duree): static { $this->duree = $duree; return $this; }

    public function getTauxInteret(): ?float { return $this->tauxInteret; }
    public function setTauxInteret(?float $tauxInteret): static { $this->tauxInteret = $tauxInteret; return $this; }

    public function getMensualite(): ?float { return $this->mensualite; }
    public function setMensualite(?float $mensualite): static { $this->mensualite = $mensualite; return $this; }

    public function getDateDemande(): ?\DateTimeInterface { return $this->dateDemande; }
    public function setDateDemande(?\DateTimeInterface $dateDemande): static { $this->dateDemande = $dateDemande; return $this; }

    public function getStatut(): ?string { return $this->statut; }
    public function setStatut(?string $statut): static { $this->statut = $statut; return $this; }

    public function getSalaire(): ?float { return $this->salaire; }
    public function setSalaire(?float $salaire): static { $this->salaire = $salaire; return $this; }

    public function getTypeContrat(): ?string { return $this->typeContrat; }
    public function setTypeContrat(?string $typeContrat): static { $this->typeContrat = $typeContrat; return $this; }

    public function getAncienneteAnnees(): ?int { return $this->ancienneteAnnees; }
    public function setAncienneteAnnees(?int $ancienneteAnnees): static { $this->ancienneteAnnees = $ancienneteAnnees; return $this; }

    public function getTotalGaranties(): float { return $this->totalGaranties; }
    public function setTotalGaranties(float $totalGaranties): static { $this->totalGaranties = $totalGaranties; return $this; }
}