<?php
// Bitácora: quién hizo qué y cuándo (solo administradores)
require __DIR__ . '/../inc/config.php';
require __DIR__ . '/../inc/plantilla.php';

$u = requiere_admin();
$pdo = db();

const ACCIONES = [
    'alta'               => 'Alta de alumno',
    'edicion'            => 'Edición de alumno',
    'baja'               => 'Baja de alumno',
    'inicio_sesion'      => 'Inicio de sesión',
    'cierre_sesion'      => 'Cierre de sesión',
    'intento_fallido'    => 'Intento de acceso fallido',
    'cambio_contrasena'  => 'Cambio de contraseña',
    'usuario_alta'       => 'Alta de usuario',
    'usuario_estado'     => 'Activar / desactivar usuario',
    'usuario_rol'        => 'Cambio de rol',
    'usuario_contrasena' => 'Contraseña asignada',
    'migracion'          => 'Migración de datos',
];

$filtro_usuario = (int)($_GET['usuario'] ?? 0);
$filtro_accion = (string)($_GET['accion'] ?? '');
$q = trim((string)($_GET['q'] ?? ''));

$where = [];
$params = [];
if ($filtro_usuario) {
    $where[] = 'b.usuario_id = ?';
    $params[] = $filtro_usuario;
}
if (isset(ACCIONES[$filtro_accion])) {
    $where[] = 'b.accion = ?';
    $params[] = $filtro_accion;
}
if ($q !== '') {
    $where[] = 'b.descripcion LIKE ?';
    $params[] = "%$q%";
}

$st = $pdo->prepare('SELECT b.*, us.usuario FROM bitacora b LEFT JOIN usuarios us ON us.id = b.usuario_id'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
    . ' ORDER BY b.fecha DESC, b.id DESC LIMIT 500');
$st->execute($params);
$eventos = $st->fetchAll();
$usuarios = $pdo->query('SELECT id, usuario FROM usuarios ORDER BY usuario')->fetchAll();

encabezado('Bitácora', $u, 'bitacora');
?>
<section class="tarjeta">
    <h4>Bitácora de movimientos</h4>
    <form method="get" class="filtros">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar en la descripción (ej. una matrícula)">
        <select name="usuario">
            <option value="">Todos los usuarios</option>
            <?php foreach ($usuarios as $us): ?>
                <option value="<?= (int)$us['id'] ?>" <?= $filtro_usuario === (int)$us['id'] ? 'selected' : '' ?>><?= e($us['usuario']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="accion">
            <option value="">Todas las acciones</option>
            <?php foreach (ACCIONES as $clave => $etiqueta): ?>
                <option value="<?= e($clave) ?>" <?= $filtro_accion === $clave ? 'selected' : '' ?>><?= e($etiqueta) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Filtrar</button>
        <?php if ($where): ?><a class="boton secundario" href="bitacora.php">Limpiar</a><?php endif; ?>
    </form>

    <div class="tabla">
    <table>
        <thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Descripción</th></tr></thead>
        <tbody>
        <?php if (!$eventos): ?>
            <tr><td colspan="4" class="vacio">No hay movimientos con esos filtros.</td></tr>
        <?php endif; ?>
        <?php foreach ($eventos as $ev): ?>
            <tr>
                <td class="sin-salto"><?= e(date('d/m/Y H:i:s', strtotime($ev['fecha']))) ?></td>
                <td><?= $ev['usuario'] ? '<strong>' . e($ev['usuario']) . '</strong>' : '<span class="nota">Sistema</span>' ?></td>
                <td><span class="accion accion-<?= e($ev['accion']) ?>"><?= e(ACCIONES[$ev['accion']] ?? $ev['accion']) ?></span></td>
                <td><?= e($ev['descripcion']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <p class="nota">Se muestran los 500 movimientos más recientes que coinciden con los filtros.</p>
</section>
<?php
pie();
