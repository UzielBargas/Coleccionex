# Coleccionex

Plataforma web para comprar, vender y organizar cartas coleccionables (Yu-Gi-Oh!, Pokémon, Magic, figuritas de fútbol).
Proyecto de Taller de Construcción de Software, EPET N.º 20 — Uziel Bargas y Aaron Cuevas.

## Cómo ponerlo en marcha (XAMPP)

1. En el panel de XAMPP iniciar **Apache** y **MySQL**.
2. Copiar la carpeta del proyecto dentro de `htdocs` (queda `htdocs/Coleccionex/`).
3. Abrir phpMyAdmin → pestaña **SQL** → pegar y ejecutar `src/coleccionex.sql`.
   **Atención:** el script borra las tablas y las crea de nuevo con datos de ejemplo.
4. Abrir **http://localhost/Coleccionex/** (nunca con doble clic: con `file://` PHP no funciona).

Prueba rápida: `http://localhost/Coleccionex/api/publicaciones.php` tiene que responder un JSON.

### Cuentas de prueba

| Usuario | Contraseña | Qué tiene |
|---|---|---|
| `CartasDelSur` | `demo1234` | Cartas de Yu-Gi-Oh!, Pokémon y fútbol a la venta |
| `ColeccionistaX` | `demo1234` | Cartas de Magic, Pokémon y fútbol a la venta |

O crear una cuenta nueva desde "Registrarse".

## Qué tiene la página

- **Visitante (sin sesión):** Inicio con buscador, juegos y últimas publicaciones; Mercado limitado a 8 cartas. Para comprar pide iniciar sesión.
- **Inicio:** saludo, saldo, accesos rápidos, explorar por juego, últimas publicaciones, "Lo que hacemos" y preguntas frecuentes.
- **Mi perfil:** datos del usuario (editar nombre, apellido, teléfono, correo, descripción), números (cartas, publicadas, ventas, compras), últimas actividades y **cargar saldo** en pesos con Mercado Pago, Ualá o Naranja X (simulado).
- **Mis cartas / mazos:** inventario, subir cartas, mazos (completo con 10 cartas), cartas sin mazo.
- **Publicar carta:** foto obligatoria, nombre, colección (o una nueva), estado, año, medidas, peso, descripción y precio. Muestra la comisión del 5% y el total a recibir.
- **Mercado:** buscador (nombre o colección), filtros por juego, colección, estado, año y rango de precio, orden por precio o recientes.
- **Detalle de carta:** ficha completa, comprar con confirmación (muestra el saldo que queda), reportar vendedor, y para las propias: publicar/pausar, agregar a mazo, eliminar.
- **Historial:** compras, ventas (con comisión) y cargas de saldo, con totales y pestañas.

## Reglas que pide el documento de análisis

- Para **comprar o cargar saldo** el perfil tiene que tener nombre y teléfono (`exigirPerfilCompleto`).
- Para **publicar** son obligatorios estado, medidas, peso, año, colección y una foto.
- La búsqueda encuentra el texto al comienzo, en el medio o al final (`LIKE '%texto%'`).
- Sin sesión solo se ven algunas cartas.
- Los **reportes** se guardan en la tabla `reportes` (estado `pendiente`) para el panel de administrador.
- La columna `usuarios.baneado` bloquea el inicio de sesión (para cuando exista el panel de administrador).

## Estructura

```
Coleccionex/
├── index.html            Página principal (modo visitante y modo cliente)
├── paginas/              sesion.html, registro.html, sobre-nosotros.html
├── css/                  Estilos.css (principal), sesion.css, sobre-nosotros.css
├── js/
│   ├── funciones.js      Cambio de secciones del menú
│   ├── app.js            Pide los datos a la API y dibuja todo
│   ├── sesion.js         Envía el formulario de login
│   └── registro.js       Envía el formulario de registro
├── api/                  PHP: consulta MySQL y responde JSON
├── imagenes/subidas/     Fotos que suben los usuarios (Apache no ejecuta PHP acá)
└── src/coleccionex.sql   Script de la base de datos
```

## Base de datos

| Tabla | Para qué |
|---|---|
| `usuarios` | Cuentas, datos de perfil, saldo en pesos, rol y si está baneado |
| `colecciones` | Juego y edición (ej: Pokémon — Base Set) |
| `cartas` | Cada fila es una carta física: dueño, colección, estado, año, medidas, peso, foto, precio y si está en venta |
| `mazos` | Los mazos de cada usuario |
| `mazo_cartas` | Qué cartas hay en cada mazo (una carta está en un solo mazo) |
| `transacciones` | Compras, ventas y cargas de saldo |
| `reportes` | Denuncias a vendedores |

Cuando alguien compra, la carta **cambia de dueño** (`cartas.usuario_id`), se descuenta el precio al comprador
y el vendedor recibe el precio menos el 5%. Todo dentro de una transacción de PDO (`beginTransaction` / `commit`).

## API (carpeta `api/`)

| Archivo | Método | Qué hace |
|---|---|---|
| `registro.php` | POST | Crea un usuario e inicia su sesión |
| `login.php` | POST | Inicia sesión con usuario o correo |
| `sesion.php` | GET | Quién está conectado (perfil, saldo y números) |
| `logout.php` | POST | Cierra la sesión |
| `perfil.php` | POST | Modifica los datos del perfil |
| `recargar.php` | POST | Carga saldo (simulado) |
| `colecciones.php` | GET | Lista de colecciones (pública) |
| `publicaciones.php` | GET | Mercado con filtros (pública, limitada sin sesión) |
| `mis_cartas.php` | GET | Inventario del usuario |
| `subir_carta.php` | POST | Sube una carta con foto y opcionalmente la publica |
| `cambiar_venta.php` | POST | Publica o pausa la venta de una carta propia |
| `borrar_carta.php` | POST | Elimina una carta del inventario |
| `comprar.php` | POST | Compra una carta |
| `reportar.php` | POST | Reporta a un vendedor |
| `historial.php` | GET | Movimientos del usuario |
| `mazos.php` / `mazo.php?id=` | GET | Mazos / cartas de un mazo |
| `crear_mazo.php` / `borrar_mazo.php` | POST | Crear / disolver un mazo |
| `agregar_a_mazo.php` / `quitar_de_mazo.php` | POST | Poner / sacar una carta de un mazo |

`funciones.php` tiene lo compartido: estados válidos, métodos de pago, motivos de reporte, comisión y funciones de ayuda.

## Pendiente

- Panel de administrador (bandeja de reportes, inspeccionar historial, banear). La base ya está preparada.
- Pago real con Mercado Pago / Ualá / Naranja X (hoy es una simulación).
