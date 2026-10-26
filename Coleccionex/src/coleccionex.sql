-- ============================================================
-- COLECCIONEX - Base de datos
-- Plataforma para comprar, vender y organizar cartas coleccionables.
-- Basada en el DER del documento de análisis.
--
-- Ejecutar en phpMyAdmin (pestaña SQL).
-- ATENCIÓN: este script BORRA las tablas y las vuelve a crear desde cero,
-- con los datos de ejemplo. No usarlo si ya hay datos reales.
-- ============================================================

CREATE DATABASE IF NOT EXISTS coleccionex
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE coleccionex;

-- Se borran en orden inverso a las claves foráneas (primero las que dependen de otras)
DROP TABLE IF EXISTS reportes;
DROP TABLE IF EXISTS transacciones;
DROP TABLE IF EXISTS mazo_cartas;
DROP TABLE IF EXISTS mazos;
DROP TABLE IF EXISTS coleccion;      -- tabla de la version anterior (ya no se usa)
DROP TABLE IF EXISTS cartas;
DROP TABLE IF EXISTS colecciones;
DROP TABLE IF EXISTS usuarios;

-- 1) USUARIOS (Cliente y Administrador del DER)
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    correo VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,           -- contraseña cifrada, nunca la original
    nombre VARCHAR(80) NOT NULL DEFAULT '',
    apellido VARCHAR(80) NOT NULL DEFAULT '',      -- opcional
    telefono VARCHAR(30) NOT NULL DEFAULT '',
    descripcion VARCHAR(300) NOT NULL DEFAULT '',  -- "Sobre mí" (opcional)
    saldo DECIMAL(12,2) NOT NULL DEFAULT 0,        -- pesos argentinos
    rol VARCHAR(20) NOT NULL DEFAULT 'cliente',    -- cliente o admin
    baneado TINYINT(1) NOT NULL DEFAULT 0,         -- 1 = no puede iniciar sesión
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 2) COLECCIONES: a qué juego y edición pertenece cada carta
CREATE TABLE colecciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    juego VARCHAR(50) NOT NULL,                    -- Yu-Gi-Oh!, Pokémon, Magic, Fútbol...
    nombre VARCHAR(100) NOT NULL                   -- nombre de la edición o set
);

-- 3) CARTAS: cada fila es UNA carta física que tiene un usuario.
--    Si en_venta = 1 aparece en el Mercado. Al comprarla, cambia de dueño.
CREATE TABLE cartas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,                       -- dueño actual
    coleccion_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    estado VARCHAR(30) NOT NULL,                   -- Nuevo, Casi nuevo, Muy bueno, Bueno, Usado, Dañado
    anio SMALLINT NOT NULL,                        -- año de lanzamiento
    medidas VARCHAR(30) NOT NULL,                  -- ej: 6,3 x 8,8 cm
    peso VARCHAR(20) NOT NULL,                     -- ej: 2 g
    descripcion VARCHAR(500) NOT NULL DEFAULT '',
    foto VARCHAR(255) NULL,                        -- ruta de la foto subida
    precio DECIMAL(12,2) NULL,                     -- precio de venta en pesos
    en_venta TINYINT(1) NOT NULL DEFAULT 0,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (coleccion_id) REFERENCES colecciones(id)
);

-- 4) MAZOS
CREATE TABLE mazos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nombre VARCHAR(60) NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- 5) MAZO_CARTAS: qué cartas hay en cada mazo. Una carta física está en un solo mazo (UNIQUE).
CREATE TABLE mazo_cartas (
    mazo_id INT NOT NULL,
    carta_id INT NOT NULL UNIQUE,
    PRIMARY KEY (mazo_id, carta_id),
    FOREIGN KEY (mazo_id) REFERENCES mazos(id) ON DELETE CASCADE,
    FOREIGN KEY (carta_id) REFERENCES cartas(id) ON DELETE CASCADE
);

-- 6) TRANSACCIONES: compras, ventas y cargas de saldo (historial)
CREATE TABLE transacciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,                       -- de quién es el movimiento
    tipo VARCHAR(20) NOT NULL,                     -- compra, venta o recarga
    carta_id INT NULL,                             -- NULL en las recargas
    otro_usuario_id INT NULL,                      -- vendedor (en compras) o comprador (en ventas)
    monto DECIMAL(12,2) NOT NULL,                  -- negativo si salió plata, positivo si entró
    comision DECIMAL(12,2) NOT NULL DEFAULT 0,     -- 5% que cobra la plataforma en las ventas
    metodo VARCHAR(30) NULL,                       -- en recargas: Mercado Pago, Ualá, Naranja X
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (carta_id) REFERENCES cartas(id) ON DELETE SET NULL,
    FOREIGN KEY (otro_usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- 7) REPORTES: denuncias de compradores a vendedores (las revisa el administrador)
