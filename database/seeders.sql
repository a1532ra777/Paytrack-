-- ========================================================
-- PayTrack - Seeders Oficiales (Datos Iniciales de Prueba)
-- Carpeta: /database/seeders.sql
-- ========================================================

USE `paytrack`;

-- Roles
INSERT INTO `roles` (`id`, `nombre`, `descripcion`, `estado`) VALUES
(1, 'Administrador', 'Acceso total y configuración del sistema', 'Activo'),
(2, 'Vendedor', 'Gestión de ventas y cobros en tienda', 'Activo'),
(3, 'Auditor', 'Consulta y reportes de transacciones', 'Activo'),
(4, 'Cliente', 'Rol público para usuarios registrados en portal', 'Activo')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

-- Tiendas
INSERT INTO `tiendas` (`id`, `nombre`, `rif`, `direccion`, `telefono`, `estado`) VALUES
(1, 'Sucursal Principal Caracas', 'J-30485921-0', 'Av. Francisco de Miranda, Torre Delta, Piso 2, Caracas', '0212-9051122', 'Activa'),
(2, 'Sucursal Valencia', 'J-40582910-3', 'Centro Comercial Sambil Valencia, Nivel Autopista, Local 45', '0241-8932211', 'Activa'),
(3, 'Sucursal Barquisimeto', 'J-50192837-1', 'Carrera 19 entre Calles 25 y 26, Barquisimeto, Lara', '0251-7894561', 'Activa')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

-- Categorías
INSERT INTO `categorias` (`id`, `nombre`, `descripcion`, `estado`) VALUES
(1, 'Electrónica', 'Dispositivos, gadgets y accesorios tecnológicos', 'Activo'),
(2, 'Servicios Digitales', 'Planes de suscripción, recargas y soporte digital', 'Activo'),
(3, 'Accesorios', 'Fundas, cables, cargadores y periféricos', 'Activo'),
(4, 'Papelería y Oficina', 'Artículos para oficinas y punto de venta', 'Activo')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

-- Usuarios
INSERT INTO `users` (`id`, `nombre_completo`, `username`, `password`, `email`, `role`, `rol_id`, `tienda_id`, `estado`) VALUES
(1, 'Fabricio Ramos', 'fabricio', 'a1532ra777', 'fabricio@paytrack.com', 'admin', 1, 1, 'Activo'),
(2, 'Fabiana Mendoza', 'Fabiana', 'a1532ra777', 'fabiana@paytrack.com', 'admin', 1, 2, 'Activo'),
(3, 'Carlos Vendedor', 'cvendedor', '123456', 'carlos@paytrack.com', 'vendedor', 2, 1, 'Activo')
ON DUPLICATE KEY UPDATE `username` = VALUES(`username`);

-- Clientes
INSERT INTO `clientes` (`id`, `nombre`, `dni`, `telefono`, `email`, `direccion`, `tipo_cliente`, `estado`) VALUES
(1, 'Aurora Santos', '32398546', '04226466851', 'aurora.santos@gmail.com', 'Urb. El Rosal, Av. Principal Casa #4', 'Natural', 'Activo'),
(2, 'Marcos Leonardo Pérez', '24879102', '04149988776', 'marcos.perez@hotmail.com', 'Calle Sucre, Los Teques, Miranda', 'Natural', 'Activo'),
(3, 'Inversiones Nova C.A.', 'J-40982314-5', '02125554433', 'contacto@inversionesnova.com', 'Bulevar Sabana Grande, Edif. Centro, Piso 4', 'Jurídico', 'Activo'),
(4, 'Valeria Sofía Blanco', '28114563', '04165551234', 'valeria.blanco@yahoo.es', 'Av. Bolívar, Valencia, Carabobo', 'Natural', 'Activo')
ON DUPLICATE KEY UPDATE `dni` = VALUES(`dni`);

