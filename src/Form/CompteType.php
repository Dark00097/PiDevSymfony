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

final class CompteType extends BaseCrudFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addFields($builder, [
            'idCompte' => ['type' => IntegerType::class, 'disabled' => true],
            'numeroCompte' => ['type' => TextType::class],
            'solde' => ['type' => NumberType::class],
            'dateOuverture' => ['type' => TextType::class],
            'statutCompte' => ['type' => TextType::class],
            'plafondRetrait' => ['type' => NumberType::class],
            'plafondVirement' => ['type' => NumberType::class],
            'typeCompte' => ['type' => TextType::class],
            'idUser' => ['type' => IntegerType::class],
        ]);
    }
}
