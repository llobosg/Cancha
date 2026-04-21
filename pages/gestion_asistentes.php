<?php
// pages/gestion_asistentes.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/permisos.php';

// Seguridad: Solo Admins pueden acceder
if (!esAdmin()) {
    header('Location: recinto_dashboard.php');
    exit;
}

$id_recinto = $_SESSION['id_recinto'];
$nombre_recinto = $_SESSION['nombre_recinto'] ?? 'Recinto Deportivo';

// Obtener lista de asistentes actuales
$stmt = $pdo->prepare("
    SELECT ar.id_admin, ar.usuario, ar.nombre_completo, ar.email, ar.telefono, ar.created_at, ar.rol
    FROM admin_recintos ar
    WHERE ar.id_recinto = ? AND ar.rol = 'asistente'
    ORDER BY ar.created_at DESC
");
$stmt->execute([$id_recinto]);
$asistentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Asistentes - CanchaSport</title>
    <link rel="stylesheet" href="../styles.css">
    <style>
        body { background: #f4f6f9; font-family: 'Segoe UI', sans-serif; margin: 0; padding: 0; }
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 1rem; }
        
        /* Estilos Top Bar CanchaSport */
        .top-bar {
            background: linear-gradient(90deg, #CE93D8 0%, #BA68C8 50%, #AB47BC 100%);
            padding: 1rem 2rem;
            box-shadow: 0 4px 12px rgba(186, 104, 200, 0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .brand-logo {
            color: white;
            font-weight: 900;
            font-size: 1.5rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .brand-logo span { font-size: 1.8rem; }
        
        .btn-back {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.4);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: 0.2s;
            backdrop-filter: blur(5px);
        }
        .btn-back:hover { background: rgba(255,255,255,0.3); transform: translateY(-2px); }

        /* Resto de estilos */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; margin-top: 1rem; }
        .btn-primary { background: #AB47BC; color: white; padding: 0.8rem 1.5rem; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; transition: 0.2s; box-shadow: 0 4px 6px rgba(171, 71, 188, 0.2); }
        .btn-primary:hover { background: #8E24AA; transform: translateY(-2px); }
        
        .table-container { background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8f9fa; color: #555; text-align: left; padding: 1rem; font-weight: 600; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #eee; }
        td { padding: 1rem; border-bottom: 1px solid #eee; color: #333; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover { background: #fafafa; }
        
        .badge { padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.8rem; font-weight: bold; }
        .badge-active { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        
        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 2000; justify-content: center; align-items: center; backdrop-filter: blur(4px); }
        .modal-content { background: white; padding: 2rem; border-radius: 16px; width: 90%; max-width: 500px; position: relative; animation: slideDown 0.3s ease; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        .close-modal { position: absolute; top: 15px; right: 20px; font-size: 1.8rem; cursor: pointer; color: #999; transition: 0.2s; line-height: 1; }
        .close-modal:hover { color: #D32F2F; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #333; }
        .form-group input { width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; box-sizing: border-box; transition: 0.2s; }
        .form-group input:focus { outline: none; border-color: #AB47BC; box-shadow: 0 0 0 3px rgba(171, 71, 188, 0.1); }
        .btn-submit { width: 100%; padding: 1rem; background: #AB47BC; color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; margin-top: 1rem; font-size: 1rem; transition: 0.2s; }
        .btn-submit:hover { background: #8E24AA; box-shadow: 0 4px 10px rgba(171, 71, 188, 0.3); }
    </style>
</head>
<body>

<!-- TOP BAR CANCHASPORT -->
<div class="top-bar">
    <a href="../index.php" class="brand-logo">
        <span>🏟️</span> CanchaSport
    </a>
    
    <!-- Botón Volver al Dashboard -->
    <a href="recinto_dashboard.php" class="btn-back">
        ← Volver al Dashboard
    </a>
</div>

<div class="container">
    <!-- Header Interno -->
    <div class="page-header">
        <div>
            <h1 style="color: #333; margin: 0; font-size: 1.8rem;">👥 Gestión de Asistentes</h1>
            <p style="color: #666; margin: 0.5rem 0 0 0; font-size: 0.95rem;">
                Administrando personal para: <strong><?= htmlspecialchars($nombre_recinto) ?></strong>
            </p>
        </div>
        <button class="btn-primary" onclick="abrirModal()">
            <span style="font-size: 1.2rem;">+</span> Nuevo Asistente
        </button>
    </div>

    <!-- Tabla -->
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Nombre Completo</th>
                    <th>Usuario (Alias)</th>
                    <th>Email</th>
                    <th>Teléfono</th>
                    <th>Fecha Ingreso</th>
                    <th>Estado</th>
                    <th style="text-align: right;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($asistentes) > 0): ?>
                    <?php foreach ($asistentes as $asistente): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($asistente['nombre_completo']) ?></strong></td>
                        <td><code style="background:#f0f0f0; padding:2px 6px; border-radius:4px; color:#555;"><?= htmlspecialchars($asistente['usuario']) ?></code></td>
                        <td><?= htmlspecialchars($asistente['email']) ?></td>
                        <td><?= htmlspecialchars($asistente['telefono'] ?? '-') ?></td>
                        <td><?= date('d/m/Y', strtotime($asistente['created_at'])) ?></td>
                        <td><span class="badge badge-active">Activo</span></td>
                        <td style="text-align: right;">
                            <button onclick="eliminarAsistente(<?= $asistente['id_admin'] ?>, '<?= htmlspecialchars($asistente['nombre_completo']) ?>')" 
                                style="background: #ffebee; color: #c62828; border: none; padding: 0.5rem 1rem; border-radius: 6px; cursor: pointer; font-size: 0.85rem; font-weight: bold; transition: 0.2s;">
                                Dar de Baja
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 3rem; color: #999;">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">📋</div>
                            No hay asistentes registrados aún.<br>
                            Usa el botón "+ Nuevo Asistente" para comenzar.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Registro -->
<div id="modalRegistro" class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick="cerrarModal()">&times;</span>
        <h2 style="color: #AB47BC; margin-top: 0; font-size: 1.5rem;">Registrar Nuevo Asistente</h2>
        <p style="color: #666; font-size: 0.9rem; margin-bottom: 1.5rem;">Crea una cuenta de acceso operativo para el recinto.</p>
        
        <form id="formAsistente">
            <div class="form-group">
                <label>Nombre Completo *</label>
                <input type="text" name="nombre_completo" required placeholder="Ej: Juan Pérez">
            </div>
            <div class="form-group">
                <label>Usuario (Alias para login) *</label>
                <input type="text" name="usuario" required placeholder="Ej: juan, jperez">
            </div>
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" required placeholder="juan@ejemplo.com">
            </div>
            <div class="form-group">
                <label>Teléfono</label>
                <input type="text" name="telefono" placeholder="+56 9...">
            </div>
            <div class="form-group">
                <label>Contraseña Temporal *</label>
                <input type="password" name="contraseña" required placeholder="Mínimo 6 caracteres">
                <small style="color: #999; font-size: 0.8rem;">El asistente podrá cambiarla luego desde su perfil.</small>
            </div>
            
            <button type="submit" class="btn-submit">Crear Asistente</button>
        </form>
    </div>
</div>

<script>
    function abrirModal() {
        document.getElementById('modalRegistro').style.display = 'flex';
    }
    
    function cerrarModal() {
        document.getElementById('modalRegistro').style.display = 'none';
        document.getElementById('formAsistente').reset();
    }

    // Submit Formulario
    document.getElementById('formAsistente').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = this.querySelector('.btn-submit');
        const originalText = btn.textContent;
        btn.textContent = 'Creando...';
        btn.disabled = true;

        const formData = new FormData(this);
        formData.append('action', 'crear_asistente');

        try {
            const res = await fetch('../api/gestion_asistentes.php', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.success) {
                alert('✅ Asistente registrado correctamente.');
                location.reload();
            } else {
                alert('❌ Error: ' + data.message);
                btn.textContent = originalText;
                btn.disabled = false;
            }
        } catch (err) {
            alert('❌ Error de conexión.');
            console.error(err);
            btn.textContent = originalText;
            btn.disabled = false;
        }
    });

    // Eliminar Asistente
    async function eliminarAsistente(id, nombre) {
        if (!confirm(`¿Estás seguro de dar de baja a ${nombre}?\n\nPerderá el acceso inmediatamente y no podrá iniciar sesión.`)) return;

        const formData = new FormData();
        formData.append('action', 'eliminar_asistente');
        formData.append('id_admin', id);

        try {
            const res = await fetch('../api/gestion_asistentes.php', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.success) {
                alert('✅ Asistente dado de baja correctamente.');
                location.reload();
            } else {
                alert('❌ Error: ' + data.message);
            }
        } catch (err) {
            alert('❌ Error de conexión.');
        }
    }

    // Cerrar modal al click fuera
    window.onclick = function(event) {
        if (event.target == document.getElementById('modalRegistro')) {
            cerrarModal();
        }
    }
</script>

</body>
</html>