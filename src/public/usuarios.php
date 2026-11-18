<?php
// Administración de usuarios (solo administradores)
require __DIR__ . '/../inc/config.php';
require __DIR__ . '/../inc/plantilla.php';

$u = requiere_admin();
$pdo = db();
$errores = [];
$form = ['nombre' => '', 'apellido_paterno' => '', 'apellido_materno' => '', 'usuario' => '', 'rol' => 'capturista'];

function buscar_usuario(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM usuarios WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        foreach (array_keys($form) as $clave) {
            $form[$clave] = campo($clave);
        }
        $form['usuario'] = strtolower($form['usuario']);
        $password = (string)($_POST['password'] ?? '');

        validar_nombre($form['nombre'], 'el nombre', true, $errores);
        validar_nombre($form['apellido_paterno'], 'el apellido paterno', true, $errores);
        validar_nombre($form['apellido_materno'], 'el apellido materno', false, $errores);
        if (!preg_match('/^[a-z0-9._-]{3,30}$/', $form['usuario'])) {
            $errores[] = 'El usuario debe tener de 3 a 30 letras minúsculas, números, punto, guion o guion bajo (sin espacios ni acentos).';
        }
        if (mb_strlen($password) < 8) {
            $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
        }
        if (!in_array($form['rol'], ['admin', 'capturista'], true)) {
            $errores[] = 'Rol no válido.';
        }

        if (!$errores) {
            try {
                $pdo->prepare('INSERT INTO usuarios (nombre, apellido_paterno, apellido_materno, usuario, password_hash, rol)
                               VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([$form['nombre'], $form['apellido_paterno'], $form['apellido_materno'] ?: null,
                               $form['usuario'], password_hash($password, PASSWORD_DEFAULT), $form['rol']]);
                bitacora((int)$u['id'], 'usuario_alta', 'Creó el usuario ' . $form['usuario'] . ' (' . nombre_completo($form)
                    . ', rol ' . $form['rol'] . ')');
                flash('Se creó el usuario ' . $form['usuario'] . '.');
                redirigir('usuarios.php');
            } catch (PDOException $ex) {
                if (($ex->errorInfo[1] ?? 0) == 1062) {
                    $errores[] = 'El usuario "' . $form['usuario'] . '" ya existe.';
                } else {
                    throw $ex;
                }
            }
        }
    } else {
        $objetivo = buscar_usuario((int)($_POST['id'] ?? 0));
        if (!$objetivo) {
            flash('Ese usuario no existe.', 'error');
            redirigir('usuarios.php');
        }
        $es_yo = (int)$objetivo['id'] === (int)$u['id'];

        if ($accion === 'estado') {
            if ($es_yo) {
                flash('No puedes desactivar tu propio usuario.', 'error');
            } else {
                $nuevo = $objetivo['activo'] ? 0 : 1;
                $pdo->prepare('UPDATE usuarios SET activo = ? WHERE id = ?')->execute([$nuevo, $objetivo['id']]);
                bitacora((int)$u['id'], 'usuario_estado', ($nuevo ? 'Activó' : 'Desactivó') . ' el usuario ' . $objetivo['usuario']);
                flash('El usuario ' . $objetivo['usuario'] . ($nuevo ? ' se activó.' : ' se desactivó. Sus registros se conservan.'));
            }
        } elseif ($accion === 'rol') {
            $rol = $objetivo['rol'] === 'admin' ? 'capturista' : 'admin';
            if ($es_yo) {
                flash('No puedes cambiar tu propio rol.', 'error');
            } else {
                $pdo->prepare('UPDATE usuarios SET rol = ? WHERE id = ?')->execute([$rol, $objetivo['id']]);
                bitacora((int)$u['id'], 'usuario_rol', 'Cambió el rol de ' . $objetivo['usuario'] . ' a ' . $rol);
                flash('Ahora ' . $objetivo['usuario'] . ' es ' . ($rol === 'admin' ? 'administrador.' : 'capturista.'));
            }
        } elseif ($accion === 'contrasena') {
            $password = (string)($_POST['password'] ?? '');
            if (mb_strlen($password) < 8) {
                flash('La nueva contraseña debe tener al menos 8 caracteres.', 'error');
            } else {
                $pdo->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($password, PASSWORD_DEFAULT), $objetivo['id']]);
                bitacora((int)$u['id'], 'usuario_contrasena', 'Asignó una nueva contraseña al usuario ' . $objetivo['usuario']);
                flash('Se cambió la contraseña de ' . $objetivo['usuario'] . '.');
            }
        }
        redirigir('usuarios.php');
    }
}

