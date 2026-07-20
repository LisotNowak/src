<?php

namespace App\Controller;

use App\Repository\portail\PortalCategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PortalController extends AbstractController
{
    #[Route('/portail', name: 'app_portail')]
    public function index(PortalCategoryRepository $categoryRepository): Response
    {
        return $this->render('portail/index.html.twig', [
            'categories' => $categoryRepository->findActiveWithActiveTiles(),
        ]);
    }
}
