<?php
/**
 * Controlador de Compras y Abastecimiento
 */

require_once dirname(__DIR__) . '/services/CompraService.php';

class CompraController {
    private $service;

    public function __construct($conexion) {
        $this->service = new CompraService($conexion);
    }

    public function index() {
        $compras = $this->service->listarCompras();
        return ["codigo" => 200, "data" => ["success" => true, "total" => count($compras), "data" => $compras]];
    }

    public function show($id) {
        $compra = $this->service->obtenerCompraPorId($id);
        if ($compra) {
            return ["codigo" => 200, "data" => ["success" => true, "data" => $compra]];
        }
        return ["codigo" => 404, "data" => ["success" => false, "error" => "Orden de compra no encontrada"]];
    }

    public function store($data) {
        try {
            $resultado = $this->service->registrarCompra($data);
            return [
                "codigo" => 201,
                "data" => array_merge(["success" => true, "message" => "Compra registrada y stock incrementado"], $resultado)
            ];
        } catch (InvalidArgumentException $e) {
            return ["codigo" => 400, "data" => ["success" => false, "error" => $e->getMessage()]];
        } catch (Exception $e) {
            return ["codigo" => 422, "data" => ["success" => false, "error" => $e->getMessage()]];
        }
    }
}
?>
