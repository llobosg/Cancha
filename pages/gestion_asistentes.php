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
    $stmt = $pdo->prepare("SELECT * FROM admin_recintos WHERE id_recinto = ? AND rol = 'asistente' ORDER BY nombre ASC");
    $stmt->execute([$id_recinto]);
    $asistentes = $stmt->fetchAll();
} catch (Exception $e) {
    die("Error DB: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestionar Asistentes</title>
    <style>
        body { font-family: sans-serif; padding: 2rem; background: #f4f4f4; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 10px; border-bottom: 1px solid #ddd; text-align: left; }
        th { background: #071289; color: white; }
        .btn { padding: 5px 10px; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px; }
        .btn-delete { background: #f44336; }
    </style>
</head>
<body>
    <div class="container">
        <h2>👥 Gestionar Asistentes</h2>
        <a href="recinto_dashboard.php" style="display:inline-block; margin-bottom:1rem; color:#071289;">← Volver al Dashboard</a>
        
        <?php if (empty($asistentes)): ?>
            <p>No hay asistentes registrados.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($asistentes as $a): ?>
                    <tr>
                        <td><?= htmlspecialchars($a['nombre']) ?></td>
                        <td><?= htmlspecialchars($a['email']) ?></td>
                        <td><?= htmlspecialchars($a['rol']) ?></td>
                        <td>
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