<?php
// api/perfil.php — modifica los datos del perfil (UPDATE protegido)
require_once __DIR__ . "/respuesta.php";
require_once __DIR__ . "/requiere_login.php";
require_once __DIR__ . "/conexion.php";

exigirPost();

$nombre = trim($_POST["nombre"] ?? "");
$apellido = trim($_POST["apellido"] ?? "");
$telefono = trim($_POST["telefono"] ?? "");
$correo = trim($_POST["correo"] ?? "");
$descripcion = trim($_POST["descripcion"] ?? "");

if ($nombre === "" || mb_strlen($nombre) > 80 || mb_strlen($apellido) > 80) {
    responder(400, ["error" => "Escribí tu nombre (hasta 80 caracteres)."]);
}
// Teléfono: números, espacios, guiones, paréntesis y +. Entre 6 y 30 caracteres
if (!preg_match('/^[0-9 +()\-]{6,30}$/', $telefono)) {
    responder(400, ["error" => "Escribí un teléfono válido, por ejemplo 299 400-1122."]);
}
if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    responder(400, ["error" => "Escribí un correo válido."]);
}
if (mb_strlen($descripcion) > 300) {
    responder(400, ["error" => "La descripción puede tener hasta 300 caracteres."]);
}

try {
    $consulta = $conexion->prepare(
        "UPDATE usuarios
         SET nombre = :nombre, apellido = :apellido, telefono = :telefono, correo = :correo, descripcion = :descripcion
         WHERE id = :id"
    );
    $consulta->execute([
        "nombre" => $nombre,
        "apellido" => $apellido,
        "telefono" => $telefono,
        "correo" => $correo,
        "descripcion" => $descripcion,
        "id" => $usuarioId,
    ]);
    responder(200, ["mensaje" => "Perfil actualizado."]);
} catch (PDOException $e) {
    if ($e->errorInfo[1] === 1062) {
        responder(409, ["error" => "Ese correo ya lo usa otra cuenta."]);
    }
    error_log($e->getMessage());
    responder(500, ["error" => "No se pudo guardar el perfil."]);
}
