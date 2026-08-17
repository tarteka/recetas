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

$tablaRecetas = $pdo->query(
    "SELECT 1 FROM sqlite_master WHERE type = \"table\" AND name = \"recetas\""
)->fetchColumn();

if ($tablaRecetas !== false) {
    $columnas = $pdo->query("PRAGMA table_info(recetas)")->fetchAll();
    $nombres = array_column($columnas, "name");
    if (!in_array("slug", $nombres, true)) {
        $pdo->exec("ALTER TABLE recetas ADD COLUMN slug TEXT");
    }
}

$pdo->exec($sql);

$slugsExistentes = array_column(
    $pdo->query("SELECT slug FROM recetas WHERE slug IS NOT NULL")->fetchAll(),
    "slug"
);
$slugsExistentes = array_fill_keys($slugsExistentes, true);

$pendientes = $pdo->query("SELECT id, titulo FROM recetas WHERE slug IS NULL")->fetchAll();
foreach ($pendientes as $receta) {
    $base = App\Service\Slugger::generar((string) $receta["titulo"]) ?: "receta";
    $slug = $base;
    $sufijo = 2;
    while (isset($slugsExistentes[$slug])) {
        $slug = $base . "-" . $sufijo;
        $sufijo++;
    }
    $slugsExistentes[$slug] = true;

    $actualizar = $pdo->prepare("UPDATE recetas SET slug = :slug WHERE id = :id");
    $actualizar->execute(["slug" => $slug, "id" => $receta["id"]]);
}
'

echo "Base de datos inicializada correctamente."

# Sustituye el shell por el proceso principal del contenedor.
exec "$@"