<?php
// api/registro.php — crea un usuario nuevo (INSERT) y ya inicia su sesión
require_once __DIR__ . "/respuesta.php";
require_once __DIR__ . "/conexion.php";

exigirPost();

$usuario = trim($_POST["usuario"] ?? "");
$correo = trim($_POST["correo"] ?? "");
$contrasena = $_POST["contrasena"] ?? "";
$confirmar = $_POST["confirmar-contrasena"] ?? "";

if ($usuario === "" || strlen($usuario) > 50 || !filter_var($correo, FILTER_VALIDATE_EMAIL) || strlen($contrasena) < 8) {
    responder(400, ["error" => "Completá usuario, un correo válido y una contraseña de 8 caracteres o más."]);
}
if ($contrasena !== $confirmar) {
    responder(400, ["error" => "Las contraseñas no coinciden."]);
}

try {
    $consulta = $conexion->prepare(
        "INSERT INTO usuarios (usuario, correo, password_hash)
         VALUES (:usuario, :correo, :hash)"
    );
    $consulta->execute([
        "usuario" => $usuario,
        "correo" => $correo,
        "hash" => password_hash($contrasena, PASSWORD_DEFAULT),
    ]);
    $nuevoId = (int) $conexion->lastInsertId();

    // Entra directo, sin tener que volver a escribir usuario y contraseña
    session_start();
    session_regenerate_id(true);
    $_SESSION["usuario_id"] = $nuevoId;
    $_SESSION["usuario_nombre"] = $usuario;

    responder(201, ["mensaje" => "¡Cuenta creada! Completá tu perfil para empezar a comprar."]);
} catch (PDOException $e) {
    if ($e->errorInfo[1] === 1062) { // 1062 = valor repetido en un campo UNIQUE
        responder(409, ["error" => "Ese usuario o correo ya está registrado."]);
    }
    error_log($e->getMessage());
    responder(500, ["error" => "No se pudo crear la cuenta."]);
}
