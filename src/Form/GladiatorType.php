<?php

namespace App\Form;

use App\Entity\Gladiator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GladiatorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du Gladiateur',
                'attr' => ['placeholder' => 'Ex: Carpophorus...']
            ])
            ->add('statHpPercent', IntegerType::class, [
                'label' => 'Pourcentage de PV (%)',
                'attr' => ['min' => 5, 'max' => 90, 'placeholder' => 'Min 5%']
            ])
            ->add('statAtkPercent', IntegerType::class, [
                'label' => "Pourcentage d'Attaque (%)",
                'attr' => ['min' => 5, 'max' => 90, 'placeholder' => 'Min 5%']
            ])
            ->add('statDefPercent', IntegerType::class, [
                'label' => 'Pourcentage de Défense (%)',
                'attr' => ['min' => 5, 'max' => 90, 'placeholder' => 'Min 5%']
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Forgé pour le combat 🏛️',
                'attr' => ['class' => 'btn-submit']
            ]);
    }

    public function configureOptions(OptionsResolver $optionsResolver): void
    {
        $optionsResolver->setDefaults([
            'data_class' => Gladiator::class,
        ]);
    }
}
