// js/app.js — trae los datos de la base (por medio de los PHP de /api) y dibuja todas las secciones.
// Mismo esquema que la guia: fetch() -> JSON -> crear elementos con createElement.
//
// La pagina funciona en dos modos:
//   - VISITANTE (sin sesion): ve el Inicio y parte del Mercado. Para comprar tiene que iniciar sesion.
//   - CLIENTE (con sesion): ve todo y puede publicar, comprar, cargar saldo y armar mazos.

// ================= CONFIGURACION =================
const MINIMO_MAZO = 10;     // un mazo esta "completo" con 10 cartas o mas (requisito del documento)
const COMISION = 0.05;      // la plataforma cobra el 5% de cada venta (igual que en PHP)
const ESTADOS = ["Nuevo", "Casi nuevo", "Muy bueno", "Bueno", "Usado", "Dañado"];

// Formato de pesos argentinos: 2500 -> "$ 2.500,00" (igual que en la guia)
const formatoPesos = new Intl.NumberFormat("es-AR", { style: "currency", currency: "ARS" });
function pesos(valor) {
    return formatoPesos.format(Number(valor));
}


// ================= ELEMENTOS DE LA PAGINA =================
const listaInventario = document.getElementById("lista-inventario");
const listaSinMazo = document.getElementById("lista-sin-mazo");
const listaMazos = document.getElementById("lista-mazos");
const listaMercado = document.getElementById("lista-mercado");
const listaUltimas = document.getElementById("lista-ultimas");
const listaJuegos = document.getElementById("lista-juegos");
const contenidoDetalle = document.getElementById("contenido-detalle");
const avisoConexion = document.getElementById("aviso-conexion");

const formMazo = document.getElementById("form-mazo");
const filtros = document.getElementById("filtros");
const formPublicar = document.getElementById("form-publicar");
const formPerfil = document.getElementById("form-perfil");
const formSaldo = document.getElementById("form-saldo");

const modalCarta = document.getElementById("modal-carta");
const modalPublicar = document.getElementById("modal-publicar");


// ================= ESTADO (los datos que llegaron de la base) =================
let usuario = null;          // null = visitante. Si hay sesion: { id, usuario, nombre, saldo, ... }
let colecciones = [];        // [{ id, juego, nombre, en_venta }]
let inventario = [];         // cartas del usuario
let mazos = [];              // mazos del usuario
let historial = [];          // movimientos del usuario
let mazoSeleccionado = null; // id del mazo elegido en "Mis cartas / mazos"
let mazoActual = null;       // detalle del mazo elegido (nombre + cartas)
let filtroHistorial = "";    // pestaña elegida en el historial


// ================= AYUDAS =================

// "Yu-Gi-Oh!" -> "yu-gi-oh", "Fútbol" -> "futbol". Sirve para armar clases CSS (juego-futbol)
function aClase(texto) {
    return texto.normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase()
        .replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "");
}

// Muestra un aviso dentro de un contenedor (Cargando…, vacio, error)
function mostrarMensaje(contenedor, texto) {
    const mensaje = document.createElement("p");
    mensaje.classList.add("mensaje");
    mensaje.textContent = texto;
    contenedor.replaceChildren(mensaje);
}

// Crea un boton con su texto, su funcion y clases extra
function crearBoton(texto, alHacerClic, clases = []) {
    const boton = document.createElement("button");
    boton.type = "button";
    boton.classList.add("boton", ...clases);
    boton.textContent = texto;
    if (alHacerClic) {
        boton.addEventListener("click", alHacerClic);
    }
    return boton;
}

// Crea un elemento con una clase y un texto (atajo para no repetir 3 lineas)
function crear(etiqueta, clase, texto) {
    const elemento = document.createElement(etiqueta);
    if (clase) elemento.className = clase;
    if (texto !== undefined) elemento.textContent = texto;
    return elemento;
}

// Aviso flotante abajo de la pantalla durante unos segundos
let temporizadorToast = null;
function avisar(texto, esError = false) {
    const toast = document.getElementById("toast");
    toast.textContent = texto;
    toast.classList.toggle("error", esError);
    toast.classList.add("visible");
    clearTimeout(temporizadorToast);
    temporizadorToast = setTimeout(() => toast.classList.remove("visible"), 3500);
}

// Lee el JSON de una respuesta. Si fallo, lanza un error con el mensaje que mando PHP
async function leerRespuesta(respuesta) {
    const datos = await respuesta.json();
    if (respuesta.status === 401 && usuario) {
        // La sesion se vencio: volver al login
        window.location.href = "paginas/sesion.html";
    }
    if (!respuesta.ok) {
        const error = new Error(datos.error || "Error del servidor: " + respuesta.status);
        error.codigo = datos.codigo;   // ej: "saldo_insuficiente", "perfil_incompleto"
        throw error;
    }
    return datos;
}

// Pide datos con GET
async function pedir(url) {
    return leerRespuesta(await fetch(url));
}

// Envia datos con POST. "campos" puede ser un objeto { nombre: valor } o un FormData (para fotos)
async function enviar(url, campos = {}) {
    let cuerpo = campos;
    if (!(campos instanceof FormData)) {
        cuerpo = new FormData();
        for (const nombre in campos) {
            cuerpo.append(nombre, campos[nombre]);
        }
    }
    return leerRespuesta(await fetch(url, { method: "POST", body: cuerpo }));
}

function esMia(carta) {
    return usuario !== null && Number(carta.usuario_id) === Number(usuario.id);
}

