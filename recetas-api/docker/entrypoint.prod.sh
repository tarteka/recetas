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

$pdo->exec($sql);
'

chown -R www-data:www-data /datos

exec docker-php-entrypoint "$@"
