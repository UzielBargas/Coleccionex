<?php
// api/colecciones.php — lista de colecciones (para los filtros y el formulario de publicar). Pública.
require_once __DIR__ . "/respuesta.php";
require_once __DIR__ . "/conexion.php";

try {
    $consulta = $conexion->query(
        "SELECT co.id, co.juego, co.nombre,
                (SELECT COUNT(*) FROM cartas c WHERE c.coleccion_id = co.id AND c.en_venta = 1) AS en_venta
         FROM colecciones co
         ORDER BY co.juego, co.nombre"
    );
    responder(200, $consulta->fetchAll());
} catch (PDOException $e) {
    error_log($e->getMessage());
    responder(500, ["error" => "No se pudieron obtener las colecciones."]);
}
