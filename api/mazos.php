<?php
// api/mazos.php — los mazos del usuario conectado, con cuantas cartas tiene cada uno (SELECT)
require_once __DIR__ . "/respuesta.php";
require_once __DIR__ . "/requiere_login.php";
require_once __DIR__ . "/conexion.php";

try {
    $consulta = $conexion->prepare(
        "SELECT m.id, m.nombre, COUNT(mc.carta_id) AS total_cartas
         FROM mazos m
         LEFT JOIN mazo_cartas mc ON mc.mazo_id = m.id
         WHERE m.usuario_id = :usuario
         GROUP BY m.id, m.nombre
         ORDER BY m.id"
    );
    $consulta->execute(["usuario" => $usuarioId]);
    responder(200, $consulta->fetchAll());
} catch (PDOException $e) {
    error_log($e->getMessage());
    responder(500, ["error" => "No se pudieron obtener tus mazos."]);
}
