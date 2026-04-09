<?php
// CreditType.php - COMPLET

namespace App\Form;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\PositiveOrZero;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;

final class CreditType extends BaseCrudFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addFields($builder, [
            'idCredit' => [
                'type' => IntegerType::class,
                'disabled' => true,
            ],
            'typeCredit' => [
                'type' => TextType::class,
                'constraints' => [
                    new NotBlank(message: 'Le type de crédit est obligatoire.'),
                    new Length(
                        min: 2, max: 100,
                        minMessage: 'Le type doit contenir au moins {{ limit }} caractères.',
                        maxMessage: 'Le type ne peut pas dépasser {{ limit }} caractères.'
                    ),
                ],
            ],
            'montantDemande' => [
                'type' => NumberType::class,
                'attr' => [
                    'min'        => 500,
                    'max'        => 10000000,
                    'step'       => '0.01',
                    'onkeypress' => 'return /[0-9.]/.test(event.key)',
                    'inputmode'  => 'decimal',
                ],
                'constraints' => [
                    new NotBlank(message: 'Le montant demandé est obligatoire.'),
                    new Positive(message: 'Le montant demandé doit être un nombre positif.'),
                    new Range(
                        min: 500, max: 10_000_000,
                        notInRangeMessage: 'Le montant doit être compris entre {{ min }} et {{ max }} DT.'
                    ),
                ],
            ],
            'autofinancement' => [
                'type' => NumberType::class,
                'attr' => [
                    'min'        => 0,
                    'step'       => '0.01',
                    'onkeypress' => 'return /[0-9.]/.test(event.key)',
                    'inputmode'  => 'decimal',
                ],
                'constraints' => [
                    new NotBlank(message: "L'autofinancement est obligatoire."),
                    new PositiveOrZero(message: "L'autofinancement doit être positif ou nul."),
                ],
            ],
            'duree' => [
                'type' => IntegerType::class,
                'attr' => [
                    'min'        => 1,
                    'max'        => 360,
                    'onkeypress' => 'return /[0-9]/.test(event.key)',
                    'inputmode'  => 'numeric',
                ],
                'constraints' => [
                    new NotBlank(message: 'La durée est obligatoire.'),
                    new Range(
                        min: 1, max: 360,
                        notInRangeMessage: 'La durée doit être comprise entre {{ min }} et {{ max }} mois.'
                    ),
                ],
            ],
            'tauxInteret' => [
                'type' => NumberType::class,
                'attr' => [
                    'min'        => 0,
                    'max'        => 100,
                    'step'       => '0.01',
                    'onkeypress' => 'return /[0-9.]/.test(event.key)',
                    'inputmode'  => 'decimal',
                ],
                'constraints' => [
                    new NotBlank(message: "Le taux d'intérêt est obligatoire."),
                    new Range(
                        min: 0, max: 100,
                        notInRangeMessage: "Le taux d'intérêt doit être compris entre {{ min }}% et {{ max }}%."
                    ),
                ],
            ],
            'mensualite' => [
                'type' => NumberType::class,
                'attr' => [
                    'min'        => 0,
                    'step'       => '0.01',
                    'onkeypress' => 'return /[0-9.]/.test(event.key)',
                    'inputmode'  => 'decimal',
                ],
                'constraints' => [
                    new NotBlank(message: 'La mensualité est obligatoire.'),
                    new Positive(message: 'La mensualité doit être un nombre positif.'),
                ],
            ],
            'montantAccorde' => [
                'type' => NumberType::class,
                'attr' => [
                    'min'        => 0,
                    'step'       => '0.01',
                    'onkeypress' => 'return /[0-9.]/.test(event.key)',
                    'inputmode'  => 'decimal',
                ],
                'constraints' => [
                    new PositiveOrZero(message: 'Le montant accordé doit être positif ou nul.'),
                ],
            ],
            'dateDemande' => [
                'type' => TextType::class,
                'constraints' => [
                    new NotBlank(message: 'La date de demande est obligatoire.'),
                    new Regex(
                        pattern: '/^\d{4}-\d{2}-\d{2}$/',
                        message: 'La date doit être au format AAAA-MM-JJ.'
                    ),
                ],
            ],
            // ❌ 'statut' supprimé ici
            'statut' => [
                'type' => ChoiceType::class,
                'choices' => [
                    'En attente' => 'En attente',
                    'Accepte' => 'Accepte',
                    'En cours' => 'En cours',
                    'Rejete' => 'Rejete',
                    'Cloture' => 'Cloture',
                ],
            ],
            'idUser' => [
                'type' => IntegerType::class,
                'attr' => [
                    'min'        => 1,
                    'onkeypress' => 'return /[0-9]/.test(event.key)',
                    'inputmode'  => 'numeric',
                ],
                'constraints' => [
                    new NotBlank(message: "L'identifiant utilisateur est obligatoire."),
                    new Positive(message: "L'identifiant utilisateur doit être un entier positif."),
                ],
            ],
            'salaire' => [
                'type' => NumberType::class,
                'attr' => [
                    'min'        => 0,
                    'max'        => 1000000,
                    'step'       => '0.01',
                    'onkeypress' => 'return /[0-9.]/.test(event.key)',
                    'inputmode'  => 'decimal',
                ],
                'constraints' => [
                    new NotBlank(message: 'Le salaire est obligatoire.'),
                    new Positive(message: 'Le salaire doit être un nombre positif.'),
                    new Range(
                        min: 0, max: 1_000_000,
                        notInRangeMessage: 'Le salaire doit être compris entre {{ min }} et {{ max }} DT.'
                    ),
                ],
            ],
            'typeContrat' => [
                'type' => TextType::class,
                'constraints' => [
                    new NotBlank(message: 'Le type de contrat est obligatoire.'),
                    new Length(
                        max: 100,
                        maxMessage: 'Le type de contrat ne peut pas dépasser {{ limit }} caractères.'
                    ),
                ],
            ],
            'ancienneteAnnees' => [
                'type' => IntegerType::class,
                'attr' => [
                    'min'        => 0,
                    'max'        => 60,
                    'onkeypress' => 'return /[0-9]/.test(event.key)',
                    'inputmode'  => 'numeric',
                ],
                'constraints' => [
                    new NotBlank(message: "L'ancienneté est obligatoire."),
                    new Range(
                        min: 0, max: 60,
                        notInRangeMessage: "L'ancienneté doit être comprise entre {{ min }} et {{ max }} ans."
                    ),
                ],
            ],
            'idCompte' => [
                'type' => IntegerType::class,
                'attr' => [
                    'min'        => 1,
                    'onkeypress' => 'return /[0-9]/.test(event.key)',
                    'inputmode'  => 'numeric',
                ],
                'constraints' => [
                    new NotBlank(message: 'Le compte associé est obligatoire.'),
                    new Positive(message: "L'identifiant du compte doit être un entier positif."),
                ],
            ],
        ]);
    }
}
