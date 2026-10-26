<?php
// api/sesion.php — ¿quién está conectado? Devuelve sus datos de perfil, su saldo y sus números.
// Si no hay sesión responde 401 y la página se muestra en modo visitante.
require_once __DIR__ . "/respuesta.php";
require_once __DIR__ . "/requiere_login.php";
require_once __DIR__ . "/conexion.php";

try {
    $consulta = $conexion->prepare(
        "SELECT id, usuario, correo, nombre, apellido, telefono, descripcion, saldo, rol, baneado, creado_en,
                (SELECT COUNT(*) FROM cartas WHERE usuario_id = u.id) AS cartas,
                (SELECT COUNT(*) FROM cartas WHERE usuario_id = u.id AND en_venta = 1) AS en_venta,
                (SELECT COUNT(*) FROM transacciones WHERE usuario_id = u.id AND tipo = 'venta') AS ventas,
                (SELECT COUNT(*) FROM transacciones WHERE usuario_id = u.id AND tipo = 'compra') AS compras,
                (SELECT COUNT(*) FROM mazos WHERE usuario_id = u.id) AS mazos
         FROM usuarios u
         WHERE id = :id"
    );
    $consulta->execute(["id" => $usuarioId]);
    $fila = $consulta->fetch();
} catch (PDOException $e) {
    error_log($e->getMessage());
    responder(500, ["error" => "No se pudo leer la sesión."]);
}

// El usuario se borró de la base o lo suspendieron: cerramos su sesión
if (!$fila || (int) $fila["baneado"] === 1) {
    $_SESSION = [];
    session_destroy();
    responder(401, ["error" => "No iniciaste sesión."]);
}

// Perfil completo = puede comprar y cargar saldo
$fila["perfil_completo"] = trim($fila["nombre"]) !== "" && trim($fila["telefono"]) !== "";

responder(200, $fila);
