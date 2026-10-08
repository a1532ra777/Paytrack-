<?php
/**
 * CRUD de Ventas - Módulo Principal (Entrega Final)
 * Gestión completa de transacciones comerciales, facturación, control de stock y pagos
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
    $factura = isset($_GET['factura']) ? trim($_GET['factura']) : null;

    if ($id || $factura) {
        $sql = "SELECT v.*, c.nombre AS cliente_nombre, c.dni AS cliente_dni, c.telefono AS cliente_telefono,
                       t.nombre AS tienda_nombre, u.username AS vendedor_nombre
                FROM ventas v
                INNER JOIN clientes c ON v.cliente_id = c.id
                INNER JOIN tiendas t ON v.tienda_id = t.id
                LEFT JOIN users u ON v.usuario_id = u.id
                WHERE " . ($id ? "v.id = ?" : "v.numero_factura = ?");
        $stmt = $conexion->prepare($sql);
        if ($id) {
            $stmt->bind_param("i", $id);
        } else {
            $stmt->bind_param("s", $factura);
        }
        $stmt->execute();
        $venta = $stmt->get_result()->fetch_assoc();

        if (!$venta) {
            responderJSON(["success" => false, "error" => "Venta no encontrada"], 404);
        }

        // Obtener detalle de productos vendidos
        $sqlDet = "SELECT dv.*, p.codigo AS producto_codigo, p.nombre AS producto_nombre 
                   FROM detalle_ventas dv
                   INNER JOIN productos p ON dv.producto_id = p.id
                   WHERE dv.venta_id = ?";
        $stmtDet = $conexion->prepare($sqlDet);
        $stmtDet->bind_param("i", $venta['id']);
        $stmtDet->execute();
        $resDet = $stmtDet->get_result();

        $detalles = [];
        while ($det = $resDet->fetch_assoc()) {
            $detalles[] = $det;
        }

        $venta['items'] = $detalles;
        responderJSON(["success" => true, "data" => $venta]);
    }

    // Listado de ventas con filtros
    $cliente_id = isset($_GET['cliente_id']) ? intval($_GET['cliente_id']) : null;
    $tienda_id = isset($_GET['tienda_id']) ? intval($_GET['tienda_id']) : null;
    $estado = isset($_GET['estado']) ? trim($_GET['estado']) : null;

    $where = [];
    $types = "";
    $params = [];

    if ($cliente_id) {
        $where[] = "v.cliente_id = ?";
        $types .= "i";
        $params[] = $cliente_id;
    }
    if ($tienda_id) {
        $where[] = "v.tienda_id = ?";
        $types .= "i";
        $params[] = $tienda_id;
    }
    if ($estado) {
        $where[] = "v.estado = ?";
        $types .= "s";
        $params[] = $estado;
    }

    $sql = "SELECT v.*, c.nombre AS cliente_nombre, c.dni AS cliente_dni, t.nombre AS tienda_nombre, u.username AS vendedor_nombre,
            (SELECT COUNT(*) FROM detalle_ventas WHERE venta_id = v.id) AS total_items
            FROM ventas v
            INNER JOIN clientes c ON v.cliente_id = c.id
            INNER JOIN tiendas t ON v.tienda_id = t.id
            LEFT JOIN users u ON v.usuario_id = u.id";

    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    $sql .= " ORDER BY v.id DESC";

    $stmt = $conexion->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $resultado = $stmt->get_result();

    $ventas = [];
    while ($row = $resultado->fetch_assoc()) {
        $ventas[] = $row;
    }

    responderJSON(["success" => true, "total" => count($ventas), "data" => $ventas]);
}

// -------------------------------------------------------------
// REGISTRAR VENTA (POST) - Transaccional con Descuento de Stock
// -------------------------------------------------------------
if (($metodo === 'POST' && !$accion) || $accion === 'crear' || $accion === 'guardar') {
    $cliente_id = isset($datos['cliente_id']) ? intval($datos['cliente_id']) : 0;
    $tienda_id = isset($datos['tienda_id']) ? intval($datos['tienda_id']) : 1;
    $usuario_id = isset($datos['usuario_id']) ? intval($datos['usuario_id']) : 1;
    $metodo_pago = isset($datos['metodo_pago']) ? trim($datos['metodo_pago']) : 'Pago Móvil';
    $referencia_pago = isset($datos['referencia_pago']) ? trim($datos['referencia_pago']) : null;
    $items = isset($datos['items']) && is_array($datos['items']) ? $datos['items'] : [];

    if ($cliente_id <= 0 || empty($items)) {
        responderJSON([
            "success" => false,
            "error" => "Se requiere un 'cliente_id' válido y la lista de 'items' (productos) a facturar."
        ], 400);
    }

    // Verificar cliente
    $stmtCl = $conexion->prepare("SELECT id, nombre, dni, telefono FROM clientes WHERE id = ?");
    $stmtCl->bind_param("i", $cliente_id);
    $stmtCl->execute();
    $cliente = $stmtCl->get_result()->fetch_assoc();
    if (!$cliente) {
        responderJSON(["success" => false, "error" => "Cliente no encontrado."], 404);
    }

    // Generar correlativo de factura
    $numero_factura = isset($datos['numero_factura']) && !empty($datos['numero_factura'])
        ? trim($datos['numero_factura'])
        : 'FAC-' . str_pad((string)mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);

    // Iniciar Transacción
    $conexion->begin_transaction();

    try {
        $subtotalVenta = 0.0;
        $itemsProcesados = [];

        // Validar existencias de cada producto
        foreach ($items as $item) {
            $producto_id = isset($item['producto_id']) ? intval($item['producto_id']) : 0;
            $cantidad = isset($item['cantidad']) ? intval($item['cantidad']) : 0;

            if ($producto_id <= 0 || $cantidad <= 0) {
                throw new Exception("Cada ítem debe tener un producto_id válido y una cantidad mayor a cero.");
            }

            // Consultar producto y bloquear fila (FOR UPDATE)
            $stmtProd = $conexion->prepare("SELECT id, nombre, precio, stock FROM productos WHERE id = ? FOR UPDATE");
            $stmtProd->bind_param("i", $producto_id);
            $stmtProd->execute();
            $producto = $stmtProd->get_result()->fetch_assoc();

            if (!$producto) {
                throw new Exception("El producto con ID $producto_id no existe.");
            }

            // Validar stock disponible
            if ($producto['stock'] < $cantidad) {
                throw new Exception("Stock insuficiente para el producto '{$producto['nombre']}'. Stock actual: {$producto['stock']}, Solicitado: $cantidad.");
            }

            $precio_unitario = isset($item['precio_unitario']) ? floatval($item['precio_unitario']) : floatval($producto['precio']);
            $subtotalItem = $cantidad * $precio_unitario;
            $subtotalVenta += $subtotalItem;

            $itemsProcesados[] = [
                "producto_id" => $producto_id,
                "cantidad" => $cantidad,
                "precio_unitario" => $precio_unitario,
                "subtotal" => $subtotalItem,
                "stock_actual" => $producto['stock']
            ];
        }

        $impuesto = 0.00; // Si aplica IVA puede configurarse aquí
        $totalVenta = $subtotalVenta + $impuesto;

        // 1. Insertar Cabecera de Venta
        $stmtInsV = $conexion->prepare("INSERT INTO ventas (numero_factura, cliente_id, tienda_id, usuario_id, metodo_pago, referencia_pago, subtotal, impuesto, total, estado) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Completada')");
        $stmtInsV->bind_param("siiissddd", $numero_factura, $cliente_id, $tienda_id, $usuario_id, $metodo_pago, $referencia_pago, $subtotalVenta, $impuesto, $totalVenta);
        if (!$stmtInsV->execute()) {
            throw new Exception("Error al registrar factura: " . $stmtInsV->error);
        }

        $venta_id = $stmtInsV->insert_id;

        // 2. Insertar Detalle de Ventas y Descontar Stock
        $stmtInsDet = $conexion->prepare("INSERT INTO detalle_ventas (venta_id, producto_id, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?)");
        $stmtDescStock = $conexion->prepare("UPDATE productos SET stock = stock - ?, estado = CASE WHEN stock - ? <= 0 THEN 'Agotado' ELSE 'Disponible' END WHERE id = ?");

        foreach ($itemsProcesados as $det) {
            $stmtInsDet->bind_param("iiidd", $venta_id, $det['producto_id'], $det['cantidad'], $det['precio_unitario'], $det['subtotal']);
            if (!$stmtInsDet->execute()) {
                throw new Exception("Error al guardar detalle de producto ID " . $det['producto_id']);
            }

            // Descontar inventario
            $stmtDescStock->bind_param("iii", $det['cantidad'], $det['cantidad'], $det['producto_id']);
            if (!$stmtDescStock->execute()) {
                throw new Exception("Error al descontar stock del producto ID " . $det['producto_id']);
            }
        }

        // 3. Registrar en tabla payments para histórico cruzado de PayTrack
        $refHist = $referencia_pago ? $referencia_pago : $numero_factura;
        $fechaActual = date('Y-m-d H:i');
        $stmtPay = $conexion->prepare("INSERT INTO payments (client_name, dni, phone, method, amount, reference, status, datetime) VALUES (?, ?, ?, ?, ?, ?, 'Pagado', ?)");
        $stmtPay->bind_param("ssssdss", $cliente['nombre'], $cliente['dni'], $cliente['telefono'], $metodo_pago, $totalVenta, $refHist, $fechaActual);
        $stmtPay->execute();

        $conexion->commit();

        responderJSON([
            "success" => true,
            "message" => "Venta procesada con éxito y stock actualizado",
            "id" => $venta_id,
            "numero_factura" => $numero_factura,
            "total" => $totalVenta,
            "items_vendidos" => count($itemsProcesados)
        ], 201);

    } catch (Exception $e) {
        $conexion->rollback();
        responderJSON(["success" => false, "error" => $e->getMessage()], 400);
    }
}

// -------------------------------------------------------------
// ANULAR VENTA (POST con accion=anular)
// -------------------------------------------------------------
if ($accion === 'anular') {
    $id = isset($datos['id']) ? intval($datos['id']) : (isset($_GET['id']) ? intval($_GET['id']) : null);

    if (!$id) {
        responderJSON(["success" => false, "error" => "Se requiere 'id' de la venta para anular."], 400);
    }

    $conexion->begin_transaction();

    try {
        $stmtV = $conexion->prepare("SELECT * FROM ventas WHERE id = ? FOR UPDATE");
        $stmtV->bind_param("i", $id);
        $stmtV->execute();
        $venta = $stmtV->get_result()->fetch_assoc();

        if (!$venta) {
            throw new Exception("Venta no encontrada.");
        }
        if ($venta['estado'] === 'Anulada') {
            throw new Exception("La venta ya se encuentra anulada.");
        }

        // Devolver productos al stock
        $stmtDet = $conexion->prepare("SELECT producto_id, cantidad FROM detalle_ventas WHERE venta_id = ?");
        $stmtDet->bind_param("i", $id);
        $stmtDet->execute();
        $detalles = $stmtDet->get_result();

        $stmtReponer = $conexion->prepare("UPDATE productos SET stock = stock + ?, estado = 'Disponible' WHERE id = ?");

        while ($row = $detalles->fetch_assoc()) {
            $stmtReponer->bind_param("ii", $row['cantidad'], $row['producto_id']);
            $stmtReponer->execute();
        }

        // Marcar venta como anulada
        $stmtUp = $conexion->prepare("UPDATE ventas SET estado = 'Anulada' WHERE id = ?");
        $stmtUp->bind_param("i", $id);
        $stmtUp->execute();

        $conexion->commit();

        responderJSON(["success" => true, "message" => "Venta anulada exitosamente y stock reincorporado al inventario"]);

    } catch (Exception $e) {
        $conexion->rollback();
        responderJSON(["success" => false, "error" => $e->getMessage()], 400);
    }
}

responderJSON(["success" => false, "error" => "Método o acción no válida"], 400);
?>
