<?php
require __DIR__ . '/../inc/config.php';
require __DIR__ . '/../inc/plantilla.php';

$u = requiere_login();
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $actual = (string)($_POST['actual'] ?? '');
    $nueva = (string)($_POST['nueva'] ?? '');
    $confirmar = (string)($_POST['confirmar'] ?? '');

    $st = db()->prepare('SELECT password_hash FROM usuarios WHERE id = ?');
    $st->execute([$u['id']]);
    if (!password_verify($actual, (string)$st->fetchColumn())) {
        $errores[] = 'La contraseña actual no es correcta.';
    }
    if (mb_strlen($nueva) < 8) {
        $errores[] = 'La nueva contraseña debe tener al menos 8 caracteres.';
    }
    if ($nueva !== $confirmar) {
        $errores[] = 'La confirmación no coincide con la nueva contraseña.';
    }

    if (!$errores) {
        db()->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($nueva, PASSWORD_DEFAULT), $u['id']]);
        bitacora((int)$u['id'], 'cambio_contrasena', 'El usuario ' . $u['usuario'] . ' cambió su contraseña');
        flash('Tu contraseña se cambió correctamente.');
        redirigir('cuenta.php');
    }
}

$st = db()->prepare('SELECT COUNT(*) FROM alumnos WHERE creado_por = ?');
$st->execute([$u['id']]);
$mis_registros = (int)$st->fetchColumn();

encabezado('Mi cuenta', $u, 'cuenta');
?>
<section class="tarjeta">
    <h4>Mi cuenta</h4>
    <dl class="datos">
        <dt>Nombre</dt><dd><?= e(nombre_completo($u)) ?></dd>
        <dt>Usuario</dt><dd><?= e($u['usuario']) ?></dd>
        <dt>Rol</dt><dd><?= $u['rol'] === 'admin' ? 'Administrador' : 'Capturista' ?></dd>
        <dt>Alumnos que he registrado</dt><dd><a href="index.php?usuario=<?= (int)$u['id'] ?>"><?= $mis_registros ?></a></dd>
    </dl>
</section>

<section class="tarjeta">
    <h4>Cambiar contraseña</h4>
    <?php lista_errores($errores); ?>
    <form method="post" class="vertical angosto">
        <?= csrf_campo() ?>
        <label>Contraseña actual
            <input type="password" name="actual" autocomplete="current-password" required>
        </label>
        <label>Nueva contraseña (mínimo 8 caracteres)
            <input type="password" name="nueva" minlength="8" autocomplete="new-password" required>
        </label>
        <label>Confirmar nueva contraseña
            <input type="password" name="confirmar" minlength="8" autocomplete="new-password" required>
        </label>
        <button type="submit">Cambiar contraseña</button>
    </form>
</section>
<?php
pie();
