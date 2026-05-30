<?php
// api/get_kpis_financieros.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['id_recinto'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit;
}

$id_recinto = $_SESSION['id_recinto'];

// Recibir filtros
$fecha_inicio = $_GET['fecha_inicio'] ?? null;
$fecha_fin = $_GET['fecha_fin'] ?? null;

// Default: Mes Actual si no vienen fechas
if (!$fecha_inicio || !$fecha_fin) {
    $fecha_inicio = date('Y-m-01');
    $fecha_fin = date('Y-m-t');
}

try {
    // === 1. INGRESOS DEL PERIODO (Pagados dentro del rango de fechas de la reserva) ===
    $stmt_ingresos = $pdo->prepare("
        SELECT COALESCE(SUM(r.monto_total), 0) as total
        FROM reservas r
        JOIN canchas c ON r.id_cancha = c.id_cancha
        WHERE c.id_recinto = ?
        AND r.fecha BETWEEN ? AND ?
        AND r.estado_pago = 'pagado'
        AND r.estado != 'cancelada'
    ");
    $stmt_ingresos->execute([$id_recinto, $fecha_inicio, $fecha_fin]);
    $ingresos = $stmt_ingresos->fetchColumn();

    // === 2. SALDO PENDIENTE (Parciales dentro del rango) ===
    $stmt_pendiente = $pdo->prepare("
        SELECT COALESCE(SUM(r.monto_total - r.monto_recaudacion), 0) as total
        FROM reservas r
        JOIN canchas c ON r.id_cancha = c.id_cancha
        WHERE c.id_recinto = ?
        AND r.fecha BETWEEN ? AND ?
        AND r.estado_pago = 'parcial'
        AND r.estado != 'cancelada'
    ");
    $stmt_pendiente->execute([$id_recinto, $fecha_inicio, $fecha_fin]);
    $pendiente = $stmt_pendiente->fetchColumn();

    // === 3. EN RESERVA (Futuras no pagadas) ===
    // Lógica: Reservas cuya fecha esté entre Hoy y Fecha Fin del filtro.
    // Si el filtro es pasado, no hay reservas "futuras" en ese rango.
    $hoy = date('Y-m-d');
    
    // Si la fecha fin del filtro es pasada, no hay reservas futuras en ese periodo
    if ($fecha_fin < $hoy) {
        $monto_reserva = 0;
        $cant_reserva = 0;
    } else {
        // Usamos la mayor entre 'Hoy' y 'Fecha Inicio Filtro' para definir el inicio de la búsqueda
        $inicio_busqueda = ($fecha_inicio > $hoy) ? $fecha_inicio : $hoy;

        $stmt_reserva = $pdo->prepare("
            SELECT COALESCE(SUM(r.monto_total), 0) as total, COUNT(*) as cant
            FROM reservas r
            JOIN canchas c ON r.id_cancha = c.id_cancha
            WHERE c.id_recinto = ?
            AND r.fecha >= ? 
            AND r.fecha <= ? -- Respetamos el límite superior del filtro
            AND r.estado_pago IN ('pendiente', 'parcial')
            AND r.estado != 'cancelada'
        ");
        $stmt_reserva->execute([$id_recinto, $inicio_busqueda, $fecha_fin]);
        $row_reserva = $stmt_reserva->fetch(PDO::FETCH_ASSOC);
        $monto_reserva = $row_reserva['total'];
        $cant_reserva = $row_reserva['cant'];
    }

    // === 4. DEUDA VENCIDA (Pasadas no pagadas antes del inicio del filtro) ===
    // Lógica: Reservas con fecha anterior a la fecha de inicio del filtro que no están pagadas.
    $fecha_limite_deuda = date('Y-m-d', strtotime($fecha_inicio . ' -1 day'));
    
    $stmt_deuda = $pdo->prepare("
        SELECT COALESCE(SUM(r.monto_total), 0) as total
        FROM reservas r
        JOIN canchas c ON r.id_cancha = c.id_cancha
        WHERE c.id_recinto = ?
        AND r.fecha <= ?
        AND r.estado_pago IN ('pendiente', 'parcial')
        AND r.estado != 'cancelada'
    ");
    $stmt_deuda->execute([$id_recinto, $fecha_limite_deuda]);
    $deuda = $stmt_deuda->fetchColumn();

    // Consultas adicionales para contar cantidades (opcional pero recomendado para performance)
    // Cantidad Deuda Vencida
    $stmt_count_deuda = $pdo->prepare("
        SELECT COUNT(*) FROM reservas r
        JOIN canchas c ON r.id_cancha = c.id_cancha
        WHERE c.id_recinto = ? AND r.fecha <= ? 
        AND r.estado_pago IN ('pendiente', 'parcial') AND r.estado != 'cancelada'
    ");
    $stmt_count_deuda->execute([$id_recinto, $fecha_limite_deuda]);
    $cant_deuda = $stmt_count_deuda->fetchColumn();

    echo json_encode([
        'success' => true,
        'data' => [
            'ingresos' => floatval($ingresos),
            'pendiente' => floatval($pendiente),
            'reserva_monto' => floatval($monto_reserva),
            'reserva_cant' => intval($cant_reserva), // ✅ FIX
            'deuda' => floatval($deuda),
            'deuda_cant' => intval($cant_deuda)
        ]
    ]);

} catch (Exception $e) {
    error_log("Error KPIs Financieros: " . $e->getMessage());
    echo json_encode(['error' => 'Error al calcular KPIs: ' . $e->getMessage()]);
}
?>