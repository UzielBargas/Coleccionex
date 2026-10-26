<?php
// api/crear_mazo.php — crea un mazo vacío (INSERT protegido)
require_once __DIR__ . "/respuesta.php";
require_once __DIR__ . "/requiere_login.php";
require_once __DIR__ . "/conexion.php";

exigirPost();

$nombre = trim($_POST["nombre"] ?? "");
if ($nombre === "" || mb_strlen($nombre) > 60) {
    responder(400, ["error" => "Escribí un nombre de hasta 60 caracteres."]);
}

try {
    $consulta = $conexion->prepare(
        "INSERT INTO mazos (usuario_id, nombre) VALUES (:usuario, :nombre)"
    );
    $consulta->execute(["usuario" => $usuarioId, "nombre" => $nombre]);
    responder(201, [
        "mensaje" => "Mazo creado.",
        "id" => (int) $conexion->lastInsertId(),
    ]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    responder(500, ["error" => "No se pudo crear el mazo."]);
}
