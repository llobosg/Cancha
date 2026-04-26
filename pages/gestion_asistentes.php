<?php
// pages/gestion_asistentes.php

// 1. Incluir config PRIMERO (inicia sesión automáticamente)
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/permisos.php';

// 2. Logging para debug (ver en Railway)
error_log("[GestionAsistentes] === INICIO ===");
error_log("[GestionAsistentes] Sesión: id_recinto=" . ($_SESSION['id_recinto'] ?? 'NO SET') . ", rol=" . ($_SESSION['recinto_rol'] ?? 'NO SET'));

// 3. Verificación de seguridad
if (!estaAutenticado() || $_SESSION['recinto_rol'] !== 'admin') {
    error_log("[GestionAsistentes] ❌ Acceso denegado");
    header('Location: recinto_dashboard.php');
    exit;
}

$id_recinto = (int)$_SESSION['id_recinto'];
$nombre_recinto = $_SESSION['nombre_recinto'] ?? 'Recinto Deportivo';

// 4. Obtener asistentes
try {
    $stmt = $pdo->prepare("
        SELECT id_admin, usuario, nombre_completo, email, telefono, created_at, rol
        FROM admin_recintos
        WHERE id_recinto = ? AND rol = 'asistente'
        ORDER BY created_at DESC
    ");
    $stmt->execute([$id_recinto]);
    $asistentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("[GestionAsistentes] ✅ " . count($asistentes) . " asistentes encontrados");
} catch (Exception $e) {
    error_log("[GestionAsistentes] ❌ Error: " . $e->getMessage());
    $asistentes = [];
}
?>
<!-- ... el resto del HTML se mantiene igual ... -->