// Fecha de MySQL ("2026-10-22 17:44:16") -> "22/10/2026 17:44"
function formatearFecha(texto) {
    const fecha = new Date(texto.replace(" ", "T"));
    return fecha.toLocaleDateString("es-AR") + " " + fecha.toLocaleTimeString("es-AR", { hour: "2-digit", minute: "2-digit" });
}

// Pone la foto de una carta como fondo de un elemento; si no tiene, la inicial del nombre
function ponerFoto(elemento, carta) {
    elemento.textContent = "";
    elemento.style.backgroundImage = "";
    if (carta.foto) {
        elemento.style.backgroundImage = "url('" + carta.foto + "')";
    } else {
        elemento.textContent = (carta.nombre || "?").charAt(0);
    }
}


// ================= LA CARTA (card-box) =================
// Arma un <article class="card-box"> con foto, nombre, colección, estado y precio.
// opciones: { boton: <button>, enInventario: true }
function crearCarta(carta, opciones = {}) {
    const tarjeta = crear("article", "card-box juego-" + aClase(carta.juego));
    tarjeta.title = carta.nombre + " — " + carta.juego + " · " + carta.coleccion;

    const arte = crear("div", "arte");
    ponerFoto(arte, carta);

    const nombre = crear("h3", "", carta.nombre);
    const info = crear("div", "info", carta.coleccion + " · " + carta.anio);

    // Etiquetas: estado, "Tuya", "En venta", mazo
    const etiquetas = crear("div", "etiquetas");
    etiquetas.appendChild(crear("span", "chip chip-estado", carta.estado));
    if (opciones.enInventario) {
        if (Number(carta.en_venta) === 1) etiquetas.appendChild(crear("span", "chip chip-venta", "En venta"));
        if (carta.mazo) etiquetas.appendChild(crear("span", "chip chip-mazo", carta.mazo));
    } else if (esMia(carta)) {
        etiquetas.appendChild(crear("span", "chip chip-tuya", "Tuya"));
    }

    tarjeta.append(arte, nombre, info, etiquetas);

    if (Number(carta.en_venta) === 1) {
        tarjeta.appendChild(crear("div", "precio", pesos(carta.precio)));
    } else if (opciones.enInventario) {
        tarjeta.appendChild(crear("div", "sin-precio", "No publicada"));
    }

    if (opciones.boton) {
        // Que el clic del boton no abra tambien el detalle de la carta
        opciones.boton.addEventListener("click", (evento) => evento.stopPropagation());
        tarjeta.appendChild(opciones.boton);
    }

    // Tocar la carta abre su detalle
    tarjeta.addEventListener("click", () => abrirDetalle(carta));
    return tarjeta;
}


// ================= COLECCIONES Y JUEGOS =================
async function cargarColecciones() {
    colecciones = await pedir("api/colecciones.php");
    dibujarJuegos();
    llenarFiltroJuegos();
}

// Agrupa las colecciones por juego: { "Pokémon": 12, "Fútbol": 6 }
function juegosConCantidad() {
    const juegos = {};
    colecciones.forEach((coleccion) => {
        juegos[coleccion.juego] = (juegos[coleccion.juego] || 0) + Number(coleccion.en_venta);
    });
    return juegos;
}

function dibujarJuegos() {
    listaJuegos.replaceChildren();
    const juegos = juegosConCantidad();
    for (const juego in juegos) {
        const boton = crear("button", "juego juego-" + aClase(juego));
        boton.type = "button";
        boton.append(crear("strong", "", juego), crear("span", "", juegos[juego] + " a la venta"));
        boton.addEventListener("click", () => {
            filtros.reset();
            filtros.juego.value = juego;
            llenarFiltroColecciones();
            mostrarSeccion("mercado");
            cargarMercado();
        });
        listaJuegos.appendChild(boton);
    }
}


// ================= INICIO =================
async function cargarUltimas() {
    try {
        const datos = await pedir("api/publicaciones.php?limite=6");
        if (datos.publicaciones.length === 0) {
            mostrarMensaje(listaUltimas, "Todavía no hay publicaciones.");
            return;
        }
        listaUltimas.replaceChildren(...datos.publicaciones.map((carta) => crearCarta(carta)));
    } catch (error) {
        console.error(error);
        mostrarMensaje(listaUltimas, "No se pudieron cargar las publicaciones.");
    }
}

function dibujarInicio() {
    if (!usuario) return;
    document.getElementById("titulo-bienvenida").textContent = "¡Hola, " + (usuario.nombre || usuario.usuario) + "!";
    document.getElementById("resumen-bienvenida").textContent =
        "Tenés " + usuario.cartas + " cartas en tu inventario, " + usuario.en_venta + " publicadas y " +
        usuario.mazos + (Number(usuario.mazos) === 1 ? " mazo." : " mazos.");
    document.getElementById("saldo-inicio").textContent = pesos(usuario.saldo);
    document.getElementById("aviso-perfil").hidden = usuario.perfil_completo;
}

// El buscador del inicio lleva al Mercado con lo que se escribio
document.getElementById("buscador-inicio").addEventListener("submit", (evento) => {
    evento.preventDefault();
    filtros.reset();
    filtros.q.value = evento.target.q.value;
    mostrarSeccion("mercado");
    cargarMercado();
});


// ================= MERCADO =================
function llenarSelectEstados() {
    document.querySelectorAll(".select-estado").forEach((select) => {
        ESTADOS.forEach((estado) => {
            const opcion = crear("option", "", estado);
            opcion.value = estado;
            select.appendChild(opcion);
        });
    });
}

