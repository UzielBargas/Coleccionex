<?php
// api/comprar.php — compra una carta publicada por otro usuario (UPDATE + INSERT protegido).
// Pasos: verificar perfil -> descontar saldo al comprador -> pasar la carta al comprador
//        -> pagar al vendedor (menos 5% de comisión) -> guardar en el historial de los dos.
require_once __DIR__ . "/respuesta.php";
require_once __DIR__ . "/requiere_login.php";
require_once __DIR__ . "/conexion.php";
require_once __DIR__ . "/funciones.php";

exigirPost();
exigirPerfilCompleto($conexion, $usuarioId);

$cartaId = leerId($_POST, "carta_id");
if ($cartaId === false) {
    responder(400, ["error" => "Carta inválida."]);
}

try {
    $consulta = $conexion->prepare(
        "SELECT id, usuario_id, nombre, precio FROM cartas WHERE id = :id AND en_venta = 1"
    );
    $consulta->execute(["id" => $cartaId]);
    $carta = $consulta->fetch();

    if (!$carta) {
        responder(404, ["error" => "Esa carta ya no está a la venta."]);
    }
    if ((int) $carta["usuario_id"] === (int) $usuarioId) {
        responder(400, ["error" => "No podés comprar tu propia carta."]);
    }

    $precio = (float) $carta["precio"];
    $comision = round($precio * COMISION, 2);
    $vendedorId = (int) $carta["usuario_id"];

    // Transacción: o se hace todo o no se hace nada
    $conexion->beginTransaction();

    // 1) Cobrar al comprador, solo si le alcanza (la condición está en el WHERE)
    $consulta = $conexion->prepare(
        "UPDATE usuarios SET saldo = saldo - :precio WHERE id = :id AND saldo >= :minimo"
    );
    $consulta->execute(["precio" => $precio, "id" => $usuarioId, "minimo" => $precio]);
    if ($consulta->rowCount() === 0) {
        $conexion->rollBack();
        responder(409, [
            "error" => "No te alcanza el saldo. Cargá saldo desde Mi perfil.",
            "codigo" => "saldo_insuficiente",
        ]);
    }

    // 2) Pasar la carta al comprador. Si otro la compró un segundo antes, no la encuentra.
    $consulta = $conexion->prepare(
        "UPDATE cartas SET usuario_id = :comprador, en_venta = 0
         WHERE id = :id AND en_venta = 1 AND usuario_id = :vendedor"
    );
    $consulta->execute(["comprador" => $usuarioId, "id" => $cartaId, "vendedor" => $vendedorId]);
    if ($consulta->rowCount() === 0) {
        $conexion->rollBack();
        responder(409, ["error" => "Alguien la compró antes que vos."]);
    }

    // 3) Si estaba en un mazo del vendedor, sale del mazo
    $consulta = $conexion->prepare("DELETE FROM mazo_cartas WHERE carta_id = :id");
    $consulta->execute(["id" => $cartaId]);

    // 4) Pagar al vendedor
    $consulta = $conexion->prepare("UPDATE usuarios SET saldo = saldo + :monto WHERE id = :id");
    $consulta->execute(["monto" => $precio - $comision, "id" => $vendedorId]);

    // 5) Historial: una fila para el comprador y otra para el vendedor
    registrarTransaccion($conexion, [
        "usuario" => $usuarioId, "tipo" => "compra", "carta" => $cartaId,
        "otro" => $vendedorId, "monto" => -$precio,
    ]);
    registrarTransaccion($conexion, [
        "usuario" => $vendedorId, "tipo" => "venta", "carta" => $cartaId,
        "otro" => $usuarioId, "monto" => $precio - $comision, "comision" => $comision,
    ]);

    $conexion->commit();

    responder(201, [
        "mensaje" => "¡Compraste " . $carta["nombre"] . "! Ya está en tu inventario.",
        "saldo" => saldoDe($conexion, $usuarioId),
    ]);
} catch (PDOException $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    error_log($e->getMessage());
    responder(500, ["error" => "No se pudo completar la compra."]);
}
