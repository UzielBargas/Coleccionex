<?php
// api/mazo.php?id=3 — detalle de UN mazo: su nombre y sus cartas (SELECT)
require_once __DIR__ . "/respuesta.php";
require_once __DIR__ . "/requiere_login.php";
require_once __DIR__ . "/conexion.php";
require_once __DIR__ . "/funciones.php";

$mazoId = leerId($_GET, "id");
if ($mazoId === false) {
    responder(400, ["error" => "Mazo inválido."]);
}

try {
    // Primero comprobamos que el mazo sea del usuario conectado
    $consulta = $conexion->prepare(
        "SELECT id, nombre FROM mazos WHERE id = :id AND usuario_id = :usuario"
    );
    $consulta->execute(["id" => $mazoId, "usuario" => $usuarioId]);
    $mazo = $consulta->fetch();

    if (!$mazo) {
        responder(404, ["error" => "Ese mazo no existe."]);
    }

    $consulta = $conexion->prepare(
        "SELECT " . CAMPOS_CARTA . "
         FROM " . TABLAS_CARTA . "
         JOIN mazo_cartas mc ON mc.carta_id = c.id
         WHERE mc.mazo_id = :id
         ORDER BY c.nombre"
    );
    $consulta->execute(["id" => $mazoId]);
    $mazo["cartas"] = $consulta->fetchAll();

    responder(200, $mazo);
} catch (PDOException $e) {
    error_log($e->getMessage());
    responder(500, ["error" => "No se pudo obtener el mazo."]);
}
