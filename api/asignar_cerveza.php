<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
session_start();

try {
    // Validar sesión y parámetros
    if (!isset($_SESSION['club_id']) || empty($_POST['id_inscrito'])) {
        throw new Exception('Acceso no autorizado');
    }

    $id_inscrito = (int)$_POST['id_inscrito'];
    $lleva_cerveza = (int)($_POST['lleva_cerveza'] ?? 0);
    $club_id = $_SESSION['club_id'];

    // Verificar que el inscrito pertenece al club
    $stmt_check = $pdo->prepare("
        SELECT i.id_inscrito 
        FROM inscritos i
        JOIN reservas r ON i.id_evento = r.id_reserva
        WHERE i.id_inscrito = ? AND r.id_club = ?
    ");
    $stmt_check->execute([$id_inscrito, $club_id]);
    if (!$stmt_check->fetch()) {
        throw new Exception('Inscripción no encontrada o no pertenece a tu club');
    }

    // Actualizar estado
    $pdo->prepare("UPDATE inscritos SET lleva_cerveza = ? WHERE id_inscrito = ?")
         ->execute([$lleva_cerveza, $id_inscrito]);

    // Opcional: enviar correo si se asigna
    if ($lleva_cerveza) {
        // Obtener email del socio
        $stmt_email = $pdo->prepare("
            SELECT s.email, s.alias
            FROM inscritos i
            JOIN socios s ON i.id_socio = s.id_socio
            WHERE i.id_inscrito = ?
        ");
        $stmt_email->execute([$id_inscrito]);
        $socio = $stmt_email->fetch();
        
        if ($socio && !empty($socio['email'])) {
            $subject = '🍻 ¡Te toca llevar cervezas!';
            $message = "Hola {$socio['alias']},\n\nEl responsable de tu club te ha asignado llevar cervezas al próximo partido.\n\n¡Gracias por tu aporte!";
            mail($socio['email'], $subject, $message);
        }
    }

    echo json_encode(['success' => true, 'message' => 'Asignación actualizada']);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>