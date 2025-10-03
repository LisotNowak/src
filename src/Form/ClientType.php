<?php

namespace App\Form;

use App\Entity\client\Client;
use App\Entity\client\Categorie;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ClientType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('societeNom', TextType::class, [
                'label' => 'Nom de la société',
                'required' => true,
            ])
            ->add('triNom', TextType::class, [
                'label' => 'Nom',
                'required' => true,
            ])
            ->add('triPrenom', TextType::class, [
                'label' => 'Prénom',
                'required' => true,
            ])
            ->add('adresse1', TextType::class, [
                'label' => 'Adresse 1',
                'required' => true,
            ])
            // ->add('adresse2', TextType::class, [
            //     'label' => 'Adresse 2',
            //     'required' => false,
            // ])
            ->add('codePostal', TextType::class, [
                'label' => 'Code Postal',
                'required' => true,
            ])
            ->add('ville', TextType::class, [
                'label' => 'Ville',
                'required' => true,
            ])
            ->add('pays', TextType::class, [
                'label' => 'Pays',
                'required' => true,
            ])
            ->add('categorieEntity', EntityType::class, [
                'class' => Categorie::class,
                'choice_label' => 'nomCategorie',
                'label' => 'Catégorie',
                'placeholder' => 'Choisir une catégorie',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Client::class,
        ]);
    }
}