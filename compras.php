<?php
/**
 * CRUD de Compras (Gestión de Abastecimiento e Inventario)
 * Soporta transacciones atómicas e incremento automático de stock
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
        // Cabecera de la compra
        $sql = "SELECT c.*, t.nombre AS tienda_nombre, u.username AS usuario_nombre 
                FROM compras c
                INNER JOIN tiendas t ON c.tienda_id = t.id
                LEFT JOIN users u ON c.usuario_id = u.id
                WHERE c.id = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $compra = $stmt->get_result()->fetch_assoc();

        if (!$compra) {
            responderJSON(["success" => false, "error" => "Compra no encontrada"], 404);
        }

        // Detalle de productos de la compra
        $sqlDet = "SELECT dc.*, p.codigo AS producto_codigo, p.nombre AS producto_nombre 
                   FROM detalle_compras dc
                   INNER JOIN productos p ON dc.producto_id = p.id
                   WHERE dc.compra_id = ?";
        $stmtDet = $conexion->prepare($sqlDet);
        $stmtDet->bind_param("i", $id);
        $stmtDet->execute();
        $resDet = $stmtDet->get_result();

        $detalles = [];
        while ($det = $resDet->fetch_assoc()) {
            $detalles[] = $det;
        }

        $compra['items'] = $detalles;
        responderJSON(["success" => true, "data" => $compra]);
    }

    // Listado general de compras
    $sql = "SELECT c.*, t.nombre AS tienda_nombre, u.username AS usuario_nombre,
            (SELECT COUNT(*) FROM detalle_compras WHERE compra_id = c.id) AS total_items
            FROM compras c
            INNER JOIN tiendas t ON c.tienda_id = t.id
            LEFT JOIN users u ON c.usuario_id = u.id
            ORDER BY c.id DESC";
    $resultado = $conexion->query($sql);
    $compras = [];
    while ($row = $resultado->fetch_assoc()) {
        $compras[] = $row;
    }

    responderJSON(["success" => true, "total" => count($compras), "data" => $compras]);
}

// -------------------------------------------------------------
// REGISTRAR COMPRA (POST)
// -------------------------------------------------------------
if (($metodo === 'POST' && !$accion) || $accion === 'crear' || $accion === 'guardar') {
    $proveedor = isset($datos['proveedor']) ? trim($datos['proveedor']) : '';
    $rif_proveedor = isset($datos['rif_proveedor']) ? trim($datos['rif_proveedor']) : null;
    $tienda_id = isset($datos['tienda_id']) ? intval($datos['tienda_id']) : 1;
    $usuario_id = isset($datos['usuario_id']) ? intval($datos['usuario_id']) : 1;
    $notas = isset($datos['notas']) ? trim($datos['notas']) : null;
    $items = isset($datos['items']) && is_array($datos['items']) ? $datos['items'] : [];

    if (empty($proveedor) || empty($items)) {
        responderJSON([
            "success" => false, 
            "error" => "El campo 'proveedor' y al menos un producto en 'items' son obligatorios para procesar la compra."
        ], 400);
    }

    // Generar número de compra único si no se envía
    $numero_compra = isset($datos['numero_compra']) && !empty($datos['numero_compra']) 
        ? trim($datos['numero_compra']) 
        : 'COM-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

    // Validar tienda existente
    $stmtT = $conexion->prepare("SELECT id FROM tiendas WHERE id = ?");
    $stmtT->bind_param("i", $tienda_id);
    $stmtT->execute();
    if ($stmtT->get_result()->num_rows === 0) {
        responderJSON(["success" => false, "error" => "La tienda especificada no existe."], 404);
    }

    // INICIAR TRANSACCIÓN ATÓMICA
    $conexion->begin_transaction();

    try {
        $totalCompra = 0.0;
        $itemsProcesados = [];

        // Validar y calcular productos
        foreach ($items as $item) {
            $producto_id = isset($item['producto_id']) ? intval($item['producto_id']) : 0;
            $cantidad = isset($item['cantidad']) ? intval($item['cantidad']) : 0;
            $precio_unitario = isset($item['precio_unitario']) ? floatval($item['precio_unitario']) : 0.0;

            if ($producto_id <= 0 || $cantidad <= 0 || $precio_unitario < 0) {
                throw new Exception("Datos de ítem inválidos. Verifique producto_id, cantidad y precio unitario.");
            }

            // Verificar existencia del producto
            $stmtP = $conexion->prepare("SELECT id, nombre, stock FROM productos WHERE id = ?");
            $stmtP->bind_param("i", $producto_id);
            $stmtP->execute();
            $resP = $stmtP->get_result()->fetch_assoc();
            if (!$resP) {
                throw new Exception("El producto con ID $producto_id no existe en el catálogo.");
            }

            $subtotal = $cantidad * $precio_unitario;
            $totalCompra += $subtotal;

            $itemsProcesados[] = [
                "producto_id" => $producto_id,
                "cantidad" => $cantidad,
                "precio_unitario" => $precio_unitario,
                "subtotal" => $subtotal
            ];
        }

        // Insertar cabecera de compra
        $stmtInsC = $conexion->prepare("INSERT INTO compras (numero_compra, proveedor, rif_proveedor, tienda_id, usuario_id, total, estado, notas) 
                                       VALUES (?, ?, ?, ?, ?, ?, 'Completada', ?)");
        $stmtInsC->bind_param("sssiids", $numero_compra, $proveedor, $rif_proveedor, $tienda_id, $usuario_id, $totalCompra, $notas);
        if (!$stmtInsC->execute()) {
            throw new Exception("Error al insertar cabecera de compra: " . $stmtInsC->error);
        }

        $compra_id = $stmtInsC->insert_id;

        // Insertar detalles e incrementar stock
        $stmtInsDet = $conexion->prepare("INSERT INTO detalle_compras (compra_id, producto_id, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?)");
        $stmtStock = $conexion->prepare("UPDATE productos SET stock = stock + ? WHERE id = ?");

        foreach ($itemsProcesados as $det) {
            $stmtInsDet->bind_param("iiidd", $compra_id, $det['producto_id'], $det['cantidad'], $det['precio_unitario'], $det['subtotal']);
            if (!$stmtInsDet->execute()) {
                throw new Exception("Error al guardar detalle de producto ID " . $det['producto_id']);
            }

            // Incrementar stock automáticamente
            $stmtStock->bind_param("ii", $det['cantidad'], $det['producto_id']);
            if (!$stmtStock->execute()) {
                throw new Exception("Error al actualizar inventario del producto ID " . $det['producto_id']);
            }
        }

        $conexion->commit();

        responderJSON([
            "success" => true,
            "message" => "Compra registrada exitosamente y stock de productos actualizado",
            "id" => $compra_id,
            "numero_compra" => $numero_compra,
            "total" => $totalCompra,
            "items_registrados" => count($itemsProcesados)
        ], 201);

    } catch (Exception $e) {
        $conexion->rollback();
        responderJSON(["success" => false, "error" => $e->getMessage()], 400);
    }
}

// -------------------------------------------------------------
// ANULAR COMPRA (PUT / POST con accion=anular)
// -------------------------------------------------------------
if ($accion === 'anular') {
    $id = isset($datos['id']) ? intval($datos['id']) : (isset($_GET['id']) ? intval($_GET['id']) : null);

    if (!$id) {
        responderJSON(["success" => false, "error" => "Se requiere 'id' de la compra para anular."], 400);
    }

    $conexion->begin_transaction();

    try {
        $stmtC = $conexion->prepare("SELECT * FROM compras WHERE id = ? FOR UPDATE");
        $stmtC->bind_param("i", $id);
        $stmtC->execute();
        $compra = $stmtC->get_result()->fetch_assoc();

        if (!$compra) {
            throw new Exception("Compra no encontrada.");
        }
        if ($compra['estado'] === 'Anulada') {
            throw new Exception("La compra ya se encuentra anulada previamente.");
        }

        // Obtener los productos para revertir el stock sumado
        $stmtDet = $conexion->prepare("SELECT producto_id, cantidad FROM detalle_compras WHERE compra_id = ?");
        $stmtDet->bind_param("i", $id);
        $stmtDet->execute();
        $detalles = $stmtDet->get_result();

        $stmtRevertir = $conexion->prepare("UPDATE productos SET stock = GREATEST(0, stock - ?) WHERE id = ?");

        while ($row = $detalles->fetch_assoc()) {
            $stmtRevertir->bind_param("ii", $row['cantidad'], $row['producto_id']);
            $stmtRevertir->execute();
        }

        // Marcar compra como Anulada
        $stmtUp = $conexion->prepare("UPDATE compras SET estado = 'Anulada' WHERE id = ?");
        $stmtUp->bind_param("i", $id);
        $stmtUp->execute();

        $conexion->commit();

        responderJSON(["success" => true, "message" => "Compra anulada y stock revertido correctamente"]);

    } catch (Exception $e) {
        $conexion->rollback();
        responderJSON(["success" => false, "error" => $e->getMessage()], 400);
    }
}

responderJSON(["success" => false, "error" => "Método o acción no válida"], 400);
?>
