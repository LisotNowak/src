<?php

namespace App\Service\tracabilite;

use App\Entity\tracabilite\Equipe;
use App\Entity\User;
use App\Repository\tracabilite\EquipeRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Équipes dont l'utilisateur connecté peut voir et saisir les données.
 * Un admin tracabilité voit toutes les équipes ; un manager uniquement celles qui lui sont associées.
 */
class EquipeAccess implements ResetInterface
{
    /** @var Equipe[]|null */
    private ?array $equipes = null;
    private bool $calcule = false;

    public function __construct(
        private readonly Security $security,
        private readonly EquipeRepository $equipeRepo,
    ) {}

    /**
     * Noms des équipes (chefNom des saisies) autorisés, ou null si aucune restriction.
     * Hors contexte HTTP (commandes console), aucune restriction n'est appliquée.
     *
     * @return string[]|null
     */
    public function chefsAutorises(): ?array
    {
        $equipes = $this->equipesRestreintes();
        return $equipes === null ? null : array_map(fn(Equipe $e) => $e->getNom(), $equipes);
    }

    /** Équipes visibles par l'utilisateur, triées par nom */
    public function equipesVisibles(): array
    {
        return $this->equipesRestreintes() ?? $this->equipeRepo->findAllSorted();
    }

    public function peutVoirChef(?string $chefNom): bool
    {
        $chefs = $this->chefsAutorises();
        return $chefs === null || in_array($chefNom, $chefs, true);
    }

    public function reset(): void
    {
        $this->equipes = null;
        $this->calcule = false;
    }

    /** @return Equipe[]|null */
    private function equipesRestreintes(): ?array
    {
        if (!$this->calcule) {
            $this->calcule = true;
            $this->equipes = null;

            if ($this->security->getToken() !== null && !$this->security->isGranted('ROLE_ADMIN_TRACABILITE')) {
                $user = $this->security->getUser();
                $this->equipes = $user instanceof User ? $this->equipeRepo->findByResponsable($user) : [];
            }
        }
        return $this->equipes;
    }
}
