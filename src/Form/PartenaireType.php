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

final class PartenaireType extends BaseCrudFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addFields($builder, [
            'idPartenaire' => ['type' => IntegerType::class, 'disabled' => true],
            'nom' => ['type' => TextType::class],
            'categorie' => ['type' => TextType::class],
            'description' => ['type' => TextareaType::class],
            'ville' => ['type' => TextType::class],
            'tauxCashback' => ['type' => NumberType::class],
            'tauxCashbackMax' => ['type' => NumberType::class],
            'plafondMensuel' => ['type' => NumberType::class],
            'conditions' => ['type' => TextareaType::class],
            'status' => ['type' => TextType::class],
            'rating' => ['type' => NumberType::class],
        ]);
    }
}
