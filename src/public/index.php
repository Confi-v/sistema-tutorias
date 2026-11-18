<?php
// Alumnos: alta, edición, baja, búsqueda y quién registró / editó cada uno
require __DIR__ . '/../inc/config.php';
require __DIR__ . '/../inc/plantilla.php';

$u = requiere_login();
$pdo = db();

// Campos del formulario => etiqueta (para mensajes y bitácora)
const CAMPOS = [
    'nombre'                 => 'Nombre(s) del alumno',
    'apellido_paterno'       => 'Apellido paterno del alumno',
    'apellido_materno'       => 'Apellido materno del alumno',
    'matricula'              => 'Matrícula',
    'correo'                 => 'Correo electrónico',
    'tutor_nombre'           => 'Nombre(s) del tutor',
    'tutor_apellido_paterno' => 'Apellido paterno del tutor',
    'tutor_apellido_materno' => 'Apellido materno del tutor',
];

function buscar_alumno(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM alumnos WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

$errores = [];
$form = array_fill_keys(array_keys(CAMPOS), '');
$editando = null;   // alumno que se está editando

// ---------------- Acciones (POST) ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'eliminar') {
        $alumno = buscar_alumno((int)($_POST['id'] ?? 0));
        if (!$alumno) {
            flash('Ese alumno ya no existe.', 'error');
        } elseif (!puede_modificar($u, $alumno)) {
            flash('Solo puedes eliminar los alumnos que tú registraste.', 'error');
        } else {
            $pdo->prepare('DELETE FROM alumnos WHERE id = ?')->execute([$alumno['id']]);
            bitacora((int)$u['id'], 'baja', 'Eliminó al alumno ' . nombre_completo($alumno)
                . ' (matrícula ' . $alumno['matricula'] . ', correo ' . $alumno['correo']
                . ', tutor ' . nombre_completo($alumno, 'tutor_') . ')', (int)$alumno['id']);
            flash('Se eliminó a ' . nombre_completo($alumno) . '.');
        }
        redirigir('index.php');
    }

    if ($accion === 'guardar') {
        foreach (CAMPOS as $clave => $etiqueta) {
            $form[$clave] = campo($clave);
        }
        $form['matricula'] = strtoupper($form['matricula']);
        $form['correo'] = strtolower($form['correo']);
        $id = (int)($_POST['id'] ?? 0);

        if ($id) {
            $editando = buscar_alumno($id);
            if (!$editando) {
                flash('Ese alumno ya no existe.', 'error');
                redirigir('index.php');
            }
            if (!puede_modificar($u, $editando)) {
                flash('Solo puedes editar los alumnos que tú registraste.', 'error');
                redirigir('index.php');
            }
        }

        // Validaciones
        validar_nombre($form['nombre'], 'el nombre del alumno', true, $errores);
        validar_nombre($form['apellido_paterno'], 'el apellido paterno del alumno', true, $errores);
        validar_nombre($form['apellido_materno'], 'el apellido materno del alumno', false, $errores);
        if (!preg_match('/^[A-Z0-9-]{4,20}$/', $form['matricula'])) {
            $errores[] = 'La matrícula debe tener de 4 a 20 letras, números o guiones.';
        }
        if (!filter_var($form['correo'], FILTER_VALIDATE_EMAIL) || strlen($form['correo']) > 100) {
            $errores[] = 'El correo electrónico no es válido.';
        }
        validar_nombre($form['tutor_nombre'], 'el nombre del tutor', true, $errores);
        validar_nombre($form['tutor_apellido_paterno'], 'el apellido paterno del tutor', true, $errores);
        validar_nombre($form['tutor_apellido_materno'], 'el apellido materno del tutor', false, $errores);

        if (!$errores) {
            $valores = array_map(fn($v) => $v === '' ? null : $v, $form);
            try {
                if ($editando) {
                    // Qué cambió, para la bitácora
                    $cambios = [];
                    foreach (CAMPOS as $clave => $etiqueta) {
                        $antes = (string)($editando[$clave] ?? '');
                        if ($antes !== $form[$clave]) {
                            $cambios[] = "$etiqueta: \"$antes\" → \"{$form[$clave]}\"";
                        }
                    }
                    if (!$cambios) {
                        flash('No hubo cambios que guardar.', 'info');
                        redirigir('index.php');
                    }
                    $sql = 'UPDATE alumnos SET ' . implode(', ', array_map(fn($c) => "$c = ?", array_keys(CAMPOS)))
                         . ', modificado_por = ?, modificado_en = NOW() WHERE id = ?';
                    $pdo->prepare($sql)->execute([...array_values($valores), $u['id'], $editando['id']]);
                    bitacora((int)$u['id'], 'edicion', 'Editó al alumno ' . nombre_completo($form)
                        . ' (matrícula ' . $form['matricula'] . '). ' . implode('; ', $cambios), (int)$editando['id']);
                    flash('Se guardaron los cambios de ' . nombre_completo($form) . '.');
                } else {
                    $sql = 'INSERT INTO alumnos (' . implode(', ', array_keys(CAMPOS)) . ', creado_por) VALUES ('
                         . str_repeat('?, ', count(CAMPOS)) . '?)';
                    $pdo->prepare($sql)->execute([...array_values($valores), $u['id']]);
                    $nuevo = (int)$pdo->lastInsertId();
                    bitacora((int)$u['id'], 'alta', 'Registró al alumno ' . nombre_completo($form)
                        . ' (matrícula ' . $form['matricula'] . ', tutor ' . nombre_completo($form, 'tutor_') . ')', $nuevo);
                    flash('Se registró a ' . nombre_completo($form) . '.');
                }
                redirigir('index.php');
            } catch (PDOException $ex) {
                if (($ex->errorInfo[1] ?? 0) == 1062) {
                    $errores[] = 'La matrícula ' . $form['matricula'] . ' ya está registrada.';
                } else {
                    throw $ex;
                }
            }
        }
    }
}

