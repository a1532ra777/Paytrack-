<?php
/**
 * CRUD de Categorías de Productos
 * Módulo para gestionar las categorías de catálogo en PayTrack
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
        $stmt = $conexion->prepare("SELECT id, nombre, descripcion, estado, created_at FROM categorias WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($categoria = $resultado->fetch_assoc()) {
            responderJSON(["success" => true, "data" => $categoria]);
        } else {
            responderJSON(["success" => false, "error" => "Categoría no encontrada"], 404);
        }
    }

    $sql = "SELECT id, nombre, descripcion, estado, created_at FROM categorias ORDER BY nombre ASC";
    $resultado = $conexion->query($sql);
    $categorias = [];
    while ($row = $resultado->fetch_assoc()) {
        $categorias[] = $row;
    }

    responderJSON(["success" => true, "total" => count($categorias), "data" => $categorias]);
}

// -------------------------------------------------------------
// CREAR CATEGORÍA (POST)
// -------------------------------------------------------------
if (($metodo === 'POST' && !$accion) || $accion === 'crear' || $accion === 'guardar') {
    $nombre = isset($datos['nombre']) ? trim($datos['nombre']) : '';
    $descripcion = isset($datos['descripcion']) ? trim($datos['descripcion']) : '';
    $estado = isset($datos['estado']) && in_array($datos['estado'], ['Activo', 'Inactivo']) ? $datos['estado'] : 'Activo';

    if (empty($nombre)) {
        responderJSON(["success" => false, "error" => "El campo 'nombre' de categoría es requerido."], 400);
    }

    // Verificar si ya existe
    $stmtCheck = $conexion->prepare("SELECT id FROM categorias WHERE nombre = ?");
    $stmtCheck->bind_param("s", $nombre);
    $stmtCheck->execute();
    if ($stmtCheck->get_result()->num_rows > 0) {
        responderJSON(["success" => false, "error" => "Ya existe una categoría llamada '$nombre'."], 409);
    }

    $stmt = $conexion->prepare("INSERT INTO categorias (nombre, descripcion, estado) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $nombre, $descripcion, $estado);

    if ($stmt->execute()) {
        responderJSON([
            "success" => true,
            "message" => "Categoría creada con éxito",
            "id" => $stmt->insert_id,
            "data" => [
                "id" => $stmt->insert_id,
                "nombre" => $nombre,
                "descripcion" => $descripcion,
                "estado" => $estado
            ]
        ], 201);
    } else {
        responderJSON(["success" => false, "error" => "Error al guardar categoría: " . $stmt->error], 500);
    }
}

// -------------------------------------------------------------
// ACTUALIZAR CATEGORÍA (PUT / POST)
// -------------------------------------------------------------
if ($metodo === 'PUT' || $accion === 'actualizar' || $accion === 'editar') {
    $id = isset($datos['id']) ? intval($datos['id']) : (isset($_GET['id']) ? intval($_GET['id']) : null);
    $nombre = isset($datos['nombre']) ? trim($datos['nombre']) : '';
    $descripcion = isset($datos['descripcion']) ? trim($datos['descripcion']) : null;
    $estado = isset($datos['estado']) ? trim($datos['estado']) : 'Activo';

    if (!$id || empty($nombre)) {
        responderJSON(["success" => false, "error" => "Se requiere 'id' y 'nombre' para actualizar la categoría."], 400);
    }

    // Verificar nombre duplicado en otra categoría
    $stmtCheck = $conexion->prepare("SELECT id FROM categorias WHERE nombre = ? AND id != ?");
    $stmtCheck->bind_param("si", $nombre, $id);
    $stmtCheck->execute();
    if ($stmtCheck->get_result()->num_rows > 0) {
        responderJSON(["success" => false, "error" => "Ya existe otra categoría con el nombre '$nombre'."], 409);
    }

    $stmt = $conexion->prepare("UPDATE categorias SET nombre = ?, descripcion = ?, estado = ? WHERE id = ?");
    $stmt->bind_param("sssi", $nombre, $descripcion, $estado, $id);

    if ($stmt->execute()) {
        responderJSON(["success" => true, "message" => "Categoría actualizada correctamente"]);
    } else {
        responderJSON(["success" => false, "error" => "Error al actualizar categoría: " . $stmt->error], 500);
    }
}

// -------------------------------------------------------------
// ELIMINAR CATEGORÍA (DELETE / POST)
// -------------------------------------------------------------
if ($metodo === 'DELETE' || $accion === 'eliminar') {
    $id = isset($datos['id']) ? intval($datos['id']) : (isset($_GET['id']) ? intval($_GET['id']) : null);

    if (!$id) {
        responderJSON(["success" => false, "error" => "Se requiere 'id' de la categoría para eliminar."], 400);
    }

    // Comprobar si tiene productos dependientes
    $stmtCheck = $conexion->prepare("SELECT id FROM productos WHERE categoria_id = ? LIMIT 1");
    $stmtCheck->bind_param("i", $id);
    $stmtCheck->execute();
    if ($stmtCheck->get_result()->num_rows > 0) {
        responderJSON([
            "success" => false,
            "error" => "No se puede eliminar la categoría porque tiene productos asignados."
        ], 409);
    }

    $stmt = $conexion->prepare("DELETE FROM categorias WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        responderJSON(["success" => true, "message" => "Categoría eliminada con éxito"]);
    } else {
        responderJSON(["success" => false, "error" => "No se encontró la categoría o ya fue eliminada."], 404);
    }
}

responderJSON(["success" => false, "error" => "Método o acción no válida"], 400);
?>
