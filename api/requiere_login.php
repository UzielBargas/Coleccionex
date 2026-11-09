<?php
// api/requiere_login.php — se incluye al principio de cada endpoint protegido
session_start();

if (!isset($_SESSION["usuario_id"])) {
    responder(401, ["error" => "Tenés que iniciar sesión."]);
}

// Atajo: el id del usuario conectado
$usuarioId = $_SESSION["usuario_id"];
