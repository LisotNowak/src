<?php

namespace App\Form\portail;

use App\Entity\portail\PortalCategory;
use App\Entity\portail\PortalTile;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class PortalTileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre',
                'constraints' => [new NotBlank()],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
            ])
            ->add('categorie', EntityType::class, [
                'label' => 'Catégorie',
                'class' => PortalCategory::class,
                'choice_label' => 'nom',
                'query_builder' => fn ($repo) => $repo->createQueryBuilder('c')
                    ->orderBy('c.position', 'ASC')
                    ->addOrderBy('c.nom', 'ASC'),
                'placeholder' => 'Choisir une catégorie',
                'constraints' => [new NotBlank()],
            ])
            ->add('url', TextType::class, [
                'label' => 'Lien (route interne ou URL externe)',
                'constraints' => [new NotBlank()],
                'attr' => ['placeholder' => '/tracabilite ou https://...'],
            ])
            ->add('icone', TextType::class, [
                'label' => 'Icône (classe Font Awesome, ex: fas fa-box)',
                'required' => false,
                'help' => '<a href="https://fontawesome.com/search?ic=free-collection" target="_blank" rel="noopener">Voir les icônes disponibles <i class="fas fa-external-link-alt"></i></a>',
                'help_html' => true,
            ])
            ->add('couleur', ChoiceType::class, [
                'label' => 'Couleur de la tuile',
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
            ->add('nouvelOnglet', CheckboxType::class, [
                'label' => 'Ouvrir dans un nouvel onglet',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PortalTile::class,
        ]);
    }
}
