# Sistema de Tutorías - UPSLP - Sistemas Virtuales
FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive

# Apache (servidor web), PHP con soporte para MySQL y MariaDB (base de datos compatible con MySQL)
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
       apache2 php libapache2-mod-php php-mysql mariadb-server mariadb-client \
    && rm -rf /var/lib/apt/lists/* /var/www/html/index.html

# Apache escucha en el puerto 10085 en lugar del 80
RUN sed -i 's/^Listen 80$/Listen 10085/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:10085>/' /etc/apache2/sites-available/000-default.conf \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Archivos de la aplicación y script de la base de datos
COPY src/public/ /var/www/html/
COPY db/ /docker-init/
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Variables de conexión a la base de datos
ENV DB_HOST=127.0.0.1 \
    DB_NAME=tutorias \
    DB_USER=tutorias \
    DB_PASS=tutorias123

# Carpeta de datos de MariaDB: se monta como volumen para que los datos persistan
VOLUME ["/var/lib/mysql"]

# Puerto que publica el contenedor
EXPOSE 10085

# Al arrancar: inicia MariaDB, crea la base de datos y arranca Apache
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
