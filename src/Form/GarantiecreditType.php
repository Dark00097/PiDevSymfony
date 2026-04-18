<?php

namespace App\Form;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class GarantiecreditType extends BaseCrudFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $today = new \DateTimeImmutable('today');

        $this->addFields($builder, [
            'idGarantie' => [
                'type' => HiddenType::class,
            ],
            'idUser' => [
                'type' => HiddenType::class,
            ],
            'idCredit' => [
                'type' => HiddenType::class,
            ],

            'typeGarantie' => [
                'type' => ChoiceType::class,
                'placeholder' => 'Sélectionner',
                'choices' => [
                    'Hypothèque immobilière' => 'Hypothèque immobilière',
                    'Hypotheque immobiliere' => 'Hypotheque immobiliere',
                    'Titre véhicule'         => 'Titre véhicule',
                    'Titre vehicule'         => 'Titre vehicule',
                    'Caution personnelle'    => 'Caution personnelle',
                    'Garantie bancaire'      => 'Garantie bancaire',
                    'Police assurance'       => 'Police assurance',
                    'Nantissement'           => 'Nantissement',
                    'Autre garantie'         => 'Autre garantie',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le type de garantie est obligatoire.'),
                    new Assert\Choice(
                        choices: ['Hypothèque immobilière', 'Hypotheque immobiliere', 'Titre véhicule', 'Titre vehicule', 'Caution personnelle', 'Garantie bancaire', 'Police assurance', 'Nantissement', 'Autre garantie'],
                        message: 'Veuillez sélectionner un type de garantie valide.'
                    ),
                ],
            ],

            'description' => [
                'type' => TextareaType::class,
                'constraints' => [
                    new Assert\NotBlank(message: 'La description est obligatoire.'),
                    new Assert\Length(
                        min: 10,
                        max: 1000,
                        minMessage: 'La description doit contenir au moins {{ limit }} caractères.',
                        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.'
                    ),
                ],
            ],

            'adresseBien' => [
                'type' => TextType::class,
                'constraints' => [
                    new Assert\NotBlank(message: "L'adresse du bien est obligatoire."),
                    new Assert\Length(
                        min: 5,
                        max: 255,
                        minMessage: "L'adresse doit contenir au moins {{ limit }} caractères.",
                        maxMessage: "L'adresse ne peut pas dépasser {{ limit }} caractères."
                    ),
                ],
            ],

            'valeurEstimee' => [
                'type' => NumberType::class,
                'constraints' => [
                    new Assert\NotBlank(message: 'La valeur estimée est obligatoire.'),
                    new Assert\Positive(message: 'La valeur estimée doit être un nombre positif.'),
                    new Assert\Range(
                        min: 1000,
                        max: 100_000_000,
                        notInRangeMessage: 'La valeur estimée doit être comprise entre {{ min }} et {{ max }} DT.'
                    ),
                ],
            ],

            'valeurRetenue' => [
                'type' => NumberType::class,
                'required' => false,
                'constraints' => [
                    new Assert\Positive(message: 'La valeur retenue doit être positive.'),
                    new Assert\Callback(function ($value, $context) {
                        if ($value === null || $value === '') {
                            return;
                        }
                        $form = $context->getObject();
                        $estimated = method_exists($form, 'getParent') && $form->getParent()
                            ? (float) ($form->getParent()->get('valeurEstimee')->getData() ?? 0)
                            : 0;
                        if ($estimated > 0 && (float) $value > $estimated) {
                            $context->buildViolation('La valeur retenue ne peut pas dépasser la valeur estimée.')
                                ->addViolation();
                        }
                    }),
                ],
            ],

            'documentJustificatif' => [
                'type' => TextType::class,
                'constraints' => [
                    new Assert\NotBlank(message: 'Le document justificatif est obligatoire.'),
                    new Assert\Length(
                        max: 255,
                        maxMessage: 'Le nom du document ne peut pas dépasser {{ limit }} caractères.'
                    ),
                ],
            ],

           'dateEvaluation' => [
    'type' => DateType::class,
    'widget' => 'single_text',
    'input'  => 'string',
    'html5'  => true,
    'attr' => [
        'min' => (new \DateTime())->format('Y-m-d'),
    ],
    'constraints' => [
        new Assert\NotBlank(message: "La date d'évaluation est obligatoire."),
        new Assert\Callback(function ($value, $context) use ($today) {
            if ($value === null || $value === '') {
                return;
            }

            try {
                $date = new \DateTimeImmutable((string) $value);

                // ✅ Correction ici
                if ($date < $today) {
                    $context->buildViolation("La date d'évaluation ne peut pas être dans le passé.")
                        ->addViolation();
                }

            } catch (\Throwable) {
                $context->buildViolation("La date d'évaluation est invalide.")
                    ->addViolation();
            }
        }),
    ],
],

            'nomGarant' => [
                'type' => TextType::class,
                'required' => false,
                'constraints' => [
                    new Assert\Length(
                        min: 2,
                        max: 150,
                        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères.',
                        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'
                    ),
                    new Assert\Regex(
                        pattern: '/^[\p{L}\s\-\']+$/u',
                        message: 'Le nom du garant ne doit contenir que des lettres, espaces ou tirets.'
                    ),
                ],
            ],

            'statut' => [
                'type' => ChoiceType::class,
                'placeholder' => 'Sélectionner',
                'choices' => [
                    'En attente' => 'En attente',
                    'Acceptée'   => 'Acceptée',
                    'Validée'    => 'Validée',
                    'Rejetée'    => 'Rejetée',
                ],
                'constraints' => [
                    new Assert\Choice(
                        choices: ['En attente', 'Acceptée', 'Validée', 'Rejetée'],
                        message: 'Veuillez sélectionner un statut valide.'
                    ),
                ],
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
    }
}