<?php
// api/login_unificado.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$input = json_decode(file_get_contents('php://input'), true);
$credencial = trim($input['credencial'] ?? '');
$password = $input['password'] ?? '';

if (empty($credencial) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Credencial y contraseña son requeridas']);
    exit;
}

try {
    // Determinar si es email o username
    $es_email = filter_var($credencial, FILTER_VALIDATE_EMAIL);
    
    // === BUSCAR EN SOCIOS (si es email) ===
    if ($es_email) {
        $stmt = $pdo->prepare("
            SELECT id_socio, password_hash, email, alias, 'socio' as tipo
            FROM socios 
            WHERE email = ? AND password_hash IS NOT NULL
        ");
        $stmt->execute([$credencial]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // Login exitoso como SOCIO
            $_SESSION['id_socio'] = $user['id_socio'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_alias'] = $user['alias'] ?? '';
            
            // Verificar si tiene clubs
            $stmt_clubes = $pdo->prepare("
                SELECT c.id_club, c.email_responsable 
                FROM socio_club sc 
                JOIN clubs c ON sc.id_club = c.id_club 
                WHERE sc.id_socio = ? AND sc.estado = 'activo' 
                LIMIT 1
            ");
            $stmt_clubes->execute([$user['id_socio']]);
            $primer_club = $stmt_clubes->fetch();
            
            if ($primer_club) {
                $club_slug = substr(md5($primer_club['id_club'] . $primer_club['email_responsable']), 0, 8);
                $_SESSION['club_id'] = $primer_club['id_club'];
                $_SESSION['current_club'] = $club_slug;
                
                echo json_encode([
                    'success' => true, 
                    'tipo' => 'socio',
                    'redirect' => 'pages/dashboard_socio.php?id_club=' . $club_slug
                ]);
            } else {
                echo json_encode([
                    'success' => true, 
                    'tipo' => 'socio',
                    'redirect' => 'pages/dashboard_socio.php'
                ]);
            }
            exit;
        }
    }
    
    // === BUSCAR EN ADMIN_RECINTOS (si NO es email, o si falló en socios) ===
    $stmt_recinto = $pdo->prepare("
        SELECT ar.id_admin, ar.id_recinto, ar.usuario, ar.contraseña, ar.rol, 
               rd.nombre as nombre_recinto, rd.email_verified
        FROM admin_recintos ar
        JOIN recintos_deportivos rd ON ar.id_recinto = rd.id_recinto
        WHERE ar.usuario = ? OR (ar.email = ? AND ?)
    ");
    // Si es email, también buscamos por email en admin_recintos
    $stmt_recinto->execute([$credencial, $credencial, $es_email ? 1 : 0]);
    $admin = $stmt_recinto->fetch();
    
    if ($admin && password_verify($password, $admin['contraseña'])) {
        if (!$admin['email_verified']) {
            echo json_encode(['success' => false, 'message' => 'Tu recinto no ha sido verificado. Revisa tu email.']);
            exit;
        }
        
        // Login exitoso como ADMIN RECINTO
        $_SESSION['id_recinto'] = $admin['id_recinto'];
        $_SESSION['id_admin'] = $admin['id_admin'];
        $_SESSION['recinto_usuario'] = $admin['usuario'];
        $_SESSION['nombre_recinto'] = $admin['nombre_recinto'];
        $_SESSION['recinto_rol'] = $admin['rol'] ?? 'admin';

        
        echo json_encode([
            'success' => true, 
            'tipo' => 'recinto',
            'redirect' => 'pages/recinto_dashboard.php'
        ]);
        exit;
    }
    
    // === NO SE ENCONTRÓ EN NINGUNA TABLA ===
    echo json_encode(['success' => false, 'message' => 'Credenciales incorrectas']);
    
} catch (Exception $e) {
    error_log("❌ Login unificado error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}
?>