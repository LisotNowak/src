<?php

namespace App\Form\portail;

use App\Entity\portail\PortalCategory;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class PortalCategoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de la catégorie',
                'constraints' => [new NotBlank()],
            ])
            ->add('icone', TextType::class, [
                'label' => 'Icône (classe Font Awesome, ex: fas fa-laptop-code)',
                'required' => false,
                'help' => '<a href="https://fontawesome.com/search?ic=free-collection" target="_blank" rel="noopener">Voir les icônes disponibles <i class="fas fa-external-link-alt"></i></a>',
                'help_html' => true,
            ])
            ->add('couleur', ChoiceType::class, [
                'label' => 'Couleur de la catégorie',
                'required' => false,
                'placeholder' => 'Couleur par défaut (bordeaux)',
                'choices' => PortalColorChoices::CHOICES,
            ])
            ->add('position', IntegerType::class, [
                'label' => 'Ordre d\'affichage',
            ])
            ->add('actif', CheckboxType::class, [
                'label' => 'Visible sur le portail',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PortalCategory::class,
        ]);
    }
}