function llenarFiltroJuegos() {
    const select = document.getElementById("filtro-juego");
    const elegido = select.value;
    select.replaceChildren(crear("option", "", "Todos los juegos"));
    select.firstChild.value = "";
    Object.keys(juegosConCantidad()).forEach((juego) => {
        const opcion = crear("option", "", juego);
        opcion.value = juego;
        select.appendChild(opcion);
    });
    select.value = elegido;
    llenarFiltroColecciones();
}

// Las colecciones del filtro dependen del juego elegido
function llenarFiltroColecciones() {
    const select = document.getElementById("filtro-coleccion");
    const juego = filtros.juego.value;
    select.replaceChildren(crear("option", "", "Todas las colecciones"));
    select.firstChild.value = "";
    colecciones.filter((c) => juego === "" || c.juego === juego).forEach((coleccion) => {
        const opcion = crear("option", "", coleccion.nombre);
        opcion.value = coleccion.id;
        select.appendChild(opcion);
    });
}

// Pide las publicaciones a PHP con los filtros del formulario (?q=...&juego=...)
async function cargarMercado() {
    const parametros = new URLSearchParams();
    new FormData(filtros).forEach((valor, nombre) => {
        if (String(valor).trim() !== "") parametros.append(nombre, valor);
    });

    mostrarMensaje(listaMercado, "Buscando…");
    try {
        const datos = await pedir("api/publicaciones.php?" + parametros.toString());
        const cantidad = datos.publicaciones.length;

        document.getElementById("texto-resultados").textContent =
            datos.total + (datos.total === 1 ? " publicación encontrada" : " publicaciones encontradas");

        // Visitante: avisamos que hay mas para ver con sesion
        document.getElementById("aviso-limitado").hidden = !datos.limitado;
        document.getElementById("texto-limitado").textContent =
            "Estás viendo " + cantidad + " de " + datos.total + ". Iniciá sesión para ver todas y poder comprar.";

        if (cantidad === 0) {
            mostrarMensaje(listaMercado, "No hay cartas con esos filtros. Probá con otros.");
            return;
        }
        listaMercado.replaceChildren(...datos.publicaciones.map((carta) => crearCarta(carta)));
    } catch (error) {
        console.error(error);
        mostrarMensaje(listaMercado, "No se pudieron cargar las publicaciones.");
    }
}

// Los filtros vuelven a buscar al cambiar (con una pequeña espera mientras se escribe)
let esperaFiltros = null;
filtros.addEventListener("input", (evento) => {
    if (evento.target.name === "juego") llenarFiltroColecciones();
    clearTimeout(esperaFiltros);
    esperaFiltros = setTimeout(cargarMercado, 350);
});
filtros.addEventListener("submit", (evento) => evento.preventDefault());
filtros.addEventListener("reset", () => setTimeout(() => {
    llenarFiltroColecciones();
    cargarMercado();
}, 0));


// ================= VENTANA: DETALLE DE UNA CARTA =================
let cartaAbierta = null;

function abrirDetalle(carta) {
    cartaAbierta = carta;
    document.getElementById("detalle-mensaje").textContent = "";

    ponerFoto(document.getElementById("detalle-foto"), carta);
    document.getElementById("detalle-nombre").textContent = carta.nombre;
    document.getElementById("detalle-coleccion").textContent = carta.juego + " · " + carta.coleccion;
    document.getElementById("detalle-estado").textContent = carta.estado;
    document.getElementById("detalle-anio").textContent = carta.anio;
    document.getElementById("detalle-medidas").textContent = carta.medidas;
    document.getElementById("detalle-peso").textContent = carta.peso;
    document.getElementById("detalle-descripcion").textContent = carta.descripcion || "";
    document.getElementById("detalle-vendedor").textContent =
        esMia(carta) ? "Esta carta es tuya." : "Publicada por @" + carta.vendedor;
    document.getElementById("detalle-precio").textContent =
        Number(carta.en_venta) === 1 ? pesos(carta.precio) : "";

    dibujarAccionesDetalle();
    if (!modalCarta.open) {
        modalCarta.showModal();
    }
}

// Los botones del detalle cambian segun quien mira la carta
function dibujarAccionesDetalle() {
    const carta = cartaAbierta;
    const zona = document.getElementById("detalle-acciones");
    zona.replaceChildren();

    // --- Visitante: para comprar hay que iniciar sesion (requisito del documento) ---
    if (!usuario) {
        const fila = crear("div", "detalle-acciones");
        const enlace = crear("a", "boton boton-grande", "Iniciá sesión para comprar");
        enlace.href = "paginas/sesion.html";
        fila.appendChild(enlace);
        zona.append(fila, crear("p", "pista", "Para comprar o ver más datos del vendedor necesitás una cuenta."));
        return;
    }

    // --- Carta propia: publicar / pausar, mazo, eliminar ---
    if (esMia(carta)) {
        zona.appendChild(accionesCartaPropia(carta));
        return;
    }

    // --- Carta de otro usuario, a la venta: comprar y reportar ---
    if (Number(carta.en_venta) === 1) {
        const fila = crear("div", "detalle-acciones");
        fila.append(
            crearBoton("Comprar · " + pesos(carta.precio), () => mostrarConfirmarCompra(zona), ["boton-grande"]),
            crearBoton("Reportar vendedor", () => mostrarFormReporte(zona), ["boton-secundario"]),
        );
        zona.appendChild(fila);
    }
}

