<?php
// api/admin_reportes.php — BANDEJA DE REPORTES del administrador (SELECT)
// Opcional: ?estado=pendiente | revisado   (sin estado: todos)
require_once __DIR__ . "/respuesta.php";
require_once __DIR__ . "/requiere_admin.php";

$condicion = "";
$parametros = [];
if (in_array($_GET["estado"] ?? "", ["pendiente", "revisado"], true)) {
    $condicion = "WHERE r.estado = :estado";
    $parametros["estado"] = $_GET["estado"];
}

try {
    $consulta = $conexion->prepare(
        "SELECT r.id, r.motivo, r.descripcion, r.estado, r.fecha,
                r.denunciante_id, de.usuario AS denunciante,
                r.denunciado_id, da.usuario AS denunciado, da.baneado AS denunciado_baneado,
                r.carta_id, c.nombre AS carta, c.foto, c.precio, c.estado AS estado_carta,
                (SELECT COUNT(*) FROM reportes r2 WHERE r2.denunciado_id = r.denunciado_id) AS reportes_del_usuario
         FROM reportes r
         JOIN usuarios de ON de.id = r.denunciante_id
         JOIN usuarios da ON da.id = r.denunciado_id
         LEFT JOIN cartas c ON c.id = r.carta_id
         " . $condicion . "
         ORDER BY (r.estado = 'pendiente') DESC, r.id DESC"
    );
    $consulta->execute($parametros);
    $reportes = $consulta->fetchAll();

    // Números para las tarjetas de arriba del panel
    $resumen = $conexion->query(
        "SELECT
            (SELECT COUNT(*) FROM reportes WHERE estado = 'pendiente') AS pendientes,
            (SELECT COUNT(*) FROM reportes WHERE estado = 'revisado') AS revisados,
            (SELECT COUNT(*) FROM usuarios WHERE baneado = 1) AS baneados,
            (SELECT COUNT(*) FROM usuarios WHERE rol = 'cliente') AS clientes"
    )->fetch();

    responder(200, ["reportes" => $reportes, "resumen" => $resumen]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    responder(500, ["error" => "No se pudieron obtener los reportes."]);
}
