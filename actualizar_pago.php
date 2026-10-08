<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

$conexion = new mysqli("localhost", "root", "", "paytrack");
if ($conexion->connect_error) {
    echo json_encode(["success" => false, "error" => "Error de conexión"]);
    exit();
}

$datos = json_decode(file_get_contents("php://input"), true);

if ($datos) {
    $id = $conexion->real_escape_string($datos['id']);
    $user = $conexion->real_escape_string($datos['user']);
    $dni = $conexion->real_escape_string($datos['dni']);
    $phone = $conexion->real_escape_string($datos['phone']);
    $method = $conexion->real_escape_string($datos['method']);
    $status = $conexion->real_escape_string($datos['status']);
    $amount = $conexion->real_escape_string($datos['amount']);
    $ref = $conexion->real_escape_string($datos['ref']);

    $sql = "UPDATE payments SET client_name='$user', dni='$dni', phone='$phone', method='$method', status='$status', amount='$amount', reference='$ref' WHERE id=$id";
    
    if ($conexion->query($sql) === TRUE) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "error" => $conexion->error]);
    }
}
$conexion->close();
?>