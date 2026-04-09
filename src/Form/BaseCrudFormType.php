<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

abstract class BaseCrudFormType extends AbstractType
{
    protected function addFields(FormBuilderInterface $builder, array $fields): void
    {
        foreach ($fields as $name => $config) {
            $type = $config['type'] ?? null;
            unset($config['type']);

            $builder->add($name, $type, array_replace([
                'required' => false,
                'label' => $config['label'] ?? $this->humanize($name),
            ], $config));
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => false,
            'allow_extra_fields' => true,
            'method' => 'POST',
        ]);
    }

    private function humanize(string $name): string
    {
        $spaced = preg_replace('/(?<!^)[A-Z]/', ' $0', $name) ?? $name;

        return ucfirst(str_replace('_', ' ', $spaced));
    }
}
