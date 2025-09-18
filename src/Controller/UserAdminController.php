<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Form\UserPointsType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/admin/users')]
class UserAdminController extends AbstractController
{
    #[Route('/', name: 'admin_users_index')]
    public function index(UserRepository $userRepository): Response
    {
        return $this->render('admin/users/index.html.twig', [
            'users' => $userRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'admin_users_new')]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // hash du mot de passe
            $user->setPassword(
                $passwordHasher->hashPassword($user, $user->getPassword())
            );

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Utilisateur créé avec succès');
            return $this->redirectToRoute('admin_users_index');
        }

        return $this->render('admin/users/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_users_edit')]
    public function edit(
        Request $request,
        User $user,
        EntityManagerInterface $em
    ): Response {
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Utilisateur mis à jour');
            return $this->redirectToRoute('admin_users_index');
        }

        return $this->render('admin/users/edit.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }


    #[Route('/{id}/delete', name: 'admin_users_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->request->get('_token'))) {
            $em->remove($user);
            $em->flush();
            $this->addFlash('success', 'Utilisateur supprimé');
        }

        return $this->redirectToRoute('admin_users_index');
    }

    #[Route('/{id}/points', name: 'admin_users_points')]
    public function editPoints(
        Request $request,
        User $user,
        EntityManagerInterface $em
    ): Response {
        $form = $this->createForm(UserPointsType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Points mis à jour pour ' . $user->getEmail());
            return $this->redirectToRoute('admin_users_index');
        }

        return $this->render('admin/users/point.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }

}
