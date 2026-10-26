<?php
// api/quitar_de_mazo.php — saca una carta de un mazo (DELETE protegido). La carta sigue en el inventario.
require_once __DIR__ . "/respuesta.php";
require_once __DIR__ . "/requiere_login.php";
require_once __DIR__ . "/conexion.php";

exigirPost();

$mazoId = leerId($_POST, "mazo_id");
$cartaId = leerId($_POST, "carta_id");
if ($mazoId === false || $cartaId === false) {
    responder(400, ["error" => "Elegí un mazo y una carta."]);
}

try {
    // El mazo tiene que ser del usuario
    $consulta = $conexion->prepare("SELECT id FROM mazos WHERE id = :id AND usuario_id = :usuario");
    $consulta->execute(["id" => $mazoId, "usuario" => $usuarioId]);
    if (!$consulta->fetch()) {
        responder(404, ["error" => "Ese mazo no existe."]);
    }

    $consulta = $conexion->prepare("DELETE FROM mazo_cartas WHERE mazo_id = :mazo AND carta_id = :carta");
    $consulta->execute(["mazo" => $mazoId, "carta" => $cartaId]);

    if ($consulta->rowCount() === 0) {
        responder(404, ["error" => "Esa carta no está en el mazo."]);
    }
    responder(200, ["mensaje" => "Carta quitada del mazo."]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    responder(500, ["error" => "No se pudo quitar la carta."]);
}
