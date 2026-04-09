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

final class CoffrevirtuelType extends BaseCrudFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addFields($builder, [
            'idCoffre' => ['type' => IntegerType::class, 'disabled' => true],
            'nom' => ['type' => TextType::class],
            'objectifMontant' => ['type' => NumberType::class],
            'montantActuel' => ['type' => NumberType::class],
            'dateCreation' => ['type' => TextType::class],
            'dateObjectifs' => ['type' => TextType::class],
            'status' => ['type' => TextType::class],
            'estVerrouille' => ['type' => CheckboxType::class],
            'idCompte' => ['type' => IntegerType::class],
            'idUser' => ['type' => IntegerType::class],
        ]);
    }
}
