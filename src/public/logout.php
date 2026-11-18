<?php
require __DIR__ . '/../inc/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $u = usuario_actual();
    if ($u) {
        bitacora((int)$u['id'], 'cierre_sesion', 'Cerró sesión el usuario ' . $u['usuario']);
    }
    $_SESSION = [];
    session_destroy();
}
redirigir('login.php');
