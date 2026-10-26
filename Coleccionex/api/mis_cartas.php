<?php
// api/mis_cartas.php — el INVENTARIO del usuario: todas sus cartas, en venta o no (SELECT)
require_once __DIR__ . "/respuesta.php";
require_once __DIR__ . "/requiere_login.php";
require_once __DIR__ . "/conexion.php";
require_once __DIR__ . "/funciones.php";

try {
    $consulta = $conexion->prepare(
        "SELECT " . CAMPOS_CARTA . ", m.id AS mazo_id, m.nombre AS mazo
         FROM " . TABLAS_CARTA . "
         LEFT JOIN mazo_cartas mc ON mc.carta_id = c.id
         LEFT JOIN mazos m ON m.id = mc.mazo_id
         WHERE c.usuario_id = :usuario
         ORDER BY c.id DESC"
    );
    $consulta->execute(["usuario" => $usuarioId]);
    responder(200, $consulta->fetchAll());
} catch (PDOException $e) {
    error_log($e->getMessage());
    responder(500, ["error" => "No se pudo obtener tu inventario."]);
}
