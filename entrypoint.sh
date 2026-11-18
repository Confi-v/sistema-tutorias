#!/bin/bash
set -e

# Si el volumen está vacío, inicializar el directorio de datos de MariaDB
if [ ! -d /var/lib/mysql/mysql ]; then
    echo ">> Inicializando el directorio de datos de MariaDB..."
    mysql_install_db --user=mysql --datadir=/var/lib/mysql > /dev/null
fi

# Si la base de datos de la aplicación aún no existe, hay que crearla
if [ ! -d "/var/lib/mysql/${DB_NAME}" ]; then
    FIRST_RUN=1
else
    echo ">> Base de datos existente encontrada: se conservan los datos."
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

# Iniciar MariaDB en segundo plano
mysqld_safe --user=mysql --bind-address=127.0.0.1 --default-time-zone='-06:00' > /dev/null 2>&1 &

# Esperar a que MariaDB esté lista
for i in $(seq 1 30); do
    mysqladmin ping --silent && break
    sleep 1
done

if [ "$FIRST_RUN" = "1" ]; then
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
fi

# Al detener el contenedor (docker stop), apagar MariaDB y Apache de forma ordenada
apagar() {
    echo ">> Deteniendo servicios..."
    apachectl -k graceful-stop 2>/dev/null
    mysqladmin -u root shutdown 2>/dev/null
    exit 0
}
trap apagar SIGTERM SIGINT

echo ">> Sistema de Tutorías listo en http://localhost:10085"
# Apache en primer plano (mantiene vivo el contenedor)
apachectl -D FOREGROUND &
wait $!
