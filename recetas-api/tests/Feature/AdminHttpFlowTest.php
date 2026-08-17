<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Service\AdminSessionService;
use GdImage;

final class AdminHttpFlowTest extends SlimAppTestCase
{
    public function test_endpoints_admin_exigen_sesion_y_csrf(): void
    {
        $sinSesion = $this->app->handle($this->crearRequest('GET', '/admin/recetas', null, null, null));
        self::assertSame(401, $sinSesion->getStatusCode(), 'El listado admin aceptó una petición sin sesión');

        $sesion = $this->crearSesionAdmin();

        $sinOrigen = $this->app->handle($this->crearRequest('POST', '/admin/recetas', '{}', $sesion, null));
        self::assertSame(403, $sinOrigen->getStatusCode(), 'Una escritura sin Origin superó CSRF');

        $origenExterno = $this->app->handle($this->crearRequest('POST', '/admin/recetas', '{}', $sesion, 'https://evil.example'));
        self::assertSame(403, $origenExterno->getStatusCode(), 'Una escritura con origen externo superó CSRF');
    }

    public function test_crud_de_categorias_y_etiquetas_administrativas(): void
    {
        $sesion = $this->crearSesionAdmin();

        $categoriaCreada = $this->app->handle($this->crearRequest(
            'POST',
            '/admin/categorias',
            json_encode(['nombre' => 'Categoría temporal'], JSON_THROW_ON_ERROR),
            $sesion
        ));
        self::assertSame(201, $categoriaCreada->getStatusCode(), 'No se creó una categoría administrativa');
        $categoriaId = (int) ($this->jsonRespuesta($categoriaCreada)['id'] ?? 0);
        self::assertGreaterThan(0, $categoriaId, 'La categoría creada no devolvió ID');

        $duplicada = $this->app->handle($this->crearRequest(
            'POST',
            '/admin/categorias',
            json_encode(['nombre' => 'Categoría temporal'], JSON_THROW_ON_ERROR),
            $sesion
        ));
        self::assertSame(409, $duplicada->getStatusCode(), 'Se permitió una categoría duplicada');

        $categoriaActualizada = $this->app->handle($this->crearRequest(
            'PUT',
            '/admin/categorias/' . $categoriaId,
            json_encode(['nombre' => 'Categoría renombrada'], JSON_THROW_ON_ERROR),
            $sesion
        ));
        self::assertSame(200, $categoriaActualizada->getStatusCode(), 'No se actualizó la categoría');
        self::assertSame(
            'categoria-renombrada',
            $this->jsonRespuesta($categoriaActualizada)['slug'] ?? null,
            'No se regeneró el slug de la categoría'
        );

        $listadoCategorias = $this->jsonRespuesta($this->app->handle($this->crearRequest('GET', '/admin/categorias', null, $sesion)));
        self::assertSame(1, $listadoCategorias['paginacion']['total'] ?? 0, 'El listado administrativo no incluye categorías sin recetas');

        $categoriaEliminada = $this->app->handle($this->crearRequest('DELETE', '/admin/categorias/' . $categoriaId, null, $sesion));
        self::assertSame(204, $categoriaEliminada->getStatusCode(), 'No se eliminó una categoría sin uso');

        $etiquetaCreada = $this->app->handle($this->crearRequest(
            'POST',
            '/admin/etiquetas',
            json_encode(['nombre' => 'Etiqueta temporal'], JSON_THROW_ON_ERROR),
            $sesion
        ));
        self::assertSame(201, $etiquetaCreada->getStatusCode(), 'No se creó una etiqueta administrativa');
        $etiquetaId = (int) ($this->jsonRespuesta($etiquetaCreada)['id'] ?? 0);
        $etiquetaEliminada = $this->app->handle($this->crearRequest('DELETE', '/admin/etiquetas/' . $etiquetaId, null, $sesion));
        self::assertSame(204, $etiquetaEliminada->getStatusCode(), 'No se eliminó una etiqueta sin uso');
    }

    public function test_validacion_devuelve_422_sin_filtrar_datos_internos(): void
    {
        $sesion = $this->crearSesionAdmin();

        $invalida = $this->app->handle($this->crearRequest(
            'POST',
            '/admin/recetas',
            json_encode(['titulo' => ' ', 'ingredientes' => [], 'pasos' => []], JSON_THROW_ON_ERROR),
            $sesion
        ));
        self::assertSame(422, $invalida->getStatusCode(), 'La API aceptó una receta inválida');

        $cuerpo = (string) $invalida->getBody();
        self::assertStringNotContainsString('/var/www', $cuerpo, 'La validación expuso rutas internas');
        self::assertStringNotContainsString('SQLSTATE', $cuerpo, 'La validación expuso detalles de SQL');
        self::assertStringNotContainsString('Stack trace', $cuerpo, 'La validación expuso una traza');
    }

