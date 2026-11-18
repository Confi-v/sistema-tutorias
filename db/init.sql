-- =============================================================
-- Sistema de Tutorías - UPSLP
-- Esquema de la base de datos. Se ejecuta cada vez que arranca
-- el contenedor; solo crea lo que todavía no existe.
-- =============================================================

-- Usuarios que pueden entrar al sistema y capturar alumnos
CREATE TABLE IF NOT EXISTS usuarios (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    nombre           VARCHAR(60)  NOT NULL,
    apellido_paterno VARCHAR(60)  NOT NULL,
    apellido_materno VARCHAR(60)  NULL,
    usuario          VARCHAR(30)  NOT NULL UNIQUE,
    password_hash    VARCHAR(255) NOT NULL,
    rol              ENUM('admin', 'capturista') NOT NULL DEFAULT 'capturista',
    activo           TINYINT(1)   NOT NULL DEFAULT 1,
    creado_en        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alumnos y su tutor. Cada registro guarda quién lo creó y quién lo editó por última vez
CREATE TABLE IF NOT EXISTS alumnos (
    id                     INT AUTO_INCREMENT PRIMARY KEY,
    nombre                 VARCHAR(60)  NOT NULL,
    apellido_paterno       VARCHAR(60)  NOT NULL,
    apellido_materno       VARCHAR(60)  NULL,
    matricula              VARCHAR(20)  NOT NULL UNIQUE,
    correo                 VARCHAR(100) NOT NULL,
    tutor_nombre           VARCHAR(60)  NOT NULL,
    tutor_apellido_paterno VARCHAR(60)  NOT NULL,
    tutor_apellido_materno VARCHAR(60)  NULL,
    creado_por             INT          NULL,
    creado_en              TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modificado_por         INT          NULL,
    modificado_en          TIMESTAMP    NULL DEFAULT NULL,
    CONSTRAINT fk_alumnos_creado_por     FOREIGN KEY (creado_por)     REFERENCES usuarios(id),
    CONSTRAINT fk_alumnos_modificado_por FOREIGN KEY (modificado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bitácora: historial de altas, ediciones, bajas e inicios de sesión
CREATE TABLE IF NOT EXISTS bitacora (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id  INT          NULL,
    accion      VARCHAR(30)  NOT NULL,
    alumno_id   INT          NULL,
    descripcion VARCHAR(1000) NOT NULL,
    fecha       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_bitacora_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX idx_bitacora_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
