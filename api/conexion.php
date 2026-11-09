<?php
// api/conexion.php — crea $conexion para que los demas PHP consulten MySQL
$servidor = "localhost";
$base = "coleccionex";
$usuario = "root";
$contrasena = ""; // Solo para XAMPP local

$dsn = "mysql:host=$servidor;dbname=$base;charset=utf8mb4";
$opciones = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $conexion = new PDO($dsn, $usuario, $contrasena, $opciones);
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(
        ["error" => "Error de conexión a la base de datos"],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}