// ---------------- Cargar alumno a editar (GET ?editar=ID) ----------------
if (!$editando && isset($_GET['editar'])) {
    $editando = buscar_alumno((int)$_GET['editar']);
    if (!$editando) {
        flash('Ese alumno ya no existe.', 'error');
        redirigir('index.php');
    }
    if (!puede_modificar($u, $editando)) {
        flash('Solo puedes editar los alumnos que tú registraste.', 'error');
        redirigir('index.php');
    }
    foreach (CAMPOS as $clave => $etiqueta) {
        $form[$clave] = (string)($editando[$clave] ?? '');
    }
}

// ---------------- Búsqueda y filtros ----------------
$q = trim((string)($_GET['q'] ?? ''));
$filtro_usuario = (int)($_GET['usuario'] ?? 0);

$where = [];
$params = [];
if ($q !== '') {
    $where[] = "(CONCAT_WS(' ', a.nombre, a.apellido_paterno, a.apellido_materno) LIKE ?
              OR CONCAT_WS(' ', a.tutor_nombre, a.tutor_apellido_paterno, a.tutor_apellido_materno) LIKE ?
              OR a.matricula LIKE ? OR a.correo LIKE ?)";
    array_push($params, "%$q%", "%$q%", "%$q%", "%$q%");
}
if ($filtro_usuario) {
    $where[] = 'a.creado_por = ?';
    $params[] = $filtro_usuario;
}

$sql = "SELECT a.*, c.usuario AS creador, m.usuario AS modificador
        FROM alumnos a
        LEFT JOIN usuarios c ON c.id = a.creado_por
        LEFT JOIN usuarios m ON m.id = a.modificado_por"
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . ' ORDER BY a.apellido_paterno, a.apellido_materno, a.nombre';
$st = $pdo->prepare($sql);
$st->execute($params);
$alumnos = $st->fetchAll();

$total = (int)$pdo->query('SELECT COUNT(*) FROM alumnos')->fetchColumn();
$usuarios = $pdo->query('SELECT id, usuario FROM usuarios ORDER BY usuario')->fetchAll();

