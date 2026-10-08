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
    $user = $conexion->real_escape_string($datos['user']);
    $dni = $conexion->real_escape_string($datos['dni']);
    $phone = $conexion->real_escape_string($datos['phone']);
    $method = $conexion->real_escape_string($datos['method']);
    $amount = $conexion->real_escape_string($datos['amount']);
    $ref = $conexion->real_escape_string($datos['ref']);
    $status = $conexion->real_escape_string($datos['status']);
    $datetime = $conexion->real_escape_string($datos['datetime']);
    $image = $conexion->real_escape_string($datos['image']);

    $sql = "INSERT INTO payments (client_name, dni, phone, method, amount, reference, status, datetime, image) 
            VALUES ('$user', '$dni', '$phone', '$method', '$amount', '$ref', '$status', '$datetime', '$image')";

    if ($conexion->query($sql) === TRUE) {
        echo json_encode(["success" => true, "message" => "Guardado con éxito"]);
    } else {
        echo json_encode(["success" => false, "error" => $conexion->error]);
    }
}
$conexion->close();
?>