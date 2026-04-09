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

final class ReclamationType extends BaseCrudFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addFields($builder, [
            'idReclamation' => ['type' => IntegerType::class, 'disabled' => true],
            'dateReclamation' => ['type' => DateType::class, 'widget' => 'single_text'],
            'typeReclamation' => ['type' => TextType::class],
            'description' => ['type' => TextareaType::class],
            'status' => ['type' => TextType::class],
            'is_inappropriate' => ['type' => CheckboxType::class],
            'is_blurred' => ['type' => CheckboxType::class],
            'idUser' => ['type' => IntegerType::class],
            'idTransaction' => ['type' => IntegerType::class],
        ]);
    }
}
