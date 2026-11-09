<?php
// api/admin_resolver.php — marca un reporte como revisado (o lo vuelve a pendiente) (UPDATE, solo administrador)
//   id=3  estado=revisado | pendiente
require_once __DIR__ . "/respuesta.php";
require_once __DIR__ . "/requiere_admin.php";

exigirPost();

$reporteId = leerId($_POST, "id");
$estado = $_POST["estado"] ?? "revisado";
if ($reporteId === false || !in_array($estado, ["pendiente", "revisado"], true)) {
    responder(400, ["error" => "Datos inválidos."]);
}

try {
    $consulta = $conexion->prepare("UPDATE reportes SET estado = :estado WHERE id = :id");
    $consulta->execute(["estado" => $estado, "id" => $reporteId]);

    if ($consulta->rowCount() === 0) {
        // rowCount es 0 si no existe, o si ya tenía ese estado: comprobamos cuál de las dos
        $existe = $conexion->prepare("SELECT id FROM reportes WHERE id = :id");
        $existe->execute(["id" => $reporteId]);
        if (!$existe->fetch()) {
            responder(404, ["error" => "Ese reporte no existe."]);
        }
    }
    responder(200, ["mensaje" => $estado === "revisado" ? "Reporte marcado como revisado." : "Reporte reabierto."]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    responder(500, ["error" => "No se pudo actualizar el reporte."]);
}
