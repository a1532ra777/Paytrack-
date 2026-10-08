<?php
/**
 * Capa de Servicios: VentaService
 * Reglas de negocio del sistema: Validación de stock, transacciones atómicas y facturación.
 */

class VentaService {
    private $db;

    public function __construct($conexion) {
        $this->db = $conexion;
    }

    public function listarVentas($filtros = []) {
        $where = [];
        $params = [];
        $types = "";

        if (!empty($filtros['cliente_id'])) {
            $where[] = "v.cliente_id = ?";
            $types .= "i";
            $params[] = intval($filtros['cliente_id']);
        }
        if (!empty($filtros['tienda_id'])) {
            $where[] = "v.tienda_id = ?";
            $types .= "i";
            $params[] = intval($filtros['tienda_id']);
        }
        if (!empty($filtros['estado'])) {
            $where[] = "v.estado = ?";
            $types .= "s";
            $params[] = trim($filtros['estado']);
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

        $stmt = $this->db->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $resultado = $stmt->get_result();

        $ventas = [];
        while ($row = $resultado->fetch_assoc()) {
            $ventas[] = $row;
        }
        return $ventas;
    }

    public function obtenerVentaPorId($id) {
        $stmt = $this->db->prepare("SELECT v.*, c.nombre AS cliente_nombre, c.dni AS cliente_dni, c.telefono AS cliente_telefono,
                                           t.nombre AS tienda_nombre, u.username AS vendedor_nombre
                                    FROM ventas v
                                    INNER JOIN clientes c ON v.cliente_id = c.id
                                    INNER JOIN tiendas t ON v.tienda_id = t.id
                                    LEFT JOIN users u ON v.usuario_id = u.id
                                    WHERE v.id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $venta = $stmt->get_result()->fetch_assoc();

        if (!$venta) {
            return null;
        }

        // Obtener ítems
        $stmtDet = $this->db->prepare("SELECT dv.*, p.codigo AS producto_codigo, p.nombre AS producto_nombre 
                                       FROM detalle_ventas dv
                                       INNER JOIN productos p ON dv.producto_id = p.id
                                       WHERE dv.venta_id = ?");
        $stmtDet->bind_param("i", $id);
        $stmtDet->execute();
        $resDet = $stmtDet->get_result();

        $items = [];
        while ($item = $resDet->fetch_assoc()) {
            $items[] = $item;
        }
        $venta['items'] = $items;
        return $venta;
    }

    public function procesarVenta($payload) {
        $cliente_id = isset($payload['cliente_id']) ? intval($payload['cliente_id']) : 0;
        $tienda_id = isset($payload['tienda_id']) ? intval($payload['tienda_id']) : 1;
        $usuario_id = isset($payload['usuario_id']) ? intval($payload['usuario_id']) : 1;
        $metodo_pago = isset($payload['metodo_pago']) ? trim($payload['metodo_pago']) : 'Pago Móvil';
        $referencia_pago = isset($payload['referencia_pago']) ? trim($payload['referencia_pago']) : null;
        $items = isset($payload['items']) && is_array($payload['items']) ? $payload['items'] : [];

        if ($cliente_id <= 0 || empty($items)) {
            throw new InvalidArgumentException("Se requiere 'cliente_id' válido y al menos un ítem en 'items'.");
        }

        // Validar cliente
        $stmtCl = $this->db->prepare("SELECT id, nombre, dni, telefono FROM clientes WHERE id = ?");
        $stmtCl->bind_param("i", $cliente_id);
        $stmtCl->execute();
        $cliente = $stmtCl->get_result()->fetch_assoc();
        if (!$cliente) {
            throw new Exception("Cliente no encontrado con ID $cliente_id.");
        }

        $numero_factura = !empty($payload['numero_factura']) 
            ? trim($payload['numero_factura']) 
            : 'FAC-' . str_pad((string)mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);

        // TRANSACCIÓN ATÓMICA
        $this->db->begin_transaction();

        try {
            $subtotalVenta = 0.0;
            $itemsProcesados = [];

            foreach ($items as $item) {
                $prodId = intval($item['producto_id'] ?? 0);
                $cant = intval($item['cantidad'] ?? 0);

                if ($prodId <= 0 || $cant <= 0) {
                    throw new InvalidArgumentException("Cada producto debe tener ID válido y cantidad mayor a cero.");
                }

                $stmtProd = $this->db->prepare("SELECT id, nombre, precio, stock FROM productos WHERE id = ? FOR UPDATE");
                $stmtProd->bind_param("i", $prodId);
                $stmtProd->execute();
                $producto = $stmtProd->get_result()->fetch_assoc();

                if (!$producto) {
                    throw new Exception("Producto ID $prodId no encontrado.");
                }

                if ($producto['stock'] < $cant) {
                    throw new Exception("Stock insuficiente para '{$producto['nombre']}'. Disponible: {$producto['stock']}, Solicitado: $cant.");
                }

                $precioUnitario = isset($item['precio_unitario']) ? floatval($item['precio_unitario']) : floatval($producto['precio']);
                $subtotalLinea = $cant * $precioUnitario;
                $subtotalVenta += $subtotalLinea;

                $itemsProcesados[] = [
                    "producto_id" => $prodId,
                    "cantidad" => $cant,
                    "precio_unitario" => $precioUnitario,
                    "subtotal" => $subtotalLinea
                ];
            }

            $totalVenta = $subtotalVenta;

            // Insertar Cabecera
            $stmtV = $this->db->prepare("INSERT INTO ventas (numero_factura, cliente_id, tienda_id, usuario_id, metodo_pago, referencia_pago, subtotal, total, estado) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Completada')");
            $stmtV->bind_param("siiissdd", $numero_factura, $cliente_id, $tienda_id, $usuario_id, $metodo_pago, $referencia_pago, $subtotalVenta, $totalVenta);
            $stmtV->execute();
            $venta_id = $stmtV->insert_id;

            // Insertar Detalles y Actualizar Stock
            $stmtDet = $this->db->prepare("INSERT INTO detalle_ventas (venta_id, producto_id, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?)");
            $stmtStock = $this->db->prepare("UPDATE productos SET stock = stock - ?, estado = CASE WHEN stock - ? <= 0 THEN 'Agotado' ELSE 'Disponible' END WHERE id = ?");

            foreach ($itemsProcesados as $det) {
                $stmtDet->bind_param("iiidd", $venta_id, $det['producto_id'], $det['cantidad'], $det['precio_unitario'], $det['subtotal']);
                $stmtDet->execute();

                $stmtStock->bind_param("iii", $det['cantidad'], $det['cantidad'], $det['producto_id']);
                $stmtStock->execute();
            }

            // Registrar en payments para concordancia histórica
            $refHist = $referencia_pago ?: $numero_factura;
            $fechaActual = date('Y-m-d H:i');
            $stmtPay = $this->db->prepare("INSERT INTO payments (client_name, dni, phone, method, amount, reference, status, datetime) VALUES (?, ?, ?, ?, ?, ?, 'Pagado', ?)");
            $stmtPay->bind_param("ssssdss", $cliente['nombre'], $cliente['dni'], $cliente['telefono'], $metodo_pago, $totalVenta, $refHist, $fechaActual);
            $stmtPay->execute();

            $this->db->commit();

            return [
                "id" => $venta_id,
                "numero_factura" => $numero_factura,
                "total" => $totalVenta,
                "items_procesados" => count($itemsProcesados)
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function anularVenta($id) {
        $this->db->begin_transaction();
        try {
            $stmtV = $this->db->prepare("SELECT * FROM ventas WHERE id = ? FOR UPDATE");
            $stmtV->bind_param("i", $id);
            $stmtV->execute();
            $venta = $stmtV->get_result()->fetch_assoc();

            if (!$venta) {
                throw new Exception("Venta no encontrada.");
            }
            if ($venta['estado'] === 'Anulada') {
                throw new Exception("La venta ya se encuentra anulada.");
            }

            // Revertir stock
            $stmtDet = $this->db->prepare("SELECT producto_id, cantidad FROM detalle_ventas WHERE venta_id = ?");
            $stmtDet->bind_param("i", $id);
            $stmtDet->execute();
            $detalles = $stmtDet->get_result();

            $stmtReponer = $this->db->prepare("UPDATE productos SET stock = stock + ?, estado = 'Disponible' WHERE id = ?");
            while ($row = $detalles->fetch_assoc()) {
                $stmtReponer->bind_param("ii", $row['cantidad'], $row['producto_id']);
                $stmtReponer->execute();
            }

            $stmtUp = $this->db->prepare("UPDATE ventas SET estado = 'Anulada' WHERE id = ?");
            $stmtUp->bind_param("i", $id);
            $stmtUp->execute();

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
}
?>
