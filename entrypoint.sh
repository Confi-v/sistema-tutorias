#!/bin/bash
# Arranque del contenedor: MariaDB + base de datos + Apache
set -e

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

# Crear la base de datos, el usuario de la aplicación y la tabla
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

echo ">> Sistema de Tutorías listo en http://localhost:10085"
# Apache en primer plano (mantiene vivo el contenedor)
exec apachectl -D FOREGROUND
