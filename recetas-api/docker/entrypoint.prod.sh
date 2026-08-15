#!/bin/sh
set -eu

mkdir -p /datos/imagenes

php -r '
require "/var/www/html/vendor/autoload.php";

$pdo = App\Database::conectar();
$sql = file_get_contents("/var/www/html/database/schema.sql");

if ($sql === false) {
    throw new RuntimeException("No se pudo leer schema.sql");
}

$tablaRecetas = $pdo->query(
    "SELECT 1 FROM sqlite_master WHERE type = \"table\" AND name = \"recetas\""
)->fetchColumn();

if ($tablaRecetas !== false) {
    $columnas = $pdo->query("PRAGMA table_info(recetas)")->fetchAll();
    $nombres = array_column($columnas, "name");
    if (!in_array("archivada_en", $nombres, true)) {
        $pdo->exec("ALTER TABLE recetas ADD COLUMN archivada_en TEXT");
    }
}

$pdo->exec($sql);
'

chown -R www-data:www-data /datos

exec docker-php-entrypoint "$@"
