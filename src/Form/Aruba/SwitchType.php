<?php

namespace App\Form\Aruba;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotEqualTo;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Switch de config/aruba/config.json (données sous forme de tableau, voir ArubaAdminController).
 */
class SwitchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $defaults = $options['defaults'];
        $builder
            ->add('id', TextType::class, [
                'label' => 'Identifiant',
                'disabled' => $options['is_edit'],
                'help' => $options['is_edit'] ? 'Non modifiable.' : 'Minuscules, chiffres et tirets (ex. bureaux-etage02). Non modifiable ensuite.',
                'constraints' => [
                    new NotBlank(),
                    new Regex(pattern: '/^[a-z0-9][a-z0-9-]{0,49}$/', message: 'Minuscules, chiffres et tirets uniquement.'),
                    new NotEqualTo(value: 'default', message: 'Identifiant réservé.'),
                ],
            ])
            ->add('name', TextType::class, [
                'label' => 'Nom affiché',
                'required' => false,
                'attr' => ['placeholder' => 'ex. sw-chlatour-bureaux-etage02'],
            ])
            ->add('host', TextType::class, [
                'label' => 'Adresse IP ou nom DNS',
                'constraints' => [
                    new NotBlank(),
                    new Regex(pattern: '/^[A-Za-z0-9][A-Za-z0-9.-]{0,252}$/', message: 'Adresse IP ou nom DNS invalide.'),
                ],
                'attr' => ['placeholder' => 'ex. 192.168.1.131'],
            ])
            ->add('site', TextType::class, [
                'label' => 'Emplacement',
                'required' => false,
                'attr' => ['placeholder' => 'ex. Bureaux étage 2'],
            ])
            ->add('os', ChoiceType::class, [
                'label' => 'Système',
                'choices' => [
                    'AOS-CX (CX 6000, 6100, 6200, 6300…)' => 'aos-cx',
                    'ArubaOS-Switch (2530, 2930F, 3810… ex-ProCurve)' => 'aos-switch',
                ],
            ])
            ->add('protocol', ChoiceType::class, [
                'label' => 'Protocole',
                'choices' => [
                    'HTTPS' => 'https',
                    'HTTP' => 'http',
                ],
            ])
            ->add('username', TextType::class, [
                'label' => 'Utilisateur',
                'required' => false,
                'attr' => ['placeholder' => 'Par défaut : ' . ($defaults['username'] ?? '')],
            ])
            ->add('password_mode', ChoiceType::class, [
                'label' => 'Mot de passe',
                'expanded' => true,
                'choices' => [
                    'Par défaut' => 'common',
                    'Spécifique' => 'own',
                ],
            ])
            ->add('password', PasswordType::class, [
                'label' => 'Mot de passe spécifique',
                'required' => false,
                'always_empty' => true,
                'attr' => ['autocomplete' => 'new-password'],
                'help' => $options['has_own_password'] ? 'Laisser vide pour conserver le mot de passe actuel.' : null,
            ])
            ->add('timeout', IntegerType::class, [
                'label' => 'Délai de réponse (s)',
                'required' => false,
                'constraints' => [new Range(min: 3, max: 120)],
                'attr' => ['placeholder' => 'Par défaut : ' . ($defaults['timeout'] ?? 15)],
            ])
            ->add('api_version', TextType::class, [
                'label' => 'Version d\'API',
                'required' => false,
                'constraints' => [new Regex(pattern: '/^(auto|v\d+(\.\d+)*)$/', message: '« auto » ou une version comme v10.09.')],
                'attr' => ['placeholder' => 'Par défaut : ' . ($defaults['api_version'] ?? 'auto')],
            ])
            ->add('verify_ssl', ChoiceType::class, [
                'label' => 'Certificat HTTPS',
                'choices' => [
                    'Par défaut (' . (!empty($defaults['verify_ssl']) ? 'vérifié' : 'non vérifié') . ')' => 'default',
                    'Vérifier le certificat' => 'yes',
                    'Ne pas vérifier (certificat auto-signé)' => 'no',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'is_edit' => false,
            'has_own_password' => false,
            'defaults' => [],
        ]);
    }
}
