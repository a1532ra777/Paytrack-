<?php
/**
 * CRUD Clientes - Módulo de Solo Consulta
 * Especificación: "Crud clientes es solo consulta"
 */

require_once __DIR__ . '/conexion.php';

$metodo = $_SERVER['REQUEST_METHOD'];
$accion = isset($_GET['accion']) ? $_GET['accion'] : (isset($_POST['accion']) ? $_POST['accion'] : 'listar');

// Si se recibe por parámetro id directo en GET
$id = isset($_GET['id']) ? intval($_GET['id']) : null;
$dni = isset($_GET['dni']) ? trim($_GET['dni']) : null;
$buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : null;

// Restricción de solo consulta: bloquear intentos de mutación (POST de creación, PUT, DELETE)
if ($metodo === 'DELETE' || $metodo === 'PUT' || ($metodo === 'POST' && in_array($accion, ['crear', 'guardar', 'eliminar', 'actualizar', 'delete', 'update', 'insert']))) {
    responderJSON([
        "success" => false,
        "error" => "Operación no permitida. El módulo de Clientes está configurado exclusivamente para modo CONSULTA.",
        "modulo" => "clientes",
        "modo" => "solo_consulta"
    ], 405);
}

// 1. Obtener cliente por ID
if ($id) {
    $stmt = $conexion->prepare("SELECT id, nombre, dni, telefono, email, direccion, tipo_cliente, estado, created_at FROM clientes WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($cliente = $resultado->fetch_assoc()) {
        responderJSON([
            "success" => true,
            "data" => $cliente
        ]);
    } else {
        responderJSON([
            "success" => false,
            "error" => "Cliente no encontrado con ID: $id"
        ], 404);
    }
}

// 2. Obtener cliente por Cédula / DNI
if ($dni) {
    $stmt = $conexion->prepare("SELECT id, nombre, dni, telefono, email, direccion, tipo_cliente, estado, created_at FROM clientes WHERE dni = ?");
    $stmt->bind_param("s", $dni);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($cliente = $resultado->fetch_assoc()) {
        responderJSON([
            "success" => true,
            "data" => $cliente
        ]);
    } else {
        responderJSON([
            "success" => false,
            "error" => "Cliente no encontrado con Cédula/DNI: $dni"
        ], 404);
    }
}

// 3. Listar o Buscar clientes
if ($buscar) {
    $param = "%" . $buscar . "%";
    $stmt = $conexion->prepare("SELECT id, nombre, dni, telefono, email, direccion, tipo_cliente, estado, created_at FROM clientes WHERE nombre LIKE ? OR dni LIKE ? OR telefono LIKE ? OR email LIKE ? ORDER BY nombre ASC");
    $stmt->bind_param("ssss", $param, $param, $param, $param);
    $stmt->execute();
    $resultado = $stmt->get_result();
} else {
    $estado = isset($_GET['estado']) ? $_GET['estado'] : null;
    if ($estado) {
        $stmt = $conexion->prepare("SELECT id, nombre, dni, telefono, email, direccion, tipo_cliente, estado, created_at FROM clientes WHERE estado = ? ORDER BY id DESC");
        $stmt->bind_param("s", $estado);
        $stmt->execute();
        $resultado = $stmt->get_result();
    } else {
        $sql = "SELECT id, nombre, dni, telefono, email, direccion, tipo_cliente, estado, created_at FROM clientes ORDER BY id DESC";
        $resultado = $conexion->query($sql);
    }
}

$clientes = [];
while ($fila = $resultado->fetch_assoc()) {
    $clientes[] = $fila;
}

responderJSON([
    "success" => true,
    "total" => count($clientes),
    "modulo" => "clientes",
    "modo" => "solo_consulta",
    "data" => $clientes
]);
?>
