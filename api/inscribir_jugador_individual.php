<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
session_start();

try {
    if (!isset($_SESSION['id_socio'])) {
        throw new Exception('Acceso no autorizado');
    }

    $slug = $_GET['slug'] ?? '';
    if (!$slug || strlen($slug) !== 8) {
        throw new Exception('Torneo no válido');
    }

    $stmt = $pdo->prepare("SELECT id_torneo, nivel FROM torneos WHERE slug = ? AND estado = 'abierto'");
    $stmt->execute([$slug]);
    $torneo = $stmt->fetch();
    if (!$torneo) throw new Exception('Torneo no encontrado');

    $id_socio = $_SESSION['id_socio'];

    // Verificar categoría
    $stmt_socio = $pdo->prepare("SELECT id_puesto FROM socios WHERE id_socio = ?");
    $stmt_socio->execute([$id_socio]);
    $socio = $stmt_socio->fetch();
    if (!$socio['id_puesto']) {
        // Redirigir a completar perfil con categoría
        echo json_encode(['success' => false, 'redirect' => '/pages/completar_perfil.php?modo=individual&categoria=' . $torneo['nivel']]);
        exit;
    }

    // Verificar si ya está inscrito
    $stmt_check = $pdo->prepare("SELECT 1 FROM parejas_torneo WHERE id_torneo = ? AND (id_socio_1 = ? OR id_socio_2 = ?)");
    $stmt_check->execute([$torneo['id_torneo'], $id_socio, $id_socio]);
    if ($stmt_check->fetch()) {
        throw new Exception('Ya estás inscrito en este torneo');
    }

    // Generar código de pareja
    $codigo_pareja = substr(md5(uniqid()), 0, 8);

    // Insertar inscripción individual
    $pdo->prepare("
        INSERT INTO parejas_torneo (id_torneo, id_socio_1, codigo_pareja, estado)
        VALUES (?, ?, ?, 'esperando_pareja')
    ")->execute([$torneo['id_torneo'], $id_socio, $codigo_pareja]);

    // Redirigir a la página de invitación
    echo json_encode([
        'success' => true,
        'redirect' => "/torneo/{$slug}/pair?code={$codigo_pareja}"
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>