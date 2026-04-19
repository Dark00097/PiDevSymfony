<?php

namespace App\Form;

use App\Entity\Compte;
use App\Entity\Transactions;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
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
        'DEPOT' => Transactions::TYPE_DEPOT,
        'RETRAIT' => Transactions::TYPE_RETRAIT,
        'VIREMENT' => Transactions::TYPE_VIREMENT,
        'PAIEMENT' => Transactions::TYPE_PAIEMENT,
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
                'choices' => self::TYPES,
                'expanded' => false,
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(message: 'Veuillez selectionner un type de transaction.'),
                    new Assert\Choice(
                        choices: [
                            Transactions::TYPE_DEPOT,
                            Transactions::TYPE_RETRAIT,
                            Transactions::TYPE_VIREMENT,
                            Transactions::TYPE_PAIEMENT,
                        ],
                        message: 'Type de transaction invalide.'
                    ),
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

        // compteDestinataire en tant que string (numéro de compte)
        $builder->add('compteDestinataire', TextType::class, [
            'required' => false,
            'label' => 'Numéro de compte destinataire',
            'attr' => [
                'placeholder' => 'Ex: 1234567890',
                'class' => 'field-virement',
            ],
            'constraints' => [
                new Assert\Length(
                    max: 255,
                    maxMessage: 'Le numéro de compte ne peut pas dépasser {{ limit }} caractères.'
                ),
            ],
        ]);

        // Nom du destinataire
        $builder->add('nomDestinataire', TextType::class, [
            'required' => false,
            'label' => 'Nom du destinataire',
            'attr' => [
                'placeholder' => 'Nom complet',
                'class' => 'field-virement field-paiement',
            ],
            'constraints' => [
                new Assert\Length(
                    max: 255,
                    maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'
                ),
            ],
        ]);

        // Email du destinataire
        $builder->add('emailDestinataire', EmailType::class, [
            'required' => false,
            'label' => 'Email du destinataire',
            'attr' => [
                'placeholder' => 'email@example.com',
                'class' => 'field-virement field-paiement',
            ],
            'constraints' => [
                new Assert\Email(message: 'Email invalide.'),
                new Assert\Length(
                    max: 255,
                    maxMessage: 'L\'email ne peut pas dépasser {{ limit }} caractères.'
                ),
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
