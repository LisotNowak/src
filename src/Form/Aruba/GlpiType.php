<?php

namespace App\Form\Aruba;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Connexion à GLPI 11 (clé "glpi" de config/aruba/config.json, secrets chiffrés dans config/aruba/secrets).
 */
class GlpiType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $keep = 'Laisser vide pour conserver la valeur actuelle.';
        $builder
            ->add('enabled', CheckboxType::class, [
                'label' => 'Interroger GLPI à chaque actualisation',
                'required' => false,
            ])
            ->add('url', TextType::class, [
                'label' => 'Adresse de GLPI',
                'constraints' => [
                    new NotBlank(),
                    new Regex(pattern: '#^https?://[A-Za-z0-9.-]+(:\d+)?(/[^\s]*)?$#', message: 'Adresse invalide (ex. https://glpi.exemple.com).'),
                ],
                'attr' => ['placeholder' => 'ex. https://glpi.exemple.com'],
            ])
            ->add('client_id', TextType::class, [
                'label' => 'ID du client OAuth',
                'constraints' => [new NotBlank()],
                'attr' => ['class' => 'font-monospace', 'autocomplete' => 'off'],
                'help' => 'GLPI › Configuration › Clients OAuth : type d\'autorisation « Password », portées « api » et « graphql ».',
            ])
            ->add('client_secret', PasswordType::class, [
                'label' => 'Secret du client OAuth',
                'required' => false,
                'always_empty' => true,
                'attr' => ['autocomplete' => 'new-password'],
                'help' => $options['has_secret'] ? $keep : null,
            ])
            ->add('username', TextType::class, [
                'label' => 'Compte GLPI',
                'constraints' => [new NotBlank()],
                'help' => 'Compte en lecture seule sur le parc.',
            ])
            ->add('password', PasswordType::class, [
                'label' => 'Mot de passe du compte',
                'required' => false,
                'always_empty' => true,
                'attr' => ['autocomplete' => 'new-password'],
                'help' => $options['has_password'] ? $keep : null,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['has_secret' => false, 'has_password' => false]);
    }
}
