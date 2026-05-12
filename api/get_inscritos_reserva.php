<?php
// api/get_inscritos_reserva.php - VERSIÓN FINAL CON ORDEN ASCENDENTE
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['id_socio']) && !isset($_SESSION['id_recinto'])) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$id_reserva = (int)($_GET['id_reserva'] ?? 0);

if (!$id_reserva) {
    echo json_encode(['error' => 'ID de reserva requerido']);
    exit;
}

try {
    // 1. Obtener el límite de jugadores esperado
    $stmt_limit = $pdo->prepare("SELECT jugadores_esperados FROM reservas WHERE id_reserva = ?");
    $stmt_limit->execute([$id_reserva]);
    $reserva_data = $stmt_limit->fetch(PDO::FETCH_ASSOC);
    $limite_cupos = (int)($reserva_data['jugadores_esperados'] ?? 0);

    // 2. Consultar inscritos ORDENADOS POR FECHA DE INSCRIPCIÓN (ASCENDENTE)
    // Esto pone al PRIMERO inscrito ARRIBA
    $stmt = $pdo->prepare("
        SELECT
            i.id_inscrito,
            i.id_socio,
            s.alias AS nombre,
            s.nombre AS nombre_completo,
            i.equipo,
            i.posicion_jugador,
            i.lleva_cerveza,
            i.created_at as fecha_inscripcion,
            r.fecha,
            r.hora_inicio,
            c.monto AS cuota_monto,
            c.estado AS estado_cuota,
            c.comentario
        FROM reservas r
        JOIN inscritos i ON r.id_reserva = i.id_evento AND i.tipo_actividad = 'reserva'
        JOIN socios s ON i.id_socio = s.id_socio
        LEFT JOIN cuotas c ON r.id_reserva = c.id_evento 
            AND i.id_socio = c.id_socio 
            AND c.tipo_actividad = 'reserva'
        WHERE r.id_reserva = ?
        ORDER BY i.created_at ASC -- ✅ CLAVE: Primer inscrito primero
    ");
    
    $stmt->execute([$id_reserva]);
    $inscritos_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 3. Procesar datos para determinar Estado (Confirmado vs Espera)
    $output = [];
    
    foreach ($inscritos_raw as $index => $row) {
        // Calcular posición basada en el índice del array (que ya está ordenado ASC)
        // Índice 0 = Posición 1 (El primero en inscribirse)
        $posicion_actual = $index + 1;
        
        // Determinar estado según el límite
        $estado = 'confirmado';
        if ($limite_cupos > 0 && $posicion_actual > $limite_cupos) {
            $estado = 'espera';
        }

        $output[] = [
            'id_inscrito' => $row['id_inscrito'],
            'id_socio' => $row['id_socio'],
            'nombre' => $row['nombre'] ?? $row['nombre_completo'] ?? 'Sin nombre',
            'equipo' => $row['equipo'] ?? '-',
            'posicion' => $row['posicion_jugador'] ?? '-',
            'lleva_cerveza' => (bool)$row['lleva_cerveza'],
            'cuota_monto' => (float)($row['cuota_monto'] ?? 0),
            'estado_cuota' => $row['estado_cuota'] ?? 'pendiente',
            'comentario' => $row['comentario'] ?? '',
            
            // ✅ NUEVOS CAMPOS PARA EL FRONTEND
            'fecha_inscripcion' => date('d/m/Y H:i', strtotime($row['fecha_inscripcion'])),
            'posicion_en_lista' => $posicion_actual,
            'estado_inscripcion' => $estado // 'confirmado' o 'espera'
        ];
    }
    
    echo json_encode($output);
    
} catch (PDOException $e) {
    error_log("❌ Error get_inscritos_reserva: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno: ' . $e->getMessage()]);
}
?>