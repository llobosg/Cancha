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
        .header {
            display:flex;
            justify-content:space-between;
            align-items:center;
            margin-bottom:1rem;
        }

        .btn-primary {
            background:#071289;
            color:white;
            padding:10px 16px;
            border:none;
            border-radius:6px;
        }

        .tabla {
            width:100%;
            border-collapse:collapse;
        }

        .tabla th {
            background:#071289;
            color:white;
        }

        .tabla td {
            padding:10px;
        }

        .btn-edit {
            background:#2196F3;
            color:white;
            border:none;
            padding:6px 10px;
        }

        .btn-delete {
            background:#f44336;
            color:white;
            border:none;
            padding:6px 10px;
        }

        .modal {
            display:none;
            position:fixed;
            top:0;
            left:0;
            width:100%;
            height:100%;
            background:rgba(0,0,0,0.5);
            justify-content:center;
            align-items:center;
        }

        .modal-content {
            background:white;
            padding:20px;
            border-radius:8px;
            width:300px;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="recinto_dashboard.php" class="btn-back">← Volver al Dashboard</a>
        <div class="header">
            <h2>👥 Asistentes</h2>
            <button class="btn-primary" onclick="openModal()">+ Nuevo Asistente</button>
        </div>
        
        <?php if (empty($asistentes)): ?>
            <p class="empty-msg">No hay asistentes registrados para este recinto.</p>
        <?php else: ?>
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($asistentes as $a): ?>
                    <tr>
                        <td><?= htmlspecialchars($a['usuario']) ?></td>
                        <td><?= htmlspecialchars($a['nombre_completo']) ?></td>
                        <td><?= htmlspecialchars($a['email']) ?></td>
                        <td>
                            <button class="btn-edit" onclick="editar(<?= $a['id_admin'] ?>, '<?= $a['email'] ?>', '<?= htmlspecialchars($a['nombre_completo']) ?>')">Editar</button>
                            <button class="btn-delete" onclick="eliminar(<?= $a['id_admin'] ?>)">Eliminar</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div id="modal" class="modal">
        <div class="modal-content">
            <h3 id="modalTitle">Nuevo Asistente</h3>

            <input id="usuario" placeholder="Usuario">
            <input id="nombre" placeholder="Nombre completo">
            <input id="email" placeholder="Email">
            <input id="password" type="password" placeholder="Contraseña">

            <button onclick="guardar()">Guardar</button>
            <button onclick="closeModal()">Cancelar</button>
        </div>
    </div>
</body>
</html>