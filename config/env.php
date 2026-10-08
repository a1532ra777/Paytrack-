<?php
/**
 * Cargador de Variables de Entorno (.env)
 * Permite parametrizar credenciales y configuraciones de forma segura sin librerías externas.
 */

if (!function_exists('cargarEnv')) {
    function cargarEnv($rutaArchivo = null) {
        if ($rutaArchivo === null) {
            $rutaArchivo = dirname(__DIR__) . '/.env';
        }

        if (!file_exists($rutaArchivo)) {
            return;
        }

        $lineas = file($rutaArchivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lineas as $linea) {
            $linea = trim($linea);
            // Ignorar comentarios
            if ($linea === '' || strpos($linea, '#') === 0) {
                continue;
            }

            // Separar clave=valor
            if (strpos($linea, '=') !== false) {
                list($clave, $valor) = explode('=', $linea, 2);
                $clave = trim($clave);
                $valor = trim($valor);

                // Quitar comillas si las tiene
                $valor = trim($valor, '"\'');

                if (!array_key_exists($clave, $_ENV)) {
                    $_ENV[$clave] = $valor;
                    putenv("$clave=$valor");
                }
            }
        }
    }
}

// Cargar automáticamente al incluir
cargarEnv();

function env($clave, $defecto = null) {
    if (isset($_ENV[$clave])) {
        return $_ENV[$clave];
    }
    $val = getenv($clave);
    return ($val !== false) ? $val : $defecto;
}
?>
