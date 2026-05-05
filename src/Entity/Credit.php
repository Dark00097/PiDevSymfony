<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'credit')]
class Credit
{
    /**
     * @var array<string, string>
     */
    public const TYPE_LABELS = [
        'Professionnel' => 'Professionnel',
        'Immobilier' => 'Immobilier',
        'Auto' => 'Auto',
        'Consommation' => 'Consommation',
        'Etudes' => 'Etudes',
        'Travaux' => 'Travaux',
        'Personnel' => 'Personnel',
        'Hypotheque' => 'Hypotheque',
        'Pret auto' => 'Pret auto',
        'Education' => 'Education',
        'Sante' => 'Sante',
        'Autre' => 'Autre',
    ];

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
    public function setTypeCredit(?string $typeCredit): static
    {
        if ($typeCredit === null) {
            $this->typeCredit = null;

            return $this;
        }

        $normalized = self::normalizeTypeCreditValue($typeCredit);
        $this->typeCredit = $normalized ?? trim($typeCredit);

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public static function getTypeChoices(): array
    {
        return self::TYPE_LABELS;
    }

    /**
     * @return array<int, string>
     */
    public static function getAllowedTypeValues(): array
    {
        return array_keys(self::TYPE_LABELS);
    }

    public static function getTypeLabel(?string $value): string
    {
        $normalized = self::normalizeTypeCreditValue((string) $value);
        if ($normalized !== null) {
            return self::TYPE_LABELS[$normalized];
        }

        $fallback = trim((string) $value);

        return $fallback !== '' ? $fallback : 'Credit';
    }

    public static function normalizeTypeCreditValue(string $value): ?string
    {
        $raw = trim($value);
        if ($raw === '') {
            return null;
        }

        $normalized = self::normalizeLookup($raw);

        return match ($normalized) {
            'professionnel' => 'Professionnel',
            'immobilier' => 'Immobilier',
            'auto', 'automobile', 'credit auto' => 'Auto',
            'consommation', 'renouvelable' => 'Consommation',
            'etudes', 'etudiant', 'etude' => 'Etudes',
            'travaux' => 'Travaux',
            'personnel' => 'Personnel',
            'hypotheque' => 'Hypotheque',
            'pret auto', 'pretauto' => 'Pret auto',
            'education' => 'Education',
            'sante' => 'Sante',
            'autre' => 'Autre',
            default => null,
        };
    }

    private static function normalizeLookup(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = strtr($value, [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
            'ç' => 'c',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ñ' => 'n',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ý' => 'y', 'ÿ' => 'y',
            'œ' => 'oe', 'æ' => 'ae',
            '’' => "'",
        ]);
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($transliterated) && $transliterated !== '') {
            $value = mb_strtolower($transliterated, 'UTF-8');
        }

        $value = str_replace("'", '', $value);
        $value = preg_replace('/[^a-z0-9_]+/', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

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