function accionesCartaPropia(carta) {
    const contenedor = crear("div");
    // Buscamos la carta en el inventario para saber en que mazo esta
    const enInventario = inventario.find((c) => Number(c.id) === Number(carta.id)) || carta;

    // Publicar o pausar la venta
    const filaVenta = crear("div", "detalle-acciones");
    if (Number(enInventario.en_venta) === 1) {
        filaVenta.appendChild(crearBoton("Pausar venta", () => cambiarVenta(carta.id, "pausar"), ["boton-secundario"]));
    } else {
        const precio = document.createElement("input");
        precio.type = "number";
        precio.min = "1";
        precio.step = "0.01";
        precio.placeholder = "Precio en pesos";
        precio.value = enInventario.precio ? Number(enInventario.precio) : "";
        filaVenta.append(precio, crearBoton("Publicar a la venta", () => cambiarVenta(carta.id, "publicar", precio.value)));
    }

    // Mazo: agregar o quitar
    const filaMazo = crear("div", "detalle-acciones");
    if (enInventario.mazo_id) {
        filaMazo.appendChild(crearBoton("Quitar de " + enInventario.mazo,
            () => quitarDelMazo(carta.id, enInventario.mazo_id), ["boton-secundario"]));
    } else if (mazos.length > 0) {
        const select = document.createElement("select");
        mazos.forEach((mazo) => {
            const opcion = crear("option", "", mazo.nombre);
            opcion.value = mazo.id;
            opcion.selected = Number(mazo.id) === mazoSeleccionado;
            select.appendChild(opcion);
        });
        filaMazo.append(select, crearBoton("Agregar al mazo", () => agregarAlMazo(carta.id, select.value)));
    }

    const filaBorrar = crear("div", "detalle-acciones");
    filaBorrar.appendChild(crearBoton("Eliminar del inventario", () => borrarCarta(carta), ["boton-peligro"]));

    contenedor.append(filaVenta, filaMazo, filaBorrar);
    return contenedor;
}

// Confirmacion antes de comprar: muestra cuanto le queda de saldo
function mostrarConfirmarCompra(zona) {
    const carta = cartaAbierta;
    const precio = Number(carta.precio);
    const saldo = Number(usuario.saldo);
    const caja = crear("div", "zona");

    if (!usuario.perfil_completo) {
        caja.append(
            crear("h3", "", "Completá tu perfil"),
            crear("p", "", "Para comprar necesitamos tu nombre y teléfono."),
            crearBoton("Ir a Mi perfil", () => { mostrarSeccion("perfil"); abrirEditarPerfil(); }),
        );
    } else if (saldo < precio) {
        caja.append(
            crear("h3", "", "Saldo insuficiente"),
            crear("p", "", "Tenés " + pesos(saldo) + " y te faltan " + pesos(precio - saldo) + "."),
            crearBoton("Cargar saldo", irACargarSaldo),
        );
    } else {
        caja.append(
            crear("h3", "", "Confirmar compra"),
            crear("p", "", "Vas a pagar " + pesos(precio) + " a @" + carta.vendedor + ". Tu saldo después de la compra: " + pesos(saldo - precio) + "."),
        );
        const fila = crear("div", "detalle-acciones");
        fila.append(
            crearBoton("Confirmar compra", () => comprar(carta)),
            crearBoton("Cancelar", dibujarAccionesDetalle, ["boton-secundario"]),
        );
        caja.appendChild(fila);
    }
    dibujarAccionesDetalle();
    zona.appendChild(caja);
}

async function comprar(carta) {
    try {
        const datos = await enviar("api/comprar.php", { carta_id: carta.id });
        modalCarta.close();
        avisar(datos.mensaje);
        await recargarTodo();
    } catch (error) {
        document.getElementById("detalle-mensaje").textContent = error.message;
        if (error.codigo === "saldo_insuficiente") {
            await recargarSesion();
        }
    }
}

// Formulario de reporte (motivos iguales a los de PHP)
function mostrarFormReporte(zona) {
    dibujarAccionesDetalle();
    const carta = cartaAbierta;
    const form = crear("form", "zona");
    form.appendChild(crear("h3", "", "Reportar a @" + carta.vendedor));

    const campoMotivo = crear("label", "campo");
    campoMotivo.appendChild(crear("span", "", "Motivo"));
    const motivo = document.createElement("select");
    motivo.name = "motivo";
    ["Estado distinto al publicado", "Precio abusivo", "Carta falsa o réplica", "No respondió / no entregó", "Otro"]
        .forEach((texto) => motivo.appendChild(crear("option", "", texto)));
    campoMotivo.appendChild(motivo);

    const campoTexto = crear("label", "campo");
    campoTexto.appendChild(crear("span", "", "Contá qué pasó"));
    const descripcion = document.createElement("textarea");
    descripcion.name = "descripcion";
    descripcion.required = true;
    descripcion.minLength = 10;
    descripcion.maxLength = 500;
    campoTexto.appendChild(descripcion);

    const enviarBoton = crear("button", "boton", "Enviar reporte");
    enviarBoton.type = "submit";
    const fila = crear("div", "detalle-acciones");
    fila.append(enviarBoton, crearBoton("Cancelar", dibujarAccionesDetalle, ["boton-secundario"]));

    form.append(campoMotivo, campoTexto, fila);
    form.addEventListener("submit", async (evento) => {
        evento.preventDefault();
        try {
            const datos = await enviar("api/reportar.php", {
                vendedor_id: carta.usuario_id,
                carta_id: carta.id,
                motivo: motivo.value,
                descripcion: descripcion.value,
            });
            avisar(datos.mensaje);
            dibujarAccionesDetalle();
        } catch (error) {
            document.getElementById("detalle-mensaje").textContent = error.message;
        }
    });
    zona.appendChild(form);
}

