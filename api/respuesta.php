<?php
// api/respuesta.php — todos los endpoints responden con un codigo HTTP y un JSON
header("Content-Type: application/json; charset=utf-8");

// Envía un código HTTP y un JSON, y termina el programa
function responder($codigo, $datos) {
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

// Corta el programa si la petición no llegó por POST
function exigirPost() {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        responder(405, ["error" => "Método no permitido"]);
    }
}

// Lee un numero entero que llego por POST o GET (ej: un id). Devuelve false si no es valido
function leerId($datos, $campo) {
    return filter_var($datos[$campo] ?? "", FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
}
