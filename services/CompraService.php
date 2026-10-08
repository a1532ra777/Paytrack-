<?php
/**
 * Capa de Servicios: CompraService
 * Reglas de negocio del sistema: Abastecimiento, costeo y recepción en inventario.
 */

class CompraService {
    private $db;

    public function __construct($conexion) {
        $this->db = $conexion;
    }

    public function listarCompras() {
        $sql = "SELECT c.*, t.nombre AS tienda_nombre, u.username AS usuario_nombre,
                (SELECT COUNT(*) FROM detalle_compras WHERE compra_id = c.id) AS total_items
                FROM compras c
                INNER JOIN tiendas t ON c.tienda_id = t.id
                LEFT JOIN users u ON c.usuario_id = u.id
                ORDER BY c.id DESC";
        $resultado = $this->db->query($sql);
        $compras = [];
        while ($row = $resultado->fetch_assoc()) {
            $compras[] = $row;
        }
        return $compras;
    }

    public function obtenerCompraPorId($id) {
        $stmt = $this->db->prepare("SELECT c.*, t.nombre AS tienda_nombre, u.username AS usuario_nombre 
                                    FROM compras c
                                    INNER JOIN tiendas t ON c.tienda_id = t.id
                                    LEFT JOIN users u ON c.usuario_id = u.id
                                    WHERE c.id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $compra = $stmt->get_result()->fetch_assoc();

        if (!$compra) {
            return null;
        }

        $stmtDet = $this->db->prepare("SELECT dc.*, p.codigo AS producto_codigo, p.nombre AS producto_nombre 
                                       FROM detalle_compras dc
                                       INNER JOIN productos p ON dc.producto_id = p.id
                                       WHERE dc.compra_id = ?");
        $stmtDet->bind_param("i", $id);
        $stmtDet->execute();
        $resDet = $stmtDet->get_result();

        $detalles = [];
        while ($det = $resDet->fetch_assoc()) {
            $detalles[] = $det;
        }
        $compra['items'] = $detalles;
        return $compra;
    }

    public function registrarCompra($payload) {
        $proveedor = trim($payload['proveedor'] ?? '');
        $rif_proveedor = trim($payload['rif_proveedor'] ?? '');
        $tienda_id = intval($payload['tienda_id'] ?? 1);
        $usuario_id = intval($payload['usuario_id'] ?? 1);
        $notas = trim($payload['notas'] ?? '');
        $items = $payload['items'] ?? [];

        if (empty($proveedor) || empty($items) || !is_array($items)) {
            throw new InvalidArgumentException("Proveedor y al menos un ítem son obligatorios.");
        }

        $numero_compra = !empty($payload['numero_compra'])
            ? trim($payload['numero_compra'])
            : 'COM-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

        $this->db->begin_transaction();

        try {
            $totalCompra = 0.0;
            $itemsProcesados = [];

            foreach ($items as $item) {
                $prodId = intval($item['producto_id'] ?? 0);
                $cant = intval($item['cantidad'] ?? 0);
                $precioUnitario = floatval($item['precio_unitario'] ?? 0.0);

                if ($prodId <= 0 || $cant <= 0 || $precioUnitario < 0) {
                    throw new InvalidArgumentException("Datos de producto inválidos en compras.");
                }

                $stmtP = $this->db->prepare("SELECT id, nombre FROM productos WHERE id = ?");
                $stmtP->bind_param("i", $prodId);
                $stmtP->execute();
                if ($stmtP->get_result()->num_rows === 0) {
                    throw new Exception("Producto ID $prodId no encontrado.");
                }

                $subtotal = $cant * $precioUnitario;
                $totalCompra += $subtotal;

                $itemsProcesados[] = [
                    "producto_id" => $prodId,
                    "cantidad" => $cant,
                    "precio_unitario" => $precioUnitario,
                    "subtotal" => $subtotal
                ];
            }

            // Insertar Cabecera
            $stmtC = $this->db->prepare("INSERT INTO compras (numero_compra, proveedor, rif_proveedor, tienda_id, usuario_id, total, estado, notas) 
                                        VALUES (?, ?, ?, ?, ?, ?, 'Completada', ?)");
            $stmtC->bind_param("sssiids", $numero_compra, $proveedor, $rif_proveedor, $tienda_id, $usuario_id, $totalCompra, $notas);
            $stmtC->execute();
            $compra_id = $stmtC->insert_id;

            // Insertar Detalle e Incrementar Stock
            $stmtDet = $this->db->prepare("INSERT INTO detalle_compras (compra_id, producto_id, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?)");
            $stmtStock = $this->db->prepare("UPDATE productos SET stock = stock + ?, estado = 'Disponible' WHERE id = ?");

            foreach ($itemsProcesados as $det) {
                $stmtDet->bind_param("iiidd", $compra_id, $det['producto_id'], $det['cantidad'], $det['precio_unitario'], $det['subtotal']);
                $stmtDet->execute();

                $stmtStock->bind_param("ii", $det['cantidad'], $det['producto_id']);
                $stmtStock->execute();
            }

            $this->db->commit();

            return [
                "id" => $compra_id,
                "numero_compra" => $numero_compra,
                "total" => $totalCompra,
                "items_registrados" => count($itemsProcesados)
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
}
?>