    public function test_ciclo_completo_de_creacion_lectura_y_actualizacion_de_una_receta(): void
    {
        $sesion = $this->crearSesionAdmin();
        $creada = $this->crearRecetaHttp($sesion);
        $id = (int) $creada['id'];
        $slug = (string) $creada['slug'];
        self::assertSame('receta-http-temporal', $slug, 'La creación no devolvió el slug derivado del título');

        $publicaPorId = $this->app->handle($this->crearRequest('GET', '/recetas/' . $id, null, null, null));
        self::assertSame(200, $publicaPorId->getStatusCode(), 'La receta creada no es pública por id');
        $publicaPorSlug = $this->app->handle($this->crearRequest('GET', '/recetas/' . $slug, null, null, null));
        self::assertSame(200, $publicaPorSlug->getStatusCode(), 'La receta creada no es pública por slug');
        self::assertSame($id, $this->jsonRespuesta($publicaPorSlug)['id'] ?? null, 'La búsqueda por slug devolvió una receta distinta');

        $datos = $this->datosRecetaHttp();
        $datos['titulo'] = 'Receta HTTP actualizada';
        $actualizada = $this->app->handle($this->crearRequest(
            'PUT',
            '/admin/recetas/' . $id,
            json_encode($datos, JSON_THROW_ON_ERROR),
            $sesion
        ));
        self::assertSame(200, $actualizada->getStatusCode(), 'No se actualizó la receta HTTP');
        self::assertSame(
            'Receta HTTP actualizada',
            $this->jsonRespuesta($actualizada)['titulo'] ?? null,
            'La actualización no persistió'
        );
    }

    public function test_no_se_puede_eliminar_una_categoria_asociada_a_una_receta(): void
    {
        $sesion = $this->crearSesionAdmin();
        $this->crearRecetaHttp($sesion);

        $consulta = $this->pdo->query("SELECT id FROM categorias WHERE nombre = 'Pruebas'");
        self::assertNotFalse($consulta, 'No se pudo consultar la categoría de prueba');
        $categoriaEnUsoId = (int) $consulta->fetchColumn();
        $intento = $this->app->handle($this->crearRequest('DELETE', '/admin/categorias/' . $categoriaEnUsoId, null, $sesion));
        self::assertSame(409, $intento->getStatusCode(), 'Se eliminó una categoría asociada a una receta');
    }

    public function test_subida_de_imagen_valida_normaliza_a_webp_y_rechaza_contenido_invalido(): void
    {
        $sesion = $this->crearSesionAdmin();
        $id = (int) $this->crearRecetaHttp($sesion)['id'];

        $imagenInvalida = $this->crearRequestBinaria(
            'POST',
            '/admin/recetas/' . $id . '/imagen',
            'no-es-una-imagen',
            'image/jpeg',
            $sesion
        );
        self::assertSame(422, $this->app->handle($imagenInvalida)->getStatusCode(), 'Aceptó contenido que no era imagen');

        $origen = imagecreatetruecolor(320, 240);
        self::assertInstanceOf(GdImage::class, $origen, 'No se pudo crear la imagen de prueba');
        $color = imagecolorallocate($origen, 90, 130, 80);
        self::assertNotFalse($color, 'No se pudo asignar el color de prueba');
        imagefill($origen, 0, 0, $color);
        ob_start();
        imagejpeg($origen, null, 85);
        $jpeg = ob_get_clean();
        self::assertIsString($jpeg, 'No se generó el JPEG de prueba');

        $subida = $this->app->handle($this->crearRequestBinaria(
            'POST',
            '/admin/recetas/' . $id . '/imagen',
            $jpeg,
            'image/jpeg',
            $sesion
        ));
        self::assertSame(200, $subida->getStatusCode(), 'No se subió la imagen válida');

        $imagenUrl = (string) ($this->jsonRespuesta($subida)['imagen_url'] ?? '');
        $imagenRuta = $this->directorioImagenes . '/' . basename($imagenUrl);
        $imagenInfo = getimagesize($imagenRuta);
        self::assertIsArray($imagenInfo, 'No se pudo leer la imagen generada');
        self::assertSame(1200, $imagenInfo[0], 'La imagen no se normalizó a 1200 de ancho');
        self::assertSame(800, $imagenInfo[1], 'La imagen no se normalizó a 800 de alto');
        self::assertSame('image/webp', $imagenInfo['mime'], 'La imagen no se convirtió a WebP');
    }

