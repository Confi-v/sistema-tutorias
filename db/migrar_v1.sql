-- =============================================================
-- Migración de la versión 1 (un solo campo de nombre) a la versión 2.
-- La ejecuta entrypoint.sh solo si encuentra la tabla anterior,
-- que antes se renombra a "alumnos_v1" como respaldo.
-- Separa "Nombre(s) ApellidoPaterno ApellidoMaterno" con esta regla:
--   3 o más palabras: la última es el materno y la penúltima el paterno
--   2 palabras: nombre y paterno
--   1 palabra: solo nombre (el paterno queda vacío para corregirlo)
-- =============================================================

INSERT INTO alumnos (nombre, apellido_paterno, apellido_materno, matricula, correo,
                     tutor_nombre, tutor_apellido_paterno, tutor_apellido_materno, creado_en)
SELECT
    CASE WHEN p.na >= 3 THEN SUBSTRING_INDEX(p.a, ' ', p.na - 2)
         WHEN p.na = 2  THEN SUBSTRING_INDEX(p.a, ' ', 1)
         ELSE p.a END,
    CASE WHEN p.na >= 3 THEN SUBSTRING_INDEX(SUBSTRING_INDEX(p.a, ' ', -2), ' ', 1)
         WHEN p.na = 2  THEN SUBSTRING_INDEX(p.a, ' ', -1)
         ELSE '' END,
    CASE WHEN p.na >= 3 THEN SUBSTRING_INDEX(p.a, ' ', -1) ELSE NULL END,
    p.matricula,
    p.correo_alumno,
    CASE WHEN p.nt >= 3 THEN SUBSTRING_INDEX(p.t, ' ', p.nt - 2)
         WHEN p.nt = 2  THEN SUBSTRING_INDEX(p.t, ' ', 1)
         ELSE p.t END,
    CASE WHEN p.nt >= 3 THEN SUBSTRING_INDEX(SUBSTRING_INDEX(p.t, ' ', -2), ' ', 1)
         WHEN p.nt = 2  THEN SUBSTRING_INDEX(p.t, ' ', -1)
         ELSE '' END,
    CASE WHEN p.nt >= 3 THEN SUBSTRING_INDEX(p.t, ' ', -1) ELSE NULL END,
    p.fecha_registro
FROM (
    SELECT v.*,
           REGEXP_REPLACE(TRIM(v.nombre_alumno), ' +', ' ') AS a,
           REGEXP_REPLACE(TRIM(v.nombre_tutor),  ' +', ' ') AS t,
           LENGTH(REGEXP_REPLACE(TRIM(v.nombre_alumno), ' +', ' '))
             - LENGTH(REPLACE(REGEXP_REPLACE(TRIM(v.nombre_alumno), ' +', ' '), ' ', '')) + 1 AS na,
           LENGTH(REGEXP_REPLACE(TRIM(v.nombre_tutor), ' +', ' '))
             - LENGTH(REPLACE(REGEXP_REPLACE(TRIM(v.nombre_tutor), ' +', ' '), ' ', '')) + 1 AS nt
    FROM alumnos_v1 v
) p
WHERE p.matricula NOT IN (SELECT matricula FROM alumnos);

INSERT INTO bitacora (usuario_id, accion, descripcion)
VALUES (NULL, 'migracion', CONCAT('Se migraron los registros de la versión 1 (respaldo en la tabla alumnos_v1). ',
                                  'Revisa que los nombres y apellidos se hayan separado bien.'));
