<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
session_start();

try {
    // Verificar que sea responsable de club
    if (!isset($_SESSION['club_id']) || empty($_POST['id_socio'])) {
        throw new Exception('Acceso no autorizado');
    }

    $id_socio = (int)$_POST['id_socio'];
    $club_id = $_SESSION['club_id'];

    // Verificar que el socio pertenece al club
    $stmt_check = $pdo->prepare("SELECT id_socio FROM socios WHERE id_socio = ? AND id_club = ?");
    $stmt_check->execute([$id_socio, $club_id]);
    if (!$stmt_check->fetch()) {
        throw new Exception('Socio no encontrado o no pertenece a tu club');
    }

    // Iniciar transacción
    $pdo->beginTransaction();

    // Eliminar registros dependientes en orden correcto
    $tables_to_clean = [
        'jugadores_equipo',   // Tabla con FK a socios
        'inscritos',          // Inscripciones
        'cuotas',             // Cuotas pendientes/pagadas
        'suscripciones_push'  // Notificaciones push
    ];

    foreach ($tables_to_clean as $table) {
        $pdo->prepare("DELETE FROM {$table} WHERE id_socio = ?")->execute([$id_socio]);
    }

    // Finalmente, eliminar el socio
    $pdo->prepare("DELETE FROM socios WHERE id_socio = ?")->execute([$id_socio]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Socio eliminado correctamente']);

} catch (Exception $e) {
    if (isset($pdo)) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>