<?php
// =============================================================
// Configuración y funciones comunes del Sistema de Tutorías
// (esta carpeta queda fuera de la web: no se puede abrir desde el navegador)
// =============================================================
declare(strict_types=1);

date_default_timezone_set('America/Mexico_City');

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

/** Conexión única a la base de datos */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $name = getenv('DB_NAME') ?: 'tutorias';
        $user = getenv('DB_USER') ?: 'tutorias';
        $pass = getenv('DB_PASS') ?: 'tutorias123';
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $ex) {
            http_response_code(500);
            exit('No se pudo conectar con la base de datos. Revisa que el contenedor haya terminado de iniciar.');
        }
    }
    return $pdo;
}

/** Escapar texto para mostrarlo en HTML */
function e($texto): string
{
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}

function redirigir(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/** Nombre completo a partir de los campos nombre / apellido_paterno / apellido_materno */
function nombre_completo(array $fila, string $prefijo = ''): string
{
    return trim(preg_replace('/\s+/', ' ', ($fila[$prefijo . 'nombre'] ?? '') . ' '
        . ($fila[$prefijo . 'apellido_paterno'] ?? '') . ' '
        . ($fila[$prefijo . 'apellido_materno'] ?? '')));
}

// ---------- Sesión y permisos ----------

/** Usuario con sesión iniciada (se vuelve a leer de la BD por si lo desactivaron) */
function usuario_actual(): ?array
{
    static $usuario = false;
    if ($usuario === false) {
        $usuario = null;
        if (!empty($_SESSION['uid'])) {
            $st = db()->prepare('SELECT id, nombre, apellido_paterno, apellido_materno, usuario, rol, activo
                                 FROM usuarios WHERE id = ?');
            $st->execute([$_SESSION['uid']]);
            $fila = $st->fetch();
            if ($fila && $fila['activo']) {
                $usuario = $fila;
            } else {
                $_SESSION = [];
            }
        }
    }
    return $usuario;
}

function requiere_login(): array
{
    $u = usuario_actual();
    if (!$u) {
        redirigir('login.php');
    }
    return $u;
}

function es_admin(?array $u): bool
{
    return $u !== null && $u['rol'] === 'admin';
}

function requiere_admin(): array
{
    $u = requiere_login();
    if (!es_admin($u)) {
        flash('Esa sección es solo para administradores.', 'error');
        redirigir('index.php');
    }
    return $u;
}

/** Un capturista solo puede editar o borrar lo que él registró; el administrador, todo */
function puede_modificar(array $u, array $alumno): bool
{
    return es_admin($u) || (int)$alumno['creado_por'] === (int)$u['id'];
}

// ---------- Protección CSRF para todos los formularios ----------

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function verificar_csrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        flash('La página expiró. Intenta de nuevo.', 'error');
        redirigir($_SERVER['REQUEST_URI'] ?? 'index.php');
    }
}

// ---------- Mensajes entre páginas ----------

function flash(string $mensaje, string $tipo = 'ok'): void
{
    $_SESSION['flash'] = [$mensaje, $tipo];
}

function tomar_flash(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

// ---------- Bitácora ----------

function bitacora(?int $usuario_id, string $accion, string $descripcion, ?int $alumno_id = null): void
{
    $st = db()->prepare('INSERT INTO bitacora (usuario_id, accion, alumno_id, descripcion) VALUES (?, ?, ?, ?)');
    $st->execute([$usuario_id, $accion, $alumno_id, mb_substr($descripcion, 0, 1000)]);
}

// ---------- Validaciones ----------

const PATRON_NOMBRE = "/^[\\p{L}][\\p{L} .'-]{0,59}$/u";

function validar_nombre(string $valor, string $campo, bool $obligatorio, array &$errores): void
{
    if ($valor === '') {
        if ($obligatorio) {
            $errores[] = "Falta $campo.";
        }
    } elseif (!preg_match(PATRON_NOMBRE, $valor)) {
        $errores[] = "$campo solo puede tener letras, espacios, punto, guion o apóstrofo (máx. 60).";
    }
}

/** Limpia espacios repetidos de un campo enviado por formulario */
function campo(string $nombre): string
{
    return trim(preg_replace('/\s+/u', ' ', (string)($_POST[$nombre] ?? '')));
}
