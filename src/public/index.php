<?php
// Sistema de Tutorías - UPSLP
// Conexión a la base de datos MariaDB que corre dentro del mismo contenedor
$host = getenv('DB_HOST') ?: '127.0.0.1';
$db   = getenv('DB_NAME') ?: 'tutorias';
$user = getenv('DB_USER') ?: 'tutorias';
$pass = getenv('DB_PASS') ?: 'tutorias123';

$mensaje = '';
$tipo = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    die('Error al conectar con la base de datos: ' . htmlspecialchars($e->getMessage()));
}

// Eliminar registro
if (isset($_POST['eliminar'])) {
    $stmt = $pdo->prepare('DELETE FROM alumnos WHERE id = ?');
    $stmt->execute([(int)$_POST['eliminar']]);
    $mensaje = 'Registro eliminado.';
    $tipo = 'ok';
}
// Guardar registro
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre    = trim($_POST['nombre_alumno'] ?? '');
    $matricula = trim($_POST['matricula'] ?? '');
    $tutor     = trim($_POST['nombre_tutor'] ?? '');
    $correo    = trim($_POST['correo_alumno'] ?? '');

    if ($nombre === '' || $matricula === '' || $tutor === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $mensaje = 'Llena todos los campos con datos válidos.';
        $tipo = 'error';
    } else {
        try {
            $stmt = $pdo->prepare('INSERT INTO alumnos (nombre_alumno, matricula, nombre_tutor, correo_alumno) VALUES (?, ?, ?, ?)');
            $stmt->execute([$nombre, $matricula, $tutor, $correo]);
            $mensaje = 'Alumno registrado correctamente.';
            $tipo = 'ok';
        } catch (PDOException $e) {
            $mensaje = ($e->errorInfo[1] ?? 0) == 1062 ? 'Esa matrícula ya está registrada.' : 'Error al guardar: ' . $e->getMessage();
            $tipo = 'error';
        }
    }
}

$alumnos = $pdo->query('SELECT * FROM alumnos ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sistema de Tutorías · UPSLP</title>
<link rel="stylesheet" href="estilos.css">
</head>
<body>
<header class="institucional">
    <h1>UNIVERSIDAD POLITÉCNICA DE SAN LUIS POTOSÍ</h1>
    <h2>SISTEMA DE TUTORÍAS</h2>
    <h3>MATERIA DE SISTEMAS VIRTUALES</h3>
</header>

<main>
    <section class="tarjeta">
        <h4>Registrar alumno</h4>
        <?php if ($mensaje): ?>
            <p class="mensaje <?= $tipo ?>"><?= e($mensaje) ?></p>
        <?php endif; ?>
        <form method="post">
            <div class="rejilla">
                <label>Nombre del alumno <input type="text" name="nombre_alumno" maxlength="100" required></label>
                <label>Matrícula <input type="text" name="matricula" maxlength="20" required></label>
                <label>Nombre del tutor <input type="text" name="nombre_tutor" maxlength="100" required></label>
                <label>Correo electrónico del alumno <input type="email" name="correo_alumno" maxlength="100" required></label>
            </div>
            <div class="botones"><button type="submit">Guardar</button></div>
        </form>
    </section>

    <section class="tarjeta">
        <h4>Alumnos registrados (<?= count($alumnos) ?>)</h4>
        <div class="tabla">
        <table>
            <thead>
                <tr><th>#</th><th>Nombre del alumno</th><th>Matrícula</th><th>Nombre del tutor</th><th>Correo</th><th>Registro</th><th></th></tr>
            </thead>
            <tbody>
            <?php if (!$alumnos): ?>
                <tr><td colspan="7" class="vacio">Aún no hay alumnos registrados.</td></tr>
            <?php endif; ?>
            <?php foreach ($alumnos as $a): ?>
                <tr>
                    <td><?= e($a['id']) ?></td>
                    <td><?= e($a['nombre_alumno']) ?></td>
                    <td><?= e($a['matricula']) ?></td>
                    <td><?= e($a['nombre_tutor']) ?></td>
                    <td><?= e($a['correo_alumno']) ?></td>
                    <td><?= e($a['fecha_registro']) ?></td>
                    <td class="acciones">
                        <form method="post" onsubmit="return confirm('¿Eliminar este registro?');">
                            <button class="chico peligro" name="eliminar" value="<?= e($a['id']) ?>">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </section>
</main>

<footer>UPSLP · Sistemas Virtuales · Proyecto final con Docker</footer>
</body>
</html>
