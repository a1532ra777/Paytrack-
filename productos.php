<?php
/**
 * CRUD de Productos
 * Módulo para gestionar el catálogo e inventario en PayTrack
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
    $codigo = isset($_GET['codigo']) ? trim($_GET['codigo']) : null;

    if ($id || $codigo) {
        $sql = "SELECT p.*, c.nombre AS categoria_nombre, t.nombre AS tienda_nombre 
                FROM productos p
                LEFT JOIN categorias c ON p.categoria_id = c.id
                LEFT JOIN tiendas t ON p.tienda_id = t.id
                WHERE " . ($id ? "p.id = ?" : "p.codigo = ?");
        $stmt = $conexion->prepare($sql);
        if ($id) {
            $stmt->bind_param("i", $id);
        } else {
            $stmt->bind_param("s", $codigo);
        }
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($producto = $resultado->fetch_assoc()) {
            responderJSON(["success" => true, "data" => $producto]);
        } else {
            responderJSON(["success" => false, "error" => "Producto no encontrado"], 404);
        }
    }

    // Listado con filtros opcionales
    $categoria_id = isset($_GET['categoria_id']) ? intval($_GET['categoria_id']) : null;
    $tienda_id = isset($_GET['tienda_id']) ? intval($_GET['tienda_id']) : null;
    $buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : null;

    $where = [];
    $types = "";
    $params = [];

    if ($categoria_id) {
        $where[] = "p.categoria_id = ?";
        $types .= "i";
        $params[] = $categoria_id;
    }
    if ($tienda_id) {
        $where[] = "p.tienda_id = ?";
        $types .= "i";
        $params[] = $tienda_id;
    }
    if ($buscar) {
        $where[] = "(p.nombre LIKE ? OR p.codigo LIKE ?)";
        $types .= "ss";
        $like = "%$buscar%";
        $params[] = $like;
        $params[] = $like;
    }

    $sql = "SELECT p.*, c.nombre AS categoria_nombre, t.nombre AS tienda_nombre 
            FROM productos p
            LEFT JOIN categorias c ON p.categoria_id = c.id
            LEFT JOIN tiendas t ON p.tienda_id = t.id";

    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    $sql .= " ORDER BY p.id DESC";

    $stmt = $conexion->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $resultado = $stmt->get_result();

    $productos = [];
    while ($row = $resultado->fetch_assoc()) {
        $productos[] = $row;
    }

    responderJSON(["success" => true, "total" => count($productos), "data" => $productos]);
}

// -------------------------------------------------------------
// CREAR PRODUCTO (POST)
// -------------------------------------------------------------
if (($metodo === 'POST' && !$accion) || $accion === 'crear' || $accion === 'guardar') {
    $codigo = isset($datos['codigo']) ? trim($datos['codigo']) : '';
    $nombre = isset($datos['nombre']) ? trim($datos['nombre']) : '';
    $descripcion = isset($datos['descripcion']) ? trim($datos['descripcion']) : '';
    $categoria_id = isset($datos['categoria_id']) ? intval($datos['categoria_id']) : 0;
    $tienda_id = isset($datos['tienda_id']) && !empty($datos['tienda_id']) ? intval($datos['tienda_id']) : null;
    $precio = isset($datos['precio']) ? floatval($datos['precio']) : 0.00;
    $costo = isset($datos['costo']) ? floatval($datos['costo']) : 0.00;
    $stock = isset($datos['stock']) ? intval($datos['stock']) : 0;
    $stock_minimo = isset($datos['stock_minimo']) ? intval($datos['stock_minimo']) : 5;
    $imagen = isset($datos['imagen']) ? trim($datos['imagen']) : '';
    $estado = isset($datos['estado']) && in_array($datos['estado'], ['Disponible', 'Agotado', 'Inactivo']) ? $datos['estado'] : 'Disponible';

    if (empty($codigo) || empty($nombre) || $categoria_id <= 0) {
        responderJSON(["success" => false, "error" => "Los campos 'codigo', 'nombre' y 'categoria_id' son obligatorios."], 400);
    }

    // Validar unicidad del código
    $stmtCheck = $conexion->prepare("SELECT id FROM productos WHERE codigo = ?");
    $stmtCheck->bind_param("s", $codigo);
    $stmtCheck->execute();
    if ($stmtCheck->get_result()->num_rows > 0) {
        responderJSON(["success" => false, "error" => "Ya existe un producto con el código '$codigo'."], 409);
    }

    // Validar categoría existente
    $stmtCat = $conexion->prepare("SELECT id FROM categorias WHERE id = ?");
    $stmtCat->bind_param("i", $categoria_id);
    $stmtCat->execute();
    if ($stmtCat->get_result()->num_rows === 0) {
        responderJSON(["success" => false, "error" => "La categoría especificada no existe."], 404);
    }

    $stmt = $conexion->prepare("INSERT INTO productos (codigo, nombre, descripcion, categoria_id, tienda_id, precio, costo, stock, stock_minimo, imagen, estado) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssiiddiiss", $codigo, $nombre, $descripcion, $categoria_id, $tienda_id, $precio, $costo, $stock, $stock_minimo, $imagen, $estado);

    if ($stmt->execute()) {
        responderJSON([
            "success" => true,
            "message" => "Producto creado exitosamente",
            "id" => $stmt->insert_id,
            "data" => [
                "id" => $stmt->insert_id,
                "codigo" => $codigo,
                "nombre" => $nombre,
                "precio" => $precio,
                "stock" => $stock,
                "estado" => $estado
            ]
        ], 201);
    } else {
        responderJSON(["success" => false, "error" => "Error al registrar producto: " . $stmt->error], 500);
    }
}

// -------------------------------------------------------------
// ACTUALIZAR PRODUCTO (PUT / POST)
// -------------------------------------------------------------
if ($metodo === 'PUT' || $accion === 'actualizar' || $accion === 'editar') {
    $id = isset($datos['id']) ? intval($datos['id']) : (isset($_GET['id']) ? intval($_GET['id']) : null);

    if (!$id) {
        responderJSON(["success" => false, "error" => "Se requiere 'id' del producto para actualizar."], 400);
    }

    // Verificar existencia del producto
    $stmtProd = $conexion->prepare("SELECT * FROM productos WHERE id = ?");
    $stmtProd->bind_param("i", $id);
    $stmtProd->execute();
    $prodActual = $stmtProd->get_result()->fetch_assoc();
    if (!$prodActual) {
        responderJSON(["success" => false, "error" => "Producto no encontrado."], 404);
    }

    $codigo = isset($datos['codigo']) ? trim($datos['codigo']) : $prodActual['codigo'];
    $nombre = isset($datos['nombre']) ? trim($datos['nombre']) : $prodActual['nombre'];
    $descripcion = isset($datos['descripcion']) ? trim($datos['descripcion']) : $prodActual['descripcion'];
    $categoria_id = isset($datos['categoria_id']) ? intval($datos['categoria_id']) : $prodActual['categoria_id'];
    $tienda_id = isset($datos['tienda_id']) ? (empty($datos['tienda_id']) ? null : intval($datos['tienda_id'])) : $prodActual['tienda_id'];
    $precio = isset($datos['precio']) ? floatval($datos['precio']) : $prodActual['precio'];
    $costo = isset($datos['costo']) ? floatval($datos['costo']) : $prodActual['costo'];
    $stock = isset($datos['stock']) ? intval($datos['stock']) : $prodActual['stock'];
    $stock_minimo = isset($datos['stock_minimo']) ? intval($datos['stock_minimo']) : $prodActual['stock_minimo'];
    $imagen = isset($datos['imagen']) ? trim($datos['imagen']) : $prodActual['imagen'];
    $estado = isset($datos['estado']) ? trim($datos['estado']) : $prodActual['estado'];

    // Validar código duplicado en otro producto
    $stmtCheck = $conexion->prepare("SELECT id FROM productos WHERE codigo = ? AND id != ?");
    $stmtCheck->bind_param("si", $codigo, $id);
    $stmtCheck->execute();
    if ($stmtCheck->get_result()->num_rows > 0) {
        responderJSON(["success" => false, "error" => "Ya existe otro producto con el código '$codigo'."], 409);
    }

    $stmt = $conexion->prepare("UPDATE productos SET codigo = ?, nombre = ?, descripcion = ?, categoria_id = ?, tienda_id = ?, precio = ?, costo = ?, stock = ?, stock_minimo = ?, imagen = ?, estado = ? WHERE id = ?");
    $stmt->bind_param("sssiiddiissi", $codigo, $nombre, $descripcion, $categoria_id, $tienda_id, $precio, $costo, $stock, $stock_minimo, $imagen, $estado, $id);

    if ($stmt->execute()) {
        responderJSON(["success" => true, "message" => "Producto actualizado exitosamente"]);
    } else {
        responderJSON(["success" => false, "error" => "Error al actualizar producto: " . $stmt->error], 500);
    }
}

// -------------------------------------------------------------
// ELIMINAR PRODUCTO (DELETE / POST)
// -------------------------------------------------------------
if ($metodo === 'DELETE' || $accion === 'eliminar') {
    $id = isset($datos['id']) ? intval($datos['id']) : (isset($_GET['id']) ? intval($_GET['id']) : null);

    if (!$id) {
        responderJSON(["success" => false, "error" => "Se requiere 'id' para eliminar el producto."], 400);
    }

    // Verificar si tiene ventas asociadas
    $stmtCheckV = $conexion->prepare("SELECT id FROM detalle_ventas WHERE producto_id = ? LIMIT 1");
    $stmtCheckV->bind_param("i", $id);
    $stmtCheckV->execute();
    if ($stmtCheckV->get_result()->num_rows > 0) {
        // En lugar de borrar físicamente y romper relaciones históricas, sugerir o aplicar desactivación
        responderJSON([
            "success" => false,
            "error" => "No se puede eliminar el producto porque tiene historial en ventas. Se sugiere cambiar su estado a 'Inactivo'."
        ], 409);
    }

    $stmt = $conexion->prepare("DELETE FROM productos WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        responderJSON(["success" => true, "message" => "Producto eliminado exitosamente"]);
    } else {
        responderJSON(["success" => false, "error" => "No se encontró el producto o ya fue eliminado."], 404);
    }
}

responderJSON(["success" => false, "error" => "Método o acción no válida"], 400);
?>
