<?php
// api/get_inscritos_torneos.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Validar admin/responsable
if (!isset($_SESSION['id_recinto']) || !in_array($_SESSION['recinto_rol'] ?? '', ['admin', 'responsable'])) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$id_torneo = (int)($_GET['id_torneo'] ?? 0);
if (!$id_torneo) {
    echo json_encode(['error' => 'ID de torneo requerido']);
    exit;
}

try {
    // ✅ Consulta compatible con MySQL 5.7+ (sin ROW_NUMBER)
    $stmt = $pdo->prepare("
        SELECT 
            pt.id_pareja,
            pt.codigo_pareja,
            pt.created_at,
            -- Jugador 1 (principal)
            IFNULL(s1.nombre, '—') as jugador1,
            IFNULL(s1.email, '—') as contacto,
            IFNULL(s1.celular, '') as celular1,
            -- Jugador 2 (invitado)
            IFNULL(s2.nombre, '') as jugador2,
            IFNULL(s2.email, '') as email2
        FROM parejas_torneo pt
        LEFT JOIN socios s1 ON pt.id_socio_1 = s1.id_socio
        LEFT JOIN socios s2 ON pt.id_socio_2 = s2.id_socio
        WHERE pt.id_torneo = ?
        ORDER BY pt.created_at ASC
    ");
    $stmt->execute([$id_torneo]);
    $parejas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ✅ Transformar datos con numeración manual (compatible con MySQL 5.7)
    $resultado = [];
    $numero = 1;
    foreach ($parejas as $p) {
        $resultado[] = [
            'id_pareja' => $p['id_pareja'],
            'nombre_pareja' => '#' . $numero,
            'numero' => $numero,
            'jugador1' => $p['jugador1'],
            'contacto' => $p['contacto'],
            'jugador2' => !empty($p['jugador2']) ? $p['jugador2'] : '<em style="color:#888;">Pendiente</em>',
            'email2' => $p['email2'],
            'celular1' => $p['celular1']
        ];
        $numero++;
    }
    
    echo json_encode($resultado);
    
} catch (Exception $e) {
    error_log("Error get_inscritos_torneos: " . $e->getMessage());
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
}
?>