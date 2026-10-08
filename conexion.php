<?php
/**
 * Conexión centralizada a la base de datos MySQL (PayTrack)
 * Compatible con XAMPP / MariaDB / MySQL
 * Parametrización mediante variables de entorno (.env) según Rúbrica Técnica
 */

require_once __DIR__ . '/config/env.php';

// Encabezados CORS y tipo de contenido JSON
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

// Manejo de peticiones pre-flight OPTIONS
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Parámetros de conexión desde variables de entorno
$db_host = env('DB_HOST', 'localhost');
$db_port = intval(env('DB_PORT', 3306));
$db_user = env('DB_USER', 'root');
$db_pass = env('DB_PASS', '');
$db_name = env('DB_NAME', 'paytrack');
$db_charset = env('DB_CHARSET', 'utf8mb4');

try {
    // Modo estricto de reporte de errores
    mysqli_report(MYSQLI_REPORT_OFF);

    $conexion = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);

    if ($conexion->connect_error) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "Error de conexión a la base de datos: " . $conexion->connect_error,
            "servidor" => "$db_host:$db_port",
            "database" => $db_name,
            "ayuda" => "Verifique que MySQL esté iniciado en XAMPP y que la base de datos 'paytrack' haya sido importada."
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // Configurar codificación UTF-8 para caracteres especiales y acentos
    $conexion->set_charset($db_charset);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Excepción en el servidor de base de datos: " . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * Función auxiliar para obtener el cuerpo de la petición (JSON, $_POST o php://input)
 */
function obtenerDatosEntrada() {
    $raw = file_get_contents("php://input");
    $json = json_decode($raw, true);
    if (is_array($json)) {
        return $json;
    }
    return !empty($_POST) ? $_POST : [];
}

/**
 * Función auxiliar para responder JSON estándar con códigos de estado HTTP
 */
function responderJSON($data, $codigo_http = 200) {
    http_response_code($codigo_http);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}
?>
