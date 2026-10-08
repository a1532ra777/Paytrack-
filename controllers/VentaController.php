<?php
/**
 * Controlador de Ventas (Entrega Final)
 */

require_once dirname(__DIR__) . '/services/VentaService.php';

class VentaController {
    private $service;

    public function __construct($conexion) {
        $this->service = new VentaService($conexion);
    }

    public function index($params = []) {
        $ventas = $this->service->listarVentas($params);
        return ["codigo" => 200, "data" => ["success" => true, "total" => count($ventas), "data" => $ventas]];
    }

    public function show($id) {
        $venta = $this->service->obtenerVentaPorId($id);
        if ($venta) {
            return ["codigo" => 200, "data" => ["success" => true, "data" => $venta]];
        }
        return ["codigo" => 404, "data" => ["success" => false, "error" => "Factura de venta no encontrada"]];
    }

    public function store($data) {
        try {
            $resultado = $this->service->procesarVenta($data);
            return [
                "codigo" => 201,
                "data" => array_merge(["success" => true, "message" => "Venta procesada con éxito y stock descontado"], $resultado)
            ];
        } catch (InvalidArgumentException $e) {
            return ["codigo" => 400, "data" => ["success" => false, "error" => $e->getMessage()]];
        } catch (Exception $e) {
            return ["codigo" => 422, "data" => ["success" => false, "error" => $e->getMessage()]];
        }
    }

    public function destroy($id) {
        try {
            $this->service->anularVenta($id);
            return ["codigo" => 200, "data" => ["success" => true, "message" => "Venta anulada y stock retornado a inventario"]];
        } catch (Exception $e) {
            return ["codigo" => 400, "data" => ["success" => false, "error" => $e->getMessage()]];
        }
    }
}
?>