async function cambiarVenta(cartaId, accion, precio = "") {
    try {
        const datos = await enviar("api/cambiar_venta.php", { carta_id: cartaId, accion: accion, precio: precio });
        avisar(datos.mensaje);
        modalCarta.close();
        await recargarTodo();
    } catch (error) {
        document.getElementById("detalle-mensaje").textContent = error.message;
    }
}

async function borrarCarta(carta) {
    if (!confirm("¿Eliminar \"" + carta.nombre + "\" de tu inventario? No se puede deshacer.")) {
        return;
    }
    try {
        const datos = await enviar("api/borrar_carta.php", { carta_id: carta.id });
        avisar(datos.mensaje);
        modalCarta.close();
        await recargarTodo();
    } catch (error) {
        document.getElementById("detalle-mensaje").textContent = error.message;
    }
}


// ================= VENTANA: PUBLICAR / SUBIR CARTA =================
const vistaFoto = document.getElementById("vista-foto");
const inputFoto = document.getElementById("input-foto");
const selectColeccion = document.getElementById("select-coleccion");
const checkPublicar = document.getElementById("check-publicar");
const inputPrecio = document.getElementById("input-precio");

function abrirPublicar() {
    if (!usuario) {
        window.location.href = "paginas/sesion.html";
        return;
    }
    formPublicar.reset();
    document.getElementById("mensaje-publicar").textContent = "";
    vistaFoto.style.backgroundImage = "";
    vistaFoto.textContent = "Tocá para elegir una foto *";
    llenarSelectColecciones();
    actualizarFormPublicar();
    modalPublicar.showModal();
}
document.querySelectorAll(".btn-publicar").forEach((boton) => boton.addEventListener("click", abrirPublicar));

// Colecciones agrupadas por juego (<optgroup>) + "Otra colección…"
function llenarSelectColecciones() {
    selectColeccion.replaceChildren();
    const vacia = crear("option", "", "Elegí una colección");
    vacia.value = "";
    selectColeccion.appendChild(vacia);

    const grupos = {};
    colecciones.forEach((coleccion) => {
        if (!grupos[coleccion.juego]) {
            grupos[coleccion.juego] = document.createElement("optgroup");
            grupos[coleccion.juego].label = coleccion.juego;
            selectColeccion.appendChild(grupos[coleccion.juego]);
        }
        const opcion = crear("option", "", coleccion.nombre);
        opcion.value = coleccion.id;
        grupos[coleccion.juego].appendChild(opcion);
    });

    const otra = crear("option", "", "Otra colección…");
    otra.value = "nueva";
    selectColeccion.appendChild(otra);
}

// Muestra u oculta campos segun lo elegido, y calcula el resumen (precio, comision, total)
function actualizarFormPublicar() {
    const nueva = selectColeccion.value === "nueva";
    document.querySelectorAll("[data-nueva-coleccion]").forEach((campo) => {
        campo.hidden = !nueva;
        campo.querySelector("input").required = nueva;
    });

    const publicar = checkPublicar.checked;
    document.getElementById("campo-precio").hidden = !publicar;
    inputPrecio.required = publicar;
    document.getElementById("btn-enviar-publicar").textContent = publicar ? "Publicar carta" : "Guardar en mi inventario";

    const precio = publicar ? Number(inputPrecio.value) || 0 : 0;
    const comision = Math.round(precio * COMISION * 100) / 100;
    document.getElementById("resumen-precio").textContent = pesos(precio);
    document.getElementById("resumen-comision").textContent = "- " + pesos(comision);
    document.getElementById("resumen-total").textContent = pesos(precio - comision);
}
formPublicar.addEventListener("input", actualizarFormPublicar);

// Vista previa de la foto elegida
inputFoto.addEventListener("change", () => {
    const archivo = inputFoto.files[0];
    if (!archivo) return;
    if (archivo.size > 3 * 1024 * 1024) {
        document.getElementById("mensaje-publicar").textContent = "La foto pesa más de 3 MB. Elegí otra.";
        inputFoto.value = "";
        return;
    }
    vistaFoto.textContent = "";
    vistaFoto.style.backgroundImage = "url('" + URL.createObjectURL(archivo) + "')";
});

formPublicar.addEventListener("submit", async (evento) => {
    evento.preventDefault();
    const mensaje = document.getElementById("mensaje-publicar");
    mensaje.textContent = "Subiendo…";

    // FormData junta TODOS los campos, incluida la foto
    const datos = new FormData(formPublicar);
    if (selectColeccion.value === "nueva") {
        datos.delete("coleccion_id");
    }
    if (!checkPublicar.checked) {
        datos.delete("precio");
    }

    try {
        const respuesta = await enviar("api/subir_carta.php", datos);
        modalPublicar.close();
        avisar(respuesta.mensaje);
        await recargarTodo();
    } catch (error) {
        mensaje.textContent = error.message;
    }
});
document.querySelectorAll(".cerrar-form").forEach((boton) => boton.addEventListener("click", () => boton.closest("dialog").close()));


// ================= MIS CARTAS (INVENTARIO) =================
async function cargarInventario() {
    inventario = await pedir("api/mis_cartas.php");
}

