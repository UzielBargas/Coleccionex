<?php
// api/agregar_a_mazo.php — pone una carta del inventario dentro de un mazo (INSERT protegido).
// Cada carta física puede estar en UN solo mazo (UNIQUE en mazo_cartas.carta_id).
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
    // El mazo y la carta tienen que ser del usuario
    $consulta = $conexion->prepare("SELECT nombre FROM mazos WHERE id = :id AND usuario_id = :usuario");
    $consulta->execute(["id" => $mazoId, "usuario" => $usuarioId]);
    $mazo = $consulta->fetch();
    if (!$mazo) {
        responder(404, ["error" => "Ese mazo no existe."]);
    }

    $consulta = $conexion->prepare("SELECT id FROM cartas WHERE id = :id AND usuario_id = :usuario");
    $consulta->execute(["id" => $cartaId, "usuario" => $usuarioId]);
    if (!$consulta->fetch()) {
        responder(404, ["error" => "Esa carta no está en tu inventario."]);
    }

    $consulta = $conexion->prepare("INSERT INTO mazo_cartas (mazo_id, carta_id) VALUES (:mazo, :carta)");
    $consulta->execute(["mazo" => $mazoId, "carta" => $cartaId]);

    responder(201, ["mensaje" => "Carta agregada a " . $mazo["nombre"] . "."]);
} catch (PDOException $e) {
    if ($e->errorInfo[1] === 1062) { // 1062 = la carta ya está en un mazo (UNIQUE)
        responder(409, ["error" => "Esa carta ya está en un mazo. Sacala primero."]);
    }
    error_log($e->getMessage());
    responder(500, ["error" => "No se pudo agregar la carta al mazo."]);
}
