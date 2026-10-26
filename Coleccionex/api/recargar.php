<?php
// api/recargar.php — carga saldo en pesos (UPDATE protegido).
// IMPORTANTE: es una SIMULACIÓN para el proyecto. No se conecta con Mercado Pago, Ualá ni Naranja X
// y no se cobra dinero real. En un sitio real, el saldo se acredita cuando la billetera confirma el pago.
require_once __DIR__ . "/respuesta.php";
require_once __DIR__ . "/requiere_login.php";
require_once __DIR__ . "/conexion.php";
require_once __DIR__ . "/funciones.php";

exigirPost();
exigirPerfilCompleto($conexion, $usuarioId);   // el documento pide validar la identidad antes de cargar saldo

$monto = leerPrecio($_POST["monto"] ?? "");
$metodo = $_POST["metodo"] ?? "";

if ($monto === false || $monto < 500 || $monto > 1000000) {
    responder(400, ["error" => "El monto tiene que estar entre $ 500 y $ 1.000.000."]);
}
if (!in_array($metodo, METODOS_PAGO, true)) {
    responder(400, ["error" => "Elegí un método de pago."]);
}

try {
    $conexion->beginTransaction();

    $consulta = $conexion->prepare("UPDATE usuarios SET saldo = saldo + :monto WHERE id = :id");
    $consulta->execute(["monto" => $monto, "id" => $usuarioId]);

    registrarTransaccion($conexion, [
        "usuario" => $usuarioId, "tipo" => "recarga", "monto" => $monto, "metodo" => $metodo,
    ]);

    $conexion->commit();

    responder(200, [
        "mensaje" => "Saldo cargado con " . $metodo . ".",
        "saldo" => saldoDe($conexion, $usuarioId),
    ]);
} catch (PDOException $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    error_log($e->getMessage());
    responder(500, ["error" => "No se pudo cargar el saldo."]);
}
