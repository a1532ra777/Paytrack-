<?php
/**
 * Capa de Enrutamiento RESTful Centralizada (API Router)
 * Cumple con la Arquitectura en Capas de la Guía Técnica (Routes -> Controllers -> Services)
 * y la Tabla de Verificación de Endpoints:
 * GET    /api/{modulo}     -> 200 OK
 * GET    /api/{modulo}/:id -> 200 OK / 404 Not Found
 * POST   /api/{modulo}     -> 201 Created / 400 Bad Request
 * PUT    /api/{modulo}/:id -> 200 OK / 422 Unprocessable
 * DELETE /api/{modulo}/:id -> 200 OK / 204 No Content
 */

require_once dirname(__DIR__) . '/conexion.php';

// Importar Controladores
require_once dirname(__DIR__) . '/controllers/ClienteController.php';
require_once dirname(__DIR__) . '/controllers/RolController.php';
require_once dirname(__DIR__) . '/controllers/TiendaController.php';
require_once dirname(__DIR__) . '/controllers/CategoriaController.php';
require_once dirname(__DIR__) . '/controllers/ProductoController.php';
require_once dirname(__DIR__) . '/controllers/VentaController.php';
require_once dirname(__DIR__) . '/controllers/CompraController.php';
require_once dirname(__DIR__) . '/controllers/UsuarioController.php';
require_once dirname(__DIR__) . '/controllers/ReporteController.php';

// MIDDLEWARE GLOBAL DE MANEJO DE ERRORES
set_exception_handler(function ($e) {
    responderJSON([
        "success" => false,
        "error" => "Error interno capturado por Middleware Global: " . $e->getMessage(),
        "tipo" => get_class($e)
    ], 500);
});

// Determinar el método HTTP real (incluyendo soporte de emulación _method o header X-HTTP-Method-Override)
$metodo = $_SERVER['REQUEST_METHOD'];
if ($metodo === 'POST' && isset($_POST['_method'])) {
    $metodo = strtoupper($_POST['_method']);
} elseif (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
    $metodo = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
}

// Analizar la ruta solicitada
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Normalizar la ruta eliminando el prefijo del proyecto
$baseDir = dirname($_SERVER['SCRIPT_NAME']); // ej: /paytrack/api
$rutaRelativa = substr($uri, strlen($baseDir));
$segmentos = array_values(array_filter(explode('/', trim($rutaRelativa, '/'))));

// Si se pasa por query params (ej: api/index.php?modulo=productos&id=2)
$modulo = !empty($segmentos[0]) ? strtolower($segmentos[0]) : (isset($_GET['modulo']) ? strtolower($_GET['modulo']) : null);
$id = !empty($segmentos[1]) ? intval($segmentos[1]) : (isset($_GET['id']) ? intval($_GET['id']) : null);
$subrecurso = !empty($segmentos[2]) ? strtolower($segmentos[2]) : null;

if (!$modulo) {
    responderJSON([
        "nombre" => "PayTrack RESTful API",
        "version" => "2.0.0",
        "estado" => "operativo",
        "modulos_disponibles" => [
            "clientes" => "GET /api/clientes (Solo consulta)",
            "roles" => "GET|POST|PUT|DELETE /api/roles",
            "tiendas" => "GET|POST|PUT|DELETE /api/tiendas",
            "categorias" => "GET|POST|PUT|DELETE /api/categorias",
            "productos" => "GET|POST|PUT|DELETE /api/productos",
            "ventas" => "GET|POST|DELETE /api/ventas (Entrega Final)",
            "compras" => "GET|POST /api/compras",
            "usuarios" => "GET|POST|PUT|DELETE /api/usuarios",
            "reportes" => "GET /api/reportes"
        ]
    ], 200);
}

$payload = obtenerDatosEntrada();
$queryParams = $_GET;

