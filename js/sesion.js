// js/sesion.js — envía el formulario de paginas/sesion.html a api/login.php
const formulario = document.getElementById("form-login");
const mensaje = document.getElementById("mensaje");

formulario.addEventListener("submit", async (evento) => {
    evento.preventDefault(); // evita que el navegador recargue la página
    mensaje.textContent = "Verificando…";

    try {
        const respuesta = await fetch("../api/login.php", {
            method: "POST",
            body: new FormData(formulario),
        });
        const datos = await respuesta.json();

        if (!respuesta.ok) {
            mensaje.textContent = datos.error; // ej.: "Usuario o contraseña incorrectos."
            return;
        }

        window.location.href = "../index.html";
    } catch (error) {
        console.error(error);
        mensaje.textContent = "No se pudo conectar con el servidor.";
    }
});
