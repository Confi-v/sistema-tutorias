# Sistema de Tutorías - UPSLP - Sistemas Virtuales
FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive

# Paso 2: instalar el servidor web Apache
RUN apt-get update \
    && apt-get install -y --no-install-recommends apache2 \
    && rm -rf /var/lib/apt/lists/* /var/www/html/index.html

# Copiar la página estática a la carpeta que publica Apache
COPY src/public/ /var/www/html/

# Apache en primer plano para que el contenedor siga vivo
CMD ["apachectl", "-D", "FOREGROUND"]
