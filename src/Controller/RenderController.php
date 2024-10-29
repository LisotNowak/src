<?php
// src/Controller/RenderController.php
namespace App\Controller;

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

class RenderController extends AbstractController
{
    #[Route('/', name: 'app_accueil')]
    public function number(): Response
    {

        return $this->render('accueil.html.twig');
    }



    #[Route('/calculette', name: 'app_calculette')]
    public function calculette(): Response
    {
        // Vérifiez si l'utilisateur est déjà authentifié
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            // Redirigez l'utilisateur s'il est déjà authentifié
            return $this->render('calculette.html.twig');
        }
        return $this->redirectToRoute('app_accueil');

    }

    
    #[Route('/generateurSignature', name: 'app_generateur_signature')]
    public function generateur_signature(): Response
    {
        // Vérifiez si l'utilisateur est déjà authentifié
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            // Redirigez l'utilisateur s'il est déjà authentifié
            return $this->render('generateurSignature.html.twig');
        }
        return $this->redirectToRoute('app_accueil');
        
    }


}