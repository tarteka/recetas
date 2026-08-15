<?php

declare(strict_types=1);

namespace App;

use PDO;

final class Database
{
    public static function conectar(): PDO
    {
        // Abre la base SQLite persistente y activa las claves foráneas.
        $pdo = new PDO('sqlite:/datos/recetas.sqlite');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_TIMEOUT, 5);

        $pdo->exec('PRAGMA busy_timeout = 5000');
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA foreign_keys = ON');

        return $pdo;
    }
}