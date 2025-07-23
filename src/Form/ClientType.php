<?php

namespace App\Form;

use App\Entity\client\Client;
use App\Entity\client\Categorie;
use App\Entity\client\Signataire;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ClientType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('nom', TextType::class)
            ->add('prenom', TextType::class)
            ->add('ville', TextType::class)
            ->add('pays', TextType::class)
            ->add('societeNom', TextType::class)
            ->add('categorie', EntityType::class, [
                'class' => Categorie::class,
                'choice_label' => 'nomCategorie',
                'required' => false,
            ])
            ->add('signataire', EntityType::class, [
                'class' => Signataire::class,
                'choice_label' => 'signataire',
                'required' => false,
                'placeholder' => '-- Choisir un signataire --',
            ])
            // Ajoutez d'autres champs si besoin
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Client::class,
        ]);
    }
}