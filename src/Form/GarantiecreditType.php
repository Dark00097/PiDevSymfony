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
        $allowPastEvaluationDate = (bool) ($options['allow_past_evaluation_date'] ?? false);

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
                'placeholder' => 'Selectionner',
                'choices' => [
                    'Hypotheque immobiliere' => 'Hypotheque immobiliere',
                    'Titre vehicule' => 'Titre vehicule',
                    'Caution personnelle' => 'Caution personnelle',
                    'Garantie bancaire' => 'Garantie bancaire',
                    'Police assurance' => 'Police assurance',
                    'Nantissement' => 'Nantissement',
                    'Autre garantie' => 'Autre garantie',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le type de garantie est obligatoire.'),
                    new Assert\Choice(
                        choices: [
                            'Hypotheque immobiliere',
                            'Titre vehicule',
                            'Caution personnelle',
                            'Garantie bancaire',
                            'Police assurance',
                            'Nantissement',
                            'Autre garantie',
                        ],
                        message: 'Veuillez selectionner un type de garantie valide.'
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
                        minMessage: 'La description doit contenir au moins {{ limit }} caracteres.',
                        maxMessage: 'La description ne peut pas depasser {{ limit }} caracteres.'
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
                        minMessage: "L'adresse doit contenir au moins {{ limit }} caracteres.",
                        maxMessage: "L'adresse ne peut pas depasser {{ limit }} caracteres."
                    ),
                ],
            ],
            'adresseComplete' => [
                'type' => TextType::class,
                'required' => false,
                'constraints' => [
                    new Assert\Length(
                        max: 255,
                        maxMessage: "L'adresse complete ne peut pas depasser {{ limit }} caracteres."
                    ),
                ],
            ],
            'ville' => [
                'type' => TextType::class,
                'required' => false,
                'constraints' => [
                    new Assert\Length(
                        max: 120,
                        maxMessage: 'La ville ne peut pas depasser {{ limit }} caracteres.'
                    ),
                ],
            ],
            'codePostal' => [
                'type' => TextType::class,
                'required' => false,
                'constraints' => [
                    new Assert\Length(
                        max: 30,
                        maxMessage: 'Le code postal ne peut pas depasser {{ limit }} caracteres.'
                    ),
                ],
            ],
            'pays' => [
                'type' => TextType::class,
                'required' => false,
                'constraints' => [
                    new Assert\Length(
                        max: 120,
                        maxMessage: 'Le pays ne peut pas depasser {{ limit }} caracteres.'
                    ),
                ],
            ],
            'latitude' => [
                'type' => HiddenType::class,
                'required' => false,
                'constraints' => [
                    new Assert\Callback(function ($value, $context) {
                        if ($value === null || $value === '') {
                            return;
                        }

                        if (!is_numeric($value) || (float) $value < -90 || (float) $value > 90) {
                            $context->buildViolation('Latitude invalide.')
                                ->addViolation();
                        }
                    }),
                ],
            ],
            'longitude' => [
                'type' => HiddenType::class,
                'required' => false,
                'constraints' => [
                    new Assert\Callback(function ($value, $context) {
                        if ($value === null || $value === '') {
                            return;
                        }

                        if (!is_numeric($value) || (float) $value < -180 || (float) $value > 180) {
                            $context->buildViolation('Longitude invalide.')
                                ->addViolation();
                        }
                    }),
                ],
            ],
            'statutVerificationAdresse' => [
                'type' => HiddenType::class,
                'required' => false,
            ],
            'valeurEstimee' => [
                'type' => NumberType::class,
                'constraints' => [
                    new Assert\NotBlank(message: 'La valeur estimee est obligatoire.'),
                    new Assert\Positive(message: 'La valeur estimee doit etre un nombre positif.'),
                    new Assert\Range(
                        min: 1000,
                        max: 100_000_000,
                        notInRangeMessage: 'La valeur estimee doit etre comprise entre {{ min }} et {{ max }} DT.'
                    ),
                ],
            ],
            'valeurRetenue' => [
                'type' => NumberType::class,
                'required' => false,
                'constraints' => [
                    new Assert\Positive(message: 'La valeur retenue doit etre positive.'),
                    new Assert\Callback(function ($value, $context) {
                        if ($value === null || $value === '') {
                            return;
                        }

                        $form = $context->getObject();
                        $estimated = method_exists($form, 'getParent') && $form->getParent()
                            ? (float) ($form->getParent()->get('valeurEstimee')->getData() ?? 0)
                            : 0;

                        if ($estimated > 0 && (float) $value > $estimated) {
                            $context->buildViolation('La valeur retenue ne peut pas depasser la valeur estimee.')
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
                        maxMessage: 'Le nom du document ne peut pas depasser {{ limit }} caracteres.'
                    ),
                ],
            ],
            'dateEvaluation' => [
                'type' => DateType::class,
                'widget' => 'single_text',
                'input' => 'string',
                'html5' => true,
                'attr' => $allowPastEvaluationDate ? [] : [
                    'min' => (new \DateTime())->format('Y-m-d'),
                ],
                'constraints' => [
                    new Assert\NotBlank(message: "La date d'evaluation est obligatoire."),
                    new Assert\Callback(function ($value, $context) use ($today, $allowPastEvaluationDate) {
                        if ($value === null || $value === '') {
                            return;
                        }

                        try {
                            $date = new \DateTimeImmutable((string) $value);
                            if (!$allowPastEvaluationDate && $date < $today) {
                                $context->buildViolation("La date d'evaluation ne peut pas etre dans le passe.")
                                    ->addViolation();
                            }
                        } catch (\Throwable) {
                            $context->buildViolation("La date d'evaluation est invalide.")
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
                        minMessage: 'Le nom doit contenir au moins {{ limit }} caracteres.',
                        maxMessage: 'Le nom ne peut pas depasser {{ limit }} caracteres.'
                    ),
                    new Assert\Regex(
                        pattern: '/^[\p{L}\s\-\']+$/u',
                        message: 'Le nom du garant ne doit contenir que des lettres, espaces ou tirets.'
                    ),
                ],
            ],
            'statut' => [
                'type' => ChoiceType::class,
                'placeholder' => 'Selectionner',
                'choices' => [
                    'En attente' => 'En attente',
                    'Acceptee' => 'Acceptee',
                    'Validee' => 'Validee',
                    'A verifier' => 'A verifier',
                    'Suspect' => 'Suspect',
                    'Rejetee' => 'Rejetee',
                ],
                'constraints' => [
                    new Assert\Choice(
                        choices: ['En attente', 'Acceptee', 'Validee', 'A verifier', 'Suspect', 'Rejetee'],
                        message: 'Veuillez selectionner un statut valide.'
                    ),
                ],
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'allow_past_evaluation_date' => false,
        ]);
        $resolver->setAllowedTypes('allow_past_evaluation_date', 'bool');
    }
}
