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
    $stmt = $pdo->prepare("SELECT * FROM admin_recintos WHERE id_recinto = ? AND rol = 'asistente' ORDER BY nombre_completo ASC");
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Asistentes - CanchaSport</title>
    <style>
        :root {
            --primary: #071289;
            --secondary: #AB47BC;
            --bg-light: #f4f6f9;
            --text-dark: #2D3748;
            --white: #ffffff;
            --danger: #EF5350;
            --success: #66BB6A;
        }
        body {
            font-family: 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-light);
            color: var(--text-dark);
            margin: 0;
            padding: 2rem;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: var(--white);
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            border-bottom: 2px solid #eee;
            padding-bottom: 1rem;
        }
        h2 {
            color: var(--primary);
            margin: 0;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-back {
            text-decoration: none;
            color: #666;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.3rem;
            transition: color 0.2s;
        }
        .btn-back:hover { color: var(--primary); }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(7, 18, 137, 0.3);
            transition: transform 0.2s;
        }
        .btn-primary:hover { transform: translateY(-2px); }

        /* Tabla Estilizada */
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 1rem; }
        th {
            background: var(--primary);
            color: white;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
        }
        th:first-child { border-top-left-radius: 12px; }
        th:last-child { border-top-right-radius: 12px; }
        td {
            padding: 1rem;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f9fafb; }

        /* Botones de Acción */
        .actions { display: flex; gap: 0.5rem; }
        .btn-action {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85rem;
            transition: opacity 0.2s;
        }
        .btn-edit { background: #E3F2FD; color: #1565C0; }
        .btn-delete { background: #FFEBEE; color: #C62828; }
        .btn-action:hover { opacity: 0.8; }

        /* Modal Moderno */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            animation: slideUp 0.3s ease;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .modal h3 { color: var(--primary); margin-top: 0; }
        
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.4rem; font-weight: 600; font-size: 0.9rem; color: #555; }
        .form-group input {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #eee;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        .form-group input:focus { border-color: var(--secondary); outline: none; }
        .form-group input:disabled { background: #f5f5f5; color: #999; cursor: not-allowed; }

        .modal-actions { display: flex; gap: 1rem; margin-top: 1.5rem; }
        .btn-save { flex: 1; background: var(--success); color: white; border: none; padding: 0.8rem; border-radius: 10px; font-weight: bold; cursor: pointer; }
        .btn-cancel { flex: 1; background: #eee; color: #555; border: none; padding: 0.8rem; border-radius: 10px; font-weight: bold; cursor: pointer; }

        .empty-msg { text-align: center; padding: 3rem; color: #888; background: #f9f9f9; border-radius: 12px; }
        
        /* Toast Notification */
        #toast-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .toast {
            background: #333;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            font-size: 0.9rem;
            animation: slideInRight 0.3s ease;
            min-width: 200px;
            text-align: center;
        }
        .toast.success { background: #4CAF50; }
        .toast.error { background: #F44336; }
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="recinto_dashboard.php" class="btn-back">← Volver al Dashboard</a>
            <h2>👥 Asistentes</h2>
            <button class="btn-primary" onclick="openModal()">+ Nuevo Asistente</button>
        </div>
        
        <?php if (empty($asistentes)): ?>
            <div class="empty-msg">
                <div style="font-size: 3rem; margin-bottom: 1rem;">🕵️‍♂️</div>
                <p>No hay asistentes registrados para este recinto.</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Nombre Completo</th>
                            <th>Email</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($asistentes as $a): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($a['usuario']) ?></strong></td>
                            <td><?= htmlspecialchars($a['nombre_completo']) ?></td>
                            <td><?= htmlspecialchars($a['email']) ?></td>
                            <td>
                                <div class="actions">
                                    <button class="btn-action btn-edit" onclick="editar(<?= $a['id_admin'] ?>, '<?= htmlspecialchars($a['email']) ?>', '<?= htmlspecialchars($a['nombre_completo']) ?>', '<?= htmlspecialchars($a['usuario']) ?>')">Editar</button>
                                    <button class="btn-action btn-delete" onclick="eliminar(<?= $a['id_admin'] ?>)">Eliminar</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- MODAL -->
    <div id="modal" class="modal">
        <div class="modal-content">
            <h3 id="modalTitle">Nuevo Asistente</h3>
            <form id="formAsistente" onsubmit="event.preventDefault(); guardar();">
                <div class="form-group">
                    <label>Usuario *</label>
                    <!-- Quitamos required del HTML para controlarlo con JS -->
                    <input type="text" id="usuario" placeholder="ej. juan.perez">
                </div>
                <div class="form-group">
                    <label>Nombre Completo *</label>
                    <input type="text" id="nombre" required placeholder="Juan Pérez">
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" id="email" required placeholder="juan@correo.com">
                </div>
                <div class="form-group" id="passGroup" style="position: relative;">
                    <label>Contraseña *</label>
                    <input type="password" id="password" placeholder="Mínimo 6 caracteres" 
                        style="width: 100%; padding: 0.8rem; padding-right: 40px; border: 2px solid #eee; border-radius: 10px; box-sizing: border-box;">
                    
                    <!-- Ojito -->
                    <button type="button" onclick="togglePassword('password', this)" 
                        style="position: absolute; right: 10px; top: 38px; /* Ajustado por el label */ transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #666; font-size: 1.2rem; padding: 0;">
                        👁️
                    </button>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancelar</button>
                    <button type="submit" class="btn-save">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <div id="toast-container"></div>

    <script src="../assets/js/asistentes.js"></script>
</body>
</html>