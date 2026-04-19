<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ReclamationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ReclamationRepository::class)]
#[ORM\Table(name: 'reclamation')]
class Reclamation
{
    private const RECLAMATION_TYPES = [
        'Echec de montant',
        'Virement non recu',
        'Erreur de transaction',
        'Probleme de connexion au compte',
    ];

    private const RECLAMATION_STATUSES = [
        'Valide',
        'Ferme',
        'Attende',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idReclamation', type: 'integer')]
    private ?int $idreclamation = null;

    #[ORM\Column(name: 'dateReclamation', type: 'date')]
    #[Assert\NotNull(message: 'La date de reclamation est obligatoire.')]
    #[Assert\LessThanOrEqual('today', message: 'La date ne doit pas etre dans le futur.')]
    private \DateTimeInterface $datereclamation;

    #[ORM\Column(name: 'typeReclamation', type: 'string', length: 50)]
    #[Assert\NotBlank(message: 'Veuillez selectionner un type de reclamation.')]
    #[Assert\Choice(choices: self::RECLAMATION_TYPES, message: 'Le type de reclamation selectionne est invalide.')]
    private string $typereclamation;

    #[ORM\Column(name: 'description', type: 'string', length: 150)]
    #[Assert\NotBlank(message: 'La description est obligatoire.')]
    #[Assert\Length(
        min: 10,
        max: 150,
        minMessage: 'La description doit contenir au moins 10 caracteres.',
        maxMessage: 'La description ne doit pas depasser 150 caracteres.'
    )]
    private string $description;

    #[ORM\Column(name: 'status', type: 'string', length: 30)]
    #[Assert\NotBlank(message: 'Veuillez selectionner un status.')]
    #[Assert\Choice(choices: self::RECLAMATION_STATUSES, message: 'Le status selectionne est invalide.')]
    private string $status;

    #[ORM\Column(name: 'is_inappropriate', type: 'boolean')]
    private bool $isInappropriate;

    #[ORM\Column(name: 'is_blurred', type: 'boolean')]
    private bool $isBlurred;

    #[ORM\Column(name: 'sentiment', type: 'string', length: 20)]
    private string $sentiment = 'neutral';

    #[ORM\ManyToOne(targetEntity: Users::class)]
    #[ORM\JoinColumn(name: 'idUser', referencedColumnName: 'idUser', nullable: true)]
    private ?Users $users = null;

    #[ORM\ManyToOne(targetEntity: Transactions::class)]
    #[ORM\JoinColumn(name: 'idTransaction', referencedColumnName: 'idTransaction', nullable: true)]
    #[Assert\NotNull(message: 'Veuillez selectionner une transaction.')]
    private ?Transactions $transactions = null;

    public function getIdreclamation(): ?int
    {
        return $this->idreclamation;
    }

    public function getDatereclamation(): ?\DateTimeInterface
    {
        return $this->datereclamation;
    }

    public function setDatereclamation(\DateTimeInterface $datereclamation): self
    {
        $this->datereclamation = $datereclamation;
        return $this;
    }

    public function getTypereclamation(): ?string
    {
        return $this->typereclamation;
    }

    public function setTypereclamation(string $typereclamation): self
    {
        $this->typereclamation = $typereclamation;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function isInappropriate(): bool
    {
        return $this->isInappropriate;
    }

    public function setIsInappropriate(bool $isInappropriate): self
    {
        $this->isInappropriate = $isInappropriate;
        return $this;
    }

    public function isBlurred(): bool
    {
        return $this->isBlurred;
    }

    public function setIsBlurred(bool $isBlurred): self
    {
        $this->isBlurred = $isBlurred;
        return $this;
    }

    public function getSentiment(): string
    {
        return $this->sentiment;
    }

    public function setSentiment(string $sentiment): self
    {
        $this->sentiment = $sentiment;
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

    public function getTransactions(): ?Transactions
    {
        return $this->transactions;
    }

    public function setTransactions(?Transactions $transactions): self
    {
        $this->transactions = $transactions;
        return $this;
    }

    public static function getReclamationTypes(): array
    {
        return self::RECLAMATION_TYPES;
    }

    public static function getReclamationStatuses(): array
    {
        return self::RECLAMATION_STATUSES;
    }
}
