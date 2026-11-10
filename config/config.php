<?php
declare(strict_types=1);

return [
    'db' => [
        'host'    => '127.0.0.1',
        'name'    => 'helpdesk_dsi',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'nom'      => 'HelpDesk DSI',
        'base_url' => '/helpdesk/public',
        'debug'    => false,
        'url_publique' => 'https://0gmqc78c-80.eun1.devtunnels.ms/helpdesk/public',
    ],
   
    'mail' => [
        'actif'        => true,
        'hote'         => 'smtp.gmail.com',
        'port'         => 587,          // 1025 : Mailpit / MailHog ; 587 (tls) ou 465 (ssl) : serveur réel
        'chiffrement'  => 'tls',            // '' (aucun), 'tls' (STARTTLS) ou 'ssl'
        'utilisateur'  => 'moh.tyler110@gmail.com',
        'mot_de_passe' => 'vhedtbqj anszorba',
        'expediteur'   => 'moh.tyler110@gmail.com',
        'nom'          => 'HelpDesk DSI',
    ],

        
    'inscription' => [
        
        'codes' => [
            'employe'    => 'EMP-2026',
            'technicien' => 'TECH-2026',
        ],
        
        'domaines_email' => [],

        'afficher_code_demo' => false,
    ],
];