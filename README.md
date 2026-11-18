# Sistema de Tutorías · UPSLP

Proyecto final de **Sistemas Virtuales**: aplicación web que corre en un contenedor Docker con Apache + PHP y una base de datos MariaDB (compatible con MySQL). Los datos se guardan en un volumen de Docker, así que no se pierden al detener o borrar el contenedor.

- Puerto: **10085**
- Volumen de datos: `tutorias_datos` → `/var/lib/mysql`
- Usuario inicial: **admin** / contraseña **admin123** (cámbiala en *Mi cuenta* al entrar)

## Funciones

- Inicio de sesión con usuarios y contraseñas cifradas.
- Registro de alumnos con nombre(s), apellido paterno y materno, matrícula, correo y tutor (nombre y apellidos).
- Editar y eliminar alumnos. El **capturista** solo puede modificar los alumnos que él registró; el **administrador** puede modificar todos.
- Cada alumno muestra **quién lo registró** y **quién lo editó por última vez**, con fecha y hora.
- Búsqueda por nombre, matrícula, correo o tutor, y filtro por usuario que registró.
- **Usuarios** (administrador): crear usuarios, activar o desactivar, cambiar rol y asignar contraseña.
- **Bitácora** (administrador): historial de altas, ediciones (qué campo cambió y de qué a qué), bajas, inicios de sesión e intentos fallidos.
- Colores institucionales de la UPSLP (azul y naranja).

## Base de datos

| Tabla | Para qué |
|---|---|
| `usuarios` | Personas que entran al sistema (nombre, apellidos, usuario, contraseña cifrada, rol, activo) |
| `alumnos` | Nombre, apellido paterno, apellido materno, matrícula, correo, nombre y apellidos del tutor, `creado_por`, `creado_en`, `modificado_por`, `modificado_en` |
| `bitacora` | Quién hizo qué y cuándo |

El esquema está en `db/init.sql`. Si el volumen trae datos de la primera versión (un solo campo de nombre), el contenedor los migra solo al arrancar y deja un respaldo en la tabla `alumnos_v1`.

## Estructura

```
sistema-tutorias/
├── Dockerfile          # Construye la imagen (Ubuntu + Apache + PHP + MariaDB)
├── entrypoint.sh       # Inicia MariaDB, crea/actualiza la BD y arranca Apache
├── db/
│   ├── init.sql        # Tablas usuarios, alumnos y bitacora
│   └── migrar_v1.sql   # Migración desde la primera versión
└── src/
    ├── inc/            # Configuración y plantilla (fuera de la web)
    └── public/         # Páginas: login, alumnos, usuarios, bitácora, mi cuenta, estilos
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
docker run -d --name tutorias --restart unless-stopped -p 10085:10085 -v tutorias_datos:/var/lib/mysql sistema-tutorias
```

Abrir en el navegador: **http://localhost:10085** (desde otra máquina de la red: `http://IP-DEL-SERVIDOR:10085`) y entrar con `admin` / `admin123`.

> Si Docker pide permisos, antepón `sudo` a los comandos `docker`.
> Para usar otra contraseña inicial del administrador, agrega `-e ADMIN_PASS=TuContraseña` al `docker run` la primera vez.

## Comandos útiles

| Acción | Comando |
|---|---|
| Ver si está corriendo (y si está *healthy*) | `docker ps` |
| Ver los mensajes del contenedor | `docker logs tutorias` |
| Detener | `docker stop tutorias` |
| Volver a iniciar | `docker start tutorias` |
| Borrar el contenedor (los datos se conservan) | `docker rm -f tutorias` |
| Ver el volumen de datos | `docker volume inspect tutorias_datos` |
| Consultar alumnos desde la terminal | `docker exec -it tutorias mysql -u root --default-character-set=utf8mb4 tutorias -e "SELECT * FROM alumnos;"` |
| Consultar la bitácora | `docker exec -it tutorias mysql -u root --default-character-set=utf8mb4 tutorias -e "SELECT * FROM bitacora ORDER BY fecha DESC LIMIT 20;"` |
| Borrar también los datos | `docker volume rm tutorias_datos` |

## Probar que los datos persisten

```bash
# Registrar alumnos desde la página, luego:
docker rm -f tutorias
docker run -d --name tutorias --restart unless-stopped -p 10085:10085 -v tutorias_datos:/var/lib/mysql sistema-tutorias
# Volver a entrar en http://localhost:10085: los alumnos siguen ahí
```

## Actualizar después de cambios en GitHub

```bash
cd sistema-tutorias
git pull
docker rm -f tutorias
docker build -t sistema-tutorias .
docker run -d --name tutorias --restart unless-stopped -p 10085:10085 -v tutorias_datos:/var/lib/mysql sistema-tutorias
```

Los datos del volumen se conservan y, si hace falta, se actualiza el esquema de la base de datos al arrancar.

## Si olvidas la contraseña del administrador

```bash
docker exec -it tutorias bash -c 'HASH=$(php -r "echo password_hash(\"admin123\", PASSWORD_DEFAULT);"); mysql -u root tutorias -e "UPDATE usuarios SET password_hash=\"$HASH\", activo=1 WHERE usuario=\"admin\";"'
```
Esto deja la contraseña de `admin` otra vez en `admin123`.