encabezado('Alumnos', $u, 'alumnos');
?>
<section class="tarjeta" id="formulario">
    <h4><?= $editando ? 'Editar alumno · ' . e(nombre_completo($editando)) : 'Registrar alumno' ?></h4>
    <?php lista_errores($errores); ?>
    <form method="post" action="index.php">
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="guardar">
        <?php if ($editando): ?><input type="hidden" name="id" value="<?= (int)$editando['id'] ?>"><?php endif; ?>

        <fieldset>
            <legend>Datos del alumno</legend>
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
                <label>Matrícula *
                    <input type="text" name="matricula" value="<?= e($form['matricula']) ?>" maxlength="20" required>
                </label>
                <label>Correo electrónico *
                    <input type="email" name="correo" value="<?= e($form['correo']) ?>" maxlength="100" required>
                </label>
            </div>
        </fieldset>

        <fieldset>
            <legend>Tutor asignado</legend>
            <div class="rejilla">
                <label>Nombre(s) *
                    <input type="text" name="tutor_nombre" value="<?= e($form['tutor_nombre']) ?>" maxlength="60" required>
                </label>
                <label>Apellido paterno *
                    <input type="text" name="tutor_apellido_paterno" value="<?= e($form['tutor_apellido_paterno']) ?>" maxlength="60" required>
                </label>
                <label>Apellido materno
                    <input type="text" name="tutor_apellido_materno" value="<?= e($form['tutor_apellido_materno']) ?>" maxlength="60">
                </label>
            </div>
        </fieldset>

        <div class="botones">
            <button type="submit"><?= $editando ? 'Guardar cambios' : 'Registrar alumno' ?></button>
            <?php if ($editando): ?><a class="boton secundario" href="index.php">Cancelar</a><?php endif; ?>
            <span class="nota">* Campo obligatorio</span>
        </div>
    </form>
</section>

<section class="tarjeta">
    <h4>Alumnos registrados (<?= count($alumnos) ?><?= count($alumnos) !== $total ? " de $total" : '' ?>)</h4>

    <form method="get" class="filtros">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar por nombre, matrícula, correo o tutor">
        <select name="usuario">
            <option value="">Registrados por cualquier usuario</option>
            <?php foreach ($usuarios as $us): ?>
                <option value="<?= (int)$us['id'] ?>" <?= $filtro_usuario === (int)$us['id'] ? 'selected' : '' ?>>Registrados por <?= e($us['usuario']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Buscar</button>
        <?php if ($q !== '' || $filtro_usuario): ?><a class="boton secundario" href="index.php">Limpiar</a><?php endif; ?>
    </form>

    <div class="tabla">
    <table>
        <thead>
            <tr>
                <th>Alumno</th><th>Matrícula</th><th>Correo</th><th>Tutor</th>
                <th>Registró</th><th>Última edición</th><th></th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$alumnos): ?>
            <tr><td colspan="7" class="vacio"><?= $total ? 'Ningún alumno coincide con la búsqueda.' : 'Aún no hay alumnos registrados.' ?></td></tr>
        <?php endif; ?>
        <?php foreach ($alumnos as $a): ?>
            <tr class="<?= $editando && (int)$editando['id'] === (int)$a['id'] ? 'editando' : '' ?>">
                <td><?= e(nombre_completo($a)) ?></td>
                <td><?= e($a['matricula']) ?></td>
                <td><?= e($a['correo']) ?></td>
                <td><?= e(nombre_completo($a, 'tutor_')) ?></td>
                <td><strong><?= e($a['creador'] ?? 'migrado') ?></strong><br><small><?= e(date('d/m/Y H:i', strtotime($a['creado_en']))) ?></small></td>
                <td><?php if ($a['modificado_en']): ?><strong><?= e($a['modificador'] ?? '') ?></strong><br><small><?= e(date('d/m/Y H:i', strtotime($a['modificado_en']))) ?></small><?php else: ?><span class="nota">Sin cambios</span><?php endif; ?></td>
                <td class="acciones">
                    <?php if (puede_modificar($u, $a)): ?>
                        <a class="boton chico" href="index.php?editar=<?= (int)$a['id'] ?>#formulario">Editar</a>
                        <form method="post" onsubmit="return confirm(<?= e(json_encode('¿Eliminar a ' . nombre_completo($a) . '?', JSON_UNESCAPED_UNICODE)) ?>);">
                            <?= csrf_campo() ?>
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                            <button class="chico peligro">Eliminar</button>
                        </form>
                    <?php else: ?>
                        <span class="nota">Solo lectura</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</section>
<?php
pie();
