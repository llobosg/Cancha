<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
session_start();

try {
    $slug = $_POST['slug'] ?? '';
    if (!$slug || strlen($slug) !== 8) {
        throw new Exception('Torneo no válido');
    }

    // Buscar torneo
    $stmt_torneo = $pdo->prepare("
        SELECT id_torneo, nivel, num_parejas_max 
        FROM torneos 
        WHERE slug = ? AND estado = 'abierto'
    ");
    $stmt_torneo->execute([$slug]);
    $torneo = $stmt_torneo->fetch();
    if (!$torneo) {
        throw new Exception('Torneo no encontrado o cerrado');
    }

    // Verificar cupo
    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM parejas_torneo WHERE id_torneo = ?");
    $stmt_count->execute([$torneo['id_torneo']]);
    if ($stmt_count->fetchColumn() >= $torneo['num_parejas_max']) {
        throw new Exception('Cupo lleno');
    }

    // Determinar tipo de jugador
    $es_socio = isset($_SESSION['id_socio']);
    $id_socio = $es_socio ? (int)$_SESSION['id_socio'] : null;
    $id_temporal = null;

    if (!$es_socio) {
        // Validar datos mínimos
        $nombre = trim($_POST['nombre'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if (!$nombre || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Nombre y email válidos son requeridos');
        }

        // Verificar si ya existe como temporal
        $stmt_check = $pdo->prepare("SELECT id_jugador FROM jugadores_temporales WHERE email = ?");
        $stmt_check->execute([$email]);
        $temp = $stmt_check->fetch();

        if ($temp) {
            $id_temporal = $temp['id_jugador'];
        } else {
            // Crear nuevo jugador temporal
            $token = hash('sha256', $email . time() . random_bytes(16));
            $pdo->prepare("
                INSERT INTO jugadores_temporales (nombre, email, token_registro)
                VALUES (?, ?, ?)
            ")->execute([$nombre, $email, $token]);
            $id_temporal = $pdo->lastInsertId();
        }
    }

    // Verificar que no esté ya inscrito
    $stmt_check_inscrito = $pdo->prepare("
        SELECT 1 FROM parejas_torneo 
        WHERE id_torneo = ? 
          AND (
            (id_socio_1 = ? OR id_socio_2 = ?) 
            OR (id_jugador_temp_1 = ? OR id_jugador_temp_2 = ?)
          )
    ");
    $stmt_check_inscrito->execute([
        $torneo['id_torneo'],
        $id_socio, $id_socio,
        $id_temporal, $id_temporal
    ]);
    if ($stmt_check_inscrito->fetch()) {
        throw new Exception('Ya estás inscrito en este torneo');
    }

    // Generar código de pareja
    $codigo_pareja = substr(md5(uniqid()), 0, 8);

    // Insertar inscripción
    if ($es_socio) {
        $pdo->prepare("
            INSERT INTO parejas_torneo (id_torneo, id_socio_1, codigo_pareja, estado)
            VALUES (?, ?, ?, 'esperando_pareja')
        ")->execute([$torneo['id_torneo'], $id_socio, $codigo_pareja]);
    } else {
        $pdo->prepare("
            INSERT INTO parejas_torneo (id_torneo, id_jugador_temp_1, codigo_pareja, estado)
            VALUES (?, ?, ?, 'esperando_pareja')
        ")->execute([$torneo['id_torneo'], $id_temporal, $codigo_pareja]);
    }

    echo json_encode([
        'success' => true,
        'redirect' => "/pages/torneo_pair.php?slug={$slug}&code={$codigo_pareja}"
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>