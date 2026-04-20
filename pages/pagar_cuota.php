<?php
// Intentar aumentar límites de subida si el servidor lo permite
@ini_set('upload_max_filesize', '10M');
@ini_set('post_max_size', '10M');
@ini_set('max_execution_time', '300');

require_once __DIR__ . '/../includes/config.php';
session_start();

if (!isset($_SESSION['id_socio']) || !isset($_GET['id_cuota'])) {
    header('Location: ../index.php');
    exit;
}

$id_cuota = (int)$_GET['id_cuota'];
$id_socio = $_SESSION['id_socio'];

// === 1. Obtener datos de la cuota ===
$stmt = $pdo->prepare("
    SELECT 
        c.id_cuota, c.monto, c.fecha_vencimiento, c.estado, c.tipo_actividad, c.id_evento, c.comentario as comentario_previo,
        s.nombre AS socio_nombre, s.email AS socio_email, s.alias,
        sc.id_club, cl.nombre AS club_nombre, cl.email_responsable,
        r.fecha AS fecha_origen, r.monto_total, r.monto_recaudacion, r.tipo_pago as tipo_pago_defecto, r.mes, r.valor_mes,
        COALESCE(ca.nombre_cancha, te.tipoevento, 'Cuota General') AS detalle_origen
    FROM cuotas c
    INNER JOIN socios s ON c.id_socio = s.id_socio
    INNER JOIN socio_club sc ON s.id_socio = sc.id_socio AND sc.estado = 'activo'
    INNER JOIN clubs cl ON sc.id_club = cl.id_club
    LEFT JOIN reservas r ON c.id_evento = r.id_reserva AND c.tipo_actividad = 'reserva'
    LEFT JOIN canchas ca ON r.id_cancha = ca.id_cancha
    LEFT JOIN eventos e ON c.id_evento = e.id_evento AND c.tipo_actividad = 'evento'
    LEFT JOIN tipoeventos te ON e.id_tipoevento = te.id_tipoevento
    WHERE c.id_cuota = ? AND c.id_socio = ?
    LIMIT 1
");
$stmt->execute([$id_cuota, $id_socio]);
$cuota = $stmt->fetch();

if (!$cuota) {
    die('<div style="text-align:center;color:white;margin-top:50px;"><h2>❌ Cuota no encontrada</h2><a href="dashboard_socio.php" style="color:#FFD700;">Volver</a></div>');
}

$error = '';
$success = false;

// === 2. Procesar Formulario ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha_pago = $_POST['fecha_pago'] ?? '';
    $comentario = trim($_POST['comentario'] ?? '');
    $tipo_pago_seleccionado = $_POST['tipo_pago'] ?? 'semana';
    $monto_ingresado = (float)($_POST['monto_pagado'] ?? 0);
    
    // Validaciones básicas
    if (empty($fecha_pago)) {
        $error = 'La fecha de pago es obligatoria.';
    } elseif ($monto_ingresado <= 0) {
        $error = 'El monto ingresado debe ser mayor a 0.';
    } else {
        // Validar monto
        $monto_esperado = 0;
        if ($tipo_pago_seleccionado === 'mes') {
            $monto_esperado = (float)($cuota['valor_mes'] ?? 0);
            if ($monto_esperado == 0) $monto_esperado = (float)$cuota['monto'];
            if (abs($monto_ingresado - $monto_esperado) > 1) {
                $error = "El monto mensual debe ser $" . number_format($monto_esperado, 0, ',', '.');
            }
        } else {
            $monto_esperado = (float)$cuota['monto'];
            if (abs($monto_ingresado - $monto_esperado) > 1) {
                $error = "El monto semanal debe ser $" . number_format($monto_esperado, 0, ',', '.');
            }
        }

        // Manejo de archivo adjunto (MEJORADO)
        $adjunto = null;
        if (!$error && !empty($_FILES['adjunto']['name'])) {
            $target_dir = __DIR__ . '/../uploads/comprobantes/';
            
            // Asegurar carpeta
            if (!is_dir($target_dir)) {
                if (!mkdir($target_dir, 0755, true)) {
                    $error = 'Error interno: No se puede crear la carpeta de archivos.';
                }
            }
            
            if (!$error) {
                $file_error = $_FILES['adjunto']['error'];
                
                // Verificar errores de subida de PHP
                if ($file_error !== UPLOAD_ERR_OK) {
                    switch ($file_error) {
                        case UPLOAD_ERR_INI_SIZE:
                        case UPLOAD_ERR_FORM_SIZE:
                            $error = 'El archivo es muy grande. Por favor usa una foto comprimida o PDF (< 2MB).';
                            break;
                        case UPLOAD_ERR_PARTIAL:
                            $error = 'El archivo se subió solo parcialmente.';
                            break;
                        case UPLOAD_ERR_NO_FILE:
                            $error = 'No se seleccionó ningún archivo.';
                            break;
                        default:
                            $error = 'Error al subir el archivo (Código: ' . $file_error . ').';
                    }
                } else {
                    $ext = strtolower(pathinfo($_FILES['adjunto']['name'], PATHINFO_EXTENSION));
                    $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
                    
                    if (!in_array($ext, $allowed)) {
                        $error = 'Solo se permiten JPG, PNG o PDF.';
                    } else {
                        $file_name = 'pago_' . $id_cuota . '_' . time() . '.' . $ext;
                        $target_file = $target_dir . $file_name;
                        
                        if (move_uploaded_file($_FILES['adjunto']['tmp_name'], $target_file)) {
                            $adjunto = $file_name;
                        } else {
                            $error = 'Error al guardar el archivo en el servidor.';
                        }
                    }
                }
            }
        }

        if (!$error) {
            try {
                $pdo->beginTransaction();

                // 1. Actualizar Cuota (AGREGANDO monto = ?)
                $stmt_upd = $pdo->prepare("
                    UPDATE cuotas 
                    SET estado = 'en_revision', 
                        fecha_pago = ?, 
                        comentario = CONCAT(IFNULL(comentario, ''), '\n[Usuario]: ', ?),
                        adjunto = ?,
                        monto = ?  -- ✅ AGREGADO: Guardamos el monto que el usuario ingresó
                    WHERE id_cuota = ?
                ");
                
                // Agregamos $monto_ingresado a los parámetros
                $stmt_upd->execute([$fecha_pago, $comentario, $adjunto, $monto_ingresado, $id_cuota]);

                // 2. Si es reserva, actualizar monto_recaudacion
                if ($cuota['tipo_actividad'] === 'reserva' && $cuota['id_evento']) {
                    $stmt_curr = $pdo->prepare("SELECT monto_recaudacion FROM reservas WHERE id_reserva = ?");
                    $stmt_curr->execute([$cuota['id_evento']]);
                    $current_recaudado = (float)($stmt_curr->fetchColumn() ?: 0);
                    
                    $nuevo_total = $current_recaudado + $monto_ingresado;
                    $nuevo_estado_pago = 'pendiente';
                    
                    if ($nuevo_total >= (float)$cuota['monto_total']) {
                        $nuevo_estado_pago = 'pagado';
                        $nuevo_total = (float)$cuota['monto_total'];
                    } elseif ($nuevo_total > 0) {
                        $nuevo_estado_pago = 'parcial';
                    }

                    // Actualizar reserva (verificar si tiene updated_at, si no, quitarlo también)
                    // Asumimos que reservas SÍ tiene updated_at por ser tabla principal, pero si falla, quítalo.
                    $stmt_res = $pdo->prepare("UPDATE reservas SET monto_recaudacion = ?, estado_pago = ?, updated_at = NOW() WHERE id_reserva = ?");
                    $stmt_res->execute([$nuevo_total, $nuevo_estado_pago, $cuota['id_evento']]);
                }

                $pdo->commit();
                $success = true;

            } catch (Exception $e) {
                $pdo->rollBack();
                // Mensaje más amigable si falla SQL
                if (strpos($e->getMessage(), 'updated_at') !== false) {
                     $error = 'Error de base de datos: Falta columna de actualización. Contacta al administrador.';
                } else {
                     $error = 'Error al procesar el pago: ' . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Pagar Cuota - <?= htmlspecialchars($cuota['club_nombre']) ?></title>
    <link rel="stylesheet" href="../styles.css">
    <style>
        body {
            background: linear-gradient(rgba(0, 20, 10, 0.85), rgba(0, 30, 15, 0.9)), url('../assets/img/cancha_pasto2.jpg') center/cover fixed;
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #333;
            padding: 10px;
        }
        .modal-container {
            background: white;
            width: 100%;
            max-width: 600px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.5);
            overflow: hidden;
            position: relative;
        }
        .modal-header {
            background: #071289;
            color: white;
            padding: 0.8rem 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h2 { margin: 0; font-size: 1.2rem; }
        .close-x { font-size: 1.5rem; cursor: pointer; color: #FFD700; text-decoration: none; font-weight: bold; }
        
        .modal-body { padding: 1.2rem; }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.8rem;
        }
        .full-width { grid-column: span 2; }
        
        .form-group { display: flex; flex-direction: column; }
        .form-group label { 
            font-weight: 600; margin-bottom: 0.2rem; color: #444; font-size: 0.8rem; text-transform: uppercase;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px; font-size: 0.9rem; box-sizing: border-box; background: #fff;
        }
        .form-group input[readonly] { background: #f0f2f5; color: #666; }
        
        .radio-group { display: flex; gap: 1rem; align-items: center; background: #f8f9fa; padding: 0.5rem; border-radius: 4px; border: 1px solid #eee; }
        .radio-option { display: flex; align-items: center; gap: 0.3rem; font-size: 0.9rem; cursor: pointer; }
        .radio-option input { margin: 0; width: auto; }
        
        .btn-submit {
            background: #28a745; color: white; border: none; padding: 0.8rem; border-radius: 6px; font-size: 1rem; font-weight: bold; cursor: pointer; width: 100%; margin-top: 1rem;
        }
        .btn-cancel { display: block; text-align: center; margin-top: 0.8rem; color: #666; text-decoration: none; font-size: 0.9rem; }
        .alert { padding: 0.8rem; border-radius: 6px; margin-bottom: 1rem; text-align: center; font-size: 0.9rem; }
        .alert-error { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }

        @media (max-width: 360px) {
            .form-grid { gap: 0.5rem; }
            .modal-body { padding: 1rem; }
            .form-group label { font-size: 0.75rem; }
        }
    </style>
</head>
<body>

<div class="modal-container">
    <div class="modal-header">
        <h2>💳 Pagar Cuota</h2>
        <a href="dashboard_socio.php?id_club=<?= htmlspecialchars($_SESSION['current_club'] ?? '') ?>" class="close-x">&times;</a>
    </div>

    <div class="modal-body">
        <?php if ($success): ?>
            <div class="alert alert-success">
                <h3 style="margin-top:0">✅ ¡Pago Registrado!</h3>
                <p>Tu comprobante está en revisión.</p>
                <a href="dashboard_socio.php?id_club=<?= htmlspecialchars($_SESSION['current_club'] ?? '') ?>" class="btn-submit" style="text-decoration:none; display:inline-block; margin-top:0.5rem;">Volver</a>
            </div>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Detalle del Pago</label>
                        <input type="text" value="<?= htmlspecialchars($cuota['detalle_origen']) ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label>Mes Aplica</label>
                        <input type="text" value="<?= htmlspecialchars($cuota['mes'] ?? date('F Y')) ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Fecha Vence</label>
                        <input type="text" value="<?= date('d/m/Y', strtotime($cuota['fecha_vencimiento'])) ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label>$ Valor Semana</label>
                        <input type="text" value="$<?= number_format($cuota['monto'], 0, ',', '.') ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>$ Valor Mes</label>
                        <input type="text" value="$<?= number_format($cuota['valor_mes'] ?? $cuota['monto'], 0, ',', '.') ?>" readonly>
                    </div>

                    <div class="form-group full-width">
                        <label>Tipo de Pago *</label>
                        <div class="radio-group">
                            <label class="radio-option">
                                <input type="radio" name="tipo_pago" value="semana" checked onchange="toggleMontoInput()"> Semana
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="tipo_pago" value="mes" onchange="toggleMontoInput()"> Mes
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Monto a Pagar $ *</label>
                        <input type="number" id="monto_pagado" name="monto_pagado" step="100" required 
                               value="<?= $cuota['monto'] ?>" readonly style="background:#e8f5e9; font-weight:bold; color:#2e7d32;">
                    </div>
                    <div class="form-group">
                        <label>Fecha Pago *</label>
                        <input type="date" id="fecha_pago" name="fecha_pago" required value="<?= date('Y-m-d') ?>">
                    </div>

                    <div class="form-group full-width">
                        <label>Comprobante (Opcional - Max 2MB)</label>
                        <input type="file" id="adjunto" name="adjunto" accept=".jpg,.jpeg,.png,.pdf">
                        <small style="color:#666; font-size:0.7rem;">Si da error, intenta reducir el tamaño de la foto.</small>
                    </div>

                    <div class="form-group full-width">
                        <label>Comentarios</label>
                        <textarea id="comentario" name="comentario" rows="2" placeholder="Ej: Transferencia..."></textarea>
                    </div>
                </div>

                <button type="submit" class="btn-submit">Registrar Pago</button>
                <a href="javascript:history.back()" class="btn-cancel">Cancelar</a>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
    const valorSemana = <?= (float)$cuota['monto'] ?>;
    const valorMes = <?= (float)($cuota['valor_mes'] ?? $cuota['monto']) ?>;
    const inputMonto = document.getElementById('monto_pagado');

    function toggleMontoInput() {
        const tipo = document.querySelector('input[name="tipo_pago"]:checked').value;
        if (tipo === 'mes') {
            inputMonto.value = valorMes;
        } else {
            inputMonto.value = valorSemana;
        }
    }
    toggleMontoInput();
</script>

</body>
</html>