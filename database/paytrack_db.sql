-- ========================================================
-- PayTrack - Sistema de Control y Gestión de Pagos / Ventas
-- Script Oficial de Base de Datos MySQL / MariaDB (XAMPP)
-- ========================================================

CREATE DATABASE IF NOT EXISTS `paytrack` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `paytrack`;

SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- 1. Tabla: roles
-- --------------------------------------------------------
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(50) NOT NULL UNIQUE,
    `descripcion` VARCHAR(255) NULL,
    `estado` ENUM('Activo', 'Inactivo') NOT NULL DEFAULT 'Activo',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 2. Tabla: tiendas
-- --------------------------------------------------------
DROP TABLE IF EXISTS `tiendas`;
CREATE TABLE `tiendas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(100) NOT NULL,
    `rif` VARCHAR(25) NOT NULL UNIQUE,
    `direccion` TEXT NOT NULL,
    `telefono` VARCHAR(30) NOT NULL,
    `estado` ENUM('Activa', 'Inactiva') NOT NULL DEFAULT 'Activa',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 3. Tabla: categorias
-- --------------------------------------------------------
DROP TABLE IF EXISTS `categorias`;
CREATE TABLE `categorias` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(100) NOT NULL UNIQUE,
    `descripcion` TEXT NULL,
    `estado` ENUM('Activo', 'Inactivo') NOT NULL DEFAULT 'Activo',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 4. Tabla: users (Usuarios del sistema)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre_completo` VARCHAR(120) NOT NULL DEFAULT '',
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `email` VARCHAR(120) NULL,
    `role` VARCHAR(50) NOT NULL DEFAULT 'admin',
    `rol_id` INT NULL,
    `tienda_id` INT NULL,
    `estado` ENUM('Activo', 'Inactivo') NOT NULL DEFAULT 'Activo',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_users_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_users_tienda` FOREIGN KEY (`tienda_id`) REFERENCES `tiendas` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 5. Tabla: clientes (CRUD de consulta)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `clientes`;
CREATE TABLE `clientes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(120) NOT NULL,
    `dni` VARCHAR(30) NOT NULL UNIQUE,
    `telefono` VARCHAR(30) NOT NULL,
    `email` VARCHAR(120) NULL,
    `direccion` TEXT NULL,
    `tipo_cliente` ENUM('Natural', 'Jurídico') NOT NULL DEFAULT 'Natural',
    `estado` ENUM('Activo', 'Inactivo') NOT NULL DEFAULT 'Activo',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 6. Tabla: productos
-- --------------------------------------------------------
DROP TABLE IF EXISTS `productos`;
CREATE TABLE `productos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(50) NOT NULL UNIQUE,
    `nombre` VARCHAR(150) NOT NULL,
    `descripcion` TEXT NULL,
    `categoria_id` INT NOT NULL,
    `tienda_id` INT NULL,
    `precio` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `costo` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `stock` INT NOT NULL DEFAULT 0,
    `stock_minimo` INT NOT NULL DEFAULT 5,
    `imagen` VARCHAR(255) NULL,
    `estado` ENUM('Disponible', 'Agotado', 'Inactivo') NOT NULL DEFAULT 'Disponible',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_productos_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_productos_tienda` FOREIGN KEY (`tienda_id`) REFERENCES `tiendas` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 7. Tabla: compras
-- --------------------------------------------------------
DROP TABLE IF EXISTS `compras`;
CREATE TABLE `compras` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `numero_compra` VARCHAR(50) NOT NULL UNIQUE,
    `proveedor` VARCHAR(120) NOT NULL,
    `rif_proveedor` VARCHAR(30) NULL,
    `tienda_id` INT NOT NULL,
    `usuario_id` INT NULL,
    `total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `estado` ENUM('Completada', 'Anulada') NOT NULL DEFAULT 'Completada',
    `notas` TEXT NULL,
    `fecha` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_compras_tienda` FOREIGN KEY (`tienda_id`) REFERENCES `tiendas` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_compras_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 8. Tabla: detalle_compras
-- --------------------------------------------------------
DROP TABLE IF EXISTS `detalle_compras`;
CREATE TABLE `detalle_compras` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `compra_id` INT NOT NULL,
    `producto_id` INT NOT NULL,
    `cantidad` INT NOT NULL,
    `precio_unitario` DECIMAL(12,2) NOT NULL,
    `subtotal` DECIMAL(12,2) NOT NULL,
    CONSTRAINT `fk_detcomp_compra` FOREIGN KEY (`compra_id`) REFERENCES `compras` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_detcomp_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 9. Tabla: ventas
