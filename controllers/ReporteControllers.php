<?php
/**
 * Controlador de Reportes y Consultas SQL Avanzadas
 * Cumplimiento de la Rúbrica de la Prof. Magda Perozo (Sistemas de Base de Datos - 4to Semestre)
 */

class ReporteController {
    private $db;

    public function __construct($conexion) {
        $this->db = $conexion;
    }

    /**
     * Reporte 1: Ventas agrupadas por Tienda con INNER JOIN, SUM, COUNT, AVG, GROUP BY, HAVING
     */
    public function ventasPorTienda() {
        $sql = "SELECT 
                    t.id AS tienda_id,
                    t.nombre AS nombre_tienda,
                    t.rif AS rif_tienda,
                    COUNT(v.id) AS total_transacciones,
                    SUM(v.total) AS monto_total_ventas,
                    ROUND(AVG(v.total), 2) AS promedio_por_venta
                FROM tiendas t
                INNER JOIN ventas v ON t.id = v.tienda_id
                WHERE v.estado = 'Completada'
                GROUP BY t.id, t.nombre, t.rif
                HAVING SUM(v.total) > 0.00
                ORDER BY monto_total_ventas DESC";
        $res = $this->db->query($sql);
        $data = [];
        while ($r = $res->fetch_assoc()) {
            $data[] = $r;
        }
        return ["codigo" => 200, "data" => ["success" => true, "consulta" => "INNER JOIN + SUM + COUNT + AVG + GROUP BY + HAVING", "data" => $data]];
    }

    /**
     * Reporte 2: Productos y Categorías con RIGHT JOIN y funciones agregadas
     */
    public function balanceCategorias() {
        $sql = "SELECT 
                    c.id AS categoria_id,
                    c.nombre AS categoria,
                    COUNT(p.id) AS cantidad_productos,
                    COALESCE(SUM(p.stock), 0) AS inventario_total_unidades,
                    COALESCE(ROUND(AVG(p.precio), 2), 0.00) AS precio_promedio
                FROM productos p
                RIGHT JOIN categorias c ON p.categoria_id = c.id
                GROUP BY c.id, c.nombre
                ORDER BY cantidad_productos DESC";
        $res = $this->db->query($sql);
        $data = [];
        while ($r = $res->fetch_assoc()) {
            $data[] = $r;
        }
        return ["codigo" => 200, "data" => ["success" => true, "consulta" => "RIGHT JOIN + COUNT + SUM + AVG + GROUP BY", "data" => $data]];
    }

    /**
     * Reporte 3: Compras a proveedores con 4 JOINs
     */
    public function comprasProveedores() {
        $sql = "SELECT 
                    com.numero_compra AS orden_compra,
                    com.proveedor AS proveedor,
                    t.nombre AS tienda_receptora,
                    u.username AS usuario_responsable,
                    p.nombre AS articulo,
                    dc.cantidad AS unidades_recibidas,
                    dc.precio_unitario AS costo_unitario,
                    dc.subtotal AS costo_total_linea,
                    p.stock AS inventario_actual
                FROM compras com
                INNER JOIN tiendas t ON com.tienda_id = t.id
                INNER JOIN users u ON com.usuario_id = u.id
                INNER JOIN detalle_compras dc ON com.id = dc.compra_id
                INNER JOIN productos p ON dc.producto_id = p.id
                WHERE com.estado = 'Completada'
                ORDER BY com.fecha DESC";
        $res = $this->db->query($sql);
        $data = [];
        while ($r = $res->fetch_assoc()) {
            $data[] = $r;
        }
        return ["codigo" => 200, "data" => ["success" => true, "consulta" => "INNER JOIN Multitabla (4 tablas)", "data" => $data]];
    }
}
?>
