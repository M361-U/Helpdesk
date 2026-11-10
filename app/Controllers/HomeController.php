<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;

final class HomeController extends Controller
{
    public function index(): void
    {
        $this->vue('home/index', [
            'titre'    => 'Accueil',
            'connecte' => Auth::estConnecte(),
        ], 'public');
    }
}