<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\GarantiecreditRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GarantiecreditRepository::class)]
#[ORM\Table(name: 'garantiecredit')]
class Garantiecredit
{
    /**
     * @var array<string, string>
     */
    public const TYPE_LABELS = [
        'titre_vehicule' => 'Titre véhicule',
        'hypotheque' => 'Hypothèque',
        'caution_bancaire' => 'Caution bancaire',
        'depot_garantie' => 'Dépôt de garantie',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idGarantie', type: 'integer')]
    private ?int $idgarantie = null;

    #[ORM\Column(name: 'typeGarantie', type: 'string', length: 50)]
    private string $typegarantie;

    #[ORM\Column(name: 'description', type: 'string', length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'adresseBien', type: 'string', length: 255, nullable: true)]
    private ?string $adressebien = null;

    #[ORM\Column(name: 'adresseComplete', type: 'string', length: 255, nullable: true)]
    private ?string $adressecomplete = null;

    #[ORM\Column(name: 'ville', type: 'string', length: 120, nullable: true)]
    private ?string $ville = null;

    #[ORM\Column(name: 'codePostal', type: 'string', length: 30, nullable: true)]
    private ?string $codepostal = null;

    #[ORM\Column(name: 'pays', type: 'string', length: 120, nullable: true)]
    private ?string $pays = null;

    #[ORM\Column(name: 'latitude', type: 'float', nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(name: 'longitude', type: 'float', nullable: true)]
    private ?float $longitude = null;

    #[ORM\Column(name: 'statutVerificationAdresse', type: 'string', length: 30, options: ['default' => 'A verifier'])]
    private string $statutverificationadresse = 'A verifier';

    #[ORM\Column(name: 'valeurEstimee', type: 'float', precision: 22)]
    private float $valeurestimee;

    #[ORM\Column(name: 'valeurRetenue', type: 'float', precision: 22)]
    private float $valeurretenue;

    #[ORM\Column(name: 'documentJustificatif', type: 'string', length: 255, nullable: true)]
    private ?string $documentjustificatif = null;

    #[ORM\Column(name: 'documentUrl', type: 'string', length: 255, nullable: true)]
    private ?string $documenturl = null;

    #[ORM\Column(name: 'documentPublicId', type: 'string', length: 255, nullable: true)]
    private ?string $documentpublicid = null;

    #[ORM\Column(name: 'documentMimeType', type: 'string', length: 120, nullable: true)]
    private ?string $documentmimetype = null;

    #[ORM\Column(name: 'documentUploadedAt', type: 'string', length: 30, nullable: true)]
    private ?string $documentuploadedat = null;

    #[ORM\Column(name: 'statutVerificationDocument', type: 'string', length: 30, options: ['default' => 'en_attente'])]
    private string $statutverificationdocument = 'en_attente';

    #[ORM\Column(name: 'statutDocument', type: 'string', length: 20, options: ['default' => 'en_attente'])]
    private string $statutdocument = 'en_attente';

    #[ORM\Column(name: 'remarqueAdmin', type: 'string', length: 500, nullable: true)]
    private ?string $remarqueadmin = null;

    #[ORM\Column(name: 'dateEvaluation', type: 'string', length: 20)]
    private string $dateevaluation;

    #[ORM\Column(name: 'nomGarant', type: 'string', length: 100, nullable: true)]
    private ?string $nomgarant = null;

    #[ORM\Column(name: 'statut', type: 'string', length: 30)]
    private string $statut;

    #[ORM\Column(name: 'idUser', type: 'integer')]
    private int $iduser;

    #[ORM\ManyToOne(targetEntity: Credit::class)]
    #[ORM\JoinColumn(name: 'idCredit', referencedColumnName: 'idCredit', nullable: true)]
    private ?Credit $credit = null;

    public function getIdGarantie(): ?int
    {
        return $this->idgarantie;
    }

    public function getTypeGarantie(): string
    {
        return $this->typegarantie;
    }

    public function setTypeGarantie(string $typeGarantie): self
    {
        $normalized = self::normalizeTypeValue($typeGarantie);
        $this->typegarantie = $normalized ?? trim($typeGarantie);

        return $this;
    }

    public function getTypeGarantieLabel(): string
    {
        return self::typeLabel($this->typegarantie);
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

    public static function typeLabel(?string $value): string
    {
        $normalized = self::normalizeTypeValue($value ?? '');
        if ($normalized !== null) {
            return self::TYPE_LABELS[$normalized];
        }

        $fallback = trim((string) $value);

        return $fallback !== '' ? $fallback : 'Garantie';
    }

    public static function normalizeTypeValue(string $value): ?string
    {
        $raw = trim($value);
        if ($raw === '') {
            return null;
        }

        $normalized = self::normalizeLookup($raw);

        return match ($normalized) {
            'titre_vehicule', 'titre vehicule', 'titrevehicule', 'gage de vehicule', 'gage vehicule' => 'titre_vehicule',
            'hypotheque', 'hypotheque immobiliere', 'hypothequeimmobiliere' => 'hypotheque',
            'caution_bancaire', 'caution bancaire', 'garantie bancaire', 'caution personnelle', 'caution solidaire' => 'caution_bancaire',
            'depot_garantie', 'depot garantie', 'depot de garantie', 'depot au comptant' => 'depot_garantie',
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
}
