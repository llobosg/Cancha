<?php
// api/gestion_eventos.php
// ✅ Sin output buffering para evitar conflictos con JSON

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';

try {
    // Validar sesión básica
    if (!isset($_SESSION['id_socio'])) {
        throw new Exception('No autorizado');
    }

    $action = $_POST['action'] ?? '';
    $id_socio_actual = $_SESSION['id_socio'];
    $id_club = $_SESSION['club_id'] ?? null;

    // === ACCIÓN: BAJARSE ===
    if ($action === 'bajarse') {
        $id_actividad = (int)($_POST['id_actividad'] ?? 0);
        $tipo_actividad = $_POST['tipo_actividad'] ?? 'reserva';
        
        // Determinar qué socio bajar
        $id_socio_a_bajar = isset($_POST['id_socio_objetivo']) && !empty($_POST['id_socio_objetivo']) 
            ? (int)$_POST['id_socio_objetivo'] 
            : $id_socio_actual;

        // Verificar permisos si es un responsable bajando a otro
        if ($id_socio_a_bajar !== $id_socio_actual && $id_club) {
            // ✅ Validar permiso usando tabla socio_club
            $stmt_check = $pdo->prepare("
                SELECT s.es_responsable 
                FROM socios s
                JOIN socio_club sc ON s.id_socio = sc.id_socio
                WHERE s.id_socio = ? AND sc.id_club = ? AND sc.estado = 'activo'
            ");
            $stmt_check->execute([$id_socio_actual, $id_club]);
            $es_resp = $stmt_check->fetchColumn();

            if (!$es_resp) {
                throw new Exception('No tienes permisos para bajar a otros jugadores');
            }
        }

        // Ejecutar baja en inscritos (sin id_club)
        $stmt_delete = $pdo->prepare("
            DELETE FROM inscritos 
            WHERE id_evento = ? AND id_socio = ? AND tipo_actividad = ?
        ");
        $stmt_delete->execute([$id_actividad, $id_socio_a_bajar, $tipo_actividad]);
        
        // Borrar cuota asociada si existe
        $stmt_cuota = $pdo->prepare("
            DELETE FROM cuotas 
            WHERE id_evento = ? AND id_socio = ? AND tipo_actividad = ?
        ");
        $stmt_cuota->execute([$id_actividad, $id_socio_a_bajar, $tipo_actividad]);
        
        // ✅ Salir inmediatamente después del JSON
        echo json_encode(['success' => true, 'message' => 'Baja registrada']);
        exit;
        
    } 
    // === ACCIÓN: ANOTARSE (se mantiene tu lógica existente) ===
    elseif ($action === 'anotarse') {
        // ... tu lógica existente de anotarse ...
        // Asegúrate de que al final también haga: echo json_encode(...); exit;
        echo json_encode(['success' => true, 'message' => 'Inscripción confirmada']);
        exit;
    }
    else {
        throw new Exception('Acción no válida');
    }

} catch (Exception $e) {
    error_log("Error gestion_eventos: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
?>