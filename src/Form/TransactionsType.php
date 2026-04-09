<?php

namespace App\Form;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class TransactionsType extends BaseCrudFormType
{
    private const CATEGORIES = [
        'Alimentation',
        'Education',
        'Loyer',
        'Restaurant',
        'Vetements',
        'Assurance sante',
    ];

    private const TYPES = [
        'Credit',
        'Debit',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $accountChoices = [];
        foreach ($options['account_choices'] as $accountId => $accountNumber) {
            $accountChoices[(string) $accountNumber] = $accountId;
        }

        $this->addFields($builder, [
            'idTransaction' => ['type' => IntegerType::class, 'disabled' => true],
            'idCompte' => [
                'type' => ChoiceType::class,
                'choices' => $accountChoices,
                'placeholder' => 'Selectionner un compte',
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(message: 'Veuillez selectionner un compte.'),
                ],
            ],
            'categorie' => [
                'type' => ChoiceType::class,
                'choices' => array_combine(self::CATEGORIES, self::CATEGORIES),
                'placeholder' => 'Selectionner une categorie',
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(message: 'Veuillez selectionner une categorie.'),
                ],
            ],
            'dateTransaction' => [
                'type' => DateType::class,
                'widget' => 'single_text',
                'input' => 'string',
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(message: 'La date de transaction est obligatoire.'),
                    new Assert\LessThanOrEqual('today', message: 'La date ne doit pas etre dans le futur.'),
                ],
            ],
            'montant' => [
                'type' => NumberType::class,
                'constraints' => [
                    new Assert\NotBlank(message: 'Le montant est obligatoire.'),
                    new Assert\Positive(message: 'Le montant doit etre superieur a 0.'),
                ],
            ],
            'typeTransaction' => [
                'type' => ChoiceType::class,
                'choices' => array_combine(self::TYPES, self::TYPES),
                'expanded' => false,
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(message: 'Veuillez selectionner un type de transaction.'),
                ],
            ],
            'soldeApres' => [
                'type' => NumberType::class,
                'required' => false,
                'constraints' => [
                    new Assert\GreaterThanOrEqual(0, message: 'Le solde apres doit etre positif ou egal a 0.'),
                ],
            ],
            'description' => [
                'type' => TextareaType::class,
                'required' => false,
                'constraints' => [
                    new Assert\Length(
                        min: 10,
                        minMessage: 'La description doit contenir au moins 10 caracteres.',
                        allowEmptyString: true
                    ),
                ],
            ],
            'montantPaye' => [
                'type' => NumberType::class,
                'required' => false,
                'constraints' => [
                    new Assert\GreaterThanOrEqual(0, message: 'Le montant paye doit etre positif ou egal a 0.'),
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
    }
}
