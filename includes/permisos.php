<?php
// includes/permisos.php

function esAdmin() {
    return isset($_SESSION['recinto_rol']) && $_SESSION['recinto_rol'] === 'admin';
}

function esAsistente() {
    return isset($_SESSION['recinto_rol']) && $_SESSION['recinto_rol'] === 'asistente';
}

function requiereAdmin() {
    if (!esAdmin()) {
        // Opcional: Mostrar mensaje de error o redirigir
        // header('Location: recinto_dashboard.php?error=acceso_denegado');
        return false;
    }
    return true;
}
?>