CREATE TABLE reportes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    denunciante_id INT NOT NULL,
    denunciado_id INT NOT NULL,
    carta_id INT NULL,
    motivo VARCHAR(60) NOT NULL,
    descripcion VARCHAR(500) NOT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'pendiente',   -- pendiente, revisado
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (denunciante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (denunciado_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (carta_id) REFERENCES cartas(id) ON DELETE SET NULL
);


-- ================= DATOS DE EJEMPLO =================

-- Dos vendedores de prueba para que el Mercado no arranque vacío.
-- Contraseña de los dos: demo1234
INSERT INTO usuarios (id, usuario, correo, password_hash, nombre, apellido, telefono, descripcion, saldo) VALUES
    (1, 'CartasDelSur', 'cartasdelsur@coleccionex.test', '$2y$10$sU.GKWpIPsm5FFPmcKiSL.G9XA1lvyqpApvCDg1z8hXnNctnmUceK',
        'Martina', 'Ríos', '299 400-1122', 'Coleccionista de Pokémon y Yu-Gi-Oh! desde chica. Envíos a todo el país.', 85000),
    (2, 'ColeccionistaX', 'coleccionistax@coleccionex.test', '$2y$10$sU.GKWpIPsm5FFPmcKiSL.G9XA1lvyqpApvCDg1z8hXnNctnmUceK',
        'Tomás', 'Godoy', '299 455-7788', 'Figuritas de fútbol y cartas de Magic. Todo en funda desde el primer día.', 120000);

INSERT INTO colecciones (id, juego, nombre) VALUES
    (1, 'Yu-Gi-Oh!', 'Legend of Blue Eyes'),
    (2, 'Yu-Gi-Oh!', 'Metal Raiders'),
    (3, 'Pokémon', 'Base Set'),
    (4, 'Pokémon', 'Escarlata y Púrpura 151'),
    (5, 'Magic: The Gathering', 'Dominaria United'),
    (6, 'Fútbol', 'Panini Mundial Qatar 2022'),
    (7, 'Fútbol', 'Panini Liga Profesional Argentina');

-- Cartas a la venta (precios en pesos argentinos)
INSERT INTO cartas (usuario_id, coleccion_id, nombre, estado, anio, medidas, peso, descripcion, precio, en_venta) VALUES
    (1, 1, 'Dragón Blanco de Ojos Azules', 'Casi nuevo', 2002, '5,9 x 8,6 cm', '2 g', 'Primera edición en español. Sin marcas, siempre en funda.', 185000, 1),
    (1, 1, 'Mago Oscuro', 'Muy bueno', 2002, '5,9 x 8,6 cm', '2 g', 'Leve desgaste en una esquina, el resto impecable.', 95000, 1),
    (2, 2, 'Exodia la Prohibida', 'Bueno', 2002, '5,9 x 8,6 cm', '2 g', 'Bordes con un poco de uso. Ideal para colección.', 70000, 1),
    (1, 2, 'Dragón Negro de Ojos Rojos', 'Usado', 2002, '5,9 x 8,6 cm', '2 g', 'Tiene marcas de juego, se ve en las fotos.', 32000, 1),
    (1, 3, 'Charizard Holográfico', 'Muy bueno', 1999, '6,3 x 8,8 cm', '2 g', 'Holo con buen brillo. Pequeña raya en el reverso.', 650000, 1),
    (1, 3, 'Pikachu', 'Nuevo', 1999, '6,3 x 8,8 cm', '2 g', 'Recién sacada del sobre, guardada en top loader.', 28000, 1),
    (2, 3, 'Blastoise Holográfico', 'Bueno', 1999, '6,3 x 8,8 cm', '2 g', 'Buen estado general, bordes algo blancos.', 210000, 1),
    (1, 4, 'Mewtwo ex', 'Nuevo', 2023, '6,3 x 8,8 cm', '2 g', 'Carta especial ilustrada, perfecta.', 45000, 1),
    (2, 4, 'Bulbasaur', 'Casi nuevo', 2023, '6,3 x 8,8 cm', '2 g', 'Carta de la colección 151 en español.', 6500, 1),
    (2, 5, 'Sheoldred, la Apocalipsis', 'Casi nuevo', 2022, '6,3 x 8,8 cm', '2 g', 'Muy buscada para mazos competitivos.', 120000, 1),
    (2, 5, 'Liliana del Velo Oscuro', 'Muy bueno', 2022, '6,3 x 8,8 cm', '2 g', 'Versión sin foil.', 38000, 1),
    (2, 6, 'Lionel Messi', 'Nuevo', 2022, '5,0 x 7,0 cm', '1 g', 'Figurita del álbum oficial, sin pegar.', 15000, 1),
    (2, 6, 'Emiliano "Dibu" Martínez', 'Nuevo', 2022, '5,0 x 7,0 cm', '1 g', 'Sin pegar, perfecta.', 9000, 1),
    (1, 6, 'Julián Álvarez', 'Casi nuevo', 2022, '5,0 x 7,0 cm', '1 g', 'Un mínimo detalle en el borde.', 7000, 1),
    (2, 7, 'Escudo de River Plate', 'Nuevo', 2024, '5,0 x 7,0 cm', '1 g', 'Figurita brillante del escudo.', 4500, 1),
    (1, 7, 'Escudo de Boca Juniors', 'Nuevo', 2024, '5,0 x 7,0 cm', '1 g', 'Figurita brillante del escudo.', 4500, 1);

-- Nota: los usuarios nuevos se crean desde paginas/registro.html (no a mano en phpMyAdmin).
