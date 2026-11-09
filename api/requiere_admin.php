<?php
// api/requiere_admin.php — se incluye al principio de cada endpoint del panel de administrador.
// Primero exige sesión (requiere_login.php) y después que el usuario tenga rol = 'admin'.
require_once __DIR__ . "/requiere_login.php";
require_once __DIR__ . "/conexion.php";

$consulta = $conexion->prepare("SELECT rol FROM usuarios WHERE id = :id");
$consulta->execute(["id" => $usuarioId]);

if ($consulta->fetchColumn() !== "admin") {
    responder(403, ["error" => "Solo un administrador puede hacer esto."]);
}
