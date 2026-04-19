<?php

namespace App\Form;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class CreditType extends BaseCrudFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $today = new \DateTimeImmutable('today');
        $allowPastRequestDate = (bool) ($options['allow_past_request_date'] ?? false);

        $this->addFields($builder, [
            'idCredit' => [
                'type' => HiddenType::class,
            ],
            'idUser' => [
                'type' => HiddenType::class,
            ],
            'idCompte' => [
                'type' => HiddenType::class,
            ],
            'idGarantie' => [
                'type' => HiddenType::class,
            ],

            'typeCredit' => [
                'type' => ChoiceType::class,
                'placeholder' => 'Selectionner',
                'choices' => [
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
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le type de credit est obligatoire.'),
                    new Assert\Choice(
                        choices: ['Professionnel', 'Immobilier', 'Auto', 'Consommation', 'Etudes', 'Travaux', 'Personnel', 'Hypotheque', 'Pret auto', 'Education', 'Sante', 'Autre'],
                        message: 'Veuillez selectionner un type de credit valide.'
                    ),
                ],
            ],

            'dateDemande' => [
                'type' => DateType::class,
                'widget' => 'single_text',
                'input' => 'string',
                'html5' => true,
                'attr' => $allowPastRequestDate ? [] : [
                    'min' => $today->format('Y-m-d'),
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'La date de demande est obligatoire.'),
                    new Assert\Callback(function ($value, $context) use ($today, $allowPastRequestDate) {
                        if ($value === null || $value === '') {
                            return;
                        }

                        try {
                            $date = new \DateTimeImmutable((string) $value);
                            if (!$allowPastRequestDate && $date < $today) {
                                $context->buildViolation('La date de demande ne peut pas etre dans le passe.')
                                    ->addViolation();
                            }
                        } catch (\Throwable) {
                            $context->buildViolation('La date de demande est invalide.')->addViolation();
                        }
                    }),
                ],
            ],

            'montantDemande' => [
                'type' => NumberType::class,
                'constraints' => [
                    new Assert\NotBlank(message: 'Le montant demande est obligatoire.'),
                    new Assert\Positive(message: 'Le montant demande doit etre un nombre positif.'),
                    new Assert\Range(
                        min: 500,
                        max: 10_000_000,
                        notInRangeMessage: 'Le montant doit etre compris entre {{ min }} et {{ max }} DT.'
                    ),
                ],
            ],

            'autofinancement' => [
                'type' => NumberType::class,
                'constraints' => [
                    new Assert\NotBlank(message: "L'autofinancement est obligatoire."),
                    new Assert\PositiveOrZero(message: "L'autofinancement doit etre positif ou nul."),
                ],
            ],

            'duree' => [
                'type' => ChoiceType::class,
                'placeholder' => 'Selectionner',
                'choices' => array_combine(
                    array_map(fn (int $m) => "$m mois", [6, 12, 18, 24, 36, 48, 60, 72, 84, 120]),
                    [6, 12, 18, 24, 36, 48, 60, 72, 84, 120]
                ),
                'constraints' => [
                    new Assert\NotBlank(message: 'La duree est obligatoire.'),
                    new Assert\Choice(
                        choices: [6, 12, 18, 24, 36, 48, 60, 72, 84, 120],
                        message: 'Veuillez selectionner une duree valide.'
                    ),
                ],
            ],

            'tauxInteret' => [
                'type' => NumberType::class,
                'constraints' => [
                    new Assert\NotBlank(message: "Le taux d'interet est obligatoire."),
                    new Assert\Range(
                        min: 0,
                        max: 100,
                        notInRangeMessage: "Le taux d'interet doit etre compris entre {{ min }}% et {{ max }}%."
                    ),
                ],
            ],

            'mensualite' => [
                'type' => NumberType::class,
                'required' => false,
                'constraints' => [
                    new Assert\Positive(message: 'La mensualite doit etre un nombre positif.'),
                ],
            ],

            'montantAccorde' => [
                'type' => NumberType::class,
                'required' => false,
                'constraints' => [
                    new Assert\PositiveOrZero(message: 'Le montant accorde doit etre positif ou nul.'),
                ],
            ],

            'salaire' => [
                'type' => NumberType::class,
                'constraints' => [
                    new Assert\NotBlank(message: 'Le salaire est obligatoire.'),
                    new Assert\Positive(message: 'Le salaire doit etre un nombre positif.'),
                    new Assert\Range(
                        min: 0,
                        max: 1_000_000,
                        notInRangeMessage: 'Le salaire doit etre compris entre {{ min }} et {{ max }} DT.'
                    ),
                ],
            ],

            'typeContrat' => [
                'type' => ChoiceType::class,
                'placeholder' => 'Selectionner',
                'choices' => [
                    'CDI' => 'CDI',
                    'CDD' => 'CDD',
                    'Fonctionnaire' => 'Fonctionnaire',
                    'Profession liberale' => 'Profession liberale',
                    'Profession liberale (legacy)' => 'Profession libÃ©rale',
                    'Autre' => 'Autre',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le type de contrat est obligatoire.'),
                    new Assert\Choice(
                        choices: ['CDI', 'CDD', 'Fonctionnaire', 'Profession liberale', 'Profession libÃ©rale', 'Autre'],
                        message: 'Veuillez selectionner un type de contrat valide.'
                    ),
                ],
            ],

            'ancienneteAnnees' => [
                'type' => IntegerType::class,
                'constraints' => [
                    new Assert\NotBlank(message: "L'anciennete est obligatoire."),
                    new Assert\Range(
                        min: 0,
                        max: 60,
                        notInRangeMessage: "L'anciennete doit etre comprise entre {{ min }} et {{ max }} ans."
                    ),
                ],
            ],

            'statut' => [
                'type' => ChoiceType::class,
                'placeholder' => 'Selectionner',
                'choices' => [
                    'En attente' => 'En attente',
                    'Accepte' => 'Accepte',
                    'Accepte (legacy)' => 'AcceptÃ©',
                    'En cours' => 'En cours',
                    'Rejete' => 'Rejete',
                    'Rejete (legacy)' => 'RejetÃ©',
                    'Cloture' => 'Cloture',
                    'Cloture (legacy)' => 'ClÃ´turÃ©',
                ],
                'constraints' => [
                    new Assert\Choice(
                        choices: ['En attente', 'Accepte', 'AcceptÃ©', 'En cours', 'Rejete', 'RejetÃ©', 'Cloture', 'ClÃ´turÃ©'],
                        message: 'Veuillez selectionner un statut valide.'
                    ),
                ],
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefault('allow_past_request_date', false);
        $resolver->setAllowedTypes('allow_past_request_date', 'bool');
    }
}
