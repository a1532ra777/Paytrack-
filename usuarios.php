<?php
/**
 * CRUD de Usuarios Independiente y Módulo de Autenticación
 * Especificación: "Crud de usuario independiente"
 * Incluye gestión de usuarios (Listar, Obtener, Crear, Actualizar, Eliminar)
 * y mantiene 100% retrocompatibilidad con Login y Register existentes.
 */

require_once __DIR__ . '/conexion.php';

$metodo = $_SERVER['REQUEST_METHOD'];
$datos = obtenerDatosEntrada();
$accion = isset($_GET['accion']) ? $_GET['accion'] : (isset($datos['accion']) ? $datos['accion'] : null);

// -------------------------------------------------------------
// COMPATIBILIDAD CON SISTEMA PREVIO: LOGIN
// -------------------------------------------------------------
if ($accion === 'login') {
    $user = isset($datos['user']) ? $conexion->real_escape_string($datos['user']) : (isset($datos['username']) ? $conexion->real_escape_string($datos['username']) : '');
    $pass = isset($datos['pass']) ? $conexion->real_escape_string($datos['pass']) : (isset($datos['password']) ? $conexion->real_escape_string($datos['password']) : '');

    if (empty($user) || empty($pass)) {
        responderJSON(["success" => false, "message" => "Debe ingresar usuario y contraseña"], 400);
    }

    $sql = "SELECT u.id, u.nombre_completo, u.username, u.email, u.role, u.rol_id, u.tienda_id, u.estado, u.password 
            FROM users u 
            WHERE u.username = '$user' AND (u.password = '$pass' OR u.password = MD5('$pass'))";
    $resultado = $conexion->query($sql);

    if ($resultado && $resultado->num_rows > 0) {
        $row = $resultado->fetch_assoc();
        unset($row['password']); // No exponer contraseña
        responderJSON(["success" => true, "user" => $row]);
    } else {
        responderJSON(["success" => false, "message" => "Datos incorrectos"], 401);
    }
}

// -------------------------------------------------------------
// COMPATIBILIDAD CON SISTEMA PREVIO: REGISTER
// -------------------------------------------------------------
if ($accion === 'register') {
    $user = isset($datos['user']) ? trim($datos['user']) : (isset($datos['username']) ? trim($datos['username']) : '');
    $pass = isset($datos['pass']) ? trim($datos['pass']) : (isset($datos['password']) ? trim($datos['password']) : '');
    $nombre = isset($datos['nombre_completo']) ? trim($datos['nombre_completo']) : $user;
    $email = isset($datos['email']) ? trim($datos['email']) : null;
    $role = isset($datos['role']) ? trim($datos['role']) : 'admin';

    if (empty($user) || empty($pass)) {
        responderJSON(["success" => false, "error" => "Usuario y contraseña requeridos"], 400);
    }

    $stmtCheck = $conexion->prepare("SELECT id FROM users WHERE username = ?");
    $stmtCheck->bind_param("s", $user);
    $stmtCheck->execute();
    if ($stmtCheck->get_result()->num_rows > 0) {
        responderJSON(["success" => false, "error" => "El usuario ya existe"], 409);
    }

    $stmt = $conexion->prepare("INSERT INTO users (nombre_completo, username, password, email, role, rol_id, estado) VALUES (?, ?, ?, ?, ?, 1, 'Activo')");
    $stmt->bind_param("sssss", $nombre, $user, $pass, $email, $role);

    if ($stmt->execute()) {
        responderJSON(["success" => true, "id" => $stmt->insert_id, "message" => "Usuario registrado con éxito"]);
    } else {
        responderJSON(["success" => false, "error" => "Error al registrar: " . $stmt->error], 500);
    }
}

