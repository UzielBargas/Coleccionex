<?php
// api/logout.php — cierra la sesión
require_once __DIR__ . "/respuesta.php";

session_start();
exigirPost();

$_SESSION = [];
session_destroy();

responder(200, ["mensaje" => "Sesión cerrada."]);
