<?php
// pages/gestion_asistentes.php
require_once __DIR__ . '/../includes/config.php';

// Verificar permisos (solo admin)
if (!isset($_SESSION['id_recinto']) || $_SESSION['recinto_rol'] !== 'admin') {
    header('Location: login_recintos.php');
    exit;
}

$id_recinto = $_SESSION['id_recinto'];

// Obtener asistentes
try {
    // CORRECCIÓN: Ordenamos por 'email' en lugar de 'nombre' si 'nombre' no existe.
    // Si tu tabla tiene 'usuario' o 'alias', cámbialo aquí.
    $stmt = $pdo->prepare("SELECT * FROM admin_recintos WHERE id_recinto = ? AND rol = 'asistente' ORDER BY email ASC");
    $stmt->execute([$id_recinto]);
    $asistentes = $stmt->fetchAll();
} catch (Exception $e) {
    // Mostrar error detallado para debug
    die("Error DB: " . $e->getMessage() . "<br><small>Revisa si la columna 'email' existe en admin_recintos</small>");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestionar Asistentes</title>
    <style>
        body { font-family: sans-serif; padding: 2rem; background: #f4f4f4; color: #333; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { color: #071289; margin-top: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 12px; border-bottom: 1px solid #ddd; text-align: left; }
        th { background: #071289; color: white; }
        tr:hover { background: #f9f9f9; }
        .btn { padding: 6px 12px; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px; font-size: 0.9rem; }
        .btn-delete { background: #f44336; margin-left: 5px; }
        .btn-back { display: inline-block; margin-bottom: 1rem; color: #071289; text-decoration: none; font-weight: bold; }
        .empty-msg { text-align: center; color: #666; padding: 2rem; }
    </style>
</head>
<body>
    <div class="container">
        <a href="recinto_dashboard.php" class="btn-back">← Volver al Dashboard</a>
        <h2>👥 Gestionar Asistentes</h2>
        
        <?php if (empty($asistentes)): ?>
            <p class="empty-msg">No hay asistentes registrados para este recinto.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Email / Usuario</th>
                        <th>Rol</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($asistentes as $a): ?>
                    <tr>
                        <td><?= htmlspecialchars($a['id_admin']) ?></td>
                        <!-- Mostramos email si existe, sino id_admin -->
                        <td><?= htmlspecialchars($a['email'] ?? $a['usuario'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($a['rol']) ?></td>
                        <td>
                            <!-- Aquí podrías agregar un botón para eliminar o editar -->
                            <a href="#" class="btn btn-delete" onclick="alert('Función eliminar en desarrollo')">Eliminar</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>