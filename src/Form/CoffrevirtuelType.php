<?php

namespace App\Form;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class CoffrevirtuelType extends BaseCrudFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $today = new \DateTimeImmutable('today');

        $this->addFields($builder, [
            'idCoffre' => [
                'type' => HiddenType::class,
            ],
            'idUser' => [
                'type' => HiddenType::class,
            ],
            'nom' => [
                'type' => TextType::class,
                'constraints' => [
                    new Assert\NotBlank(message: 'Le nom du coffre est obligatoire et doit contenir au moins 3 caractères.'),
                    new Assert\Length(
                        min: 3,
                        minMessage: 'Le nom du coffre est obligatoire et doit contenir au moins 3 caractères.'
                    ),
                ],
            ],
            'objectifMontant' => [
                'type' => NumberType::class,
                'constraints' => [
                    new Assert\NotBlank(message: "L’objectif de montant est obligatoire."),
                    new Assert\GreaterThan(
                        value: 0,
                        message: 'L’objectif de montant doit être supérieur à 0.'
                    ),
                ],
            ],
            'montantActuel' => [
                'type' => NumberType::class,
                'constraints' => [
                    new Assert\NotBlank(message: 'Le montant actuel est obligatoire.'),
                    new Assert\GreaterThanOrEqual(
                        value: 0,
                        message: 'Le montant actuel doit être positif ou égal à 0.'
                    ),
                    new Assert\Callback(function ($value, $context) {
                        $data = $context->getRoot()->getData();
                        $objectif = (float) ($data['objectifMontant'] ?? 0);
                        $actuel = (float) ($value ?? 0);

                        if ($objectif > 0 && $actuel > $objectif) {
                            $context->buildViolation('Le montant actuel ne doit pas dépasser l’objectif de montant.')
                                ->addViolation();
                        }
                    }),
                ],
            ],
            'dateCreation' => [
                'type' => DateType::class,
                'widget' => 'single_text',
                'input' => 'string',
                'html5' => true,
                'constraints' => [
                    new Assert\NotBlank(message: 'La date de création est obligatoire.'),
                    new Assert\Callback(function ($value, $context) use ($today) {
                        if ($value === null || $value === '') {
                            return;
                        }

                        try {
                            $date = new \DateTimeImmutable((string) $value);
                            if ($date > $today) {
                                $context->buildViolation('La date de création ne doit pas être supérieure à la date actuelle.')
                                    ->addViolation();
                            }
                        } catch (\Throwable) {
                            $context->buildViolation('La date de création est invalide.')->addViolation();
                        }
                    }),
                ],
            ],
            'dateObjectifs' => [
                'type' => DateType::class,
                'widget' => 'single_text',
                'input' => 'string',
                'html5' => true,
                'required' => false,
                'constraints' => [
                    new Assert\Callback(function ($value, $context) {
                        if ($value === null || $value === '') {
                            return;
                        }

                        $data = $context->getRoot()->getData();
                        $dateCreation = (string) ($data['dateCreation'] ?? '');

                        try {
                            $objectiveDate = new \DateTimeImmutable((string) $value);
                        } catch (\Throwable) {
                            $context->buildViolation('La date d’objectif est invalide.')->addViolation();
                            return;
                        }

                        if ($dateCreation === '') {
                            return;
                        }

                        try {
                            $creationDate = new \DateTimeImmutable($dateCreation);
                            if ($objectiveDate < $creationDate) {
                                $context->buildViolation('La date d’objectif doit être supérieure ou égale à la date de création.')
                                    ->addViolation();
                            }
                        } catch (\Throwable) {
                        }
                    }),
                ],
            ],
            'status' => [
                'type' => ChoiceType::class,
                'placeholder' => 'Sélectionner',
                'choices' => [
                    'Actif' => 'Actif',
                    'Fermé' => 'Fermé',
                    'Bloqué' => 'Bloqué',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Veuillez sélectionner un statut.'),
                    new Assert\Choice(
                        choices: ['Actif', 'Fermé', 'Bloqué'],
                        message: 'Veuillez sélectionner un statut.'
                    ),
                ],
            ],
            'estVerrouille' => [
                'type' => ChoiceType::class,
                'placeholder' => 'Sélectionner',
                'choices' => [
                    'Non' => '0',
                    'Oui' => '1',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Veuillez indiquer si le coffre est verrouillé.'),
                    new Assert\Choice(
                        choices: ['0', '1'],
                        message: 'Veuillez indiquer si le coffre est verrouillé.'
                    ),
                    new Assert\Callback(function ($value, $context) {
                        $data = $context->getRoot()->getData();
                        $objectif = (float) ($data['objectifMontant'] ?? 0);
                        $actuel = (float) ($data['montantActuel'] ?? 0);

                        if ($objectif > 0 && $actuel >= $objectif && (string) $value !== '1') {
                            $context->buildViolation('Le coffre doit être verrouillé lorsque l’objectif est atteint.')
                                ->addViolation();
                        }
                    }),
                ],
            ],
            'idCompte' => [
                'type' => ChoiceType::class,
                'placeholder' => 'Choisir un compte',
                'choices' => $options['account_choices'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Veuillez sélectionner un compte associé.'),
                ],
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'account_choices' => [],
        ]);
        $resolver->setAllowedTypes('account_choices', 'array');
    }
}
