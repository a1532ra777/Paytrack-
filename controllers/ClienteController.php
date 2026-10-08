<?php
/**
 * Controlador de Clientes (Solo Consulta)
 */

class ClienteController {
    private $db;

    public function __construct($conexion) {
        $this->db = $conexion;
    }

    public function index($params = []) {
        $buscar = $params['buscar'] ?? null;
        $estado = $params['estado'] ?? null;

        if ($buscar) {
            $like = "%$buscar%";
            $stmt = $this->db->prepare("SELECT * FROM clientes WHERE nombre LIKE ? OR dni LIKE ? OR telefono LIKE ? OR email LIKE ? ORDER BY nombre ASC");
            $stmt->bind_param("ssss", $like, $like, $like, $like);
            $stmt->execute();
            $res = $stmt->get_result();
        } elseif ($estado) {
            $stmt = $this->db->prepare("SELECT * FROM clientes WHERE estado = ? ORDER BY id DESC");
            $stmt->bind_param("s", $estado);
            $stmt->execute();
            $res = $stmt->get_result();
        } else {
            $res = $this->db->query("SELECT * FROM clientes ORDER BY id DESC");
        }

        $clientes = [];
        while ($row = $res->fetch_assoc()) {
            $clientes[] = $row;
        }

        return [
            "codigo" => 200,
            "data" => [
                "success" => true,
                "total" => count($clientes),
                "modo" => "solo_consulta",
                "data" => $clientes
            ]
        ];
    }

    public function show($id) {
        $stmt = $this->db->prepare("SELECT * FROM clientes WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $cliente = $stmt->get_result()->fetch_assoc();

        if ($cliente) {
            return ["codigo" => 200, "data" => ["success" => true, "data" => $cliente]];
        }
        return ["codigo" => 404, "data" => ["success" => false, "error" => "Cliente no encontrado"]];
    }

    public function store() {
        return [
            "codigo" => 405,
            "data" => [
                "success" => false,
                "error" => "Método no permitido. El módulo de clientes está restringido exclusivamente a consultas.",
                "modo" => "solo_consulta"
            ]
        ];
    }
}
?>
