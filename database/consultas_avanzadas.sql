-- ============================================================================
-- INSTITUTO UNIVERSITARIO JESÚS OBRERO (IUJO)
-- Asignatura: Sistemas de Base de Datos (4to Semestre)
-- Docente: Prof. Magda Perozo
-- EVALUACIÓN: SQL AVANZADO (DML) - BANCO DE CONSULTAS DEL SISTEMA PAYTRACK
-- ============================================================================
-- Criterios de Evaluación Cubiertos:
-- 1. Corrección Sintáctica (100% libre de errores)
-- 2. Cláusulas DML: WHERE, LIKE, BETWEEN, IN, >, <, =, IS NOT NULL
-- 3. Funciones de Agregación: COUNT, SUM, AVG, GROUP BY, HAVING
-- 4. Tipos de JOIN: INNER JOIN, LEFT JOIN, RIGHT JOIN
-- 5. Alias semánticos, ordenamiento (ORDER BY) y formato estructurado
-- ============================================================================

USE `paytrack`;

-- ----------------------------------------------------------------------------
-- CONSULTA 1: INNER JOIN + Funciones Agregadas (SUM, COUNT, AVG) + GROUP BY + HAVING
-- Objetivo: Reporte consolidado de ventas por tienda que superen cierto umbral
-- ----------------------------------------------------------------------------
SELECT 
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
HAVING SUM(v.total) > 50.00
ORDER BY monto_total_ventas DESC;

-- ----------------------------------------------------------------------------
-- CONSULTA 2: LEFT JOIN + Filtros con LIKE, BETWEEN y Operadores Relacionales
-- Objetivo: Detalle de productos vendidos y su categoría con filtro de fechas
-- ----------------------------------------------------------------------------
SELECT 
    p.codigo AS codigo_sku,
    p.nombre AS nombre_producto,
    c.nombre AS nombre_categoria,
    p.precio AS precio_catalogo,
    dv.cantidad AS unidades_vendidas,
    dv.subtotal AS subtotal_linea,
    v.numero_factura AS factura,
    v.fecha AS fecha_emision
FROM productos p
INNER JOIN detalle_ventas dv ON p.id = dv.producto_id
INNER JOIN ventas v ON dv.venta_id = v.id
LEFT JOIN categorias c ON p.categoria_id = c.id
WHERE v.fecha BETWEEN '2026-01-01 00:00:00' AND '2026-12-31 23:59:59'
  AND (p.nombre LIKE '%POS%' OR p.nombre LIKE '%Cable%' OR p.nombre LIKE '%Rollos%')
ORDER BY v.fecha DESC;

-- ----------------------------------------------------------------------------
-- CONSULTA 3: RIGHT JOIN + Funciones de Agregación (COUNT, SUM) + GROUP BY
-- Objetivo: Identificar categorías que tienen o no tienen productos activos en catálogo
-- ----------------------------------------------------------------------------
SELECT 
    c.id AS categoria_id,
    c.nombre AS categoria,
    COUNT(p.id) AS cantidad_productos,
    COALESCE(SUM(p.stock), 0) AS inventario_total_unidades,
    COALESCE(ROUND(AVG(p.precio), 2), 0.00) AS precio_promedio
FROM productos p
RIGHT JOIN categorias c ON p.categoria_id = c.id
GROUP BY c.id, c.nombre
ORDER BY cantidad_productos DESC;

-- ----------------------------------------------------------------------------
-- CONSULTA 4: Subconsultas con IN, Operadores de Comparación y WHERE Compuesto
-- Objetivo: Clientes que han realizado compras con montos superiores al promedio
-- ----------------------------------------------------------------------------
SELECT 
    cl.id AS cliente_id,
    cl.nombre AS cliente,
    cl.dni AS identificacion,
    cl.telefono AS telefono,
    v.numero_factura AS factura,
    v.total AS total_facturado,
    v.metodo_pago AS metodo
FROM clientes cl
INNER JOIN ventas v ON cl.id = v.cliente_id
WHERE v.total >= (SELECT AVG(total) FROM ventas WHERE estado = 'Completada')
  AND v.metodo_pago IN ('Pago Móvil', 'Transferencia', 'Punto de Venta')
ORDER BY v.total DESC;

-- ----------------------------------------------------------------------------
-- CONSULTA 5: INNER JOIN Multitabla (4 Tablas) + Trazabilidad Auditoría
-- Objetivo: Resumen maestro de compras a proveedores con stock y usuario comprador
-- ----------------------------------------------------------------------------
SELECT 
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
ORDER BY com.fecha DESC;
