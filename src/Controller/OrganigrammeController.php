<?php
// src/Controller/OrganigrammeController.php
namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use App\Service\EventService;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use GuzzleHttp\Client;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use DateTime;

class OrganigrammeController extends AbstractController
{
    #[Route('/organigrammeData', name: 'app_organigrammeData')]
    public function organigrammeData(): Response
    {
        $uploadDir = $this->getParameter('uploads_directory');

        // Vérifier si le répertoire existe
        if (!is_dir($uploadDir)) {
            throw $this->createNotFoundException('Le répertoire des uploads n\'existe pas.');
        }

        // Récupérer tous les fichiers dans le répertoire
        $files = array_diff(scandir($uploadDir), ['..', '.']); // Exclut '.' et '..'

        if (empty($files)) {
            return $this->render('file_upload/latest.html.twig', [
                'message' => 'Aucun fichier trouvé dans le répertoire.',
            ]);
        }

        // Trier les fichiers par date de modification (du plus récent au plus ancien)
        usort($files, function ($a, $b) use ($uploadDir) {
            return filemtime($uploadDir . '/' . $b) - filemtime($uploadDir . '/' . $a);
        });

        // Prendre le dernier fichier
        $latestFile = $files[0];
        $filePath = $uploadDir . '/' . $latestFile;

        // Lire le contenu du fichier
        $fileContent = file_get_contents($filePath);


        
        $tabUser = $fileContent;



		return new JsonResponse($tabUser);
    }


}