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
 * Paramètres communs à tous les switchs (config/aruba/config.json : parallel, defaults, other_switches).
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
                'label' => 'Switchs interrogés en même temps',
                'constraints' => [new NotBlank(), new Range(min: 1, max: 20)],
            ])
            ->add('other_switches', TextareaType::class, [
                'label' => 'Switchs non interrogés',
                'required' => false,
                'attr' => ['rows' => 5, 'class' => 'font-monospace', 'placeholder' => "192.168.1.128 ; sw-chlatour-video"],
                'help' => 'Une ligne par switch : adresse IP ; nom. Switchs sans API : ils apparaissent sur la carte quand un voisin LLDP les annonce.',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['has_password' => false]);
    }
}
