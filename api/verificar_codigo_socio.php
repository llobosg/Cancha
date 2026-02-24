<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $codigo = $_POST['codigo'] ?? '';
    if (strlen($codigo) !== 4 || !ctype_digit($codigo)) {
        throw new Exception('Código inválido');
    }

    // Iniciar sesión para guardar datos
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Determinar modo
    $modo_individual = isset($_POST['id_socio']);
    
    if ($modo_individual) {
        $id_socio = $_POST['id_socio'] ?? null;
        if (!$id_socio || !is_numeric($id_socio)) {
            throw new Exception('Socio no válido');
        }
        
        // Verificar código para socio individual
        $stmt = $pdo->prepare("
            SELECT id_socio, email, email_verified 
            FROM socios 
            WHERE id_socio = ? AND verification_code = ? AND email_verified = 0
        ");
        $stmt->execute([$id_socio, $codigo]);
        $socio = $stmt->fetch();
        
        if (!$socio) {
            throw new Exception('Código incorrecto o ya verificado');
        }
        
        // Actualizar verificación
        $stmt = $pdo->prepare("UPDATE socios SET email_verified = 1 WHERE id_socio = ?");
        $stmt->execute([$id_socio]);
        
        // Guardar en sesión
        $_SESSION['id_socio'] = $id_socio;
        $_SESSION['user_email'] = $socio['email'];
        $_SESSION['modo_individual'] = true;
        $_SESSION['club_id'] = null;
        $_SESSION['current_club'] = null;
        
        $response_data = [
            'success' => true,
            'id_socio' => $id_socio,
            'club_slug' => '' // Sin club en modo individual
        ];
        
    } else {
        $club_slug = $_POST['club_slug'] ?? '';
        if (strlen($club_slug) !== 8 || !ctype_alnum($club_slug)) {
            throw new Exception('Club no válido');
        }
        
        // Obtener id_club desde slug
        $stmt = $pdo->prepare("SELECT id_club, email_responsable FROM clubs WHERE email_verified = 1");
        $stmt->execute();
        $clubs = $stmt->fetchAll();
        $id_club = null;
        
        foreach ($clubs as $c) {
            if (substr(md5($c['id_club'] . $c['email_responsable']), 0, 8) === $club_slug) {
                $id_club = $c['id_club'];
                break;
            }
        }
        
        if (!$id_club) {
            throw new Exception('Club no encontrado');
        }
        
        // Verificar código para socio de club
        $stmt = $pdo->prepare("
            SELECT s.id_socio, s.email, s.email_verified 
            FROM socios s
            WHERE s.id_club = ? AND s.verification_code = ? AND s.email_verified = 0
        ");
        $stmt->execute([$id_club, $codigo]);
        $socio = $stmt->fetch();
        
        if (!$socio) {
            throw new Exception('Código incorrecto o ya verificado');
        }
        
        // Actualizar verificación
        $stmt = $pdo->prepare("UPDATE socios SET email_verified = 1 WHERE id_socio = ?");
        $stmt->execute([$socio['id_socio']]);
        
        // Guardar en sesión
        $_SESSION['id_socio'] = $socio['id_socio'];
        $_SESSION['user_email'] = $socio['email'];
        $_SESSION['modo_individual'] = false;
        $_SESSION['club_id'] = $id_club;
        $_SESSION['current_club'] = $club_slug;
        
        $response_data = [
            'success' => true,
            'id_socio' => $socio['id_socio'],
            'club_slug' => $club_slug
        ];
    }
    
    echo json_encode($response_data);
    
} catch (Exception $e) {
    error_log("Verificación código socio error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>