<?php
require_once __DIR__ . '/../includes/config.php';
session_start();

if (!isset($_SESSION['id_socio']) || !isset($_GET['id_cuota'])) {
    header('Location: ../index.php');
    exit;
}

$id_cuota = (int)$_GET['id_cuota'];
$id_socio = $_SESSION['id_socio'];

// === 1. Obtener datos de la cuota y reserva asociada ===
$stmt = $pdo->prepare("
    SELECT 
        c.id_cuota, c.monto, c.fecha_vencimiento, c.estado, c.tipo_actividad, c.id_evento, c.comentario as comentario_previo,
        s.nombre AS socio_nombre, s.email AS socio_email, s.alias,
        sc.id_club, cl.nombre AS club_nombre, cl.email_responsable,
        r.fecha AS fecha_origen, r.monto_total, r.monto_recaudacion, r.tipo_pago as tipo_pago_defecto, r.mes, r.valor_mes,
        -- Obtenemos el nombre de la cancha directamente si es reserva, o el tipo de evento si es evento
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
    die('<div style="text-align:center;color:white;margin-top:50px;"><h2>❌ Cuota no encontrada o no pertenece a tu usuario</h2><a href="dashboard_socio.php" style="color:#FFD700;">Volver</a></div>');
}

$error = '';
$success = false;

