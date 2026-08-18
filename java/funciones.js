const botones = document.querySelectorAll('.nav-btn');
const secciones = document.querySelectorAll('.seccion');



botones.forEach(btn => {

    btn.addEventListener('click', () => {

        // quitar activo de botones
        botones.forEach(b => b.classList.remove('activo'));

        // activar botón actual
        btn.classList.add('activo');

        // ocultar secciones
        secciones.forEach(sec => sec.classList.remove('activa'));

        // mostrar sección correspondiente
        const destino = btn.dataset.target;

        document.getElementById(destino).classList.add('activa');

    });

});
