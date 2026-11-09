<?php
// api/login.php — verifica usuario (o correo) y contraseña (SELECT) e inicia la sesión
require_once __DIR__ . "/respuesta.php";
require_once __DIR__ . "/conexion.php";

session_start();
exigirPost();

$usuario = trim($_POST["usuario"] ?? "");
$contrasena = $_POST["contrasena"] ?? "";

if ($usuario === "" || $contrasena === "") {
    responder(400, ["error" => "Completá usuario y contraseña."]);
}

try {
    // Se puede entrar con el nombre de usuario o con el correo
    $consulta = $conexion->prepare(
        "SELECT id, usuario, password_hash, baneado FROM usuarios
         WHERE usuario = :usuario OR correo = :correo"
    );
    $consulta->execute(["usuario" => $usuario, "correo" => $usuario]);
    $fila = $consulta->fetch();
} catch (PDOException $e) {
    error_log($e->getMessage());
    responder(500, ["error" => "No se pudo iniciar sesión."]);
}

if (!$fila || !password_verify($contrasena, $fila["password_hash"])) {
    responder(401, ["error" => "Usuario o contraseña incorrectos."]);
}

// Cuenta bloqueada por un administrador
if ((int) $fila["baneado"] === 1) {
    responder(403, ["error" => "Tu cuenta fue suspendida por infringir las normas de Coleccionex."]);
}

session_regenerate_id(true);
$_SESSION["usuario_id"] = $fila["id"];
$_SESSION["usuario_nombre"] = $fila["usuario"];

responder(200, ["usuario" => $fila["usuario"]]);
