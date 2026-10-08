<?php
/**
 * CRUD de Tiendas / Sucursales
 * Módulo para gestionar los puntos de venta de PayTrack
 */

require_once __DIR__ . '/conexion.php';

$metodo = $_SERVER['REQUEST_METHOD'];
$datos = obtenerDatosEntrada();
$accion = isset($_GET['accion']) ? $_GET['accion'] : (isset($datos['accion']) ? $datos['accion'] : null);

// -------------------------------------------------------------
// OBTENER O LISTAR (GET)
// -------------------------------------------------------------
if ($metodo === 'GET' || $accion === 'listar' || $accion === 'obtener') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : (isset($datos['id']) ? intval($datos['id']) : null);

    if ($id) {
        $stmt = $conexion->prepare("SELECT id, nombre, rif, direccion, telefono, estado, created_at FROM tiendas WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($tienda = $resultado->fetch_assoc()) {
            responderJSON(["success" => true, "data" => $tienda]);
        } else {
            responderJSON(["success" => false, "error" => "Tienda no encontrada"], 404);
        }
    }

    $sql = "SELECT id, nombre, rif, direccion, telefono, estado, created_at FROM tiendas ORDER BY id ASC";
    $resultado = $conexion->query($sql);
    $tiendas = [];
    while ($row = $resultado->fetch_assoc()) {
        $tiendas[] = $row;
    }

    responderJSON(["success" => true, "total" => count($tiendas), "data" => $tiendas]);
}

// -------------------------------------------------------------
// CREAR TIENDA (POST)
// -------------------------------------------------------------
if (($metodo === 'POST' && !$accion) || $accion === 'crear' || $accion === 'guardar') {
    $nombre = isset($datos['nombre']) ? trim($datos['nombre']) : '';
    $rif = isset($datos['rif']) ? trim($datos['rif']) : '';
    $direccion = isset($datos['direccion']) ? trim($datos['direccion']) : '';
    $telefono = isset($datos['telefono']) ? trim($datos['telefono']) : '';
    $estado = isset($datos['estado']) && in_array($datos['estado'], ['Activa', 'Inactiva']) ? $datos['estado'] : 'Activa';

    if (empty($nombre) || empty($rif)) {
        responderJSON(["success" => false, "error" => "Los campos 'nombre' y 'rif' son obligatorios."], 400);
    }

    // Comprobar si el RIF ya existe
    $stmtCheck = $conexion->prepare("SELECT id FROM tiendas WHERE rif = ?");
    $stmtCheck->bind_param("s", $rif);
    $stmtCheck->execute();
    if ($stmtCheck->get_result()->num_rows > 0) {
        responderJSON(["success" => false, "error" => "Ya existe una tienda con el RIF '$rif'."], 409);
    }

    $stmt = $conexion->prepare("INSERT INTO tiendas (nombre, rif, direccion, telefono, estado) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $nombre, $rif, $direccion, $telefono, $estado);

    if ($stmt->execute()) {
        responderJSON([
            "success" => true,
            "message" => "Tienda creada exitosamente",
            "id" => $stmt->insert_id,
            "data" => [
                "id" => $stmt->insert_id,
                "nombre" => $nombre,
                "rif" => $rif,
                "direccion" => $direccion,
                "telefono" => $telefono,
                "estado" => $estado
            ]
        ], 201);
    } else {
        responderJSON(["success" => false, "error" => "Error al guardar tienda: " . $stmt->error], 500);
    }
}

// -------------------------------------------------------------
// ACTUALIZAR TIENDA (PUT / POST)
// -------------------------------------------------------------
if ($metodo === 'PUT' || $accion === 'actualizar' || $accion === 'editar') {
    $id = isset($datos['id']) ? intval($datos['id']) : (isset($_GET['id']) ? intval($_GET['id']) : null);
    $nombre = isset($datos['nombre']) ? trim($datos['nombre']) : '';
    $rif = isset($datos['rif']) ? trim($datos['rif']) : '';
    $direccion = isset($datos['direccion']) ? trim($datos['direccion']) : '';
    $telefono = isset($datos['telefono']) ? trim($datos['telefono']) : '';
    $estado = isset($datos['estado']) ? trim($datos['estado']) : 'Activa';

    if (!$id || empty($nombre) || empty($rif)) {
        responderJSON(["success" => false, "error" => "Los campos 'id', 'nombre' y 'rif' son requeridos para actualizar."], 400);
    }

    // Verificar si el RIF le pertenece a otra tienda
    $stmtCheck = $conexion->prepare("SELECT id FROM tiendas WHERE rif = ? AND id != ?");
    $stmtCheck->bind_param("si", $rif, $id);
    $stmtCheck->execute();
    if ($stmtCheck->get_result()->num_rows > 0) {
        responderJSON(["success" => false, "error" => "Ya existe otra tienda registrada con el RIF '$rif'."], 409);
    }

    $stmt = $conexion->prepare("UPDATE tiendas SET nombre = ?, rif = ?, direccion = ?, telefono = ?, estado = ? WHERE id = ?");
    $stmt->bind_param("sssssi", $nombre, $rif, $direccion, $telefono, $estado, $id);

    if ($stmt->execute()) {
        responderJSON(["success" => true, "message" => "Tienda actualizada correctamente"]);
    } else {
        responderJSON(["success" => false, "error" => "Error al actualizar tienda: " . $stmt->error], 500);
    }
}

// -------------------------------------------------------------
// ELIMINAR TIENDA (DELETE / POST)
// -------------------------------------------------------------
if ($metodo === 'DELETE' || $accion === 'eliminar') {
    $id = isset($datos['id']) ? intval($datos['id']) : (isset($_GET['id']) ? intval($_GET['id']) : null);

    if (!$id) {
        responderJSON(["success" => false, "error" => "Se requiere 'id' para eliminar la tienda."], 400);
    }

    // Comprobar relaciones activas (ventas o compras)
    $stmtCheck = $conexion->prepare("SELECT id FROM ventas WHERE tienda_id = ? LIMIT 1");
    $stmtCheck->bind_param("i", $id);
    $stmtCheck->execute();
    if ($stmtCheck->get_result()->num_rows > 0) {
        responderJSON([
            "success" => false,
            "error" => "No se puede eliminar la tienda porque registra transacciones de ventas vinculadas."
        ], 409);
    }

    $stmt = $conexion->prepare("DELETE FROM tiendas WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        responderJSON(["success" => true, "message" => "Tienda eliminada con éxito"]);
    } else {
        responderJSON(["success" => false, "error" => "No se encontró la tienda o ya fue eliminada."], 404);
    }
}

responderJSON(["success" => false, "error" => "Método o acción no válida"], 400);
?>
