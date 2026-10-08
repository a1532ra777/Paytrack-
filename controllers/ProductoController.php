<?php
/**
 * Controlador de Productos e Inventario
 */

class ProductoController {
    private $db;

    public function __construct($conexion) {
        $this->db = $conexion;
    }

    public function index($params = []) {
        $where = [];
        $tipos = "";
        $valores = [];

        if (!empty($params['categoria_id'])) {
            $where[] = "p.categoria_id = ?";
            $tipos .= "i";
            $valores[] = intval($params['categoria_id']);
        }
        if (!empty($params['tienda_id'])) {
            $where[] = "p.tienda_id = ?";
            $tipos .= "i";
            $valores[] = intval($params['tienda_id']);
        }
        if (!empty($params['buscar'])) {
            $where[] = "(p.nombre LIKE ? OR p.codigo LIKE ?)";
            $tipos .= "ss";
            $like = "%" . trim($params['buscar']) . "%";
            $valores[] = $like;
            $valores[] = $like;
        }

        $sql = "SELECT p.*, c.nombre AS categoria_nombre, t.nombre AS tienda_nombre 
                FROM productos p
                LEFT JOIN categorias c ON p.categoria_id = c.id
                LEFT JOIN tiendas t ON p.tienda_id = t.id";

        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY p.id DESC";

        $stmt = $this->db->prepare($sql);
        if (!empty($valores)) {
            $stmt->bind_param($tipos, ...$valores);
        }
        $stmt->execute();
        $res = $stmt->get_result();

        $productos = [];
        while ($p = $res->fetch_assoc()) {
            $productos[] = $p;
        }

        return ["codigo" => 200, "data" => ["success" => true, "total" => count($productos), "data" => $productos]];
    }

    public function show($id) {
        $stmt = $this->db->prepare("SELECT p.*, c.nombre AS categoria_nombre, t.nombre AS tienda_nombre 
                                    FROM productos p
                                    LEFT JOIN categorias c ON p.categoria_id = c.id
                                    LEFT JOIN tiendas t ON p.tienda_id = t.id
                                    WHERE p.id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $prod = $stmt->get_result()->fetch_assoc();

        if ($prod) {
            return ["codigo" => 200, "data" => ["success" => true, "data" => $prod]];
        }
        return ["codigo" => 404, "data" => ["success" => false, "error" => "Producto no encontrado"]];
    }

    public function store($data) {
        $codigo = trim($data['codigo'] ?? '');
        $nombre = trim($data['nombre'] ?? '');
        $descripcion = trim($data['descripcion'] ?? '');
        $categoria_id = intval($data['categoria_id'] ?? 0);
        $tienda_id = !empty($data['tienda_id']) ? intval($data['tienda_id']) : null;
        $precio = floatval($data['precio'] ?? 0.0);
        $costo = floatval($data['costo'] ?? 0.0);
        $stock = intval($data['stock'] ?? 0);
        $stock_minimo = intval($data['stock_minimo'] ?? 5);
        $imagen = trim($data['imagen'] ?? '');
        $estado = $data['estado'] ?? 'Disponible';

        if (empty($codigo) || empty($nombre) || $categoria_id <= 0) {
            return ["codigo" => 400, "data" => ["success" => false, "error" => "Código, nombre y categoría son requeridos"]];
        }

        $stmtCheck = $this->db->prepare("SELECT id FROM productos WHERE codigo = ?");
        $stmtCheck->bind_param("s", $codigo);
        $stmtCheck->execute();
        if ($stmtCheck->get_result()->num_rows > 0) {
            return ["codigo" => 409, "data" => ["success" => false, "error" => "El código/SKU '$codigo' ya existe"]];
        }

        $stmt = $this->db->prepare("INSERT INTO productos (codigo, nombre, descripcion, categoria_id, tienda_id, precio, costo, stock, stock_minimo, imagen, estado) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssiiddiiss", $codigo, $nombre, $descripcion, $categoria_id, $tienda_id, $precio, $costo, $stock, $stock_minimo, $imagen, $estado);

        if ($stmt->execute()) {
            return ["codigo" => 201, "data" => ["success" => true, "id" => $stmt->insert_id, "message" => "Producto registrado exitosamente"]];
        }
        return ["codigo" => 500, "data" => ["success" => false, "error" => "Error al registrar producto"]];
    }

    public function update($id, $data) {
        if (!$id) {
            return ["codigo" => 422, "data" => ["success" => false, "error" => "ID de producto requerido"]];
        }

        $stmtActual = $this->db->prepare("SELECT * FROM productos WHERE id = ?");
        $stmtActual->bind_param("i", $id);
        $stmtActual->execute();
        $actual = $stmtActual->get_result()->fetch_assoc();
        if (!$actual) {
            return ["codigo" => 404, "data" => ["success" => false, "error" => "Producto no encontrado"]];
        }

        $codigo = trim($data['codigo'] ?? $actual['codigo']);
        $nombre = trim($data['nombre'] ?? $actual['nombre']);
        $descripcion = trim($data['descripcion'] ?? $actual['descripcion']);
        $categoria_id = intval($data['categoria_id'] ?? $actual['categoria_id']);
        $tienda_id = isset($data['tienda_id']) ? (!empty($data['tienda_id']) ? intval($data['tienda_id']) : null) : $actual['tienda_id'];
        $precio = isset($data['precio']) ? floatval($data['precio']) : floatval($actual['precio']);
        $costo = isset($data['costo']) ? floatval($data['costo']) : floatval($actual['costo']);
        $stock = isset($data['stock']) ? intval($data['stock']) : intval($actual['stock']);
        $stock_minimo = isset($data['stock_minimo']) ? intval($data['stock_minimo']) : intval($actual['stock_minimo']);
        $imagen = trim($data['imagen'] ?? $actual['imagen']);
        $estado = trim($data['estado'] ?? $actual['estado']);

        $stmt = $this->db->prepare("UPDATE productos SET codigo = ?, nombre = ?, descripcion = ?, categoria_id = ?, tienda_id = ?, precio = ?, costo = ?, stock = ?, stock_minimo = ?, imagen = ?, estado = ? WHERE id = ?");
        $stmt->bind_param("sssiiddiissi", $codigo, $nombre, $descripcion, $categoria_id, $tienda_id, $precio, $costo, $stock, $stock_minimo, $imagen, $estado, $id);

        if ($stmt->execute()) {
            return ["codigo" => 200, "data" => ["success" => true, "message" => "Producto actualizado correctamente"]];
        }
        return ["codigo" => 500, "data" => ["success" => false, "error" => "Error al actualizar producto"]];
    }

    public function destroy($id) {
        if (!$id) {
            return ["codigo" => 400, "data" => ["success" => false, "error" => "ID requerido"]];
        }

        $stmtCheck = $this->db->prepare("SELECT id FROM detalle_ventas WHERE producto_id = ? LIMIT 1");
        $stmtCheck->bind_param("i", $id);
        $stmtCheck->execute();
        if ($stmtCheck->get_result()->num_rows > 0) {
            return ["codigo" => 409, "data" => ["success" => false, "error" => "No se puede eliminar el producto porque posee historial de ventas."]];
        }

        $stmt = $this->db->prepare("DELETE FROM productos WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            return ["codigo" => 200, "data" => ["success" => true, "message" => "Producto eliminado con éxito"]];
        }
        return ["codigo" => 404, "data" => ["success" => false, "error" => "Producto no encontrado"]];
    }
}
?>
