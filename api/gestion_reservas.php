<?php
// api/gestion_reservas.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Validar sesión
if (!isset($_SESSION['id_recinto'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$id_recinto = (int)$_SESSION['id_recinto'];
$action = $_POST['action'] ?? '';

try {
    if ($action === 'crear_manual') {
        // === CREAR RESERVA MANUAL ===
        
        // 1. Obtener datos del POST
        $id_cancha = (int)($_POST['id_cancha'] ?? 0);
        $fecha = $_POST['fecha'] ?? '';
        $hora_inicio = $_POST['hora_inicio'] ?? '';
        $hora_fin = $_POST['hora_fin'] ?? '';
        $id_socio = !empty($_POST['id_socio']) ? (int)$_POST['id_socio'] : null;
        $usuario_creacion = trim($_POST['usuario_creacion'] ?? $_SESSION['recinto_usuario'] ?? 'Admin');
        
        // Datos opcionales para nuevo socio
        $nuevo_nombre = trim($_POST['nombreNuevoSocio'] ?? '');
        $nuevo_email = trim($_POST['emailNuevoSocio'] ?? '');
        $nuevo_tel = trim($_POST['telNuevoSocio'] ?? '');
        
        // Validaciones básicas
        if (!$id_cancha || !$fecha || !$hora_inicio || !$hora_fin) {
            throw new Exception("Faltan datos obligatorios (cancha, fecha, hora)");
        }

        // 2. Calcular duración y precio
        $hIni = strtotime($hora_inicio);
        $hFin = strtotime($hora_fin);
        $duracion_min = ($hFin - $hIni) / 60;
        
        if ($duracion_min <= 0) {
            throw new Exception("Hora fin debe ser posterior a hora inicio");
        }

        // Obtener precio base de la cancha
        $stmt_cancha = $pdo->prepare("SELECT valor_arriendo FROM canchas WHERE id_cancha = ? AND id_recinto = ?");
        $stmt_cancha->execute([$id_cancha, $id_recinto]);
        $cancha = $stmt_cancha->fetch();
        
        if (!$cancha) {
            throw new Exception("Cancha no encontrada o no pertenece a este recinto");
        }

        $precio_hora = (float)$cancha['valor_arriendo'];
        $horas = $duracion_min / 60;
        $monto_base = $precio_hora * $horas;
        $monto_total = $monto_base; // Inicialmente igual al base

        // 3. Manejar Socio (Existente o Nuevo)
        $final_id_socio = $id_socio;
        
        if (!$final_id_socio && !empty($nuevo_email)) {
            // Verificar si ya existe
            $stmt_check = $pdo->prepare("SELECT id_socio FROM socios WHERE email = ?");
            $stmt_check->execute([$nuevo_email]);
            $existing = $stmt_check->fetch();
            
            if ($existing) {
                $final_id_socio = $existing['id_socio'];
            } else {
                // Crear nuevo socio
                $stmt_insert = $pdo->prepare("
                    INSERT INTO socios (nombre, alias, email, celular, rol, activo, email_verified, created_at) 
                    VALUES (?, ?, ?, ?, 'Jugador', 'Si', 0, NOW())
                ");
                $alias = explode(' ', $nuevo_nombre)[0] ?? substr($nuevo_email, 0, 5);
                $stmt_insert->execute([
                    $nuevo_nombre ?: 'Sin Nombre', 
                    $alias, 
                    $nuevo_email, 
                    $nuevo_tel ?: ''
                ]);
                $final_id_socio = (int)$pdo->lastInsertId();
            }
        }

        // 4. Aplicar Descuento de Convenio SI EXISTE SOCIO
        if ($final_id_socio) {
            $stmt_conv = $pdo->prepare("
                SELECT c.porc_dscto 
                FROM socios s
                JOIN convenios c ON s.id_convenio = c.id_convenio
                WHERE s.id_socio = ? 
                AND c.estado = 'activo'
                AND (c.vigente_hasta IS NULL OR c.vigente_hasta >= CURDATE())
                LIMIT 1
            ");
            $stmt_conv->execute([$final_id_socio]);
            $conv = $stmt_conv->fetch();
            
            if ($conv && $conv['porc_dscto'] > 0) {
                $descuento = $monto_base * ($conv['porc_dscto'] / 100);
                $monto_total = $monto_base - $descuento;
            }
        }

        // 5. Insertar Reserva
        $stmt_reserva = $pdo->prepare("
            INSERT INTO reservas (
                id_cancha, id_socio, nombre_cliente, email_cliente, telefono_cliente,
                fecha, hora_inicio, hora_fin, monto_total, estado_pago, estado, usuario_creacion, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', 'confirmada', ?, NOW())
        ");
        
        // Si hay socio, intentar obtener sus datos de contacto
        $cliente_nom = 'Reserva Manual';
        $cliente_mail = '';
        $cliente_tel = '';
        
        if ($final_id_socio) {
            $stmt_socio_data = $pdo->prepare("SELECT nombre, email, celular FROM socios WHERE id_socio = ?");
            $stmt_socio_data->execute([$final_id_socio]);
            $socio_data = $stmt_socio_data->fetch();
            if ($socio_data) {
                $cliente_nom = $socio_data['nombre'];
                $cliente_mail = $socio_data['email'];
                $cliente_tel = $socio_data['celular'];
            }
        }

        $stmt_reserva->execute([
            $id_cancha,
            $final_id_socio,
            $cliente_nom,
            $cliente_mail,
            $cliente_tel,
            $fecha,
            $hora_inicio,
            $hora_fin,
            $monto_total,
            $usuario_creacion
        ]);
        
        $id_reserva = $pdo->lastInsertId();

        echo json_encode([
            'success' => true, 
            'message' => 'Reserva creada correctamente',
            'id_reserva' => $id_reserva,
            'monto_final' => $monto_total
        ]);

    } else {
        throw new Exception("Acción no reconocida: " . $action);
    }

} catch (Exception $e) {
    error_log("❌ Error gestión_reservas: " . $e->getMessage());
    http_response_code(400); // Código HTTP válido
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>