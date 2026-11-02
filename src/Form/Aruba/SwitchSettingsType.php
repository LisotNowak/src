<?php

namespace App\Form\Aruba;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Paramètres communs à tous les switchs (config/aruba/config.json : parallel, defaults, other_switches,
 * retention_days, retention_full_days, retention_presence_days, auto_collect, auto_interval).
 */
class SwitchSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'label' => 'Utilisateur par défaut',
                'constraints' => [new NotBlank()],
            ])
            ->add('password', PasswordType::class, [
                'label' => 'Mot de passe par défaut',
                'required' => false,
                'always_empty' => true,
                'attr' => ['autocomplete' => 'new-password'],
                'help' => $options['has_password'] ? 'Laisser vide pour conserver le mot de passe actuel.' : null,
            ])
            ->add('timeout', IntegerType::class, [
                'label' => 'Délai de réponse par défaut (s)',
                'constraints' => [new NotBlank(), new Range(min: 3, max: 120)],
            ])
            ->add('api_version', TextType::class, [
                'label' => 'Version d\'API par défaut',
                'constraints' => [new NotBlank(), new Regex(pattern: '/^(auto|v\d+(\.\d+)*)$/', message: '« auto » ou une version comme v10.09.')],
                'help' => '« auto » : la version la plus récente proposée par chaque switch.',
            ])
            ->add('verify_ssl', CheckboxType::class, [
                'label' => 'Vérifier les certificats HTTPS par défaut',
                'required' => false,
                'help' => 'À laisser décoché tant que les switchs utilisent leur certificat auto-signé.',
            ])
            ->add('parallel', IntegerType::class, [
                'label' => 'Équipements interrogés en même temps',
                'constraints' => [new NotBlank(), new Range(min: 1, max: 20)],
            ])
            ->add('auto_collect', CheckboxType::class, [
                'label' => 'Collecte automatique',
                'required' => false,
            ])
            ->add('auto_interval', IntegerType::class, [
                'label' => 'Intervalle (minutes)',
                'constraints' => [new NotBlank(), new Range(min: 5, max: 1440)],
                'help' => 'Temps entre deux collectes, compté depuis la précédente (même lancée à la main). 5 à 1440.',
            ])
            ->add('retention_full_days', IntegerType::class, [
                'label' => 'Toutes les photos du réseau gardées pendant (jours)',
                'constraints' => [new NotBlank(), new Range(min: 0, max: 3650)],
                'help' => 'Photo complète de chaque collecte (environ 250 Ko en base, compressée). Au-delà, une seule photo par jour est gardée. 0 : toutes les photos.',
            ])
            ->add('retention_days', IntegerType::class, [
                'label' => 'Conservation des photos du réseau (jours)',
                'constraints' => [new NotBlank(), new Range(min: 0, max: 3650)],
                'help' => 'Au-delà, plus aucune photo. La plus récente est toujours gardée. 0 : sans limite.',
            ])
            ->add('retention_presence_days', IntegerType::class, [
                'label' => 'Conservation de la présence des appareils (jours)',
                'constraints' => [new NotBlank(), new Range(min: 0, max: 3650)],
                'help' => 'Périodes pendant lesquelles chaque appareil a été vu à une position. Les déplacements et les changements de ports sont gardés sans limite. 0 : sans limite.',
            ])
            ->add('other_switches', TextareaType::class, [
                'label' => 'Switchs sans API',
                'required' => false,
                'attr' => ['rows' => 5, 'class' => 'font-monospace', 'placeholder' => "192.168.1.128 ; sw-chlatour-video"],
                'help' => 'Une ligne par switch : adresse IP ; nom.',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['has_password' => false]);
    }
}
