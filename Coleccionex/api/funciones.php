<?php
// api/funciones.php — datos y funciones que usan varios endpoints

const COMISION = 0.05;   // la plataforma se queda con el 5% de cada venta

// Opciones válidas (las mismas que muestran los <select> del HTML)
const ESTADOS = ["Nuevo", "Casi nuevo", "Muy bueno", "Bueno", "Usado", "Dañado"];
const METODOS_PAGO = ["Mercado Pago", "Ualá", "Naranja X"];
const MOTIVOS_REPORTE = [
    "Estado distinto al publicado",
    "Precio abusivo",
    "Carta falsa o réplica",
    "No respondió / no entregó",
    "Otro",
];

// Columnas de una carta que devuelven los endpoints (con su colección y su dueño).
// Se usa así: "SELECT " . CAMPOS_CARTA . " FROM " . TABLAS_CARTA . " WHERE ..."
const CAMPOS_CARTA = "c.id, c.usuario_id, u.usuario AS vendedor, c.nombre, c.estado, c.anio,
                      c.medidas, c.peso, c.descripcion, c.foto, c.precio, c.en_venta, c.creado_en,
                      co.id AS coleccion_id, co.juego, co.nombre AS coleccion";
const TABLAS_CARTA = "cartas c
                      JOIN colecciones co ON co.id = c.coleccion_id
                      JOIN usuarios u ON u.id = c.usuario_id";

// Guarda un movimiento en el historial
function registrarTransaccion($conexion, $datos) {
    $consulta = $conexion->prepare(
        "INSERT INTO transacciones (usuario_id, tipo, carta_id, otro_usuario_id, monto, comision, metodo)
         VALUES (:usuario, :tipo, :carta, :otro, :monto, :comision, :metodo)"
    );
    $consulta->execute([
        "usuario" => $datos["usuario"],
        "tipo" => $datos["tipo"],
        "carta" => $datos["carta"] ?? null,
        "otro" => $datos["otro"] ?? null,
        "monto" => $datos["monto"],
        "comision" => $datos["comision"] ?? 0,
        "metodo" => $datos["metodo"] ?? null,
    ]);
}

// Saldo actual del usuario, como número
function saldoDe($conexion, $usuarioId) {
    $consulta = $conexion->prepare("SELECT saldo FROM usuarios WHERE id = :id");
    $consulta->execute(["id" => $usuarioId]);
    return (float) $consulta->fetchColumn();
}

// Para comprar o cargar saldo, el perfil tiene que tener nombre y teléfono (requisito del documento)
function exigirPerfilCompleto($conexion, $usuarioId) {
    $consulta = $conexion->prepare("SELECT nombre, telefono FROM usuarios WHERE id = :id");
    $consulta->execute(["id" => $usuarioId]);
    $fila = $consulta->fetch();
    if (!$fila || trim($fila["nombre"]) === "" || trim($fila["telefono"]) === "") {
        responder(403, [
            "error" => "Antes de operar completá tu nombre y teléfono en Mi perfil.",
            "codigo" => "perfil_incompleto",
        ]);
    }
}

// Lee un precio en pesos que llegó por POST. Acepta "2500", "2500.50" o "2500,50". Devuelve false si no sirve
function leerPrecio($texto) {
    $texto = str_replace(",", ".", trim((string) $texto));
    $precio = filter_var($texto, FILTER_VALIDATE_FLOAT);
    if ($precio === false || $precio <= 0 || $precio > 100000000) {
        return false;
    }
    return round($precio, 2);
}
