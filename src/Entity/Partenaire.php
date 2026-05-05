<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PartenaireRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: PartenaireRepository::class)]
#[ORM\Table(name: 'partenaire')]
class Partenaire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idPartenaire', type: 'integer')]
    private ?int $idpartenaire = null;

    #[ORM\Column(name: 'nom', type: 'string', length: 100)]
    private string $nom;

    #[ORM\Column(name: 'categorie', type: 'string', length: 50)]
    private string $categorie;

    #[ORM\Column(name: 'description', type: 'string', length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'ville', type: 'string', length: 120, nullable: true)]
    private ?string $ville = null;

    #[ORM\Column(name: 'tauxCashback', type: 'decimal', precision: 5, scale: 2)]
    private string $tauxcashback;

    #[ORM\Column(name: 'tauxCashbackMax', type: 'decimal', precision: 5, scale: 2)]
    private string $tauxcashbackmax;

    #[ORM\Column(name: 'plafondMensuel', type: 'decimal', precision: 12, scale: 2)]
    private string $plafondmensuel;

    #[ORM\Column(name: 'conditions', type: 'string', length: 255, nullable: true)]
    private ?string $conditions = null;

    #[ORM\Column(name: 'status', type: 'string', length: 30)]
    private string $status;

    #[ORM\Column(name: 'rating', type: 'float', precision: 22)]
    private float $rating;

    #[ORM\OneToMany(mappedBy: 'partenaire', targetEntity: Cashback::class)]
    private Collection $cashbacks;

    #[ORM\OneToMany(mappedBy: 'partenaire', targetEntity: CashbackEntries::class)]
    private Collection $cashbackEntrieses;

    public function __construct()
    {
        $this->cashbacks         = new ArrayCollection();
        $this->cashbackEntrieses = new ArrayCollection();
    }

    public function getCashbacks(): Collection
    {
        return $this->cashbacks;
    }

    public function getCashbackEntrieses(): Collection
    {
        return $this->cashbackEntrieses;
    }

}
