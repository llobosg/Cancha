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
        body { background: #f4f6f9; font-family: 'Segoe UI', sans-serif; }
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 1rem; }
        
        /* Header */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .btn-primary { background: #AB47BC; color: white; padding: 0.8rem 1.5rem; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; transition: 0.2s; }
        .btn-primary:hover { background: #8E24AA; transform: translateY(-2px); }
        
        /* Tabla */
        .table-container { background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8f9fa; color: #555; text-align: left; padding: 1rem; font-weight: 600; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 1rem; border-bottom: 1px solid #eee; color: #333; }
        tr:last-child td { border-bottom: none; }
        tr:hover { background: #fafafa; }
        
        /* Badges */
        .badge { padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.8rem; font-weight: bold; }
        .badge-active { background: #e8f5e9; color: #2e7d32; }
        
        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1000; justify-content: center; align-items: center; backdrop-filter: blur(4px); }
        .modal-content { background: white; padding: 2rem; border-radius: 16px; width: 90%; max-width: 500px; position: relative; animation: slideDown 0.3s ease; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        .close-modal { position: absolute; top: 15px; right: 20px; font-size: 1.5rem; cursor: pointer; color: #999; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #333; }
        .form-group input { width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; }
        .form-group input:focus { outline: none; border-color: #AB47BC; ring: 2px solid #AB47BC; }
        .btn-submit { width: 100%; padding: 1rem; background: #AB47BC; color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; margin-top: 1rem; }
        .btn-submit:hover { background: #8E24AA; }
    </style>
</head>
<body>

<div class="container">
    <!-- Header -->
    <div class="page-header">
        <div>
            <h1 style="color: #333; margin: 0;">👥 Gestión de Asistentes</h1>
            <p style="color: #666; margin: 0.5rem 0 0 0;">Administra el acceso del personal operativo al recinto.</p>
        </div>
        <button class="btn-primary" onclick="abrirModal()">+ Nuevo Asistente</button>
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
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($asistentes) > 0): ?>
                    <?php foreach ($asistentes as $asistente): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($asistente['nombre_completo']) ?></strong></td>
                        <td><?= htmlspecialchars($asistente['usuario']) ?></td>
                        <td><?= htmlspecialchars($asistente['email']) ?></td>
                        <td><?= htmlspecialchars($asistente['telefono'] ?? '-') ?></td>
                        <td><?= date('d/m/Y', strtotime($asistente['created_at'])) ?></td>
                        <td><span class="badge badge-active">Activo</span></td>
                        <td>
                            <button onclick="eliminarAsistente(<?= $asistente['id_admin'] ?>, '<?= htmlspecialchars($asistente['nombre_completo']) ?>')" style="background: #ffebee; color: #c62828; border: none; padding: 0.4rem 0.8rem; border-radius: 6px; cursor: pointer; font-size: 0.85rem; font-weight: bold;">Dar de Baja</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 2rem; color: #999;">No hay asistentes registrados aún.</td>
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
        <h2 style="color: #AB47BC; margin-top: 0;">Registrar Nuevo Asistente</h2>
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
                <small style="color: #999; font-size: 0.8rem;">El asistente podrá cambiarla luego.</small>
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
            }
        } catch (err) {
            alert('❌ Error de conexión.');
            console.error(err);
        }
    });

    // Eliminar Asistente
    async function eliminarAsistente(id, nombre) {
        if (!confirm(`¿Estás seguro de dar de baja a ${nombre}? Perderá el acceso inmediatamente.`)) return;

        const formData = new FormData();
        formData.append('action', 'eliminar_asistente');
        formData.append('id_admin', id);

        try {
            const res = await fetch('../api/gestion_asistentes.php', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.success) {
                alert('✅ Asistente dado de baja.');
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