-- Productos
INSERT INTO `productos` (`id`, `codigo`, `nombre`, `descripcion`, `categoria_id`, `tienda_id`, `precio`, `costo`, `stock`, `stock_minimo`, `imagen`, `estado`) VALUES
(1, 'PROD-001', 'Lector de Tarjetas POS Bluetooth', 'Dispositivo inalámbrico de cobro móvil y chip', 1, 1, 65.00, 40.00, 30, 5, 'pos_reader.png', 'Disponible'),
(2, 'PROD-002', 'Cable USB-C Carga Rápida 2m', 'Cable reforzado de alta velocidad 60W', 3, 1, 8.50, 3.50, 100, 15, 'cable_usbc.png', 'Disponible'),
(3, 'PROD-003', 'Impresora Térmica 58mm Facturas', 'Impresora portátil USB y Bluetooth para tickets', 1, 2, 45.00, 25.00, 15, 3, 'thermal_printer.png', 'Disponible'),
(4, 'PROD-004', 'Paquete de Rollos Térmicos (Pack 10)', 'Rollos de papel térmico 58mm de alta duración', 4, 1, 12.00, 6.00, 50, 10, 'rollos_termicos.png', 'Disponible'),
(5, 'PROD-005', 'Plan Suscripción Anual PayTrack Pro', 'Licencia anual con reportes avanzados y soporte', 2, 1, 120.00, 0.00, 999, 1, 'paytrack_pro.png', 'Disponible')
ON DUPLICATE KEY UPDATE `codigo` = VALUES(`codigo`);

-- Compras
INSERT INTO `compras` (`id`, `numero_compra`, `proveedor`, `rif_proveedor`, `tienda_id`, `usuario_id`, `total`, `estado`, `notas`, `fecha`) VALUES
(1, 'COM-2026-0001', 'Mayorista Tech Global C.A.', 'J-12345678-9', 1, 1, 1600.00, 'Completada', 'Adquisición de stock inicial de lectores POS', '2026-10-01 10:30:00'),
(2, 'COM-2026-0002', 'Suministros Ofimática 2020', 'J-87654321-0', 1, 1, 300.00, 'Completada', 'Reposición de rollos de papel térmico', '2026-10-03 14:00:00')
ON DUPLICATE KEY UPDATE `numero_compra` = VALUES(`numero_compra`);

-- Detalle de Compras
INSERT INTO `detalle_compras` (`id`, `compra_id`, `producto_id`, `cantidad`, `precio_unitario`, `subtotal`) VALUES
(1, 1, 1, 40, 40.00, 1600.00),
(2, 2, 4, 50, 6.00, 300.00)
ON DUPLICATE KEY UPDATE `compra_id` = VALUES(`compra_id`);

-- Ventas
INSERT INTO `ventas` (`id`, `numero_factura`, `cliente_id`, `tienda_id`, `usuario_id`, `metodo_pago`, `referencia_pago`, `subtotal`, `impuesto`, `total`, `estado`, `fecha`) VALUES
(1, 'FAC-0001', 1, 1, 1, 'Pago Móvil', '9546545454', 200.00, 0.00, 200.00, 'Completada', '2026-10-07 01:04:00'),
(2, 'FAC-0002', 3, 1, 2, 'Transferencia', 'TRF-8837192', 130.00, 0.00, 130.00, 'Completada', '2026-10-07 14:15:00')
ON DUPLICATE KEY UPDATE `numero_factura` = VALUES(`numero_factura`);

-- Detalle de Ventas
INSERT INTO `detalle_ventas` (`id`, `venta_id`, `producto_id`, `cantidad`, `precio_unitario`, `subtotal`) VALUES
(1, 1, 1, 2, 65.00, 130.00),
(2, 1, 2, 4, 8.50, 34.00),
(3, 1, 4, 3, 12.00, 36.00),
(4, 2, 1, 2, 65.00, 130.00)
ON DUPLICATE KEY UPDATE `venta_id` = VALUES(`venta_id`);

-- Payments
INSERT INTO `payments` (`id`, `client_name`, `dni`, `phone`, `method`, `amount`, `reference`, `status`, `datetime`, `image`) VALUES
(1, 'Aurora Santos', '32398546', '04226466851', 'Pago Móvil', 200.00, '9546545454', 'Pagado', '2026-10-07 01:04', 'http://localhost/paytrack/index.html')
ON DUPLICATE KEY UPDATE `dni` = VALUES(`dni`);
