#!/bin/sh
set -e

# Inicializa el esquema SQLite persistente antes de arrancar Slim.
php -r '
require "/app/vendor/autoload.php";

$pdo = App\Database::conectar();
$sql = file_get_contents("/app/database/schema.sql");

if ($sql === false) {
    throw new RuntimeException("No se pudo leer schema.sql");
}

$pdo->exec($sql);
'

echo "Base de datos inicializada correctamente."

# Sustituye el shell por el proceso principal del contenedor.
exec "$@"