// Enrutamiento a controladores
switch ($modulo) {
    case 'clientes':
        $ctrl = new ClienteController($conexion);
        if ($metodo === 'GET') {
            $resp = $id ? $ctrl->show($id) : $ctrl->index($queryParams);
        } else {
            $resp = $ctrl->store();
        }
        break;

    case 'roles':
        $ctrl = new RolController($conexion);
        if ($metodo === 'GET') {
            $resp = $id ? $ctrl->show($id) : $ctrl->index();
        } elseif ($metodo === 'POST') {
            $resp = $ctrl->store($payload);
        } elseif ($metodo === 'PUT' || $metodo === 'PATCH') {
            $targetId = $id ?: ($payload['id'] ?? null);
            $resp = $ctrl->update($targetId, $payload);
        } elseif ($metodo === 'DELETE') {
            $targetId = $id ?: ($payload['id'] ?? null);
            $resp = $ctrl->destroy($targetId);
        }
        break;

    case 'tiendas':
        $ctrl = new TiendaController($conexion);
        if ($metodo === 'GET') {
            $resp = $id ? $ctrl->show($id) : $ctrl->index();
        } elseif ($metodo === 'POST') {
            $resp = $ctrl->store($payload);
        } elseif ($metodo === 'PUT' || $metodo === 'PATCH') {
            $targetId = $id ?: ($payload['id'] ?? null);
            $resp = $ctrl->update($targetId, $payload);
        } elseif ($metodo === 'DELETE') {
            $targetId = $id ?: ($payload['id'] ?? null);
            $resp = $ctrl->destroy($targetId);
        }
        break;

    case 'categorias':
        $ctrl = new CategoriaController($conexion);
        if ($metodo === 'GET') {
            $resp = $id ? $ctrl->show($id) : $ctrl->index();
        } elseif ($metodo === 'POST') {
            $resp = $ctrl->store($payload);
        } elseif ($metodo === 'PUT' || $metodo === 'PATCH') {
            $targetId = $id ?: ($payload['id'] ?? null);
            $resp = $ctrl->update($targetId, $payload);
        } elseif ($metodo === 'DELETE') {
            $targetId = $id ?: ($payload['id'] ?? null);
            $resp = $ctrl->destroy($targetId);
        }
        break;

    case 'productos':
        $ctrl = new ProductoController($conexion);
        if ($metodo === 'GET') {
            $resp = $id ? $ctrl->show($id) : $ctrl->index($queryParams);
        } elseif ($metodo === 'POST') {
            $resp = $ctrl->store($payload);
        } elseif ($metodo === 'PUT' || $metodo === 'PATCH') {
            $targetId = $id ?: ($payload['id'] ?? null);
            $resp = $ctrl->update($targetId, $payload);
        } elseif ($metodo === 'DELETE') {
            $targetId = $id ?: ($payload['id'] ?? null);
            $resp = $ctrl->destroy($targetId);
        }
        break;

    case 'ventas':
        $ctrl = new VentaController($conexion);
        if ($metodo === 'GET') {
            $resp = $id ? $ctrl->show($id) : $ctrl->index($queryParams);
        } elseif ($metodo === 'POST') {
            $resp = $ctrl->store($payload);
        } elseif ($metodo === 'DELETE') {
            $targetId = $id ?: ($payload['id'] ?? null);
            $resp = $ctrl->destroy($targetId);
        }
        break;

    case 'compras':
        $ctrl = new CompraController($conexion);
        if ($metodo === 'GET') {
            $resp = $id ? $ctrl->show($id) : $ctrl->index();
        } elseif ($metodo === 'POST') {
            $resp = $ctrl->store($payload);
        }
        break;

    case 'usuarios':
        $ctrl = new UsuarioController($conexion);
        if ($subrecurso === 'login' || (isset($queryParams['accion']) && $queryParams['accion'] === 'login')) {
            $resp = $ctrl->login($payload);
        } elseif ($metodo === 'GET') {
            $resp = $id ? $ctrl->show($id) : $ctrl->index();
        } elseif ($metodo === 'POST') {
            $resp = $ctrl->store($payload);
        } elseif ($metodo === 'PUT' || $metodo === 'PATCH') {
            $targetId = $id ?: ($payload['id'] ?? null);
            $resp = $ctrl->update($targetId, $payload);
        } elseif ($metodo === 'DELETE') {
            $targetId = $id ?: ($payload['id'] ?? null);
            $resp = $ctrl->destroy($targetId);
        }
        break;

    case 'reportes':
        $ctrl = new ReporteController($conexion);
        $tipo = $subrecurso ?: ($queryParams['tipo'] ?? 'ventas');
        if ($tipo === 'categorias') {
            $resp = $ctrl->balanceCategorias();
        } elseif ($tipo === 'compras') {
            $resp = $ctrl->comprasProveedores();
        } else {
            $resp = $ctrl->ventasPorTienda();
        }
        break;

    default:
        responderJSON(["success" => false, "error" => "Módulo '$modulo' no encontrado en la API."], 404);
}

responderJSON($resp['data'], $resp['codigo']);
?>
