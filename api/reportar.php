<?php
// api/reportar.php — denuncia a un vendedor (INSERT protegido). Los reportes los revisa el administrador.
require_once __DIR__ . "/respuesta.php";
require_once __DIR__ . "/requiere_login.php";
require_once __DIR__ . "/conexion.php";
require_once __DIR__ . "/funciones.php";

exigirPost();

$denunciadoId = leerId($_POST, "vendedor_id");
$cartaId = leerId($_POST, "carta_id");
$motivo = $_POST["motivo"] ?? "";
$descripcion = trim($_POST["descripcion"] ?? "");

if ($denunciadoId === false) {
    responder(400, ["error" => "Vendedor inválido."]);
}
if ((int) $denunciadoId === (int) $usuarioId) {
    responder(400, ["error" => "No podés reportarte a vos mismo."]);
}
if (!in_array($motivo, MOTIVOS_REPORTE, true)) {
    responder(400, ["error" => "Elegí el motivo del reporte."]);
}
if (mb_strlen($descripcion) < 10 || mb_strlen($descripcion) > 500) {
    responder(400, ["error" => "Contá qué pasó (entre 10 y 500 caracteres)."]);
}

try {
    $consulta = $conexion->prepare(
        "INSERT INTO reportes (denunciante_id, denunciado_id, carta_id, motivo, descripcion)
         VALUES (:denunciante, :denunciado, :carta, :motivo, :descripcion)"
    );
    $consulta->execute([
        "denunciante" => $usuarioId,
        "denunciado" => $denunciadoId,
        "carta" => $cartaId === false ? null : $cartaId,
        "motivo" => $motivo,
        "descripcion" => $descripcion,
    ]);
    responder(201, ["mensaje" => "Reporte enviado. Un administrador lo va a revisar."]);
} catch (PDOException $e) {
    if ($e->errorInfo[1] === 1452) {
        responder(404, ["error" => "Ese vendedor no existe."]);
    }
    error_log($e->getMessage());
    responder(500, ["error" => "No se pudo enviar el reporte."]);
}
