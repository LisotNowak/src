<?php

namespace App\Controller;

use App\Form\UploadFileType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class FileUploadController extends AbstractController
{
    #[Route('/upload', name: 'app_file_upload')]
    public function upload(Request $request, SluggerInterface $slugger): Response
    {

        // Vérifiez si l'utilisateur est déjà authentifié
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            // Redirigez l'utilisateur s'il est déjà authentifié
            $form = $this->createForm(UploadFileType::class);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $file = $form->get('file')->getData();

                if ($file) {
                    $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();

                    // Déplace le fichier vers le répertoire de stockage
                    try {
                        $file->move(
                            $this->getParameter('uploads_directory'),
                            $newFilename
                        );

                        $this->addFlash('success', 'Fichier téléchargé avec succès : ' . $newFilename);
                    } catch (FileException $e) {
                        $this->addFlash('error', 'Erreur lors du téléchargement du fichier');
                    }
                }
            }

            return $this->render('file_upload/index.html.twig', [
                'form' => $form->createView(),
            ]);
        }
        return $this->redirectToRoute('app_accueil');
    }

    #[Route('/files', name: 'app_file_list')]
    public function listFiles(): Response
    {
        $uploadDir = $this->getParameter('uploads_directory');
        $files = [];

        // Récupère tous les fichiers dans le répertoire
        if (is_dir($uploadDir)) {
            $files = array_diff(scandir($uploadDir), ['..', '.']); // Exclut '.' et '..'
        }

        return $this->render('file_upload/list.html.twig', [
            'files' => $files,
        ]);
    }

}
