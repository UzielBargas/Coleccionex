<?php
// api/publicaciones.php — cartas a la venta en el MERCADO, con buscador y filtros (SELECT).
// Es PÚBLICO: sin sesión se ven solo las primeras 8 (requisito del documento).
//
// Filtros opcionales por GET:
//   q=texto        busca en el nombre de la carta o de la colección (comienzo, medio o fin)
//   juego=Pokémon  coleccion=3  estado=Nuevo  anio=2002
//   min=1000  max=50000   orden=recientes | precio-asc | precio-desc
//   limite=6
require_once __DIR__ . "/respuesta.php";
require_once __DIR__ . "/conexion.php";
require_once __DIR__ . "/funciones.php";

session_start();
$logueado = isset($_SESSION["usuario_id"]);

// Armamos el WHERE de a partes, según los filtros que llegaron
$condiciones = ["c.en_venta = 1"];
$parametros = [];

$texto = trim($_GET["q"] ?? "");
if ($texto !== "") {
    // LIKE con % adelante y atrás = la palabra puede estar en cualquier parte
    $condiciones[] = "(c.nombre LIKE :q1 OR co.nombre LIKE :q2)";
    $parametros["q1"] = "%" . $texto . "%";
    $parametros["q2"] = "%" . $texto . "%";
}
if (($_GET["juego"] ?? "") !== "") {
    $condiciones[] = "co.juego = :juego";
    $parametros["juego"] = $_GET["juego"];
}
if (leerId($_GET, "coleccion") !== false) {
    $condiciones[] = "co.id = :coleccion";
    $parametros["coleccion"] = leerId($_GET, "coleccion");
}
if (in_array($_GET["estado"] ?? "", ESTADOS, true)) {
    $condiciones[] = "c.estado = :estado";
    $parametros["estado"] = $_GET["estado"];
}
if (leerId($_GET, "anio") !== false) {
    $condiciones[] = "c.anio = :anio";
    $parametros["anio"] = leerId($_GET, "anio");
}
if (leerPrecio($_GET["min"] ?? "") !== false) {
    $condiciones[] = "c.precio >= :min";
    $parametros["min"] = leerPrecio($_GET["min"]);
}
if (leerPrecio($_GET["max"] ?? "") !== false) {
    $condiciones[] = "c.precio <= :max";
    $parametros["max"] = leerPrecio($_GET["max"]);
}

$ordenes = [
    "recientes" => "c.id DESC",
    "precio-asc" => "c.precio ASC",
    "precio-desc" => "c.precio DESC",
];
$orden = $ordenes[$_GET["orden"] ?? ""] ?? $ordenes["recientes"];

// Sin sesión: máximo 8. Con sesión: lo que pida (hasta 200)
$limite = leerId($_GET, "limite");
if ($limite === false || $limite > 200) {
    $limite = 200;
}
if (!$logueado) {
    $limite = min($limite, 8);
}

$where = implode(" AND ", $condiciones);

try {
    $consulta = $conexion->prepare(
        "SELECT " . CAMPOS_CARTA . " FROM " . TABLAS_CARTA .
        " WHERE " . $where . " ORDER BY " . $orden . " LIMIT " . $limite   // $orden y $limite ya están validados
    );
    $consulta->execute($parametros);
    $publicaciones = $consulta->fetchAll();

    // Cuántas hay en total con esos filtros (para decirle al visitante cuántas se pierde)
    $consulta = $conexion->prepare("SELECT COUNT(*) FROM " . TABLAS_CARTA . " WHERE " . $where);
    $consulta->execute($parametros);
    $total = (int) $consulta->fetchColumn();

    responder(200, [
        "publicaciones" => $publicaciones,
        "total" => $total,
        "limitado" => !$logueado && $total > count($publicaciones),
    ]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    responder(500, ["error" => "No se pudieron obtener las publicaciones."]);
}
