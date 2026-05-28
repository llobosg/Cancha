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

// Recibir filtros desde el frontend
$fecha_inicio = $_GET['fecha_inicio'] ?? null;
$fecha_fin = $_GET['fecha_fin'] ?? null;

// Si no hay filtros, usamos el mes actual por defecto (comportamiento original)
if (!$fecha_inicio || !$fecha_fin) {
    $fecha_inicio = date('Y-m-01');
    $fecha_fin = date('Y-m-t');
}

try {
    // === 1. INGRESOS DEL PERIODO (Pagados) ===
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

    // === 2. SALDO PENDIENTE (Pagos Parciales dentro del periodo o acumulados hasta la fecha fin) ===
    // Nota: El saldo pendiente suele ser acumulado, pero si queremos ver lo generado en el periodo:
    // Usaremos la lógica de "monto total - monto recaudado" para reservas creadas o jugadas en el periodo.
    // Para simplificar y dar valor real al dueño, mostraremos el saldo pendiente de todas las reservas 
    // cuya fecha esté dentro del rango seleccionado.
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

    // === 3. EN RESERVA (Futuras no pagadas dentro del rango o futuras desde hoy hasta fecha_fin) ===
    // Interpretación: Reservas futuras (desde hoy) que estén dentro del rango de visión o simplemente futuras.
    // Ajustemos para que respete el filtro: Reservas con fecha entre inicio y fin, no pagadas totalmente.
    $hoy = date('Y-m-d');
    // Si el filtro es hacia el futuro, usamos la fecha de inicio del filtro si es mayor a hoy, sino hoy.
    $fecha_base_reserva = ($fecha_inicio > $hoy) ? $fecha_inicio : $hoy;
    
    $stmt_reserva = $pdo->prepare("
        SELECT COALESCE(SUM(r.monto_total), 0) as total, COUNT(*) as cant
        FROM reservas r
        JOIN canchas c ON r.id_cancha = c.id_cancha
        WHERE c.id_recinto = ?
        AND r.fecha >= ? 
        AND r.fecha <= ? -- Limitamos al final del filtro si existe
        AND r.estado_pago IN ('pendiente', 'parcial')
        AND r.estado != 'cancelada'
    ");
    // Si fecha_fin es pasada, no hay reservas "futuras" en ese rango, devolvemos 0
    if ($fecha_fin < $hoy) {
        $monto_reserva = 0;
        $cant_reserva = 0;
    } else {
        $stmt_reserva->execute([$id_recinto, $fecha_base_reserva, $fecha_fin]);
        $row_reserva = $stmt_reserva->fetch(PDO::FETCH_ASSOC);
        $monto_reserva = $row_reserva['total'];
        $cant_reserva = $row_reserva['cant'];
    }

    // === 4. DEUDA VENCIDA (Pasadas no pagadas dentro del rango o anteriores a fecha_inicio) ===
    // Interpretación: Reservas con fecha anterior a la fecha de inicio del filtro que no están pagadas.
    // O si el filtro es un día específico, deudas de días anteriores a ese día.
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

    echo json_encode([
        'success' => true,
        'data' => [
            'ingresos' => floatval($ingresos),
            'pendiente' => floatval($pendiente),
            'reserva_monto' => floatval($monto_reserva),
            'reserva_cant' => intval($cant_reserva),
            'deuda' => floatval($deuda)
        ]
    ]);

} catch (Exception $e) {
    error_log("Error KPIs Financieros: " . $e->getMessage());
    echo json_encode(['error' => 'Error al calcular KPIs']);
}
?>