<?php

namespace App\Form;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

final class TransactionsType extends BaseCrudFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addFields($builder, [
            'idTransaction' => ['type' => IntegerType::class, 'disabled' => true],
            'categorie' => ['type' => TextType::class],
            'dateTransaction' => ['type' => TextType::class],
            'montant' => ['type' => NumberType::class],
            'typeTransaction' => ['type' => TextType::class],
            'statutTransaction' => ['type' => TextType::class],
            'soldeApres' => ['type' => NumberType::class],
            'description' => ['type' => TextareaType::class],
            'montantPaye' => ['type' => NumberType::class],
            'idCompte' => ['type' => IntegerType::class],
            'idUser' => ['type' => IntegerType::class],
        ]);
    }
}