function dibujarInventario() {
    const publicadas = inventario.filter((carta) => Number(carta.en_venta) === 1).length;
    document.getElementById("total-cartas").textContent =
        "MI INVENTARIO - " + inventario.length + " CARTAS · " + publicadas + " EN VENTA";

    if (inventario.length === 0) {
        const vacio = crear("div", "vacio");
        vacio.appendChild(crear("p", "", "Todavía no tenés cartas. Subí las tuyas o comprá en el Mercado."));
        const boton = crearBoton("+ Subir mi primera carta", abrirPublicar);
        vacio.appendChild(boton);
        listaInventario.replaceChildren(vacio);
        mostrarMensaje(listaSinMazo, "Sin cartas.");
        return;
    }

    listaInventario.replaceChildren();
    listaSinMazo.replaceChildren();

    inventario.forEach((carta) => {
        // Si hay un mazo elegido, las cartas sueltas tienen el boton "Al mazo"
        let boton = null;
        if (mazoSeleccionado && !carta.mazo_id) {
            boton = crearBoton("Al mazo", () => agregarAlMazo(carta.id));
        }
        listaInventario.appendChild(crearCarta(carta, { enInventario: true, boton: boton }));

        if (!carta.mazo_id) {
            listaSinMazo.appendChild(crearCarta(carta, {
                enInventario: true,
                boton: mazoSeleccionado ? crearBoton("Al mazo", () => agregarAlMazo(carta.id)) : null,
            }));
        }
    });

    if (listaSinMazo.children.length === 0) {
        mostrarMensaje(listaSinMazo, "Todas tus cartas están en mazos.");
    }
}


// ================= MIS MAZOS =================
async function cargarMazos() {
    mazos = await pedir("api/mazos.php");
    if (mazoSeleccionado) {
        try {
            mazoActual = await pedir("api/mazo.php?id=" + mazoSeleccionado);
        } catch (error) {
            mazoSeleccionado = null;   // el mazo ya no existe
            mazoActual = null;
        }
    }
}

function dibujarMazos() {
    if (mazos.length === 0) {
        mostrarMensaje(listaMazos, "Todavía no tenés mazos. Escribí un nombre arriba y tocá \"Crear\".");
        return;
    }

    listaMazos.replaceChildren();
    mazos.forEach((mazo) => {
        const total = Number(mazo.total_cartas);
        const completo = total >= MINIMO_MAZO;

        const item = crear("div", "coleccion-mazos");
        if (Number(mazo.id) === mazoSeleccionado) {
            item.classList.add("seleccionado");
        }

        const imagen = document.createElement("img");
        imagen.src = "imagenes/Imagen coleccion cartas ejemplo.png";
        imagen.alt = "";

        const textos = crear("div", "nombre-mazo");
        textos.appendChild(crear("div", "", mazo.nombre));
        const progreso = crear("div", "progreso-mazo" + (completo ? " completo" : ""));
        const relleno = crear("div");
        relleno.style.width = Math.min(100, total * 100 / MINIMO_MAZO) + "%";
        progreso.appendChild(relleno);
        textos.appendChild(progreso);

        const cantidad = crear("span", "cantidad-mazo",
            completo ? total + " cartas ✓" : total + "/" + MINIMO_MAZO);

        item.append(imagen, textos, cantidad);
        item.addEventListener("click", () => seleccionarMazo(Number(mazo.id)));
        listaMazos.appendChild(item);
    });
}

// Crear mazo desde el formulario
formMazo.addEventListener("submit", async (evento) => {
    evento.preventDefault();
    const mensaje = document.getElementById("mensaje-mazo");
    mensaje.textContent = "";
    try {
        const datos = await enviar("api/crear_mazo.php", { nombre: formMazo.nombre.value });
        formMazo.reset();
        mazoSeleccionado = datos.id;   // el mazo nuevo queda elegido
        avisar("Mazo creado. Ahora tocá \"Al mazo\" en tus cartas.");
        await recargarTodo();
    } catch (error) {
        mensaje.textContent = error.message;
    }
});

async function seleccionarMazo(id) {
    mazoSeleccionado = id;
    mazoActual = null;
    await cargarMazos();
    dibujarMazos();
    dibujarDetalleMazo();
    dibujarInventario();
}

function dibujarDetalleMazo() {
    // Sin mazo elegido: explicamos como se arma uno
    if (!mazoActual) {
        const ayuda = crear("div", "details");
        const imagen = document.createElement("img");
        imagen.src = "imagenes/imagen-seleccionando-mazo3.png";
        imagen.alt = "";
        const lista = crear("ol", "ayuda-pasos");
        ["Creá un mazo en \"Mis mazos\" (a la izquierda).",
         "Tocá el mazo para elegirlo.",
         "Tocá \"Al mazo\" en tus cartas. Con " + MINIMO_MAZO + " cartas el mazo queda completo."]
            .forEach((texto) => lista.appendChild(crear("li", "", texto)));
        ayuda.append(imagen, lista);
        contenidoDetalle.replaceChildren(ayuda);
        return;
    }

    const total = mazoActual.cartas.length;
    const barra = crear("div", "barra-mazo");
    const estado = total >= MINIMO_MAZO ? " · Completo ✓" : " · Faltan " + (MINIMO_MAZO - total) + " para completarlo";
    barra.appendChild(crear("span", "", mazoActual.nombre + " (" + total + "/" + MINIMO_MAZO + ")" + estado));

    const acciones = crear("div");
    acciones.append(
        crearBoton("Cerrar", () => seleccionarMazo(null), ["boton-secundario"]),
        crearBoton("Disolver mazo", borrarMazo, ["boton-peligro"]),
    );
    barra.appendChild(acciones);

    const cartas = crear("div", "box box-fila");
    if (total === 0) {
        mostrarMensaje(cartas, "Mazo vacío: tocá \"Al mazo\" en tus cartas de abajo para agregarlas.");
    } else {
        mazoActual.cartas.forEach((carta) => {
            cartas.appendChild(crearCarta(carta, {
                boton: crearBoton("Quitar", () => quitarDelMazo(carta.id, mazoActual.id)),
            }));
        });
    }
    contenidoDetalle.replaceChildren(barra, cartas);
}

