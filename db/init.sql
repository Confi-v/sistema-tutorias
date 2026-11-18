-- Tabla de alumnos del Sistema de Tutorías
CREATE TABLE IF NOT EXISTS alumnos (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    nombre_alumno  VARCHAR(100) NOT NULL,
    matricula      VARCHAR(20)  NOT NULL UNIQUE,
    nombre_tutor   VARCHAR(100) NOT NULL,
    correo_alumno  VARCHAR(100) NOT NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
