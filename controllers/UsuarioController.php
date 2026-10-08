<?php
/**
 * Controlador de Usuarios (CRUD Independiente y Auth)
 */

require_once dirname(__DIR__) . '/services/AuthService.php';

class UsuarioController {
    private $db;
    private $auth;

    public function __construct($conexion) {
        $this->db = $conexion;
        $this->auth = new AuthService($conexion);
    }

    public function index() {
        $sql = "SELECT u.id, u.nombre_completo, u.username, u.email, u.role, u.rol_id, u.tienda_id, u.estado, u.created_at,
                       r.nombre AS rol_nombre, t.nombre AS tienda_nombre
                FROM users u
                LEFT JOIN roles r ON u.rol_id = r.id
                LEFT JOIN tiendas t ON u.tienda_id = t.id
                ORDER BY u.id ASC";
        $res = $this->db->query($sql);
        $usuarios = [];
        while ($u = $res->fetch_assoc()) {
            $usuarios[] = $u;
        }
        return ["codigo" => 200, "data" => ["success" => true, "total" => count($usuarios), "data" => $usuarios]];
    }

    public function show($id) {
        $stmt = $this->db->prepare("SELECT u.id, u.nombre_completo, u.username, u.email, u.role, u.rol_id, u.tienda_id, u.estado, u.created_at,
                                           r.nombre AS rol_nombre, t.nombre AS tienda_nombre
                                    FROM users u
                                    LEFT JOIN roles r ON u.rol_id = r.id
                                    LEFT JOIN tiendas t ON u.tienda_id = t.id
                                    WHERE u.id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $u = $stmt->get_result()->fetch_assoc();
        if ($u) {
            return ["codigo" => 200, "data" => ["success" => true, "data" => $u]];
        }
        return ["codigo" => 404, "data" => ["success" => false, "error" => "Usuario no encontrado"]];
    }

    public function store($data) {
        $username = trim($data['username'] ?? ($data['user'] ?? ''));
        $password = trim($data['password'] ?? ($data['pass'] ?? ''));
        $nombre = trim($data['nombre_completo'] ?? $username);
        $email = $data['email'] ?? null;
        $role = $data['role'] ?? 'admin';
        $rol_id = intval($data['rol_id'] ?? 1);
        $tienda_id = !empty($data['tienda_id']) ? intval($data['tienda_id']) : null;
        $estado = $data['estado'] ?? 'Activo';

        if (empty($username) || empty($password)) {
            return ["codigo" => 400, "data" => ["success" => false, "error" => "Username y password son obligatorios"]];
        }

        $stmtC = $this->db->prepare("SELECT id FROM users WHERE username = ?");
        $stmtC->bind_param("s", $username);
        $stmtC->execute();
        if ($stmtC->get_result()->num_rows > 0) {
            return ["codigo" => 409, "data" => ["success" => false, "error" => "El nombre de usuario ya está registrado"]];
        }

        $stmt = $this->db->prepare("INSERT INTO users (nombre_completo, username, password, email, role, rol_id, tienda_id, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssiis", $nombre, $username, $password, $email, $role, $rol_id, $tienda_id, $estado);
        if ($stmt->execute()) {
            return ["codigo" => 201, "data" => ["success" => true, "id" => $stmt->insert_id, "message" => "Usuario creado exitosamente"]];
        }
        return ["codigo" => 500, "data" => ["success" => false, "error" => "Error al crear usuario"]];
    }

    public function update($id, $data) {
        if (!$id) {
            return ["codigo" => 422, "data" => ["success" => false, "error" => "ID de usuario requerido"]];
        }

        $stmtActual = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmtActual->bind_param("i", $id);
        $stmtActual->execute();
        $actual = $stmtActual->get_result()->fetch_assoc();
        if (!$actual) {
            return ["codigo" => 404, "data" => ["success" => false, "error" => "Usuario no encontrado"]];
        }

        $username = trim($data['username'] ?? $actual['username']);
        $nombre = trim($data['nombre_completo'] ?? $actual['nombre_completo']);
        $email = $data['email'] ?? $actual['email'];
        $role = $data['role'] ?? $actual['role'];
        $rol_id = intval($data['rol_id'] ?? $actual['rol_id']);
        $tienda_id = isset($data['tienda_id']) ? (!empty($data['tienda_id']) ? intval($data['tienda_id']) : null) : $actual['tienda_id'];
        $estado = $data['estado'] ?? $actual['estado'];
        $password = !empty($data['password']) ? trim($data['password']) : $actual['password'];

        $stmt = $this->db->prepare("UPDATE users SET nombre_completo = ?, username = ?, email = ?, password = ?, role = ?, rol_id = ?, tienda_id = ?, estado = ? WHERE id = ?");
        $stmt->bind_param("sssssiisi", $nombre, $username, $email, $password, $role, $rol_id, $tienda_id, $estado, $id);
        if ($stmt->execute()) {
            return ["codigo" => 200, "data" => ["success" => true, "message" => "Usuario actualizado correctamente"]];
        }
        return ["codigo" => 500, "data" => ["success" => false, "error" => "Error al actualizar usuario"]];
    }

    public function destroy($id) {
        if (!$id) {
            return ["codigo" => 400, "data" => ["success" => false, "error" => "ID requerido"]];
        }

        $stmtAdmin = $this->db->query("SELECT COUNT(*) AS total FROM users WHERE (role = 'admin' OR rol_id = 1) AND estado = 'Activo'");
        $totAdmin = $stmtAdmin->fetch_assoc()['total'];

        $stmtU = $this->db->prepare("SELECT role, rol_id FROM users WHERE id = ?");
        $stmtU->bind_param("i", $id);
        $stmtU->execute();
        $userData = $stmtU->get_result()->fetch_assoc();

        if ($userData && ($userData['role'] === 'admin' || $userData['rol_id'] == 1) && $totAdmin <= 1) {
            return ["codigo" => 403, "data" => ["success" => false, "error" => "No se puede eliminar el único Administrador activo del sistema."]];
        }

        $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            return ["codigo" => 200, "data" => ["success" => true, "message" => "Usuario eliminado con éxito"]];
        }
        return ["codigo" => 404, "data" => ["success" => false, "error" => "Usuario no encontrado"]];
    }

    public function login($data) {
        $username = trim($data['username'] ?? ($data['user'] ?? ''));
        $password = trim($data['password'] ?? ($data['pass'] ?? ''));
        try {
            $user = $this->auth->login($username, $password);
            if ($user) {
                return ["codigo" => 200, "data" => ["success" => true, "user" => $user]];
            }
            return ["codigo" => 401, "data" => ["success" => false, "message" => "Datos incorrectos"]];
        } catch (Exception $e) {
            return ["codigo" => 403, "data" => ["success" => false, "message" => $e->getMessage()]];
        }
    }
}
?>
