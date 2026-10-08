<?php
/**
 * Controlador de Categorías
 */

class CategoriaController {
    private $db;

    public function __construct($conexion) {
        $this->db = $conexion;
    }

    public function index() {
        $res = $this->db->query("SELECT * FROM categorias ORDER BY nombre ASC");
        $cats = [];
        while ($c = $res->fetch_assoc()) {
            $cats[] = $c;
        }
        return ["codigo" => 200, "data" => ["success" => true, "total" => count($cats), "data" => $cats]];
    }

    public function show($id) {
        $stmt = $this->db->prepare("SELECT * FROM categorias WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $cat = $stmt->get_result()->fetch_assoc();
        if ($cat) {
            return ["codigo" => 200, "data" => ["success" => true, "data" => $cat]];
        }
        return ["codigo" => 404, "data" => ["success" => false, "error" => "Categoría no encontrada"]];
    }

    public function store($data) {
        $nombre = trim($data['nombre'] ?? '');
        $descripcion = trim($data['descripcion'] ?? '');
        $estado = $data['estado'] ?? 'Activo';

        if (empty($nombre)) {
            return ["codigo" => 400, "data" => ["success" => false, "error" => "El campo 'nombre' es obligatorio"]];
        }

        $stmtC = $this->db->prepare("SELECT id FROM categorias WHERE nombre = ?");
        $stmtC->bind_param("s", $nombre);
        $stmtC->execute();
        if ($stmtC->get_result()->num_rows > 0) {
            return ["codigo" => 409, "data" => ["success" => false, "error" => "La categoría ya existe"]];
        }

        $stmt = $this->db->prepare("INSERT INTO categorias (nombre, descripcion, estado) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $nombre, $descripcion, $estado);
        if ($stmt->execute()) {
            return ["codigo" => 201, "data" => ["success" => true, "id" => $stmt->insert_id, "message" => "Categoría creada con éxito"]];
        }
        return ["codigo" => 500, "data" => ["success" => false, "error" => "Error al guardar categoría"]];
    }

    public function update($id, $data) {
        $nombre = trim($data['nombre'] ?? '');
        $descripcion = $data['descripcion'] ?? null;
        $estado = $data['estado'] ?? 'Activo';

        if (!$id || empty($nombre)) {
            return ["codigo" => 422, "data" => ["success" => false, "error" => "ID y nombre son obligatorios"]];
        }

        $stmt = $this->db->prepare("UPDATE categorias SET nombre = ?, descripcion = ?, estado = ? WHERE id = ?");
        $stmt->bind_param("sssi", $nombre, $descripcion, $estado, $id);
        if ($stmt->execute()) {
            return ["codigo" => 200, "data" => ["success" => true, "message" => "Categoría actualizada con éxito"]];
        }
        return ["codigo" => 500, "data" => ["success" => false, "error" => "Error al actualizar categoría"]];
    }

    public function destroy($id) {
        if (!$id) {
            return ["codigo" => 400, "data" => ["success" => false, "error" => "ID requerido"]];
        }

        $stmtCheck = $this->db->prepare("SELECT id FROM productos WHERE categoria_id = ? LIMIT 1");
        $stmtCheck->bind_param("i", $id);
        $stmtCheck->execute();
        if ($stmtCheck->get_result()->num_rows > 0) {
            return ["codigo" => 409, "data" => ["success" => false, "error" => "No se puede eliminar una categoría con productos asignados"]];
        }

        $stmt = $this->db->prepare("DELETE FROM categorias WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            return ["codigo" => 200, "data" => ["success" => true, "message" => "Categoría eliminada con éxito"]];
        }
        return ["codigo" => 404, "data" => ["success" => false, "error" => "Categoría no encontrada"]];
    }
}
?>