// -------------------------------------------------------------
// CRUD INDEPENDIENTE: LISTAR O CONSULTAR USUARIOS (GET)
// -------------------------------------------------------------
if ($metodo === 'GET' || $accion === 'listar' || $accion === 'obtener') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : (isset($datos['id']) ? intval($datos['id']) : null);

    if ($id) {
        $stmt = $conexion->prepare("SELECT u.id, u.nombre_completo, u.username, u.email, u.role, u.rol_id, u.tienda_id, u.estado, u.created_at,
                                           r.nombre AS rol_nombre, t.nombre AS tienda_nombre
                                    FROM users u
                                    LEFT JOIN roles r ON u.rol_id = r.id
                                    LEFT JOIN tiendas t ON u.tienda_id = t.id
                                    WHERE u.id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user) {
            responderJSON(["success" => true, "data" => $user]);
        } else {
            responderJSON(["success" => false, "error" => "Usuario no encontrado"], 404);
        }
    }

    $sql = "SELECT u.id, u.nombre_completo, u.username, u.email, u.role, u.rol_id, u.tienda_id, u.estado, u.created_at,
                   r.nombre AS rol_nombre, t.nombre AS tienda_nombre
            FROM users u
            LEFT JOIN roles r ON u.rol_id = r.id
            LEFT JOIN tiendas t ON u.tienda_id = t.id
            ORDER BY u.id ASC";
    $resultado = $conexion->query($sql);
    $usuarios = [];
    while ($row = $resultado->fetch_assoc()) {
        $usuarios[] = $row;
    }

    responderJSON(["success" => true, "total" => count($usuarios), "data" => $usuarios]);
}

// -------------------------------------------------------------
// CRUD INDEPENDIENTE: CREAR USUARIO (POST)
// -------------------------------------------------------------
if (($metodo === 'POST' && !$accion) || $accion === 'crear' || $accion === 'guardar') {
    $username = isset($datos['username']) ? trim($datos['username']) : (isset($datos['user']) ? trim($datos['user']) : '');
    $password = isset($datos['password']) ? trim($datos['password']) : (isset($datos['pass']) ? trim($datos['pass']) : '');
    $nombre_completo = isset($datos['nombre_completo']) ? trim($datos['nombre_completo']) : $username;
    $email = isset($datos['email']) ? trim($datos['email']) : null;
    $rol_id = isset($datos['rol_id']) && !empty($datos['rol_id']) ? intval($datos['rol_id']) : 1;
    $tienda_id = isset($datos['tienda_id']) && !empty($datos['tienda_id']) ? intval($datos['tienda_id']) : null;
    $role = isset($datos['role']) ? trim($datos['role']) : 'admin';
    $estado = isset($datos['estado']) && in_array($datos['estado'], ['Activo', 'Inactivo']) ? $datos['estado'] : 'Activo';

    if (empty($username) || empty($password)) {
        responderJSON(["success" => false, "error" => "El 'username' y 'password' son requeridos."], 400);
    }

    // Comprobar si ya existe
    $stmtCheck = $conexion->prepare("SELECT id FROM users WHERE username = ?");
    $stmtCheck->bind_param("s", $username);
    $stmtCheck->execute();
    if ($stmtCheck->get_result()->num_rows > 0) {
        responderJSON(["success" => false, "error" => "El nombre de usuario '$username' ya se encuentra en uso."], 409);
    }

    $stmt = $conexion->prepare("INSERT INTO users (nombre_completo, username, password, email, role, rol_id, tienda_id, estado) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssiis", $nombre_completo, $username, $password, $email, $role, $rol_id, $tienda_id, $estado);

    if ($stmt->execute()) {
        responderJSON([
            "success" => true,
            "message" => "Usuario creado exitosamente",
            "id" => $stmt->insert_id,
            "data" => [
                "id" => $stmt->insert_id,
                "nombre_completo" => $nombre_completo,
                "username" => $username,
                "email" => $email,
                "role" => $role,
                "rol_id" => $rol_id,
                "estado" => $estado
            ]
        ], 201);
    } else {
        responderJSON(["success" => false, "error" => "Error al crear usuario: " . $stmt->error], 500);
    }
}