-- --------------------------------------------------------
DROP TABLE IF EXISTS `ventas`;
CREATE TABLE `ventas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `numero_factura` VARCHAR(50) NOT NULL UNIQUE,
    `cliente_id` INT NOT NULL,
    `tienda_id` INT NOT NULL,
    `usuario_id` INT NULL,
    `metodo_pago` ENUM('Pago Móvil', 'Transferencia', 'Efectivo', 'Punto de Venta', 'Zelle') NOT NULL DEFAULT 'Pago Móvil',
    `referencia_pago` VARCHAR(100) NULL,
    `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `impuesto` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `estado` ENUM('Completada', 'Pendiente', 'Anulada') NOT NULL DEFAULT 'Completada',
    `fecha` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_ventas_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_ventas_tienda` FOREIGN KEY (`tienda_id`) REFERENCES `tiendas` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_ventas_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 10. Tabla: detalle_ventas
-- --------------------------------------------------------
DROP TABLE IF EXISTS `detalle_ventas`;
CREATE TABLE `detalle_ventas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `venta_id` INT NOT NULL,
    `producto_id` INT NOT NULL,
    `cantidad` INT NOT NULL,
    `precio_unitario` DECIMAL(12,2) NOT NULL,
    `subtotal` DECIMAL(12,2) NOT NULL,
    CONSTRAINT `fk_detventa_venta` FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_detventa_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 11. Tabla: payments (Compatibilidad previa)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `client_name` VARCHAR(120) NOT NULL,
    `dni` VARCHAR(30) NOT NULL,
    `phone` VARCHAR(30) NOT NULL,
    `method` VARCHAR(50) NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `reference` VARCHAR(50) NOT NULL,
    `status` VARCHAR(50) NOT NULL,
    `datetime` VARCHAR(50) NOT NULL,
    `image` TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ========================================================
-- INSERCIÓN DE DATOS DE PRUEBA (SEEDERS)
-- ========================================================

-- Roles
INSERT INTO `roles` (`id`, `nombre`, `descripcion`, `estado`) VALUES
(1, 'Administrador', 'Acceso total y configuración del sistema', 'Activo'),
(2, 'Vendedor', 'Gestión de ventas y cobros en tienda', 'Activo'),
(3, 'Auditor', 'Consulta y reportes de transacciones', 'Activo'),
(4, 'Cliente', 'Rol público para usuarios registrados en portal', 'Activo');

-- Tiendas
INSERT INTO `tiendas` (`id`, `nombre`, `rif`, `direccion`, `telefono`, `estado`) VALUES
(1, 'Sucursal Principal Caracas', 'J-30485921-0', 'Av. Francisco de Miranda, Torre Delta, Piso 2, Caracas', '0212-9051122', 'Activa'),
(2, 'Sucursal Valencia', 'J-40582910-3', 'Centro Comercial Sambil Valencia, Nivel Autopista, Local 45', '0241-8932211', 'Activa'),
(3, 'Sucursal Barquisimeto', 'J-50192837-1', 'Carrera 19 entre Calles 25 y 26, Barquisimeto, Lara', '0251-7894561', 'Activa');

-- Categorías
INSERT INTO `categorias` (`id`, `nombre`, `descripcion`, `estado`) VALUES
(1, 'Electrónica', 'Dispositivos, gadgets y accesorios tecnológicos', 'Activo'),
(2, 'Servicios Digitales', 'Planes de suscripción, recargas y soporte digital', 'Activo'),
(3, 'Accesorios', 'Fundas, cables, cargadores y periféricos', 'Activo'),
(4, 'Papelería y Oficina', 'Artículos para oficinas y punto de venta', 'Activo');

-- Usuarios (Password en texto plano a1532ra777 para compatibilidad previa y login)
INSERT INTO `users` (`id`, `nombre_completo`, `username`, `password`, `email`, `role`, `rol_id`, `tienda_id`, `estado`) VALUES
(1, 'Fabricio Ramos', 'fabricio', 'a1532ra777', 'fabricio@paytrack.com', 'admin', 1, 1, 'Activo'),
(2, 'Fabiana Mendoza', 'Fabiana', 'a1532ra777', 'fabiana@paytrack.com', 'admin', 1, 2, 'Activo'),
(3, 'Carlos Vendedor', 'cvendedor', '123456', 'carlos@paytrack.com', 'vendedor', 2, 1, 'Activo');

