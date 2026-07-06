<?php

namespace App\Form\tracabilite;

use App\Entity\tracabilite\Saisie;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SaisieType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $equipeChoices   = array_combine($options['equipes'],   $options['equipes']);
        $tacheChoices    = array_combine($options['taches'],    $options['taches']);
        $parcelleChoices = array_combine($options['parcelles'], $options['parcelles']);

        $builder
            ->add('dateTravail', DateType::class, [
                'widget' => 'single_text',
                'label'  => 'Date de travail',
                'attr'   => ['class' => 'form-control'],
            ])
            ->add('chefNom', ChoiceType::class, [
                'label'       => 'Équipe / Chef de secteur',
                'choices'     => $equipeChoices,
                'placeholder' => '— Sélectionner une équipe —',
                'attr'        => ['class' => 'form-select', 'id' => 'saisie_chefNom'],
            ])
            ->add('tacheNom', ChoiceType::class, [
                'label'       => 'Tâche',
                'choices'     => $tacheChoices,
                'placeholder' => '— Sélectionner une tâche —',
                'attr'        => ['class' => 'form-select', 'id' => 'saisie_tacheNom'],
            ])
            ->add('parcelleNom', ChoiceType::class, [
                'label'       => 'Parcelle',
                'choices'     => $parcelleChoices,
                'placeholder' => '— Aucune (tâche RH) —',
                'required'    => false,
                'attr'        => ['class' => 'form-select', 'id' => 'saisie_parcelleNom'],
            ])
            ->add('heures', NumberType::class, [
                'label' => 'Heures travaillées',
                'scale' => 2,
                'attr'  => ['min' => 0, 'step' => 0.25, 'class' => 'form-control', 'id' => 'saisie_heures'],
            ])
            ->add('modePause', ChoiceType::class, [
                'label'   => 'Mode pause',
                'choices' => [
                    'Automatique (saison)' => 'auto',
                    'Hiver (15 min)'       => 'hiver',
                    'Été (20 min)'         => 'ete',
                    'Manuel'               => 'manuel',
                    'Aucune'               => 'aucune',
                ],
                'attr' => ['class' => 'form-select', 'id' => 'saisie_modePause'],
            ])
            ->add('minutesPause', IntegerType::class, [
                'label'    => 'Minutes de pause (si manuel)',
                'required' => false,
                'attr'     => ['min' => 0, 'class' => 'form-control', 'id' => 'saisie_minutesPause'],
            ])
            ->add('avancement', NumberType::class, [
                'label' => 'Avancement (%)',
                'scale' => 1,
                'attr'  => ['min' => 0, 'max' => 100, 'step' => 5, 'class' => 'form-control'],
            ])
            ->add('pieds', NumberType::class, [
                'label'    => 'Pieds réalisés',
                'scale'    => 0,
                'required' => false,
                'attr'     => ['min' => 0, 'class' => 'form-control'],
            ])
            ->add('piedsTotal', NumberType::class, [
                'label'    => 'Total pieds parcelle',
                'scale'    => 0,
                'required' => false,
                'attr'     => ['min' => 0, 'class' => 'form-control'],
            ])
            ->add('commentaire', TextareaType::class, [
                'label'    => 'Commentaire',
                'required' => false,
                'attr'     => ['rows' => 3, 'class' => 'form-control'],
            ])
            ->add('type', HiddenType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Saisie::class,
            'equipes'    => [],
            'taches'     => [],
            'parcelles'  => [],
        ]);
        $resolver->setAllowedTypes('equipes',   'array');
        $resolver->setAllowedTypes('taches',    'array');
        $resolver->setAllowedTypes('parcelles', 'array');
    }

    public function getBlockPrefix(): string
    {
        return 'saisie';
    }
}
