// js/registro.js — envía el formulario de paginas/registro.html a api/registro.php
const formulario = document.getElementById("form-registro");
const mensaje = document.getElementById("mensaje");

formulario.addEventListener("submit", async (evento) => {
    evento.preventDefault();

    // Primer control en el navegador (PHP lo vuelve a controlar)
    if (formulario.contrasena.value !== formulario["confirmar-contrasena"].value) {
        mensaje.textContent = "Las contraseñas no coinciden.";
        return;
    }

    mensaje.textContent = "Creando cuenta…";

    try {
        const respuesta = await fetch("../api/registro.php", {
            method: "POST",
            body: new FormData(formulario),
        });
        const datos = await respuesta.json();

        if (!respuesta.ok) {
            mensaje.textContent = datos.error;
            return;
        }

        // registro.php ya inicia la sesion: vamos directo a completar el perfil
        window.location.href = "../index.html#perfil";
    } catch (error) {
        console.error(error);
        mensaje.textContent = "No se pudo conectar con el servidor.";
    }
});
