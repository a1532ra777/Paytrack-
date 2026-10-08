-- ========================================================
-- PayTrack - DDL Schema Oficial (Estructura de Base de Datos)
-- Carpeta: /database/schema.sql
-- Motor: MySQL 8+ / MariaDB 10.4+ (InnoDB, UTF-8)
-- ========================================================

CREATE DATABASE IF NOT EXISTS `paytrack` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `paytrack`;

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Tabla: roles
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(50) NOT NULL UNIQUE,
    `descripcion` VARCHAR(255) NULL,
    `estado` ENUM('Activo', 'Inactivo') NOT NULL DEFAULT 'Activo',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabla: tiendas
DROP TABLE IF EXISTS `tiendas`;
CREATE TABLE `tiendas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(100) NOT NULL,
    `rif` VARCHAR(25) NOT NULL UNIQUE,
    `direccion` TEXT NOT NULL,
    `telefono` VARCHAR(30) NOT NULL,
    `estado` ENUM('Activa', 'Inactiva') NOT NULL DEFAULT 'Activa',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabla: categorias
DROP TABLE IF EXISTS `categorias`;
CREATE TABLE `categorias` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(100) NOT NULL UNIQUE,
    `descripcion` TEXT NULL,
    `estado` ENUM('Activo', 'Inactivo') NOT NULL DEFAULT 'Activo',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tabla: users (Usuarios del sistema)
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Tabla: clientes (CRUD de consulta)
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Tabla: productos
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Tabla: compras
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Tabla: detalle_compras
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Tabla: ventas
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Tabla: detalle_ventas
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Tabla: payments (Compatibilidad previa)
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
