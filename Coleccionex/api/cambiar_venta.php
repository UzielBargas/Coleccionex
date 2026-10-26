<?php
// api/cambiar_venta.php — pone a la venta una carta del inventario o pausa su venta (UPDATE protegido)
//   accion=publicar + precio   ó   accion=pausar
require_once __DIR__ . "/respuesta.php";
require_once __DIR__ . "/requiere_login.php";
require_once __DIR__ . "/conexion.php";
require_once __DIR__ . "/funciones.php";

exigirPost();

$cartaId = leerId($_POST, "carta_id");
$accion = $_POST["accion"] ?? "";
if ($cartaId === false || !in_array($accion, ["publicar", "pausar"], true)) {
    responder(400, ["error" => "Datos inválidos."]);
}

try {
    $consulta = $conexion->prepare("SELECT foto FROM cartas WHERE id = :id AND usuario_id = :usuario");
    $consulta->execute(["id" => $cartaId, "usuario" => $usuarioId]);
    $carta = $consulta->fetch();
    if (!$carta) {
        responder(404, ["error" => "Esa carta no está en tu inventario."]);
    }

    if ($accion === "pausar") {
        $consulta = $conexion->prepare("UPDATE cartas SET en_venta = 0 WHERE id = :id AND usuario_id = :usuario");
        $consulta->execute(["id" => $cartaId, "usuario" => $usuarioId]);
        responder(200, ["mensaje" => "Pausaste la venta. La carta sigue en tu inventario."]);
    }

    // Publicar: hace falta un precio y una foto (requisito del documento)
    $precio = leerPrecio($_POST["precio"] ?? "");
    if ($precio === false) {
        responder(400, ["error" => "Escribí un precio válido en pesos."]);
    }
    if (!$carta["foto"]) {
        responder(400, ["error" => "Para publicar, la carta necesita una foto."]);
    }

    $consulta = $conexion->prepare(
        "UPDATE cartas SET en_venta = 1, precio = :precio WHERE id = :id AND usuario_id = :usuario"
    );
    $consulta->execute(["precio" => $precio, "id" => $cartaId, "usuario" => $usuarioId]);
    responder(200, ["mensaje" => "¡Carta publicada en el Mercado!"]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    responder(500, ["error" => "No se pudo cambiar la publicación."]);
}
