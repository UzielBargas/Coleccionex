<?php
// api/subir_carta.php — agrega una carta al inventario y, si se pide, la publica a la venta (INSERT protegido).
// El formulario se envía con FormData porque incluye una FOTO (archivo).
// Requisito del documento: estado, medidas, peso, año, colección y al menos una foto son obligatorios.
require_once __DIR__ . "/respuesta.php";
require_once __DIR__ . "/requiere_login.php";
require_once __DIR__ . "/conexion.php";
require_once __DIR__ . "/funciones.php";

exigirPost();

$nombre = trim($_POST["nombre"] ?? "");
$estado = $_POST["estado"] ?? "";
$anio = filter_var($_POST["anio"] ?? "", FILTER_VALIDATE_INT, ["options" => ["min_range" => 1900, "max_range" => (int) date("Y") + 1]]);
$medidas = trim($_POST["medidas"] ?? "");
$peso = trim($_POST["peso"] ?? "");
$descripcion = trim($_POST["descripcion"] ?? "");
$publicar = ($_POST["publicar"] ?? "") === "1";
$precio = leerPrecio($_POST["precio"] ?? "");

// --- 1) Validar los datos de texto ---
$errores = [];
if ($nombre === "" || mb_strlen($nombre) > 100) $errores[] = "nombre";
if (!in_array($estado, ESTADOS, true)) $errores[] = "estado";
if ($anio === false) $errores[] = "año";
if ($medidas === "" || mb_strlen($medidas) > 30) $errores[] = "medidas";
if ($peso === "" || mb_strlen($peso) > 20) $errores[] = "peso";
if (mb_strlen($descripcion) > 500) $errores[] = "descripción (máximo 500 caracteres)";
if ($publicar && $precio === false) $errores[] = "precio";

// Colección: una de la lista, o una nueva escrita por el usuario
$coleccionId = leerId($_POST, "coleccion_id");
$nuevoJuego = trim($_POST["nuevo_juego"] ?? "");
$nuevaColeccion = trim($_POST["nueva_coleccion"] ?? "");
if ($coleccionId === false && ($nuevoJuego === "" || $nuevaColeccion === "")) {
    $errores[] = "colección";
}

if ($errores) {
    responder(400, ["error" => "Revisá estos campos: " . implode(", ", $errores) . "."]);
}

// --- 2) Validar la foto ---
$foto = $_FILES["foto"] ?? null;
if (!$foto || $foto["error"] === UPLOAD_ERR_NO_FILE) {
    responder(400, ["error" => "Subí al menos una foto de la carta."]);
}
if ($foto["error"] !== UPLOAD_ERR_OK) {
    responder(400, ["error" => "La foto no se pudo subir (puede ser muy pesada). Probá con una de menos de 3 MB."]);
}
if ($foto["size"] > 3 * 1024 * 1024) {
    responder(400, ["error" => "La foto pesa más de 3 MB."]);
}
// getimagesize mira el CONTENIDO del archivo: si no es una imagen de verdad, devuelve false
$infoImagen = @getimagesize($foto["tmp_name"]);
$extensiones = [IMAGETYPE_JPEG => "jpg", IMAGETYPE_PNG => "png", IMAGETYPE_WEBP => "webp"];
if ($infoImagen === false || !isset($extensiones[$infoImagen[2]])) {
    responder(400, ["error" => "La foto tiene que ser JPG, PNG o WEBP."]);
}

// Nombre al azar para que no se pisen fotos ni se pueda adivinar la ruta
$nombreArchivo = "carta_" . bin2hex(random_bytes(8)) . "." . $extensiones[$infoImagen[2]];
$carpeta = __DIR__ . "/../imagenes/subidas/";
$rutaPublica = "imagenes/subidas/" . $nombreArchivo;   // la ruta que se guarda en la base (relativa a index.html)

if (!move_uploaded_file($foto["tmp_name"], $carpeta . $nombreArchivo)) {
    responder(500, ["error" => "No se pudo guardar la foto en el servidor."]);
}

// --- 3) Guardar en la base ---
try {
    $conexion->beginTransaction();

    if ($coleccionId === false) {
        // Colección nueva escrita por el usuario
        $consulta = $conexion->prepare("INSERT INTO colecciones (juego, nombre) VALUES (:juego, :nombre)");
        $consulta->execute(["juego" => mb_substr($nuevoJuego, 0, 50), "nombre" => mb_substr($nuevaColeccion, 0, 100)]);
        $coleccionId = (int) $conexion->lastInsertId();
    }

    $consulta = $conexion->prepare(
        "INSERT INTO cartas (usuario_id, coleccion_id, nombre, estado, anio, medidas, peso, descripcion, foto, precio, en_venta)
         VALUES (:usuario, :coleccion, :nombre, :estado, :anio, :medidas, :peso, :descripcion, :foto, :precio, :en_venta)"
    );
    $consulta->execute([
        "usuario" => $usuarioId,
        "coleccion" => $coleccionId,
        "nombre" => $nombre,
        "estado" => $estado,
        "anio" => $anio,
        "medidas" => $medidas,
        "peso" => $peso,
        "descripcion" => $descripcion,
        "foto" => $rutaPublica,
        "precio" => $publicar ? $precio : null,
        "en_venta" => $publicar ? 1 : 0,
    ]);
    $cartaId = (int) $conexion->lastInsertId();

    $conexion->commit();

    responder(201, [
        "mensaje" => $publicar ? "¡Carta publicada en el Mercado!" : "Carta agregada a tu inventario.",
        "id" => $cartaId,
    ]);
} catch (PDOException $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    @unlink($carpeta . $nombreArchivo);   // si no se guardó en la base, borramos la foto
    if ($e->errorInfo[1] === 1452) {      // 1452 = la colección no existe (clave foránea)
        responder(400, ["error" => "Esa colección no existe."]);
    }
    error_log($e->getMessage());
    responder(500, ["error" => "No se pudo guardar la carta."]);
}