// === 2. Procesar Formulario ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha_pago = $_POST['fecha_pago'] ?? '';
    $comentario = trim($_POST['comentario'] ?? '');
    $tipo_pago_seleccionado = $_POST['tipo_pago'] ?? 'semana'; // semana o mes
    $monto_ingresado = (float)($_POST['monto_pagado'] ?? 0);
    
    // Validaciones básicas
    if (empty($fecha_pago)) {
        $error = 'La fecha de pago es obligatoria.';
    } elseif ($monto_ingresado <= 0) {
        $error = 'El monto ingresado debe ser mayor a 0.';
    } else {
        // Validar monto según tipo de pago seleccionado
        $monto_esperado = 0;
        if ($tipo_pago_seleccionado === 'mes') {
            $monto_esperado = (float)($cuota['valor_mes'] ?? 0);
            if ($monto_esperado == 0) $monto_esperado = (float)$cuota['monto']; // Fallback
            if (abs($monto_ingresado - $monto_esperado) > 1) { // Margen de error 1 peso
                $error = "El monto para pago mensual debe ser aproximadamente $" . number_format($monto_esperado, 0, ',', '.');
            }
        } else {
            // Semana
            $monto_esperado = (float)$cuota['monto'];
            if (abs($monto_ingresado - $monto_esperado) > 1) {
                $error = "El monto para pago semanal debe ser $" . number_format($monto_esperado, 0, ',', '.');
            }
        }

        // Manejo de archivo adjunto
        $adjunto = null;
        if (!$error && !empty($_FILES['adjunto']['name'])) {
            $target_dir = __DIR__ . '/../uploads/comprobantes/';
            if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
            
            $ext = strtolower(pathinfo($_FILES['adjunto']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
                $file_name = 'pago_' . $id_cuota . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['adjunto']['tmp_name'], $target_dir . $file_name)) {
                    $adjunto = $file_name;
                } else {
                    $error = 'Error al subir el archivo.';
                }
            } else {
                $error = 'Solo se permiten imágenes (JPG, PNG) o PDF.';
            }
        }

        if (!$error) {
            try {
                $pdo->beginTransaction();

                // 1. Actualizar Cuota
                $stmt_upd = $pdo->prepare("
                    UPDATE cuotas 
                    SET estado = 'en_revision', 
                        fecha_pago = ?, 
                        comentario = CONCAT(IFNULL(comentario, ''), '\n[Usuario]: ' . ?),
                        adjunto = ?,
                        updated_at = NOW()
                    WHERE id_cuota = ?
                ");
                $stmt_upd->execute([$fecha_pago, $comentario, $adjunto, $id_cuota]);

                // 2. Si es reserva, actualizar monto_recaudacion ACUMULADO
                if ($cuota['tipo_actividad'] === 'reserva' && $cuota['id_evento']) {
                    // Obtener monto actual recaudado
                    $stmt_curr = $pdo->prepare("SELECT monto_recaudacion FROM reservas WHERE id_reserva = ?");
                    $stmt_curr->execute([$cuota['id_evento']]);
                    $current_recaudado = (float)($stmt_curr->fetchColumn() ?: 0);
                    
                    $nuevo_total = $current_recaudado + $monto_ingresado;
                    
                    // Determinar estado de pago de la reserva
                    $nuevo_estado_pago = 'pendiente';
                    if ($nuevo_total >= (float)$cuota['monto_total']) {
                        $nuevo_estado_pago = 'pagado';
                        $nuevo_total = (float)$cuota['monto_total']; // Ajustar si se pasó
                    } elseif ($nuevo_total > 0) {
                        $nuevo_estado_pago = 'parcial';
                    }

                    $stmt_res = $pdo->prepare("
                        UPDATE reservas 
                        SET monto_recaudacion = ?,
                            estado_pago = ?,
                            updated_at = NOW()
                        WHERE id_reserva = ?
                    ");
                    $stmt_res->execute([$nuevo_total, $nuevo_estado_pago, $cuota['id_evento']]);
                }

                $pdo->commit();
                $success = true;

                // TODO: Aquí podrías enviar correos de confirmación si deseas

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Error al procesar el pago: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagar Cuota - <?= htmlspecialchars($cuota['club_nombre']) ?></title>
    <link rel="stylesheet" href="../styles.css">
    <style>
        body {
            background: linear-gradient(rgba(0, 20, 10, 0.7), rgba(0, 30, 15, 0.8)), url('../assets/img/cancha_pasto2.jpg') center/cover fixed;
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #333;
        }
        .modal-container {
            background: white;
            width: 95%;
            max-width: 700px;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.4);
            overflow: hidden;
            position: relative;
            animation: slideUp 0.4s ease-out;
        }
        @keyframes slideUp { from { transform: translateY(50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        
        .modal-header {
            background: #071289;
            color: white;
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h2 { margin: 0; font-size: 1.4rem; }
        .close-x { font-size: 1.8rem; cursor: pointer; color: #FFD700; text-decoration: none; font-weight: bold; }
        .close-x:hover { color: white; }

        .modal-body { padding: 2rem; }
        
        /* Grid Layout 2 Columnas */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        .full-width { grid-column: span 2; }
        
        .form-group label { display: block; font-weight: 600; margin-bottom: 0.4rem; color: #444; font-size: 0.9rem; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 0.7rem;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 1rem;
            box-sizing: border-box;
        }
        .form-group input[readonly] { background: #f0f2f5; color: #666; cursor: not-allowed; }
        
        .radio-group { display: flex; gap: 1rem; margin-top: 0.5rem; }
        .radio-option { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; }
        
        .btn-submit {
            background: #28a745;
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            margin-top: 1rem;
            transition: background 0.2s;
        }
        .btn-submit:hover { background: #218838; }
        .btn-cancel {
            display: block;
            text-align: center;
            margin-top: 1rem;
            color: #666;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .alert { padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem; text-align: center; }
        .alert-error { background: #ffebee; color: #c62828; }
        .alert-success { background: #e8f5e9; color: #2e7d32; }

        @media (max-width: 600px) {
            .form-grid { grid-template-columns: 1fr; gap: 1rem; }
            .full-width { grid-column: span 1; }
            .modal-body { padding: 1.5rem; }
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
                <h3>✅ ¡Pago Registrado!</h3>
                <p>Tu comprobante ha sido enviado a revisión.</p>
                <a href="dashboard_socio.php?id_club=<?= htmlspecialchars($_SESSION['current_club'] ?? '') ?>" class="btn-submit" style="text-decoration:none; display:inline-block; margin-top:1rem;">Volver al Dashboard</a>
            </div>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <!-- Columna 1: Detalles -->
                    <div class="form-group full-width">
                        <label>Detalle del Pago</label>
                        <input type="text" value="<?= htmlspecialchars($cuota['detalle_origen']) ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label>Mes Correspondiente</label>
                        <input type="text" value="<?= htmlspecialchars($cuota['mes'] ?? date('F Y')) ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label>Fecha Vencimiento</label>
                        <input type="text" value="<?= date('d/m/Y', strtotime($cuota['fecha_vencimiento'])) ?>" readonly>
                    </div>

                    <!-- Columna 2: Montos -->
                    <div class="form-group">
                        <label>$ Valor Semana</label>
                        <input type="text" value="$<?= number_format($cuota['monto'], 0, ',', '.') ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label>$ Valor Mes</label>
                        <input type="text" value="$<?= number_format($cuota['valor_mes'] ?? $cuota['monto'], 0, ',', '.') ?>" readonly>
                    </div>

                    <!-- Selección Tipo Pago -->
                    <div class="form-group full-width">
                        <label>Tipo de Pago *</label>
                        <div class="radio-group">
                            <label class="radio-option">
                                <input type="radio" name="tipo_pago" value="semana" checked onchange="toggleMontoInput()"> Semana
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="tipo_pago" value="mes" onchange="toggleMontoInput()"> Mes Completo
                            </label>
                        </div>
                    </div>

                    <!-- Monto a Pagar (Dinámico) -->
                    <div class="form-group full-width">
                        <label for="monto_pagado">$ Monto a Pagar *</label>
                        <input type="number" id="monto_pagado" name="monto_pagado" step="100" required 
                               value="<?= $cuota['monto'] ?>" 
                               placeholder="Ingresa el monto exacto">
                        <small style="color:#666; font-size:0.8rem;">* Debe coincidir con el valor seleccionado arriba.</small>
                    </div>

                    <!-- Fecha Pago -->
                    <div class="form-group">
                        <label for="fecha_pago">Fecha de Pago *</label>
                        <input type="date" id="fecha_pago" name="fecha_pago" required value="<?= date('Y-m-d') ?>">
                    </div>

                    <!-- Adjunto -->
                    <div class="form-group">
                        <label for="adjunto">Comprobante (Opcional)</label>
                        <input type="file" id="adjunto" name="adjunto" accept=".jpg,.jpeg,.png,.pdf">
                    </div>

                    <!-- Comentario -->
                    <div class="form-group full-width">
                        <label for="comentario">Comentarios</label>
                        <textarea id="comentario" name="comentario" rows="2" placeholder="Ej: Transferencia desde Banco Estado..."></textarea>
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
            inputMonto.readOnly = true; // Forzamos que no editen para evitar errores
            inputMonto.style.background = '#e8f5e9';
        } else {
            inputMonto.value = valorSemana;
            inputMonto.readOnly = true;
            inputMonto.style.background = '#e8f5e9';
        }
    }
    
    // Inicializar
    toggleMontoInput();
</script>

</body>
</html>