<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';

$input = json_decode(file_get_contents('php://input'), true);
$id_evento = $input['id_evento'] ?? null;

if (!$id_evento) {
    echo json_encode(['success' => false, 'message' => 'ID de evento requerido']);
    exit;
}

// ✅ VALIDACIÓN: Mínimo 12 inscritos
$stmt_count = $pdo->prepare("SELECT COUNT(*) FROM inscritos WHERE id_evento = ? AND tipo_actividad = 'reserva'");
$stmt_count->execute([$id_evento]);
$total = (int)$stmt_count->fetchColumn();

if ($total < 12) {
    echo json_encode(['success' => false, 'message' => "Se necesitan 12 jugadores para activar la IA. Hay $total."]);
    exit;
}

// Obtener inscritos con datos de perfil
$stmt = $pdo->prepare("
    SELECT s.nombre, s.id_socio, i.posicion, i.nivel 
    FROM inscritos i 
    JOIN socios s ON i.id_socio = s.id_socio 
    WHERE i.id_evento = ? AND i.tipo_actividad = 'reserva'
    ORDER BY RAND()
");
$stmt->execute([$id_evento]);
$jugadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// === ALGORITMO DE DISTRIBUCIÓN (Balanceo por nivel/posición) ===
// Aquí puedes integrar tu lógica de IA existente o usar distribución aleatoria balanceada
$num_equipos = floor(count($jugadores) / 6); // Ej: 2 equipos de 6, o 3 de 4, etc.
$equipos = [];

for ($i = 0; $i < $num_equipos; $i++) {
    $equipos[] = [
        'nombre' => 'Equipo ' . ($i + 1),
        'jugadores' => array_splice($jugadores, 0, ceil(count($jugadores) / ($num_equipos - $i)))
    ];
}

echo json_encode(['success' => true, 'equipos' => $equipos]);
?>