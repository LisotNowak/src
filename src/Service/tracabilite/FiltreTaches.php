<?php

namespace App\Service\tracabilite;

use App\Repository\tracabilite\TacheRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * Filtres « type de tâche » et « tâche » communs aux pages de la traçabilité.
 */
class FiltreTaches
{
    public function __construct(
        private readonly TacheRepository $tacheRepo,
    ) {}

    /**
     * Lit les filtres de la requête et les valide par rapport aux tâches proposées par la page.
     * Seuls les types ayant une tâche dans cette liste sont proposés.
     *
     * @param string[] $taches
     * @return array{
     *     typesTache: array<int, array{nom: string, taches: string[]}>,
     *     typeTache: string,
     *     taches: string[],
     *     tache: string
     * } « taches » : la liste à proposer, limitée au type choisi
     */
    public function resoudre(Request $request, array $taches): array
    {
        $typesTache = $this->tacheRepo->findTypesDesTaches($taches);

        $typeTache = (string) $request->query->get('typeTache', '');
        if (!isset($typesTache[$typeTache])) {
            $typeTache = '';
        }

        $proposees = $typeTache !== '' ? $typesTache[$typeTache]['taches'] : $taches;

        $tache = (string) $request->query->get('tache', '');
        if (!in_array($tache, $proposees, true)) {
            $tache = '';
        }

        return [
            'typesTache' => $typesTache,
            'typeTache'  => $typeTache,
            'taches'     => $proposees,
            'tache'      => $tache,
        ];
    }
}
