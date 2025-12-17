<?php

namespace App\Controller;

use App\Service\MaintenanceService;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

class DebugController extends AbstractController
{
    #[Route('/dev/routes', name: 'dev_routes_list')]
    public function listRenderRoutes(RouterInterface $router, MaintenanceService $maintenanceService): Response
    {
        $allRoutes = $router->getRouteCollection()->all();
        $categorizedRoutes = [];

        foreach ($allRoutes as $name => $route) {
            $controller = $route->getDefault('_controller');

            // Ignore les routes sans contrôleur (ex: redirections)
            if (!$controller || !is_string($controller) || !str_contains($controller, '::')) {
                continue;
            }

            try {
                list($class, $method) = explode('::', $controller, 2);

                // Vérifie si la classe existe et si on peut la charger
                if (!class_exists($class)) {
                    continue;
                }

                $reflectionMethod = new ReflectionMethod($class, $method);
                $sourceCode = file_get_contents($reflectionMethod->getFileName());
                $lines = array_slice(
                    explode("\n", $sourceCode),
                    $reflectionMethod->getStartLine() - 1,
                    $reflectionMethod->getEndLine() - $reflectionMethod->getStartLine() + 1
                );
                $methodCode = implode("\n", $lines);

                // Heuristique simple : cherche si "$this->render(" est appelé
                if (str_contains($methodCode, '$this->render(')) {
                    $path = $route->getPath();
                    
                    // Extraction de la catégorie depuis le chemin
                    $pathParts = explode('/', trim($path, '/'));
                    $category = !empty($pathParts[0]) ? $pathParts[0] : 'Général';

                    $defaults = $route->getDefaults();
                    $hasRequiredParameters = false;

                    // Trouve tous les paramètres dans le chemin (ex: {id})
                    if (preg_match_all('/\{([^\/]+)\}/', $path, $matches)) {
                        foreach ($matches[1] as $param) {
                            // Si un paramètre n'a pas de valeur par défaut, il est obligatoire
                            if (!array_key_exists($param, $defaults)) {
                                $hasRequiredParameters = true;
                                break;
                            }
                        }
                    }

                    $categorizedRoutes[$category][] = [
                        'name' => $name,
                        'path' => $path,
                        'controller' => $controller,
                        'has_required_parameters' => $hasRequiredParameters,
                    ];
                }
            } catch (\ReflectionException $e) {
                // Ignore les erreurs de réflexion (ex: contrôleur non trouvable)
                continue;
            }
        }

        // Trie les catégories par ordre alphabétique
        ksort($categorizedRoutes);

        return $this->render('routes_list.html.twig', [
            'categorizedRoutes' => $categorizedRoutes,
            'maintenance' => $maintenanceService,
        ]);
    }

    #[Route('/dev/maintenance/activate/{category}', name: 'dev_maintenance_activate', methods: ['POST'])]
    public function activateMaintenance(string $category, Request $request, MaintenanceService $maintenanceService): Response
    {
        $message = $request->request->get('message');
        $mode = $request->request->get('mode', 'block');
        $maintenanceService->activate($category, $message, $mode);
        
        $modeLabel = $mode === 'modal' ? 'un modal' : 'le blocage';
        $this->addFlash('success', "Le mode maintenance ({$modeLabel}) a été activé pour la catégorie '{$category}'.");
        return $this->redirectToRoute('dev_routes_list');
    }

    #[Route('/dev/maintenance/deactivate/{category}', name: 'dev_maintenance_deactivate', methods: ['POST'])]
    public function deactivateMaintenance(string $category, MaintenanceService $maintenanceService): Response
    {
        $maintenanceService->deactivate($category);
        $this->addFlash('success', "Le mode maintenance a été désactivé pour la catégorie '{$category}'.");
        return $this->redirectToRoute('dev_routes_list');
    }
}