<?php
declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    /**
     * Affiche une vue à l'intérieur d'un gabarit (layout).
     * $vue = 'auth/login' => app/Views/auth/login.php
     */
    protected function vue(string $vue, array $donnees = [], string $layout = 'main'): void
    {
        // Donnée commune à toutes les vues : la vue ne dépend ainsi jamais de la couche d'authentification
        $donnees['utilisateurCourant'] = Auth::utilisateur();
        extract($donnees, EXTR_SKIP);

        ob_start();
        require BASE_PATH . '/app/Views/' . $vue . '.php';
        $contenu = ob_get_clean();

        require BASE_PATH . '/app/Views/layouts/' . $layout . '.php';
    }

    protected function rediriger(string $chemin): never
    {
        header('Location: ' . url($chemin));
        exit;
    }

    protected function introuvable(): never
    {
        http_response_code(404);
        exit('404 : page introuvable');
    }

    protected function interdit(): never
    {
        http_response_code(403);
        exit('403 : accès refusé');
    }
}