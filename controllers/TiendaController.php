<?php
/**
 * Controlador de Tiendas
 */

class TiendaController {
    private $db;

    public function __construct($conexion) {
        $this->db = $conexion;
    }

    public function index() {
        $res = $this->db->query("SELECT * FROM tiendas ORDER BY id ASC");
        $tiendas = [];
        while ($t = $res->fetch_assoc()) {
            $tiendas[] = $t;
        }
        return ["codigo" => 200, "data" => ["success" => true, "total" => count($tiendas), "data" => $tiendas]];
    }

    public function show($id) {
        $stmt = $this->db->prepare("SELECT * FROM tiendas WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $tienda = $stmt->get_result()->fetch_assoc();
        if ($tienda) {
            return ["codigo" => 200, "data" => ["success" => true, "data" => $tienda]];
        }
        return ["codigo" => 404, "data" => ["success" => false, "error" => "Tienda no encontrada"]];
    }

    public function store($data) {
        $nombre = trim($data['nombre'] ?? '');
        $rif = trim($data['rif'] ?? '');
        $direccion = trim($data['direccion'] ?? '');
        $telefono = trim($data['telefono'] ?? '');
        $estado = $data['estado'] ?? 'Activa';

        if (empty($nombre) || empty($rif)) {
            return ["codigo" => 400, "data" => ["success" => false, "error" => "Nombre y RIF son obligatorios"]];
        }

        $stmtC = $this->db->prepare("SELECT id FROM tiendas WHERE rif = ?");
        $stmtC->bind_param("s", $rif);
        $stmtC->execute();
        if ($stmtC->get_result()->num_rows > 0) {
            return ["codigo" => 409, "data" => ["success" => false, "error" => "El RIF ya se encuentra registrado"]];
        }

        $stmt = $this->db->prepare("INSERT INTO tiendas (nombre, rif, direccion, telefono, estado) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $nombre, $rif, $direccion, $telefono, $estado);
        if ($stmt->execute()) {
            return ["codigo" => 201, "data" => ["success" => true, "id" => $stmt->insert_id, "message" => "Tienda registrada con éxito"]];
        }
        return ["codigo" => 500, "data" => ["success" => false, "error" => "Error interno al guardar tienda"]];
    }

    public function update($id, $data) {
        $nombre = trim($data['nombre'] ?? '');
        $rif = trim($data['rif'] ?? '');
        $direccion = trim($data['direccion'] ?? '');
        $telefono = trim($data['telefono'] ?? '');
        $estado = $data['estado'] ?? 'Activa';

        if (!$id || empty($nombre) || empty($rif)) {
            return ["codigo" => 422, "data" => ["success" => false, "error" => "Datos incompletos para actualizar tienda"]];
        }

        $stmt = $this->db->prepare("UPDATE tiendas SET nombre = ?, rif = ?, direccion = ?, telefono = ?, estado = ? WHERE id = ?");
        $stmt->bind_param("sssssi", $nombre, $rif, $direccion, $telefono, $estado, $id);
        if ($stmt->execute()) {
            return ["codigo" => 200, "data" => ["success" => true, "message" => "Tienda actualizada correctamente"]];
        }
        return ["codigo" => 500, "data" => ["success" => false, "error" => "Error al actualizar tienda"]];
    }

    public function destroy($id) {
        if (!$id) {
            return ["codigo" => 400, "data" => ["success" => false, "error" => "ID requerido"]];
        }

        $stmtCheck = $this->db->prepare("SELECT id FROM ventas WHERE tienda_id = ? LIMIT 1");
        $stmtCheck->bind_param("i", $id);
        $stmtCheck->execute();
        if ($stmtCheck->get_result()->num_rows > 0) {
            return ["codigo" => 409, "data" => ["success" => false, "error" => "No se puede eliminar una tienda con ventas asociadas"]];
        }

        $stmt = $this->db->prepare("DELETE FROM tiendas WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            return ["codigo" => 200, "data" => ["success" => true, "message" => "Tienda eliminada correctamente"]];
        }
        return ["codigo" => 404, "data" => ["success" => false, "error" => "Tienda no encontrada"]];
    }
}
?>
