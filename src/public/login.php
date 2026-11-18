<?php
require __DIR__ . '/../inc/config.php';
require __DIR__ . '/../inc/plantilla.php';

if (usuario_actual()) {
    redirigir('index.php');
}

$error = '';
$usuario = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $usuario = trim((string)($_POST['usuario'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    $st = db()->prepare('SELECT id, password_hash, activo FROM usuarios WHERE usuario = ?');
    $st->execute([$usuario]);
    $fila = $st->fetch();

    if ($fila && $fila['activo'] && password_verify($password, $fila['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$fila['id'];
        bitacora((int)$fila['id'], 'inicio_sesion', "Inició sesión el usuario $usuario");
        redirigir('index.php');
    }

    $error = ($fila && !$fila['activo'])
        ? 'Este usuario está desactivado. Pide al administrador que lo active.'
        : 'Usuario o contraseña incorrectos.';
    bitacora(null, 'intento_fallido', 'Intento de inicio de sesión fallido con el usuario "' . $usuario . '"');
}

encabezado('Iniciar sesión', null);
?>
<section class="tarjeta login">
    <h4>Iniciar sesión</h4>
    <?php if ($error): ?><p class="mensaje error"><?= e($error) ?></p><?php endif; ?>
    <form method="post" class="vertical">
        <?= csrf_campo() ?>
        <label>Usuario
            <input type="text" name="usuario" value="<?= e($usuario) ?>" autocomplete="username" required autofocus>
        </label>
        <label>Contraseña
            <input type="password" name="password" autocomplete="current-password" required>
        </label>
        <button type="submit">Entrar</button>
    </form>
</section>
<?php
pie();
