<?php
// api/historial.php — movimientos del usuario: compras, ventas y cargas de saldo (SELECT)
// Opcional: ?tipo=compra|venta|recarga   ?limite=5
require_once __DIR__ . "/respuesta.php";
require_once __DIR__ . "/requiere_login.php";
require_once __DIR__ . "/conexion.php";

$condiciones = ["t.usuario_id = :usuario"];
$parametros = ["usuario" => $usuarioId];

if (in_array($_GET["tipo"] ?? "", ["compra", "venta", "recarga"], true)) {
    $condiciones[] = "t.tipo = :tipo";
    $parametros["tipo"] = $_GET["tipo"];
}

$limite = leerId($_GET, "limite");
if ($limite === false || $limite > 200) {
    $limite = 200;
}

try {
    $consulta = $conexion->prepare(
        "SELECT t.id, t.tipo, t.monto, t.comision, t.metodo, t.fecha,
                c.nombre AS carta, c.foto, c.estado, co.nombre AS coleccion,
                o.usuario AS otro_usuario
         FROM transacciones t
         LEFT JOIN cartas c ON c.id = t.carta_id
         LEFT JOIN colecciones co ON co.id = c.coleccion_id
         LEFT JOIN usuarios o ON o.id = t.otro_usuario_id
         WHERE " . implode(" AND ", $condiciones) . "
         ORDER BY t.id DESC
         LIMIT " . $limite   // $limite ya es un número validado
    );
    $consulta->execute($parametros);
    responder(200, $consulta->fetchAll());
} catch (PDOException $e) {
    error_log($e->getMessage());
    responder(500, ["error" => "No se pudo obtener el historial."]);
}
