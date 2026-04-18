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

final class CompteType extends BaseCrudFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $today = new \DateTimeImmutable('today');
        $existingAccounts = $options['existing_accounts'];
        $currentId = (int) ($options['current_id'] ?? 0);

        $this->addFields($builder, [
            'idCompte' => [
                'type' => HiddenType::class,
            ],
            'idUser' => [
                'type' => HiddenType::class,
            ],
            'numeroCompte' => [
                'type' => TextType::class,
                'constraints' => [
                    new Assert\NotBlank(message: 'Le numéro de compte est obligatoire.'),
                    new Assert\Regex(
                        pattern: '/^CB-\d{3}$/',
                        message: 'Le numéro de compte doit être au format CB-XXX (ex : CB-123).'
                    ),
                    new Assert\Callback(function ($value, $context) use ($existingAccounts, $currentId) {
                        $numero = trim((string) $value);
                        if ($numero === '') {
                            return;
                        }

                        foreach ($existingAccounts as $account) {
                            $existingNumero = trim((string) ($account['numeroCompte'] ?? ''));
                            $existingId = (int) ($account['idCompte'] ?? 0);
                            if ($existingNumero === $numero && $existingId !== $currentId) {
                                $context->buildViolation('Ce numéro de compte existe déjà.')->addViolation();
                                return;
                            }
                        }
                    }),
                ],
            ],
            'solde' => [
                'type' => NumberType::class,
                'constraints' => [
                    new Assert\NotBlank(message: 'Le solde est obligatoire.'),
                    new Assert\GreaterThanOrEqual(
                        value: 0,
                        message: 'Le solde doit être un nombre positif ou égal à 0.'
                    ),
                ],
            ],
            'dateOuverture' => [
                'type' => DateType::class,
                'widget' => 'single_text',
                'input' => 'string',
                'html5' => true,
                'constraints' => [
                    new Assert\NotBlank(message: "La date d’ouverture est obligatoire."),
                    new Assert\Callback(function ($value, $context) use ($today) {
                        if ($value === null || $value === '') {
                            return;
                        }

                        try {
                            $date = new \DateTimeImmutable((string) $value);
                            if ($date > $today) {
                                $context->buildViolation('La date d’ouverture ne doit pas être supérieure à la date actuelle.')
                                    ->addViolation();
                            }
                        } catch (\Throwable) {
                            $context->buildViolation('La date d’ouverture est invalide.')->addViolation();
                        }
                    }),
                ],
            ],
            'statutCompte' => [
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
            'plafondRetrait' => [
                'type' => NumberType::class,
                'constraints' => [
                    new Assert\NotBlank(message: 'Le plafond de retrait est obligatoire.'),
                    new Assert\GreaterThan(
                        value: 0,
                        message: 'Le plafond de retrait doit être un nombre supérieur à 0.'
                    ),
                ],
            ],
            'plafondVirement' => [
                'type' => NumberType::class,
                'constraints' => [
                    new Assert\NotBlank(message: 'Le plafond de virement est obligatoire.'),
                    new Assert\GreaterThan(
                        value: 0,
                        message: 'Le plafond de virement doit être un nombre supérieur à 0.'
                    ),
                ],
            ],
            'typeCompte' => [
                'type' => ChoiceType::class,
                'placeholder' => 'Sélectionner',
                'choices' => [
                    'Courant' => 'Courant',
                    'Professionnel' => 'Professionnel',
                    'Épargne' => 'Épargne',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Veuillez sélectionner un type de compte.'),
                    new Assert\Choice(
                        choices: ['Courant', 'Professionnel', 'Épargne'],
                        message: 'Veuillez sélectionner un type de compte.'
                    ),
                ],
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'existing_accounts' => [],
            'current_id' => null,
        ]);
        $resolver->setAllowedTypes('existing_accounts', 'array');
        $resolver->setAllowedTypes('current_id', ['null', 'int']);
    }
}