    public function test_archivar_restaurar_y_eliminar_definitivamente_una_receta(): void
    {
        $sesion = $this->crearSesionAdmin();
        $id = (int) $this->crearRecetaHttp($sesion)['id'];

        $archivada = $this->app->handle($this->crearRequest('DELETE', '/admin/recetas/' . $id, null, $sesion));
        self::assertSame(200, $archivada->getStatusCode(), 'No se archivó la receta');
        self::assertSame(
            404,
            $this->app->handle($this->crearRequest('GET', '/recetas/' . $id, null, null, null))->getStatusCode(),
            'La receta archivada sigue siendo pública'
        );
        self::assertSame(
            200,
            $this->app->handle($this->crearRequest('GET', '/admin/recetas/' . $id, null, $sesion))->getStatusCode(),
            'Administración no puede consultar la receta archivada'
        );

        $restaurada = $this->app->handle($this->crearRequest('POST', '/admin/recetas/' . $id . '/restaurar', null, $sesion));
        self::assertSame(200, $restaurada->getStatusCode(), 'No se restauró la receta');
        self::assertSame(
            200,
            $this->app->handle($this->crearRequest('GET', '/recetas/' . $id, null, null, null))->getStatusCode(),
            'La receta restaurada no volvió a ser pública'
        );

        $eliminacionActiva = $this->app->handle($this->crearRequest('DELETE', '/admin/recetas/' . $id . '/definitiva', null, $sesion));
        self::assertSame(409, $eliminacionActiva->getStatusCode(), 'Se eliminó definitivamente una receta activa');

        $this->app->handle($this->crearRequest('DELETE', '/admin/recetas/' . $id, null, $sesion));
        $eliminada = $this->app->handle($this->crearRequest('DELETE', '/admin/recetas/' . $id . '/definitiva', null, $sesion));
        self::assertSame(200, $eliminada->getStatusCode(), 'No se eliminó definitivamente la receta archivada');
        self::assertNull($this->recetas->obtenerPorId($id, true), 'La receta eliminada sigue en SQLite');

        $consulta = $this->pdo->query('SELECT COUNT(*) FROM receta_ingredientes WHERE receta_id = ' . $id);
        self::assertNotFalse($consulta, 'No se pudo comprobar las relaciones de la receta eliminada');
        $relaciones = (int) $consulta->fetchColumn();
        self::assertSame(0, $relaciones, 'Las relaciones no se eliminaron en cascada');
    }

    public function test_sesion_caducada_no_es_valida_y_logout_invalida_la_sesion(): void
    {
        $sesionValida = $this->crearSesionAdmin();

        $tokenCaducado = 'sesion-caducada';
        $this->sesionesRepositorioParaExpirar($tokenCaducado);
        $requestCaducada = $this->crearRequest('GET', '/admin/recetas', null, $tokenCaducado);
        self::assertSame(401, $this->app->handle($requestCaducada)->getStatusCode(), 'Una sesión caducada sigue siendo válida');

        $logout = $this->app->handle($this->crearRequest('POST', '/admin/logout', null, $sesionValida));
        self::assertSame(204, $logout->getStatusCode(), 'El logout HTTP falló');
        self::assertSame(
            401,
            $this->app->handle($this->crearRequest('GET', '/admin/recetas', null, $sesionValida))->getStatusCode(),
            'La sesión sigue activa tras logout'
        );
    }

    /** @return array{id: int, slug: string} */
    private function crearRecetaHttp(string $sesion): array
    {
        $creada = $this->app->handle($this->crearRequest(
            'POST',
            '/admin/recetas',
            json_encode($this->datosRecetaHttp(), JSON_THROW_ON_ERROR),
            $sesion
        ));
        self::assertSame(201, $creada->getStatusCode(), 'No se creó la receta HTTP');

        $cuerpo = $this->jsonRespuesta($creada);
        $id = (int) ($cuerpo['id'] ?? 0);
        self::assertGreaterThan(0, $id, 'La creación no devolvió un ID');

        return ['id' => $id, 'slug' => (string) ($cuerpo['slug'] ?? '')];
    }

    /** @return array<string, mixed> */
    private function datosRecetaHttp(): array
    {
        return [
            'titulo' => 'Receta HTTP temporal',
            'descripcion' => 'Creada exclusivamente en memoria.',
            'raciones' => 2,
            'tiempo_preparacion_min' => 10,
            'tiempo_coccion_min' => 20,
            'tiempo_total_min' => 30,
            'fuente_url' => 'https://example.com/receta',
            'ingredientes' => [['nombre' => 'Patata', 'cantidad' => 2, 'unidad' => 'unidades', 'texto_original' => '2 patatas']],
            'pasos' => [['numero' => 1, 'instruccion' => 'Cocer las patatas.']],
            'categorias' => [['nombre' => 'Pruebas']],
            'etiquetas' => [['nombre' => 'Temporal']],
        ];
    }

    private function sesionesRepositorioParaExpirar(string $token): void
    {
        $config = new \App\Config\AdminAuthConfig();
        $repositorio = new \App\Repository\AdminSessionRepository($this->pdo);
        $repositorio->createSession(
            hash_hmac('sha256', $token, $config->sessionSecret()),
            new \App\Auth\AdminIdentity('http-admin', 'semosa@gmail.com', 'Administrador', null),
            time() - 100,
            time() - 10
        );
    }
}
