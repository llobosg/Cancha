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

$hoy = date('Y-m-d');

try {
    // === 1. INGRESOS DEL PERIODO (Pagados dentro del rango) ===
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
    // Mostramos lo que falta pagar de reservas que caen en este periodo y están parciales
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

    // === 3. EN RESERVA (Futuras no pagadas dentro del rango o futuras desde hoy) ===
    // Si el filtro es pasado, no hay reservas futuras.
    if ($fecha_fin < $hoy) {
        $monto_reserva = 0;
        $cant_reserva = 0;
    } else {
        // Reservas futuras no pagadas que caen dentro del rango de visualización
        // O desde hoy hasta el fin del filtro
        $inicio_busqueda = ($fecha_inicio > $hoy) ? $fecha_inicio : $hoy;
        
        $stmt_reserva = $pdo->prepare("
            SELECT COALESCE(SUM(r.monto_total), 0) as total, COUNT(*) as cant
            FROM reservas r
            JOIN canchas c ON r.id_cancha = c.id_cancha
            WHERE c.id_recinto = ?
            AND r.fecha >= ? 
            AND r.fecha <= ? 
            AND r.estado_pago IN ('pendiente', 'parcial')
            AND r.estado != 'cancelada'
        ");
        $stmt_reserva->execute([$id_recinto, $inicio_busqueda, $fecha_fin]);
        $row_reserva = $stmt_reserva->fetch(PDO::FETCH_ASSOC);
        $monto_reserva = $row_reserva['total'];
        $cant_reserva = $row_reserva['cant'];
    }

    // === 4. DEUDA VENCIDA (Corregida) ===
    // Lógica: Todas las reservas con fecha ANTERIOR A HOY que no estén pagadas totalmente.
    // Esto asegura que veas la deuda real acumulada, independientemente del filtro de ingresos.
    // Si quisieras deuda "del periodo", cambiarías la fecha límite por $fecha_inicio, pero eso oculta deuda antigua.
    
    $stmt_deuda = $pdo->prepare("
        SELECT COALESCE(SUM(r.monto_total), 0) as total
        FROM reservas r
        JOIN canchas c ON r.id_cancha = c.id_cancha
        WHERE c.id_recinto = ?
        AND r.fecha < ?  -- Fecha anterior a hoy
        AND r.estado_pago IN ('pendiente', 'parcial')
        AND r.estado != 'cancelada'
    ");
    $stmt_deuda->execute([$id_recinto, $hoy]);
    $deuda = $stmt_deuda->fetchColumn();

    // Cantidad de deudas (opcional, para el footer)
    $stmt_count_deuda = $pdo->prepare("
        SELECT COUNT(*) FROM reservas r
        JOIN canchas c ON r.id_cancha = c.id_cancha
        WHERE c.id_recinto = ? 
        AND r.fecha < ?
        AND r.estado_pago IN ('pendiente', 'parcial') 
        AND r.estado != 'cancelada'
    ");
    $stmt_count_deuda->execute([$id_recinto, $hoy]);
    $cant_deuda = $stmt_count_deuda->fetchColumn();

    echo json_encode([
        'success' => true,
        'data' => [
            'ingresos' => floatval($ingresos),
            'pendiente' => floatval($pendiente),
            'reserva_monto' => floatval($monto_reserva),
            'reserva_cant' => intval($cant_reserva),
            'deuda' => floatval($deuda),
            'deuda_cant' => intval($cant_deuda)
        ]
    ]);

} catch (Exception $e) {
    error_log("Error KPIs Financieros: " . $e->getMessage());
    echo json_encode(['error' => 'Error al calcular KPIs: ' . $e->getMessage()]);
}
?>