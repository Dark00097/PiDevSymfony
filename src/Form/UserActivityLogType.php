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

final class UserActivityLogType extends BaseCrudFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addFields($builder, [
            'idAction' => ['type' => IntegerType::class, 'disabled' => true],
            'idUser' => ['type' => IntegerType::class],
            'actionType' => ['type' => TextType::class],
            'actionSource' => ['type' => TextType::class],
            'details' => ['type' => TextareaType::class],
            'createdAt' => ['type' => DateTimeType::class, 'widget' => 'single_text'],
        ]);
    }
}
