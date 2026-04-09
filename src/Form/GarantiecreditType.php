<?php
// GarantiecreditType.php

namespace App\Form;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\PositiveOrZero;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;

final class GarantiecreditType extends BaseCrudFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addFields($builder, [
            'idGarantie' => [
                'type' => IntegerType::class,
                'disabled' => true,
            ],
            'typeGarantie' => [
                'type' => TextType::class,
                'constraints' => [
                    new NotBlank(message: 'Le type de garantie est obligatoire.'),
                    new Length(
                        min: 2,
                        max: 100,
                        minMessage: 'Le type doit contenir au moins {{ limit }} caractÃ¨res.',
                        maxMessage: 'Le type ne peut pas dÃ©passer {{ limit }} caractÃ¨res.'
                    ),
                ],
            ],
            'description' => [
                'type' => TextareaType::class,
                'constraints' => [
                    new NotBlank(message: 'La description est obligatoire.'),
                    new Length(
                        min: 10,
                        max: 1000,
                        minMessage: 'La description doit contenir au moins {{ limit }} caractÃ¨res.',
                        maxMessage: 'La description ne peut pas dÃ©passer {{ limit }} caractÃ¨res.'
                    ),
                ],
            ],
            'adresseBien' => [
                'type' => TextType::class,
                'constraints' => [
                    new NotBlank(message: "L'adresse du bien est obligatoire."),
                    new Length(
                        min: 5,
                        max: 255,
                        minMessage: "L'adresse doit contenir au moins {{ limit }} caractÃ¨res.",
                        maxMessage: "L'adresse ne peut pas dÃ©passer {{ limit }} caractÃ¨res."
                    ),
                ],
            ],
            'valeurEstimee' => [
                'type' => NumberType::class,
                'attr' => [
                    'min' => 1000,
                    'max' => 100000000,
                    'step' => '0.01',
                    'onkeypress' => 'return /[0-9.]/.test(event.key)',
                    'inputmode' => 'decimal',
                ],
                'constraints' => [
                    new NotBlank(message: 'La valeur estimÃ©e est obligatoire.'),
                    new Positive(message: 'La valeur estimÃ©e doit Ãªtre un nombre positif.'),
                    new Range(
                        min: 1000,
                        max: 100_000_000,
                        notInRangeMessage: 'La valeur estimÃ©e doit Ãªtre comprise entre {{ min }} et {{ max }} DT.'
                    ),
                ],
            ],
            'valeurRetenue' => [
                'type' => NumberType::class,
                'attr' => [
                    'min' => 0,
                    'step' => '0.01',
                    'onkeypress' => 'return /[0-9.]/.test(event.key)',
                    'inputmode' => 'decimal',
                ],
                'constraints' => [
                    new NotBlank(message: 'La valeur retenue est obligatoire.'),
                    new PositiveOrZero(message: 'La valeur retenue doit Ãªtre positive ou nulle.'),
                ],
            ],
            'documentJustificatif' => [
                'type' => TextType::class,
                'constraints' => [
                    new NotBlank(message: 'Le document justificatif est obligatoire.'),
                    new Length(
                        max: 255,
                        maxMessage: 'Le nom du document ne peut pas dÃ©passer {{ limit }} caractÃ¨res.'
                    ),
                ],
            ],
            'dateEvaluation' => [
                'type' => TextType::class,
                'constraints' => [
                    new NotBlank(message: "La date d'Ã©valuation est obligatoire."),
                    new Regex(
                        pattern: '/^\d{4}-\d{2}-\d{2}$/',
                        message: 'La date doit Ãªtre au format AAAA-MM-JJ.'
                    ),
                ],
            ],
            'nomGarant' => [
                'type' => TextType::class,
                'constraints' => [
                    new NotBlank(message: 'Le nom du garant est obligatoire.'),
                    new Length(
                        min: 2,
                        max: 150,
                        minMessage: 'Le nom doit contenir au moins {{ limit }} caractÃ¨res.',
                        maxMessage: 'Le nom ne peut pas dÃ©passer {{ limit }} caractÃ¨res.'
                    ),
                    new Regex(
                        pattern: '/^[\p{L}\s\-\']+$/u',
                        message: 'Le nom du garant ne doit contenir que des lettres, espaces ou tirets.'
                    ),
                ],
            ],
            'statut' => [
                'type' => ChoiceType::class,
                'choices' => [
                    'En attente' => 'En attente',
                    'Acceptee' => 'Acceptee',
                    'Validee' => 'Validee',
                    'Rejetee' => 'Rejetee',
                ],
            ],
            'idUser' => [
                'type' => IntegerType::class,
                'attr' => [
                    'min' => 1,
                    'onkeypress' => 'return /[0-9]/.test(event.key)',
                    'inputmode' => 'numeric',
                ],
                'constraints' => [
                    new NotBlank(message: "L'identifiant utilisateur est obligatoire."),
                    new Positive(message: "L'identifiant utilisateur doit Ãªtre un entier positif."),
                ],
            ],
            'idCredit' => [
                'type' => IntegerType::class,
                'attr' => [
                    'min' => 1,
                    'onkeypress' => 'return /[0-9]/.test(event.key)',
                    'inputmode' => 'numeric',
                ],
                'constraints' => [
                    new NotBlank(message: "L'identifiant du crÃ©dit est obligatoire."),
                    new Positive(message: "L'identifiant du crÃ©dit doit Ãªtre un entier positif."),
                ],
            ],
        ]);
    }
}
