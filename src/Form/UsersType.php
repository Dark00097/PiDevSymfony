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

final class UsersType extends BaseCrudFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addFields($builder, [
            'idUser' => ['type' => IntegerType::class, 'disabled' => true],
            'nom' => ['type' => TextType::class],
            'prenom' => ['type' => TextType::class],
            'email' => ['type' => EmailType::class],
            'telephone' => ['type' => TextType::class],
            'role' => ['type' => TextType::class],
            'status' => ['type' => TextType::class],
            'password' => ['type' => PasswordType::class, 'empty_data' => ''],
            'createdAt' => ['type' => DateTimeType::class, 'widget' => 'single_text'],
            'updatedAt' => ['type' => DateTimeType::class, 'widget' => 'single_text'],
            'accountOpenedFrom' => ['type' => TextType::class],
            'lastOnlineAt' => ['type' => DateTimeType::class, 'widget' => 'single_text'],
            'lastOnlineFrom' => ['type' => TextType::class],
            'biometricEnabled' => ['type' => CheckboxType::class],
            'profileImagePath' => ['type' => TextType::class],
            'accountOpenedLocation' => ['type' => TextType::class],
            'accountOpenedLat' => ['type' => TextType::class],
            'accountOpenedLng' => ['type' => TextType::class],
        ]);
    }
}
