#!/bin/bash
# Arranque del contenedor: MariaDB + esquema de la BD + Apache
set -e

# 1. Si el volumen está vacío, inicializar el directorio de datos de MariaDB
if [ ! -d /var/lib/mysql/mysql ]; then
    echo ">> Inicializando el directorio de datos de MariaDB..."
    mysql_install_db --user=mysql --datadir=/var/lib/mysql > /dev/null
fi

# 2. Limpiar archivos PID que pudieron quedar de una ejecución anterior (docker restart)
rm -f /var/run/apache2/apache2.pid /run/mysqld/mysqld.pid
mkdir -p /run/mysqld && chown -R mysql:mysql /run/mysqld /var/lib/mysql

# 3. Pasar las variables de conexión a Apache/PHP
cat > /etc/apache2/conf-enabled/db-env.conf <<CONF
SetEnv DB_HOST ${DB_HOST}
SetEnv DB_NAME ${DB_NAME}
SetEnv DB_USER ${DB_USER}
SetEnv DB_PASS ${DB_PASS}
CONF

# 4. Iniciar MariaDB en segundo plano (solo escucha dentro del contenedor)
mysqld_safe --user=mysql --bind-address=127.0.0.1 > /dev/null 2>&1 &

for i in $(seq 1 30); do
    mysqladmin ping --silent && break
    sleep 1
done

# 5. Crear la base de datos y el usuario de la aplicación si no existen
if [ ! -d "/var/lib/mysql/${DB_NAME}" ]; then
    echo ">> Creando base de datos y usuario de la aplicación..."
fi
mysql -u root <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

# 6. Si los datos vienen de la versión 1 (campo único de nombre), respaldar la tabla
consulta() { mysql -u root -N -B "${DB_NAME}" -e "$1"; }
ES_V1=$(consulta "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='${DB_NAME}' AND TABLE_NAME='alumnos' AND COLUMN_NAME='nombre_alumno'")
if [ "$ES_V1" = "1" ]; then
    echo ">> Datos de la versión 1 encontrados: se respaldan en la tabla alumnos_v1"
    consulta "RENAME TABLE alumnos TO alumnos_v1"
fi

# 7. Crear o completar las tablas (solo crea lo que falta; no borra datos)
mysql -u root "${DB_NAME}" < /docker-init/init.sql

if [ "$ES_V1" = "1" ]; then
    echo ">> Migrando alumnos de la versión 1 con nombres y apellidos separados..."
    mysql -u root "${DB_NAME}" < /docker-init/migrar_v1.sql
fi

# 8. Crear el usuario administrador si todavía no hay usuarios
USUARIOS=$(consulta "SELECT COUNT(*) FROM usuarios")
if [ "$USUARIOS" = "0" ]; then
    HASH=$(ADMIN_PASS="${ADMIN_PASS}" php -r 'echo password_hash(getenv("ADMIN_PASS"), PASSWORD_DEFAULT);')
    consulta "INSERT INTO usuarios (nombre, apellido_paterno, usuario, password_hash, rol)
              VALUES ('Administrador', 'del sistema', '${ADMIN_USER}', '${HASH}', 'admin')"
    echo ">> Usuario administrador creado: ${ADMIN_USER} (cambia la contraseña al entrar)"
else
    echo ">> Base de datos existente encontrada: se conservan los datos."
fi

# 9. Al detener el contenedor (docker stop), apagar Apache y MariaDB de forma ordenada
apagar() {
    echo ">> Deteniendo servicios..."
    apachectl -k graceful-stop 2>/dev/null || true
    mysqladmin -u root shutdown 2>/dev/null || true
    exit 0
}
trap apagar SIGTERM SIGINT

echo ">> Sistema de Tutorías listo en http://localhost:10085"
# Apache en primer plano (mantiene vivo el contenedor)
apachectl -D FOREGROUND &
wait $!