$usuarios = $pdo->query('SELECT us.*, (SELECT COUNT(*) FROM alumnos a WHERE a.creado_por = us.id) AS registros,
                                (SELECT MAX(fecha) FROM bitacora b WHERE b.usuario_id = us.id AND b.accion = \'inicio_sesion\') AS ultimo_acceso
                         FROM usuarios us ORDER BY us.activo DESC, us.usuario')->fetchAll();

encabezado('Usuarios', $u, 'usuarios');
?>
<section class="tarjeta">
    <h4>Nuevo usuario</h4>
    <?php lista_errores($errores); ?>
    <form method="post">
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="crear">
        <div class="rejilla">
            <label>Nombre(s) *
                <input type="text" name="nombre" value="<?= e($form['nombre']) ?>" maxlength="60" required>
            </label>
            <label>Apellido paterno *
                <input type="text" name="apellido_paterno" value="<?= e($form['apellido_paterno']) ?>" maxlength="60" required>
            </label>
            <label>Apellido materno
                <input type="text" name="apellido_materno" value="<?= e($form['apellido_materno']) ?>" maxlength="60">
            </label>
            <label>Usuario *
                <input type="text" name="usuario" value="<?= e($form['usuario']) ?>" maxlength="30" pattern="[A-Za-z0-9._\-]{3,30}" autocomplete="off" required>
            </label>
            <label>Contraseña * (mínimo 8)
                <input type="password" name="password" minlength="8" autocomplete="new-password" required>
            </label>
            <label>Rol
                <select name="rol">
                    <option value="capturista" <?= $form['rol'] === 'capturista' ? 'selected' : '' ?>>Capturista</option>
                    <option value="admin" <?= $form['rol'] === 'admin' ? 'selected' : '' ?>>Administrador</option>
                </select>
            </label>
        </div>
        <div class="botones"><button type="submit">Crear usuario</button></div>
    </form>
</section>

<section class="tarjeta">
    <h4>Usuarios (<?= count($usuarios) ?>)</h4>
    <div class="tabla">
    <table>
        <thead>
            <tr><th>Usuario</th><th>Nombre</th><th>Rol</th><th>Estado</th><th>Alumnos registrados</th><th>Último acceso</th><th>Acciones</th></tr>
        </thead>
        <tbody>
        <?php foreach ($usuarios as $us): $es_yo = (int)$us['id'] === (int)$u['id']; ?>
            <tr class="<?= $us['activo'] ? '' : 'inactivo' ?>">
                <td><strong><?= e($us['usuario']) ?></strong><?= $es_yo ? ' <span class="nota">(tú)</span>' : '' ?></td>
                <td><?= e(nombre_completo($us)) ?></td>
                <td><span class="rol rol-<?= e($us['rol']) ?>"><?= $us['rol'] === 'admin' ? 'Administrador' : 'Capturista' ?></span></td>
                <td><?= $us['activo'] ? 'Activo' : 'Desactivado' ?></td>
                <td><a href="index.php?usuario=<?= (int)$us['id'] ?>"><?= (int)$us['registros'] ?></a></td>
                <td><?= $us['ultimo_acceso'] ? e(date('d/m/Y H:i', strtotime($us['ultimo_acceso']))) : '<span class="nota">Nunca</span>' ?></td>
                <td class="acciones">
                    <?php if (!$es_yo): ?>
                        <form method="post">
                            <?= csrf_campo() ?><input type="hidden" name="id" value="<?= (int)$us['id'] ?>">
                            <button class="chico secundario" name="accion" value="estado"><?= $us['activo'] ? 'Desactivar' : 'Activar' ?></button>
                            <button class="chico secundario" name="accion" value="rol"><?= $us['rol'] === 'admin' ? 'Hacer capturista' : 'Hacer admin' ?></button>
                        </form>
                        <form method="post" class="en-linea">
                            <?= csrf_campo() ?><input type="hidden" name="id" value="<?= (int)$us['id'] ?>">
                            <input type="hidden" name="accion" value="contrasena">
                            <input type="password" name="password" minlength="8" placeholder="Nueva contraseña" autocomplete="new-password" required>
                            <button class="chico">Cambiar</button>
                        </form>
                    <?php else: ?>
                        <a class="boton chico secundario" href="cuenta.php">Mi cuenta</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <p class="nota">El capturista registra alumnos y solo puede editar o eliminar los que él capturó. El administrador puede editar todo y además ve Usuarios y Bitácora.</p>
    <p class="nota">Los usuarios no se borran para no perder quién hizo cada registro; en su lugar se desactivan y ya no pueden entrar.</p>
</section>
<?php
pie();
