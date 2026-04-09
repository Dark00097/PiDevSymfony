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

final class CashbackType extends BaseCrudFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addFields($builder, [
            'idCashback' => ['type' => IntegerType::class, 'disabled' => true],
            'montantAchat' => ['type' => NumberType::class],
            'tauxApplique' => ['type' => NumberType::class],
            'montantCashback' => ['type' => NumberType::class],
            'dateAchat' => ['type' => TextType::class],
            'dateCredit' => ['type' => TextType::class],
            'dateExpiration' => ['type' => TextType::class],
            'statut' => ['type' => TextType::class],
            'idPartenaire' => ['type' => IntegerType::class],
            'idTransaction' => ['type' => IntegerType::class],
        ]);
    }
}