// -------------------------------------------------------------
// CRUD INDEPENDIENTE: ACTUALIZAR USUARIO (PUT / POST)
// -------------------------------------------------------------
if ($metodo === 'PUT' || $accion === 'actualizar' || $accion === 'editar') {
    $id = isset($datos['id']) ? intval($datos['id']) : (isset($_GET['id']) ? intval($_GET['id']) : null);

    if (!$id) {
        responderJSON(["success" => false, "error" => "Se requiere 'id' del usuario para actualizar."], 400);
    }

    // Obtener datos actuales
    $stmtUser = $conexion->prepare("SELECT * FROM users WHERE id = ?");
    $stmtUser->bind_param("i", $id);
    $stmtUser->execute();
    $actual = $stmtUser->get_result()->fetch_assoc();

    if (!$actual) {
        responderJSON(["success" => false, "error" => "Usuario no encontrado."], 404);
    }

    $username = isset($datos['username']) ? trim($datos['username']) : $actual['username'];
    $nombre_completo = isset($datos['nombre_completo']) ? trim($datos['nombre_completo']) : $actual['nombre_completo'];
    $email = isset($datos['email']) ? trim($datos['email']) : $actual['email'];
    $role = isset($datos['role']) ? trim($datos['role']) : $actual['role'];
    $rol_id = isset($datos['rol_id']) && !empty($datos['rol_id']) ? intval($datos['rol_id']) : $actual['rol_id'];
    $tienda_id = isset($datos['tienda_id']) ? (empty($datos['tienda_id']) ? null : intval($datos['tienda_id'])) : $actual['tienda_id'];
    $estado = isset($datos['estado']) ? trim($datos['estado']) : $actual['estado'];
    $password = isset($datos['password']) && !empty($datos['password']) ? trim($datos['password']) : $actual['password'];

    // Comprobar si el username le pertenece a otro
    $stmtCheck = $conexion->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmtCheck->bind_param("si", $username, $id);
    $stmtCheck->execute();
    if ($stmtCheck->get_result()->num_rows > 0) {
        responderJSON(["success" => false, "error" => "El nombre de usuario '$username' ya está registrado por otra cuenta."], 409);
    }

    $stmt = $conexion->prepare("UPDATE users SET nombre_completo = ?, username = ?, email = ?, password = ?, role = ?, rol_id = ?, tienda_id = ?, estado = ? WHERE id = ?");
    $stmt->bind_param("sssssiisi", $nombre_completo, $username, $email, $password, $role, $rol_id, $tienda_id, $estado, $id);

    if ($stmt->execute()) {
        responderJSON(["success" => true, "message" => "Usuario actualizado exitosamente"]);
    } else {
        responderJSON(["success" => false, "error" => "Error al actualizar usuario: " . $stmt->error], 500);
    }
}

// -------------------------------------------------------------
// CRUD INDEPENDIENTE: ELIMINAR USUARIO (DELETE / POST)
// -------------------------------------------------------------
if ($metodo === 'DELETE' || $accion === 'eliminar') {
    $id = isset($datos['id']) ? intval($datos['id']) : (isset($_GET['id']) ? intval($_GET['id']) : null);

    if (!$id) {
        responderJSON(["success" => false, "error" => "Se requiere 'id' del usuario para eliminar."], 400);
    }

    // Proteger contra eliminación del último administrador activo
    $stmtAdmin = $conexion->query("SELECT COUNT(*) AS total FROM users WHERE (role = 'admin' OR rol_id = 1) AND estado = 'Activo'");
    $totAdmin = $stmtAdmin->fetch_assoc()['total'];

    $stmtCheckUser = $conexion->prepare("SELECT role, rol_id FROM users WHERE id = ?");
    $stmtCheckUser->bind_param("i", $id);
    $stmtCheckUser->execute();
    $userData = $stmtCheckUser->get_result()->fetch_assoc();

    if ($userData && ($userData['role'] === 'admin' || $userData['rol_id'] == 1) && $totAdmin <= 1) {
        responderJSON(["success" => false, "error" => "No se puede eliminar el único Administrador activo del sistema."], 403);
    }

    $stmt = $conexion->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        responderJSON(["success" => true, "message" => "Usuario eliminado con éxito"]);
    } else {
        responderJSON(["success" => false, "error" => "Usuario no encontrado o ya eliminado."], 404);
    }
}

responderJSON(["success" => false, "error" => "Método o acción no reconocida"], 400);
?>