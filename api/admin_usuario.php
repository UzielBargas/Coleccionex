<?php
// api/admin_usuario.php?id=5 — INSPECCIONAR un usuario: sus datos, su historial reciente,
// sus cartas y los reportes que recibió (SELECT, solo administrador)
require_once __DIR__ . "/respuesta.php";
require_once __DIR__ . "/requiere_admin.php";
require_once __DIR__ . "/funciones.php";

$id = leerId($_GET, "id");
if ($id === false) {
    responder(400, ["error" => "Usuario inválido."]);
}

try {
    $consulta = $conexion->prepare(
        "SELECT id, usuario, correo, nombre, apellido, telefono, descripcion, saldo, rol, baneado, creado_en
         FROM usuarios WHERE id = :id"
    );
    $consulta->execute(["id" => $id]);
    $usuario = $consulta->fetch();
    if (!$usuario) {
        responder(404, ["error" => "Ese usuario no existe."]);
    }

    // Últimos 30 movimientos (compras, ventas y cargas de saldo)
    $consulta = $conexion->prepare(
        "SELECT t.id, t.tipo, t.monto, t.comision, t.metodo, t.fecha,
                c.nombre AS carta, c.foto, c.estado, o.usuario AS otro_usuario
         FROM transacciones t
         LEFT JOIN cartas c ON c.id = t.carta_id
         LEFT JOIN usuarios o ON o.id = t.otro_usuario_id
         WHERE t.usuario_id = :id
         ORDER BY t.id DESC
         LIMIT 30"
    );
    $consulta->execute(["id" => $id]);
    $usuario["historial"] = $consulta->fetchAll();

    // Sus cartas
    $consulta = $conexion->prepare(
        "SELECT " . CAMPOS_CARTA . " FROM " . TABLAS_CARTA . " WHERE c.usuario_id = :id ORDER BY c.id DESC"
    );
    $consulta->execute(["id" => $id]);
    $usuario["cartas"] = $consulta->fetchAll();

    // Reportes que recibió
    $consulta = $conexion->prepare(
        "SELECT r.id, r.motivo, r.descripcion, r.estado, r.fecha, de.usuario AS denunciante
         FROM reportes r JOIN usuarios de ON de.id = r.denunciante_id
         WHERE r.denunciado_id = :id
         ORDER BY r.id DESC"
    );
    $consulta->execute(["id" => $id]);
    $usuario["reportes"] = $consulta->fetchAll();

    responder(200, $usuario);
} catch (PDOException $e) {
    error_log($e->getMessage());
    responder(500, ["error" => "No se pudo inspeccionar el usuario."]);
}
