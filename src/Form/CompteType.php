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

        $userChoices = [];
        foreach ($options['users'] as $user) {
            $userId = (int) ($user['idUser'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $fullName = trim(trim((string) ($user['prenom'] ?? '')) . ' ' . trim((string) ($user['nom'] ?? '')));
            $label = $fullName !== '' ? $fullName : sprintf('Utilisateur #%d', $userId);
            $userChoices[$label] = $userId;
        }
        ksort($userChoices, SORT_NATURAL | SORT_FLAG_CASE);

        $this->addFields($builder, [
            'idCompte' => [
                'type' => HiddenType::class,
            ],
            'idUser' => [
                'type' => ChoiceType::class,
                'placeholder' => '-- Selectionner un utilisateur --',
                'choices' => $userChoices,
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(message: 'Veuillez selectionner un utilisateur.'),
                ],
            ],
            'numeroCompte' => [
                'type' => TextType::class,
                'constraints' => [
                    new Assert\NotBlank(message: 'Le numero de compte est obligatoire.'),
                    new Assert\Regex(
                        pattern: '/^CB-\d{3,}$/',
                        message: 'Le numero de compte doit etre au format CB-XXX (ex : CB-123).'
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
                                $context->buildViolation('Ce numero de compte existe deja.')->addViolation();

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
                        message: 'Le solde doit etre un nombre positif ou egal a 0.'
                    ),
                ],
            ],
            'dateOuverture' => [
                'type' => DateType::class,
                'widget' => 'single_text',
                'input' => 'string',
                'html5' => true,
                'empty_data' => $today->format('Y-m-d'),
                'attr' => [
                    'readonly' => true,
                    'max' => $today->format('Y-m-d'),
                ],
                'constraints' => [
                    new Assert\NotBlank(message: "La date d'ouverture est obligatoire."),
                    new Assert\Callback(function ($value, $context) use ($today) {
                        if ($value === null || $value === '') {
                            return;
                        }

                        try {
                            $date = new \DateTimeImmutable((string) $value);
                            if ($date->format('Y-m-d') !== $today->format('Y-m-d')) {
                                $context->buildViolation(sprintf(
                                    "La date d'ouverture est fixee automatiquement au %s.",
                                    $today->format('Y-m-d')
                                ))
                                    ->addViolation();
                            }
                        } catch (\Throwable) {
                            $context->buildViolation("La date d'ouverture est invalide.")->addViolation();
                        }
                    }),
                ],
            ],
            'statutCompte' => [
                'type' => ChoiceType::class,
                'placeholder' => 'Selectionner',
                'choices' => [
                    'Actif' => 'Actif',
                    'Fermé' => 'Fermé',
                    'Bloqué' => 'Bloqué',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Veuillez selectionner un statut.'),
                    new Assert\Choice(
                        choices: ['Actif', 'Fermé', 'Bloqué'],
                        message: 'Veuillez selectionner un statut.'
                    ),
                ],
            ],
            'plafondRetrait' => [
                'type' => NumberType::class,
                'constraints' => [
                    new Assert\NotBlank(message: 'Le plafond de retrait est obligatoire.'),
                    new Assert\Range(
                        min: 10,
                        max: 1000,
                        notInRangeMessage: 'Le plafond de retrait doit etre compris entre 10 DT et 1000 DT.'
                    ),
                ],
            ],
            'plafondVirement' => [
                'type' => NumberType::class,
                'constraints' => [
                    new Assert\NotBlank(message: 'Le plafond de virement est obligatoire.'),
                    new Assert\Range(
                        min: 10,
                        max: 1000,
                        notInRangeMessage: 'Le plafond de virement doit etre compris entre 10 DT et 1000 DT.'
                    ),
                ],
            ],
            'typeCompte' => [
                'type' => ChoiceType::class,
                'placeholder' => 'Selectionner',
                'choices' => [
                    'Courant' => 'Courant',
                    'Professionnel' => 'Professionnel',
                    'Épargne' => 'Épargne',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Veuillez selectionner un type de compte.'),
                    new Assert\Choice(
                        choices: ['Courant', 'Professionnel', 'Épargne'],
                        message: 'Veuillez selectionner un type de compte.'
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
            'users' => [],
        ]);
        $resolver->setAllowedTypes('existing_accounts', 'array');
        $resolver->setAllowedTypes('current_id', ['null', 'int']);
        $resolver->setAllowedTypes('users', 'array');
    }
}
