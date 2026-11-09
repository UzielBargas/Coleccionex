<?php
// api/admin_banear.php — banea o reactiva una cuenta (UPDATE, solo administrador)
//   id=5  accion=banear | desbanear
// Al banear: la cuenta no puede iniciar sesión, su sesión abierta se corta (sesion.php lo controla),
// sus publicaciones salen del Mercado y sus reportes pendientes quedan revisados.
require_once __DIR__ . "/respuesta.php";
require_once __DIR__ . "/requiere_admin.php";

exigirPost();

$id = leerId($_POST, "id");
$accion = $_POST["accion"] ?? "";
if ($id === false || !in_array($accion, ["banear", "desbanear"], true)) {
    responder(400, ["error" => "Datos inválidos."]);
}
if ((int) $id === (int) $usuarioId) {
    responder(400, ["error" => "No podés banear tu propia cuenta."]);
}

try {
    $consulta = $conexion->prepare("SELECT usuario, rol FROM usuarios WHERE id = :id");
    $consulta->execute(["id" => $id]);
    $objetivo = $consulta->fetch();
    if (!$objetivo) {
        responder(404, ["error" => "Ese usuario no existe."]);
    }
    if ($objetivo["rol"] === "admin") {
        responder(400, ["error" => "No se puede banear a otro administrador."]);
    }

    $conexion->beginTransaction();

    $consulta = $conexion->prepare("UPDATE usuarios SET baneado = :baneado WHERE id = :id");
    $consulta->execute(["baneado" => $accion === "banear" ? 1 : 0, "id" => $id]);

    if ($accion === "banear") {
        // Sus cartas dejan de estar a la venta
        $consulta = $conexion->prepare("UPDATE cartas SET en_venta = 0 WHERE usuario_id = :id");
        $consulta->execute(["id" => $id]);

        // Los reportes contra él quedan resueltos
        $consulta = $conexion->prepare(
            "UPDATE reportes SET estado = 'revisado' WHERE denunciado_id = :id AND estado = 'pendiente'"
        );
        $consulta->execute(["id" => $id]);
    }

    $conexion->commit();

    responder(200, [
        "mensaje" => $accion === "banear"
            ? "@" . $objetivo["usuario"] . " fue baneado. Sus publicaciones salieron del Mercado."
            : "@" . $objetivo["usuario"] . " puede volver a usar su cuenta.",
    ]);
} catch (PDOException $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    error_log($e->getMessage());
    responder(500, ["error" => "No se pudo cambiar el estado de la cuenta."]);
}
