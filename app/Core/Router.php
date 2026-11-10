<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<string, array<int, array{0:string,1:array}>> */
    private array $routes = [];

    public function __construct(private string $cheminBase = '')
    {
    }

    public function get(string $chemin, array $gestionnaire): void
    {
        $this->ajouter('GET', $chemin, $gestionnaire);
    }

    public function post(string $chemin, array $gestionnaire): void
    {
        $this->ajouter('POST', $chemin, $gestionnaire);
    }

    private function ajouter(string $methode, string $chemin, array $gestionnaire): void
    {
        // "/tickets/{id}" devient une expression régulière avec un groupe nommé
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $chemin) . '$#';
        $this->routes[$methode][] = [$regex, $gestionnaire];
    }

    public function dispatch(string $methode, string $uri): void
{
    $chemin = parse_url($uri, PHP_URL_PATH) ?: '/';

    // Préfixes à retirer, du plus long au plus court :
    // /helpdesk/public (accès direct) puis /helpdesk (accès via .htaccess / tunnel)
    $prefixes = array_filter([$this->cheminBase, dirname($this->cheminBase)], fn($p) => $p !== '' && $p !== '/' && $p !== '.');
    foreach ($prefixes as $prefixe) {
        if ($chemin === $prefixe || str_starts_with($chemin, $prefixe . '/')) {
            $chemin = substr($chemin, strlen($prefixe));
            break;
        }
    }
        $chemin = '/' . trim($chemin, '/');

        foreach ($this->routes[$methode] ?? [] as [$regex, $gestionnaire]) {
            if (preg_match($regex, $chemin, $correspondances)) {
                $parametres = array_filter($correspondances, 'is_string', ARRAY_FILTER_USE_KEY);
                [$classe, $action] = $gestionnaire;
                (new $classe())->$action(...$parametres);
                return;
            }
        }

        http_response_code(404);
        echo '404 : page introuvable';
    }
}