async function agregarAlMazo(cartaId, mazoId = mazoSeleccionado) {
    try {
        const datos = await enviar("api/agregar_a_mazo.php", { mazo_id: mazoId, carta_id: cartaId });
        avisar(datos.mensaje);
        if (modalCarta.open) modalCarta.close();
        await recargarTodo();
    } catch (error) {
        avisar(error.message, true);
    }
}

async function quitarDelMazo(cartaId, mazoId) {
    try {
        await enviar("api/quitar_de_mazo.php", { mazo_id: mazoId, carta_id: cartaId });
        if (modalCarta.open) modalCarta.close();
        await recargarTodo();
    } catch (error) {
        avisar(error.message, true);
    }
}

async function borrarMazo() {
    if (!confirm("¿Disolver el mazo \"" + mazoActual.nombre + "\"? Las cartas quedan en tu inventario.")) {
        return;
    }
    try {
        await enviar("api/borrar_mazo.php", { id: mazoActual.id });
        mazoSeleccionado = null;
        mazoActual = null;
        avisar("Mazo disuelto. Las cartas siguen en tu inventario.");
        await recargarTodo();
    } catch (error) {
        avisar(error.message, true);
    }
}


// ================= MI PERFIL =================
function dibujarPerfil() {
    const nombreCompleto = (usuario.nombre + " " + usuario.apellido).trim();
    document.getElementById("perfil-avatar").textContent = (usuario.nombre || usuario.usuario).charAt(0).toUpperCase();
    document.getElementById("perfil-nombre").textContent = nombreCompleto || usuario.usuario;
    document.getElementById("perfil-usuario").textContent =
        "@" + usuario.usuario + " · " + usuario.correo + " · miembro desde " +
        new Date(usuario.creado_en.replace(" ", "T")).toLocaleDateString("es-AR", { month: "long", year: "numeric" });
    document.getElementById("perfil-descripcion").textContent =
        usuario.descripcion || (usuario.perfil_completo ? "" : "Completá tu perfil para poder comprar.");

    document.getElementById("dato-cartas").textContent = usuario.cartas;
    document.getElementById("dato-venta").textContent = usuario.en_venta;
    document.getElementById("dato-ventas").textContent = usuario.ventas;
    document.getElementById("dato-compras").textContent = usuario.compras;
    document.getElementById("saldo-perfil").textContent = pesos(usuario.saldo);
}

function abrirEditarPerfil() {
    formPerfil.nombre.value = usuario.nombre;
    formPerfil.apellido.value = usuario.apellido;
    formPerfil.telefono.value = usuario.telefono;
    formPerfil.correo.value = usuario.correo;
    formPerfil.descripcion.value = usuario.descripcion;
    document.getElementById("mensaje-perfil").textContent = "";
    formPerfil.hidden = false;
    formPerfil.nombre.focus();
}
document.getElementById("btn-editar-perfil").addEventListener("click", abrirEditarPerfil);
document.getElementById("btn-cancelar-perfil").addEventListener("click", () => { formPerfil.hidden = true; });

formPerfil.addEventListener("submit", async (evento) => {
    evento.preventDefault();
    try {
        const datos = await enviar("api/perfil.php", new FormData(formPerfil));
        formPerfil.hidden = true;
        avisar(datos.mensaje);
        await recargarSesion();
    } catch (error) {
        document.getElementById("mensaje-perfil").textContent = error.message;
    }
});

// ---- Cargar saldo ----
function irACargarSaldo() {
    mostrarSeccion("perfil");
    formSaldo.monto.focus();
}
document.getElementById("btn-saldo").addEventListener("click", irACargarSaldo);
document.getElementById("acceso-saldo").addEventListener("click", irACargarSaldo);

document.getElementById("montos-rapidos").addEventListener("click", (evento) => {
    if (evento.target.dataset.monto) {
        formSaldo.monto.value = evento.target.dataset.monto;
    }
});

formSaldo.addEventListener("submit", async (evento) => {
    evento.preventDefault();
    const mensaje = document.getElementById("mensaje-saldo");
    mensaje.textContent = "Procesando el pago…";
    try {
        const datos = await enviar("api/recargar.php", new FormData(formSaldo));
        formSaldo.monto.value = "";
        mensaje.textContent = "";
        avisar(datos.mensaje + " Tu saldo: " + pesos(datos.saldo));
        await Promise.all([recargarSesion(), cargarHistorial()]);
    } catch (error) {
        mensaje.textContent = error.message;
        if (error.codigo === "perfil_incompleto") abrirEditarPerfil();
    }
});


// ================= HISTORIAL =================
async function cargarHistorial() {
    historial = await pedir("api/historial.php");
    dibujarHistorial();
}

