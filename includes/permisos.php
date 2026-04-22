<?php
    // includes/permisos.php

    function tienePermiso($permiso) {
        if (!isset($_SESSION['recinto_rol'])) return false;
        
        $rol = $_SESSION['recinto_rol'];
        
        // Definición de permisos por rol
        $permisos_admin = [
            'ver_finanzas', 'gestionar_asistentes', 'ver_todos_datos', 
            'calendario', 'crear_canchas', 'reserva_manual', 'torneos', 'ocupacion'
        ];
        
        $permisos_asistente = [
            'calendario', 'crear_canchas', 'reserva_manual', 'torneos', 'ocupacion'
            // NOTA: NO incluye 'ver_finanzas' ni 'gestionar_asistentes'
        ];
        
        if ($rol === 'admin_recinto') {
            return in_array($permiso, $permisos_admin);
        } elseif ($rol === 'asistente_recinto') {
            return in_array($permiso, $permisos_asistente);
        }
        
        return false;
    }

    function esAdmin() {
        return isset($_SESSION['recinto_rol']) && $_SESSION['recinto_rol'] === 'admin_recinto';
    }

    function esAsistente() {
        return isset($_SESSION['recinto_rol']) && $_SESSION['recinto_rol'] === 'asistente_recinto';
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