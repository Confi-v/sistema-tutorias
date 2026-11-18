#!/bin/bash
# Arranque del contenedor: MariaDB + base de datos + Apache
set -e

# Si el volumen está vacío, inicializar el directorio de datos de MariaDB
if [ ! -d /var/lib/mysql/mysql ]; then
    echo ">> Inicializando el directorio de datos de MariaDB..."
    mysql_install_db --user=mysql --datadir=/var/lib/mysql > /dev/null
fi

# Limpiar archivos PID que pudieron quedar de una ejecución anterior (docker restart)
rm -f /var/run/apache2/apache2.pid /run/mysqld/mysqld.pid
mkdir -p /run/mysqld && chown -R mysql:mysql /run/mysqld /var/lib/mysql

# Pasar las variables de conexión a Apache/PHP
cat > /etc/apache2/conf-enabled/db-env.conf <<CONF
SetEnv DB_HOST ${DB_HOST}
SetEnv DB_NAME ${DB_NAME}
SetEnv DB_USER ${DB_USER}
SetEnv DB_PASS ${DB_PASS}
CONF

# Iniciar MariaDB en segundo plano (solo escucha dentro del contenedor)
mysqld_safe --user=mysql --bind-address=127.0.0.1 > /dev/null 2>&1 &

for i in $(seq 1 30); do
    mysqladmin ping --silent && break
    sleep 1
done

# Crear la base de datos solo si el volumen todavía no la tiene
if [ ! -d "/var/lib/mysql/${DB_NAME}" ]; then
    echo ">> Creando base de datos, usuario y tabla..."
    mysql -u root <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
    mysql -u root "${DB_NAME}" < /docker-init/init.sql
else
    echo ">> Base de datos existente encontrada: se conservan los datos."
fi

# Al detener el contenedor (docker stop), apagar Apache y MariaDB de forma ordenada
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
