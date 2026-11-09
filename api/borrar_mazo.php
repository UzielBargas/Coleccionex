<?php
// api/borrar_mazo.php — borra un mazo del usuario (DELETE protegido).
// Las cartas NO se pierden: vuelven a "cartas sin mazo" (solo se borran las filas de mazo_cartas).
require_once __DIR__ . "/respuesta.php";
require_once __DIR__ . "/requiere_login.php";
require_once __DIR__ . "/conexion.php";

exigirPost();

$mazoId = leerId($_POST, "id");
if ($mazoId === false) {
    responder(400, ["error" => "Mazo inválido."]);
}

try {
    // ¡Siempre con WHERE! Y tambien con usuario_id, para no borrar mazos de otro usuario
    $consulta = $conexion->prepare(
        "DELETE FROM mazos WHERE id = :id AND usuario_id = :usuario"
    );
    $consulta->execute(["id" => $mazoId, "usuario" => $usuarioId]);

    if ($consulta->rowCount() === 0) {
        responder(404, ["error" => "Ese mazo no existe."]);
    }
    responder(200, ["mensaje" => "Mazo borrado."]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    responder(500, ["error" => "No se pudo borrar el mazo."]);
}
