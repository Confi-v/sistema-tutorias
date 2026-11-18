<?php
// Encabezado (título institucional y menú) y pie de página comunes

function encabezado(string $titulo, ?array $u, string $activo = ''): void
{
    $flash = tomar_flash();
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($titulo) ?> · Sistema de Tutorías UPSLP</title>
<link rel="stylesheet" href="estilos.css">
</head>
<body>
<header class="institucional">
    <h1>UNIVERSIDAD POLITÉCNICA DE SAN LUIS POTOSÍ</h1>
    <h2>SISTEMA DE TUTORÍAS</h2>
    <h3>MATERIA DE SISTEMAS VIRTUALES</h3>
</header>
<?php if ($u): ?>
<nav class="menu">
    <div class="enlaces">
        <a href="index.php" class="<?= $activo === 'alumnos' ? 'activo' : '' ?>">Alumnos</a>
        <?php if (es_admin($u)): ?>
            <a href="usuarios.php" class="<?= $activo === 'usuarios' ? 'activo' : '' ?>">Usuarios</a>
            <a href="bitacora.php" class="<?= $activo === 'bitacora' ? 'activo' : '' ?>">Bitácora</a>
        <?php endif; ?>
        <a href="cuenta.php" class="<?= $activo === 'cuenta' ? 'activo' : '' ?>">Mi cuenta</a>
    </div>
    <div class="sesion">
        <span><?= e(nombre_completo($u)) ?> · <strong><?= e($u['usuario']) ?></strong>
            <span class="rol rol-<?= e($u['rol']) ?>"><?= $u['rol'] === 'admin' ? 'Administrador' : 'Capturista' ?></span></span>
        <form method="post" action="logout.php">
            <?= csrf_campo() ?>
            <button type="submit" class="secundario">Cerrar sesión</button>
        </form>
    </div>
</nav>
<?php endif; ?>
<main>
<?php if ($flash): ?>
    <p class="mensaje <?= e($flash[1]) ?>"><?= e($flash[0]) ?></p>
<?php endif; ?>
<?php
}

function pie(): void
{
    ?>
</main>
<footer>UPSLP · Sistemas Virtuales · Proyecto final con Docker</footer>
</body>
</html>
<?php
}

/** Muestra una lista de errores de validación */
function lista_errores(array $errores): void
{
    if (!$errores) {
        return;
    }
    echo '<div class="mensaje error"><strong>Revisa lo siguiente:</strong><ul>';
    foreach ($errores as $err) {
        echo '<li>' . e($err) . '</li>';
    }
    echo '</ul></div>';
}
