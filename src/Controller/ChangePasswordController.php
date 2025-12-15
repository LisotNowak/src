<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use App\Entity\User;

class ChangePasswordController extends AbstractController
{
    #[Route('/change-password', name: 'app_change_password')]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em
    ): Response {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createFormBuilder()
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options'  => ['label' => 'Nouveau mot de passe', 'attr' => ['autocomplete' => 'new-password', 'class' => 'form-control']],
                'second_options' => ['label' => 'Répéter le mot de passe', 'attr' => ['autocomplete' => 'new-password', 'class' => 'form-control']],
                'invalid_message' => 'Les deux mots de passe doivent correspondre.',
                'mapped' => false,
                'required' => true,
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plain = $form->get('plainPassword')->getData();
            $hashed = $passwordHasher->hashPassword($user, $plain);
            $user->setPassword($hashed);
            $user->setPasswordChanged(true);

            $em->persist($user);
            $em->flush();

            // Redirect to homepage or another route after change
            return $this->redirectToRoute('app_accueil');
        }

        return $this->render('security/change_password.html.twig', [
            'changePasswordForm' => $form->createView(),
        ]);
    }
}