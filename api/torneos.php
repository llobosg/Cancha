<?php
// api/torneos.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

try {
    // Verificar autenticación
    if (!isset($_SESSION['id_recinto'])) {
        http_response_code(401);
        echo json_encode(['error' => 'No autorizado']);
        exit;
    }
    
    $id_recinto = (int)$_SESSION['id_recinto'];
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_activos':
            // Obtener torneos activos del recinto
            $stmt = $pdo->prepare("
                SELECT 
                    t.id_torneo, t.nombre, t.deporte, t.fecha_inicio, t.fecha_fin,
                    t.estado, t.max_inscritos, t.num_canchas,
                    COUNT(i.id_inscrito) as inscritos
                FROM torneos t
                LEFT JOIN inscritos i ON t.id_torneo = i.id_torneo
                WHERE t.id_recinto = ? 
                AND t.estado IN ('abierto', 'llenando', 'en_curso')
                AND t.fecha_fin >= CURDATE()
                GROUP BY t.id_torneo
                ORDER BY t.fecha_inicio ASC
            ");
            $stmt->execute([$id_recinto]);
            $torneos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode($torneos);
            break;
            
        default:
            throw new Exception('Acción no válida');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>