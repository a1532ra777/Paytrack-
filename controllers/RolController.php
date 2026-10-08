<?php
/**
 * Controlador de Roles
 */

class RolController {
    private $db;

    public function __construct($conexion) {
        $this->db = $conexion;
    }

    public function index() {
        $res = $this->db->query("SELECT * FROM roles ORDER BY id ASC");
        $roles = [];
        while ($r = $res->fetch_assoc()) {
            $roles[] = $r;
        }
        return ["codigo" => 200, "data" => ["success" => true, "total" => count($roles), "data" => $roles]];
    }

    public function show($id) {
        $stmt = $this->db->prepare("SELECT * FROM roles WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $rol = $stmt->get_result()->fetch_assoc();
        if ($rol) {
            return ["codigo" => 200, "data" => ["success" => true, "data" => $rol]];
        }
        return ["codigo" => 404, "data" => ["success" => false, "error" => "Rol no encontrado"]];
    }

    public function store($data) {
        $nombre = trim($data['nombre'] ?? '');
        $descripcion = trim($data['descripcion'] ?? '');
        $estado = $data['estado'] ?? 'Activo';

        if (empty($nombre)) {
            return ["codigo" => 400, "data" => ["success" => false, "error" => "El campo 'nombre' es requerido"]];
        }

        $stmtC = $this->db->prepare("SELECT id FROM roles WHERE nombre = ?");
        $stmtC->bind_param("s", $nombre);
        $stmtC->execute();
        if ($stmtC->get_result()->num_rows > 0) {
            return ["codigo" => 409, "data" => ["success" => false, "error" => "El rol ya existe"]];
        }

        $stmt = $this->db->prepare("INSERT INTO roles (nombre, descripcion, estado) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $nombre, $descripcion, $estado);
        if ($stmt->execute()) {
            return ["codigo" => 201, "data" => ["success" => true, "id" => $stmt->insert_id, "message" => "Rol creado exitosamente"]];
        }
        return ["codigo" => 500, "data" => ["success" => false, "error" => "Error interno al guardar rol"]];
    }

    public function update($id, $data) {
        $nombre = trim($data['nombre'] ?? '');
        $descripcion = $data['descripcion'] ?? null;
        $estado = $data['estado'] ?? null;

        if (!$id || empty($nombre)) {
            return ["codigo" => 422, "data" => ["success" => false, "error" => "Campos obligatorios incompletos"]];
        }

        $stmt = $this->db->prepare("UPDATE roles SET nombre = ?, descripcion = COALESCE(?, descripcion), estado = COALESCE(?, estado) WHERE id = ?");
        $stmt->bind_param("sssi", $nombre, $descripcion, $estado, $id);
        if ($stmt->execute()) {
            return ["codigo" => 200, "data" => ["success" => true, "message" => "Rol actualizado correctamente"]];
        }
        return ["codigo" => 500, "data" => ["success" => false, "error" => "Error al actualizar rol"]];
    }

    public function destroy($id) {
        if (!$id) {
            return ["codigo" => 400, "data" => ["success" => false, "error" => "ID requerido"]];
        }

        $stmtCheck = $this->db->prepare("SELECT id FROM users WHERE rol_id = ?");
        $stmtCheck->bind_param("i", $id);
        $stmtCheck->execute();
        if ($stmtCheck->get_result()->num_rows > 0) {
            return ["codigo" => 409, "data" => ["success" => false, "error" => "No se puede eliminar un rol con usuarios vinculados"]];
        }

        $stmt = $this->db->prepare("DELETE FROM roles WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            return ["codigo" => 200, "data" => ["success" => true, "message" => "Rol eliminado correctamente"]];
        }
        return ["codigo" => 404, "data" => ["success" => false, "error" => "Rol no encontrado"]];
    }
}
?>
