# Sistema de Tutorías - UPSLP - Sistemas Virtuales
# Una sola imagen con Apache + PHP (aplicación web) y MariaDB (base de datos)
FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive \
    TZ=America/Mexico_City

# Instalar Apache, PHP (con soporte para MySQL), MariaDB (compatible con MySQL) y curl
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
       apache2 php libapache2-mod-php php-mysql php-mbstring mariadb-server mariadb-client curl tzdata \
    && rm -rf /var/lib/apt/lists/* /var/www/html/index.html

# Apache escucha en el puerto 10085, sin listado de carpetas ni versión visible
RUN sed -i 's/^Listen 80$/Listen 10085/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:10085>/' /etc/apache2/sites-available/000-default.conf \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf \
    && sed -i 's/Options Indexes FollowSymLinks/Options FollowSymLinks/' /etc/apache2/apache2.conf \
    && sed -i 's/^ServerTokens .*/ServerTokens Prod/; s/^ServerSignature .*/ServerSignature Off/' /etc/apache2/conf-available/security.conf \
    && sed -i 's/^expose_php = .*/expose_php = Off/; s#^;date.timezone =.*#date.timezone = America/Mexico_City#' /etc/php/*/apache2/php.ini

# Archivos de la aplicación: "public" es lo que ve el navegador, "inc" queda fuera de la web
COPY src/public/ /var/www/html/
COPY src/inc/ /var/www/inc/
COPY db/ /docker-init/
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Variables de conexión a la base de datos y usuario administrador inicial
ENV DB_HOST=127.0.0.1 \
    DB_NAME=tutorias \
    DB_USER=tutorias \
    DB_PASS=tutorias123 \
    ADMIN_USER=admin \
    ADMIN_PASS=admin123

# Carpeta de datos de MariaDB: se monta como volumen para que los datos persistan
VOLUME ["/var/lib/mysql"]

EXPOSE 10085

# Docker revisa cada 30 s que la página responda
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -fs http://localhost:10085/login.php > /dev/null || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
