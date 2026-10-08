<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
$conexion = new mysqli("localhost", "root", "", "paytrack");
$resultado = $conexion->query("SELECT * FROM payments ORDER BY id DESC");
$pagos = [];
while($row = $resultado->fetch_assoc()) {
    $pagos[] = $row;
}
echo json_encode($pagos);
$conexion->close();
?>