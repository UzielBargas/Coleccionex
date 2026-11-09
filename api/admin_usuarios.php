<?php
// api/admin_usuarios.php — lista de usuarios para el administrador, con buscador (SELECT)
//   ?q=texto  busca en usuario, nombre, apellido o correo
require_once __DIR__ . "/respuesta.php";
require_once __DIR__ . "/requiere_admin.php";

$texto = trim($_GET["q"] ?? "");
$condicion = "";
$parametros = [];
if ($texto !== "") {
    $condicion = "WHERE u.usuario LIKE :q1 OR u.nombre LIKE :q2 OR u.apellido LIKE :q3 OR u.correo LIKE :q4";
    foreach (["q1", "q2", "q3", "q4"] as $clave) {
        $parametros[$clave] = "%" . $texto . "%";
    }
}

try {
    $consulta = $conexion->prepare(
        "SELECT u.id, u.usuario, u.nombre, u.apellido, u.correo, u.saldo, u.rol, u.baneado, u.creado_en,
                (SELECT COUNT(*) FROM cartas c WHERE c.usuario_id = u.id) AS cartas,
                (SELECT COUNT(*) FROM transacciones t WHERE t.usuario_id = u.id) AS movimientos,
                (SELECT COUNT(*) FROM reportes r WHERE r.denunciado_id = u.id) AS reportes
         FROM usuarios u
         " . $condicion . "
         ORDER BY reportes DESC, u.id DESC
         LIMIT 100"
    );
    $consulta->execute($parametros);
    responder(200, $consulta->fetchAll());
} catch (PDOException $e) {
    error_log($e->getMessage());
    responder(500, ["error" => "No se pudieron obtener los usuarios."]);
}
