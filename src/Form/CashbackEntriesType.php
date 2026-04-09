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

final class CashbackEntriesType extends BaseCrudFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addFields($builder, [
            'idCashback' => ['type' => IntegerType::class, 'disabled' => true],
            'partenaireNom' => ['type' => TextType::class],
            'montantAchat' => ['type' => NumberType::class],
            'tauxApplique' => ['type' => NumberType::class],
            'montantCashback' => ['type' => NumberType::class],
            'dateAchat' => ['type' => DateTimeType::class, 'widget' => 'single_text'],
            'dateCredit' => ['type' => DateTimeType::class, 'widget' => 'single_text'],
            'dateExpiration' => ['type' => DateTimeType::class, 'widget' => 'single_text'],
            'statut' => ['type' => TextType::class],
            'transactionRef' => ['type' => TextType::class],
            'createdAt' => ['type' => DateTimeType::class, 'widget' => 'single_text'],
            'userRating' => ['type' => NumberType::class],
            'userRatingComment' => ['type' => TextareaType::class],
            'bonusDecision' => ['type' => TextType::class],
            'bonusNote' => ['type' => TextareaType::class],
            'idUser' => ['type' => IntegerType::class],
            'idPartenaire' => ['type' => IntegerType::class],
        ]);
    }
}