-- Clientes (CRUD de Consulta)
INSERT INTO `clientes` (`id`, `nombre`, `dni`, `telefono`, `email`, `direccion`, `tipo_cliente`, `estado`) VALUES
(1, 'Aurora Santos', '32398546', '04226466851', 'aurora.santos@gmail.com', 'Urb. El Rosal, Av. Principal Casa #4', 'Natural', 'Activo'),
(2, 'Marcos Leonardo Pérez', '24879102', '04149988776', 'marcos.perez@hotmail.com', 'Calle Sucre, Los Teques, Miranda', 'Natural', 'Activo'),
(3, 'Inversiones Nova C.A.', 'J-40982314-5', '02125554433', 'contacto@inversionesnova.com', 'Bulevar Sabana Grande, Edif. Centro, Piso 4', 'Jurídico', 'Activo'),
(4, 'Valeria Sofía Blanco', '28114563', '04165551234', 'valeria.blanco@yahoo.es', 'Av. Bolívar, Valencia, Carabobo', 'Natural', 'Activo');

-- Productos
INSERT INTO `productos` (`id`, `codigo`, `nombre`, `descripcion`, `categoria_id`, `tienda_id`, `precio`, `costo`, `stock`, `stock_minimo`, `imagen`, `estado`) VALUES
(1, 'PROD-001', 'Lector de Tarjetas POS Bluetooth', 'Dispositivo inalámbrico de cobro móvil y chip', 1, 1, 65.00, 40.00, 30, 5, 'pos_reader.png', 'Disponible'),
(2, 'PROD-002', 'Cable USB-C Carga Rápida 2m', 'Cable reforzado de alta velocidad 60W', 3, 1, 8.50, 3.50, 100, 15, 'cable_usbc.png', 'Disponible'),
(3, 'PROD-003', 'Impresora Térmica 58mm Facturas', 'Impresora portátil USB y Bluetooth para tickets', 1, 2, 45.00, 25.00, 15, 3, 'thermal_printer.png', 'Disponible'),
(4, 'PROD-004', 'Paquete de Rollos Térmicos (Pack 10)', 'Rollos de papel térmico 58mm de alta duración', 4, 1, 12.00, 6.00, 50, 10, 'rollos_termicos.png', 'Disponible'),
(5, 'PROD-005', 'Plan Suscripción Anual PayTrack Pro', 'Licencia anual con reportes avanzados y soporte', 2, 1, 120.00, 0.00, 999, 1, 'paytrack_pro.png', 'Disponible');

-- Compras iniciales
INSERT INTO `compras` (`id`, `numero_compra`, `proveedor`, `rif_proveedor`, `tienda_id`, `usuario_id`, `total`, `estado`, `notas`, `fecha`) VALUES
(1, 'COM-2026-0001', 'Mayorista Tech Global C.A.', 'J-12345678-9', 1, 1, 1600.00, 'Completada', 'Adquisición de stock inicial de lectores POS', '2026-10-01 10:30:00'),
(2, 'COM-2026-0002', 'Suministros Ofimática 2020', 'J-87654321-0', 1, 1, 300.00, 'Completada', 'Reposición de rollos de papel térmico', '2026-10-03 14:00:00');

-- Detalle de Compras
INSERT INTO `detalle_compras` (`id`, `compra_id`, `producto_id`, `cantidad`, `precio_unitario`, `subtotal`) VALUES
(1, 1, 1, 40, 40.00, 1600.00),
(2, 2, 4, 50, 6.00, 300.00);

-- Ventas iniciales (Entrega Final)
INSERT INTO `ventas` (`id`, `numero_factura`, `cliente_id`, `tienda_id`, `usuario_id`, `metodo_pago`, `referencia_pago`, `subtotal`, `impuesto`, `total`, `estado`, `fecha`) VALUES
(1, 'FAC-0001', 1, 1, 1, 'Pago Móvil', '9546545454', 200.00, 0.00, 200.00, 'Completada', '2026-10-07 01:04:00'),
(2, 'FAC-0002', 3, 1, 2, 'Transferencia', 'TRF-8837192', 130.00, 0.00, 130.00, 'Completada', '2026-10-07 14:15:00');

-- Detalle de Ventas
INSERT INTO `detalle_ventas` (`id`, `venta_id`, `producto_id`, `cantidad`, `precio_unitario`, `subtotal`) VALUES
(1, 1, 1, 2, 65.00, 130.00),
(2, 1, 2, 4, 8.50, 34.00),
(3, 1, 4, 3, 12.00, 36.00),
(4, 2, 1, 2, 65.00, 130.00);

-- Tabla payments (Datos existentes preservados)
INSERT INTO `payments` (`id`, `client_name`, `dni`, `phone`, `method`, `amount`, `reference`, `status`, `datetime`, `image`) VALUES
(1, 'Aurora Santos', '32398546', '04226466851', 'Pago Móvil', 200.00, '9546545454', 'Pagado', '2026-10-07 01:04', 'http://localhost/paytrack/index.html');
