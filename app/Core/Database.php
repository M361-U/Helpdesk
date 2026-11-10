<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $instance = null;

    private function __construct() {}
    private function __clone() {}

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $cfg = require __DIR__ . '/../../config/config.php';
            $db  = $cfg['db'];

            $dsn = "mysql:host={$db['host']};dbname={$db['name']};charset={$db['charset']}";

            try {
                self::$instance = new PDO($dsn, $db['user'], $db['pass'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                error_log('Connexion BD impossible : ' . $e->getMessage());
                http_response_code(500);
                exit('Erreur : service temporairement indisponible.');
            }
        }
        return self::$instance;
    }
}