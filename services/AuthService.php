<?php
/**
 * Capa de Servicios: AuthService
 * Reglas de autenticación, control de accesos y seguridad de usuarios.
 */

class AuthService {
    private $db;

    public function __construct($conexion) {
        $this->db = $conexion;
    }

    public function login($username, $password) {
        $user = $this->db->real_escape_string($username);
        $pass = $this->db->real_escape_string($password);

        $sql = "SELECT u.id, u.nombre_completo, u.username, u.email, u.role, u.rol_id, u.tienda_id, u.estado 
                FROM users u 
                WHERE u.username = '$user' AND (u.password = '$pass' OR u.password = MD5('$pass'))";
        $res = $this->db->query($sql);

        if ($res && $res->num_rows > 0) {
            $userData = $res->fetch_assoc();
            if ($userData['estado'] === 'Inactivo') {
                throw new Exception("El usuario se encuentra inactivo en el sistema.");
            }
            return $userData;
        }
        return null;
    }

    public function registrar($username, $password, $nombre = '', $email = '', $role = 'admin') {
        $stmtCheck = $this->db->prepare("SELECT id FROM users WHERE username = ?");
        $stmtCheck->bind_param("s", $username);
        $stmtCheck->execute();
        if ($stmtCheck->get_result()->num_rows > 0) {
            throw new Exception("El usuario '$username' ya existe.");
        }

        $stmt = $this->db->prepare("INSERT INTO users (nombre_completo, username, password, email, role, rol_id, estado) VALUES (?, ?, ?, ?, ?, 1, 'Activo')");
        $stmt->bind_param("sssss", $nombre, $username, $password, $email, $role);
        if ($stmt->execute()) {
            return $stmt->insert_id;
        }
        throw new Exception("Error al registrar usuario: " . $stmt->error);
    }
}
?>
