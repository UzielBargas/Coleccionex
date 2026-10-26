const botones = document.querySelectorAll('.nav-btn');
const secciones = document.querySelectorAll('.seccion');

// Secciones que solo puede ver alguien con sesion iniciada
const SECCIONES_PRIVADAS = ['perfil', 'cartas', 'historial'];


// Muestra una seccion (inicio, perfil, cartas, mercado o historial) y marca su boton del menu
function mostrarSeccion(destino) {

    const seccion = document.getElementById(destino);
    if (!seccion || !seccion.classList.contains('seccion')) {
        return;
    }

    // Un visitante que quiere entrar a una seccion privada va al login
    if (document.body.classList.contains('visitante') && SECCIONES_PRIVADAS.includes(destino)) {
        window.location.href = 'paginas/sesion.html';
        return;
    }

    // quitar activo de botones y activar el que corresponde
    botones.forEach(b => b.classList.toggle('activo', b.dataset.target === destino));

    // ocultar secciones
    secciones.forEach(sec => sec.classList.remove('activa'));

    // mostrar sección correspondiente
    seccion.classList.add('activa');
    seccion.scrollTop = 0;

    // si habia una ventana abierta (detalle de carta, publicar), se cierra
    document.querySelectorAll('dialog[open]').forEach(d => d.close());

}


botones.forEach(btn => {

    btn.addEventListener('click', () => mostrarSeccion(btn.dataset.target));

});


// Cualquier boton con data-ir="mercado" (o perfil, cartas...) lleva a esa seccion.
// Se usa delegacion: funciona tambien con botones que JavaScript crea despues.
document.addEventListener('click', (evento) => {

    const boton = evento.target.closest('[data-ir]');

    if (boton) {
        mostrarSeccion(boton.dataset.ir);
    }

});


// Enlaces como index.html#historial abren esa seccion
window.addEventListener('hashchange', () => mostrarSeccion(location.hash.slice(1)));
