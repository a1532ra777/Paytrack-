<?php
/**
 * CRUD de Roles de Usuario
 * Módulo para gestionar roles y permisos en el sistema
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
        $stmt = $conexion->prepare("SELECT id, nombre, descripcion, estado, created_at FROM roles WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($rol = $resultado->fetch_assoc()) {
            responderJSON(["success" => true, "data" => $rol]);
        } else {
            responderJSON(["success" => false, "error" => "Rol no encontrado"], 404);
        }
    }

    $sql = "SELECT id, nombre, descripcion, estado, created_at FROM roles ORDER BY id ASC";
    $resultado = $conexion->query($sql);
    $roles = [];
    while ($row = $resultado->fetch_assoc()) {
        $roles[] = $row;
    }

    responderJSON(["success" => true, "total" => count($roles), "data" => $roles]);
}

// -------------------------------------------------------------
// CREAR ROL (POST)
// -------------------------------------------------------------
if (($metodo === 'POST' && !$accion) || $accion === 'crear' || $accion === 'guardar') {
    $nombre = isset($datos['nombre']) ? trim($datos['nombre']) : '';
    $descripcion = isset($datos['descripcion']) ? trim($datos['descripcion']) : '';
    $estado = isset($datos['estado']) && in_array($datos['estado'], ['Activo', 'Inactivo']) ? $datos['estado'] : 'Activo';

    if (empty($nombre)) {
        responderJSON(["success" => false, "error" => "El campo 'nombre' del rol es obligatorio."], 400);
    }

    // Verificar unicidad
    $stmtCheck = $conexion->prepare("SELECT id FROM roles WHERE nombre = ?");
    $stmtCheck->bind_param("s", $nombre);
    $stmtCheck->execute();
    if ($stmtCheck->get_result()->num_rows > 0) {
        responderJSON(["success" => false, "error" => "Ya existe un rol con el nombre '$nombre'."], 409);
    }

    $stmt = $conexion->prepare("INSERT INTO roles (nombre, descripcion, estado) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $nombre, $descripcion, $estado);

    if ($stmt->execute()) {
        responderJSON([
            "success" => true,
            "message" => "Rol creado exitosamente",
            "id" => $stmt->insert_id,
            "data" => [
                "id" => $stmt->insert_id,
                "nombre" => $nombre,
                "descripcion" => $descripcion,
                "estado" => $estado
            ]
        ], 201);
    } else {
        responderJSON(["success" => false, "error" => "Error al guardar el rol: " . $stmt->error], 500);
    }
}

// -------------------------------------------------------------
// ACTUALIZAR ROL (PUT / POST)
// -------------------------------------------------------------
if ($metodo === 'PUT' || $accion === 'actualizar' || $accion === 'editar') {
    $id = isset($datos['id']) ? intval($datos['id']) : (isset($_GET['id']) ? intval($_GET['id']) : null);
    $nombre = isset($datos['nombre']) ? trim($datos['nombre']) : '';
    $descripcion = isset($datos['descripcion']) ? trim($datos['descripcion']) : null;
    $estado = isset($datos['estado']) ? trim($datos['estado']) : null;

    if (!$id || empty($nombre)) {
        responderJSON(["success" => false, "error" => "Se requiere 'id' y 'nombre' para actualizar el rol."], 400);
    }

    // Verificar duplicado en otro ID
    $stmtCheck = $conexion->prepare("SELECT id FROM roles WHERE nombre = ? AND id != ?");
    $stmtCheck->bind_param("si", $nombre, $id);
    $stmtCheck->execute();
    if ($stmtCheck->get_result()->num_rows > 0) {
        responderJSON(["success" => false, "error" => "Ya existe otro rol con el nombre '$nombre'."], 409);
    }

    $stmt = $conexion->prepare("UPDATE roles SET nombre = ?, descripcion = COALESCE(?, descripcion), estado = COALESCE(?, estado) WHERE id = ?");
    $stmt->bind_param("sssi", $nombre, $descripcion, $estado, $id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows >= 0) {
            responderJSON(["success" => true, "message" => "Rol actualizado correctamente"]);
        } else {
            responderJSON(["success" => false, "error" => "No se realizaron cambios o el rol no existe."], 404);
        }
    } else {
        responderJSON(["success" => false, "error" => "Error al actualizar rol: " . $stmt->error], 500);
    }
}

// -------------------------------------------------------------
// ELIMINAR ROL (DELETE / POST)
// -------------------------------------------------------------
if ($metodo === 'DELETE' || $accion === 'eliminar') {
    $id = isset($datos['id']) ? intval($datos['id']) : (isset($_GET['id']) ? intval($_GET['id']) : null);

    if (!$id) {
        responderJSON(["success" => false, "error" => "Se requiere el 'id' del rol para eliminar."], 400);
    }

    // Evitar eliminar roles con usuarios asignados
    $stmtCheck = $conexion->prepare("SELECT id FROM users WHERE rol_id = ?");
    $stmtCheck->bind_param("i", $id);
    $stmtCheck->execute();
    if ($stmtCheck->get_result()->num_rows > 0) {
        responderJSON([
            "success" => false,
            "error" => "No se puede eliminar el rol porque tiene usuarios asociados. Reasigne los usuarios antes de continuar."
        ], 409);
    }

    $stmt = $conexion->prepare("DELETE FROM roles WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        responderJSON(["success" => true, "message" => "Rol eliminado correctamente"]);
    } else {
        responderJSON(["success" => false, "error" => "No se encontró el rol o ya fue eliminado."], 404);
    }
}

responderJSON(["success" => false, "error" => "Método o acción no reconocida"], 400);
?>
