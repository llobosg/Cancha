<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
session_start();

try {
    $codigo = trim($_POST['codigo'] ?? '');
    if (strlen($codigo) !== 4 || !ctype_digit($codigo)) {
        throw new Exception('Código inválido. Debe tener 4 dígitos.');
    }

    // === Modo individual o club ===
    $modo_individual = isset($_POST['id_socio']);
    if ($modo_individual) {
        $id_socio = (int)$_POST['id_socio'];
        // Validar que el socio existe y es individual
        $stmt = $pdo->prepare("SELECT email FROM socios WHERE id_socio = ? AND id_club IS NULL");
        $stmt->execute([$id_socio]);
        $socio = $stmt->fetch();
        if (!$socio) {
            throw new Exception('Socio no encontrado o no es individual');
        }
        $email = $socio['email'];
    } else {
        // Modo club: ya validado en frontend
        $club_slug = $_POST['club_slug'] ?? '';
        // Aquí normalmente ya tendrías el socio activado previamente
        // Este flujo no aplica a torneos, así que lo dejamos como está
        echo json_encode(['success' => true, 'club_slug' => $club_slug]);
        exit;
    }

    // === Verificar código ===
    $stmt = $pdo->prepare("SELECT * FROM codigos_verificacion WHERE email = ? AND codigo = ? AND usado = 0 AND expira > NOW()");
    $stmt->execute([$email, $codigo]);
    $codigo_db = $stmt->fetch();

    if (!$codigo_db) {
        throw new Exception('Código incorrecto o expirado');
    }

    // === Activar cuenta ===
    $pdo->beginTransaction();

    // Marcar código como usado
    $pdo->prepare("UPDATE codigos_verificacion SET usado = 1 WHERE id_codigo = ?")->execute([$codigo_db['id_codigo']]);

    // Activar socio
    $pdo->prepare("UPDATE socios SET verificado = 1 WHERE email = ?")->execute([$email]);

    // === VINCULACIÓN AUTOMÁTICA AL TORNEO (si aplica) ===
    $torneo_slug = null;
    if (isset($_SESSION['torneo_slug_post_registro'])) {
        $torneo_slug = $_SESSION['torneo_slug_post_registro'];
        $torneo_code = $_SESSION['torneo_code_post_registro'] ?? '';

        // Obtener ID del torneo
        $stmt_torneo = $pdo->prepare("SELECT id_torneo FROM torneos WHERE slug = ?");
        $stmt_torneo->execute([$torneo_slug]);
        $id_torneo = $stmt_torneo->fetchColumn();

        if ($id_torneo) {
            // Buscar pareja pendiente con el código de invitación
            $stmt_pareja = $pdo->prepare("
                SELECT id_pareja 
                FROM parejas_torneo 
                WHERE codigo_pareja = ? AND estado = 'esperando_pareja'
            ");
            $stmt_pareja->execute([$torneo_code]);
            $pareja = $stmt_pareja->fetch();

            if ($pareja) {
                // Vincular al socio recién verificado
                $pdo->prepare("
                    UPDATE parejas_torneo 
                    SET id_socio_2 = ?, estado = 'completa'
                    WHERE id_pareja = ?
                ")->execute([$id_socio, $pareja['id_pareja']]);
            }
        }

        // Limpiar sesión
        unset($_SESSION['torneo_slug_post_registro']);
        unset($_SESSION['torneo_code_post_registro']);
    }

    $pdo->commit();

    // === Responder ===
    $response = ['success' => true];
    if ($torneo_slug) {
        $response['torneo_slug'] = $torneo_slug;
    }
    echo json_encode($response);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    error_log("Error en verificar_codigo_socio.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>