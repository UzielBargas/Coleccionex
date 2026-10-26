<?php
// api/borrar_carta.php — quita una carta del inventario (DELETE protegido)
require_once __DIR__ . "/respuesta.php";
require_once __DIR__ . "/requiere_login.php";
require_once __DIR__ . "/conexion.php";

exigirPost();

$cartaId = leerId($_POST, "carta_id");
if ($cartaId === false) {
    responder(400, ["error" => "Carta inválida."]);
}

try {
    $consulta = $conexion->prepare("SELECT foto FROM cartas WHERE id = :id AND usuario_id = :usuario");
    $consulta->execute(["id" => $cartaId, "usuario" => $usuarioId]);
    $carta = $consulta->fetch();
    if (!$carta) {
        responder(404, ["error" => "Esa carta no está en tu inventario."]);
    }

    // ¡Siempre con WHERE! Y con usuario_id, para no borrar cartas de otro
    $consulta = $conexion->prepare("DELETE FROM cartas WHERE id = :id AND usuario_id = :usuario");
    $consulta->execute(["id" => $cartaId, "usuario" => $usuarioId]);

    // Borramos también la foto del disco (solo si está en la carpeta de subidas)
    if ($carta["foto"] && str_starts_with($carta["foto"], "imagenes/subidas/")) {
        @unlink(__DIR__ . "/../" . $carta["foto"]);
    }

    responder(200, ["mensaje" => "Carta eliminada de tu inventario."]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    responder(500, ["error" => "No se pudo eliminar la carta."]);
}
