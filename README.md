# Sistema de Tutorías · UPSLP

Proyecto final de **Sistemas Virtuales**: aplicación web que corre en un contenedor Docker con Apache + PHP y una base de datos MariaDB (compatible con MySQL). Los datos se guardan en un volumen de Docker, así que no se pierden al detener o borrar el contenedor.

- Puerto: **10085**
- Base de datos: `tutorias`, tabla `alumnos` (nombre del alumno, matrícula, nombre del tutor, correo electrónico)
- Volumen de datos: `tutorias_datos` → `/var/lib/mysql`

## Estructura

```
sistema-tutorias/
├── Dockerfile        # Construye la imagen (Ubuntu + Apache + PHP + MariaDB)
├── entrypoint.sh     # Inicia MariaDB, crea la BD la primera vez y arranca Apache
├── db/init.sql       # Crea la tabla "alumnos"
└── src/
    ├── index.php     # Formulario y tabla de alumnos
    └── estilos.css   # Colores institucionales (azul marino y dorado)
```

## Requisitos

- Linux con Docker instalado (`docker --version`)
- Git (`git --version`)

## Correr en cualquier PC

```bash
# 1. Descargar el proyecto
git clone https://github.com/Confi-v/sistema-tutorias.git
cd sistema-tutorias

# 2. Construir la imagen
docker build -t sistema-tutorias .

# 3. Correr el contenedor con un volumen para los datos
docker run -d --name tutorias -p 10085:10085 -v tutorias_datos:/var/lib/mysql sistema-tutorias
```

Abrir en el navegador: **http://localhost:10085** (desde otra máquina de la red: `http://IP-DEL-SERVIDOR:10085`).

> Si Docker pide permisos, antepón `sudo` a los comandos `docker`.

## Comandos útiles

| Acción | Comando |
|---|---|
| Ver si está corriendo | `docker ps` |
| Ver los mensajes del contenedor | `docker logs tutorias` |
| Detener | `docker stop tutorias` |
| Volver a iniciar | `docker start tutorias` |
| Borrar el contenedor (los datos se conservan) | `docker rm -f tutorias` |
| Ver el volumen de datos | `docker volume inspect tutorias_datos` |
| Consultar la tabla desde la terminal | `docker exec -it tutorias mysql -u root --default-character-set=utf8mb4 tutorias -e "SELECT * FROM alumnos;"` |
| Borrar también los datos | `docker volume rm tutorias_datos` |

## Probar que los datos persisten

```bash
# Registrar alumnos desde la página, luego:
docker rm -f tutorias
docker run -d --name tutorias -p 10085:10085 -v tutorias_datos:/var/lib/mysql sistema-tutorias
# Recargar http://localhost:10085: los alumnos siguen ahí
```

## Actualizar después de cambios en GitHub

```bash
cd sistema-tutorias
git pull
docker rm -f tutorias
docker build -t sistema-tutorias .
docker run -d --name tutorias -p 10085:10085 -v tutorias_datos:/var/lib/mysql sistema-tutorias
```