// Una fila del historial: miniatura, que paso y cuanta plata
function crearMovimiento(movimiento) {
    const fila = crear("li", "movimiento");
    const monto = Number(movimiento.monto);

    const miniatura = crear("div", "miniatura");
    if (movimiento.tipo === "recarga") {
        miniatura.textContent = "$";
    } else {
        ponerFoto(miniatura, { foto: movimiento.foto, nombre: movimiento.carta || "?" });
    }

    const nombres = { compra: "COMPRA", venta: "VENTA", recarga: "CARGA DE SALDO" };
    const texto = crear("div");
    texto.appendChild(crear("span", "etiqueta-tipo tipo-" + movimiento.tipo, nombres[movimiento.tipo]));
    if (movimiento.tipo === "recarga") {
        texto.appendChild(crear("div", "", "Cargaste saldo con " + movimiento.metodo));
    } else {
        texto.appendChild(crear("div", "", movimiento.carta || "Carta eliminada"));
        const detalle = movimiento.tipo === "compra"
            ? "Vendedor: @" + movimiento.otro_usuario
            : "Comprador: @" + movimiento.otro_usuario + " · Comisión: " + pesos(movimiento.comision);
        texto.appendChild(crear("small", "", detalle + (movimiento.estado ? " · " + movimiento.estado : "")));
    }

    const lado = crear("div", "monto " + (monto < 0 ? "negativo" : "positivo"), (monto > 0 ? "+ " : "- ") + pesos(Math.abs(monto)));
    lado.appendChild(crear("small", "", formatearFecha(movimiento.fecha)));

    fila.append(miniatura, texto, lado);
    return fila;
}

function dibujarHistorial() {
    // Totales
    const suma = (tipo) => historial.filter((m) => m.tipo === tipo).reduce((total, m) => total + Math.abs(Number(m.monto)), 0);
    document.getElementById("total-gastado").textContent = pesos(suma("compra"));
    document.getElementById("total-ganado").textContent = pesos(suma("venta"));
    document.getElementById("total-cargado").textContent = pesos(suma("recarga"));

    // Lista segun la pestaña elegida
    const lista = document.getElementById("lista-historial");
    const filtrados = historial.filter((m) => filtroHistorial === "" || m.tipo === filtroHistorial);
    if (filtrados.length === 0) {
        mostrarMensaje(lista, "Todavía no hay movimientos acá.");
    } else {
        lista.replaceChildren(...filtrados.map(crearMovimiento));
    }

    // Ultimas 5 en el perfil
    const actividades = document.getElementById("lista-actividades");
    if (historial.length === 0) {
        mostrarMensaje(actividades, "Todavía no hay actividad. ¡Cargá saldo y comprá tu primera carta!");
    } else {
        actividades.replaceChildren(...historial.slice(0, 5).map(crearMovimiento));
    }
}

document.getElementById("pestanas-historial").addEventListener("click", (evento) => {
    const pestana = evento.target.closest(".pestana");
    if (!pestana) return;
    filtroHistorial = pestana.dataset.tipo;
    document.querySelectorAll(".pestana").forEach((p) => p.classList.toggle("activa", p === pestana));
    dibujarHistorial();
});


// ================= VENTANAS: cerrar con la X o tocando afuera =================
document.querySelectorAll(".cerrar-modal").forEach((boton) => {
    boton.addEventListener("click", () => boton.closest("dialog").close());
});
[modalCarta, modalPublicar].forEach((modal) => {
    modal.addEventListener("click", (evento) => {
        if (evento.target === modal) modal.close();
    });
});


// ================= SESION Y ARRANQUE =================

// Pide a PHP los datos del usuario conectado y actualiza todo lo que depende de ellos
async function recargarSesion() {
    const respuesta = await fetch("api/sesion.php");
    if (respuesta.status === 401) {
        usuario = null;
        return;
    }
    usuario = await leerRespuesta(respuesta);
    document.getElementById("saludo").textContent = usuario.usuario.toUpperCase();
    document.getElementById("saldo").textContent = pesos(usuario.saldo);
    dibujarInicio();
    dibujarPerfil();
}

// Despues de comprar, publicar, etc.: volver a pedir todo y redibujar
async function recargarTodo() {
    const pedidos = [cargarColecciones(), cargarUltimas(), cargarMercado()];
    if (usuario) {
        pedidos.push(recargarSesion(), cargarInventario(), cargarMazos(), cargarHistorial());
    }
    await Promise.all(pedidos);
    if (usuario) {
        dibujarMazos();
        dibujarDetalleMazo();
        dibujarInventario();
    }
}

document.getElementById("btn-salir").addEventListener("click", async () => {
    await fetch("api/logout.php", { method: "POST" });
    window.location.href = "index.html";
});

// Muestra un cartel rojo arriba de todo con lo que hay que revisar
function mostrarErrorConexion(texto) {
    avisoConexion.textContent = texto;
    avisoConexion.hidden = false;
}

async function iniciar() {
    llenarSelectEstados();

    // Con doble clic la direccion empieza con file:// y PHP no se ejecuta
    if (window.location.protocol === "file:") {
        mostrarErrorConexion("Abriste la página con doble clic. Abrila desde http://localhost/Coleccionex/ con Apache y MySQL encendidos en XAMPP.");
        return;
    }

    try {
        await recargarSesion();
        document.body.className = usuario ? "cliente" : "visitante";
        await recargarTodo();

        // index.html#mercado abre directamente esa seccion
        if (location.hash) {
            mostrarSeccion(location.hash.slice(1));
        }
    } catch (error) {
        console.error(error);
        mostrarErrorConexion("No se pudo conectar con la base de datos (" + error.message + "). " +
            "Revisá que Apache y MySQL estén iniciados en XAMPP y que hayas ejecutado src/coleccionex.sql en phpMyAdmin.");
    }
}

iniciar();
