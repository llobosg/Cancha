<?php
// api/enviar_codigo_socio.php - Versión Streaming Puro (Sin Buffers)

// Configuración inmediata de errores y cabeceras
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/config.php';

// Respuesta por defecto en caso de fallo catastrófico
http_response_code(500);
$final_response = ['success' => false, 'message' => 'Error interno no capturado'];

try {
    // 1. Validar Método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    // 2. Logs de Debug (Solo en servidor, no afecta output)
    error_log(" [DEBUG] Inicio proceso registro");

    // 3. Validar Campos Obligatorios
    $required = ['nombre', 'alias', 'email', 'genero', 'rol', 'password', 'password_confirm', 'deporte'];
    foreach ($required as $field) {
        if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
            throw new Exception("Falta campo obligatorio: $field");
        }
    }

    // 4. Recoger Datos
    $nombre = trim($_POST['nombre']);
    $alias = trim($_POST['alias']);
    $email = trim($_POST['email']);
    $genero = $_POST['genero'];
    $rol = $_POST['rol'];
    $deporte = $_POST['deporte'];
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    $club_slug = $_POST['club_slug'] ?? '';
    
    // Datos con defaults
    $fecha_nac = $_POST['fecha_nac'] ?? '2000-01-01';
    $celular = trim($_POST['celular'] ?? '+56900000000');
    $direccion = trim($_POST['direccion'] ?? 'Pendiente');
    $pais = $_POST['pais'] ?? 'Chile';
    $region = $_POST['region'] ?? 'Metropolitana';
    $ciudad = $_POST['ciudad'] ?? 'Santiago';
    $comuna = $_POST['comuna'] ?? 'Ñuñoa';
    $habilidad = $_POST['habilidad'] ?? 'Intermedia';
    $id_puesto = !empty($_POST['id_puesto']) ? (int)$_POST['id_puesto'] : 1;

    // Validaciones básicas
    if ($password !== $password_confirm) throw new Exception('Contraseñas no coinciden');
    if (strlen($password) < 6) throw new Exception('Contraseña muy corta');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Email inválido');

    // Verificar email duplicado
    $stmt_check = $pdo->prepare("SELECT id_socio FROM socios WHERE email = ? LIMIT 1");
    $stmt_check->execute([$email]);
    if ($stmt_check->fetch()) throw new Exception('Email ya registrado');

    // 5. Preparar Hash y Código
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $verification_code = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    $modo_individual = empty($club_slug);
    $id_club = null;

    // Lógica Club (simplificada)
    if (!$modo_individual) {
        // Aquí iría tu lógica de buscar club por slug. 
        // Si falla, lanzamos excepción o lo tratamos como individual.
        // Por simplicidad en este debug, asumimos individual si no encontramos club válido rápido.
        $id_club = null; 
        $modo_individual = true; 
    }

    // 6. INSERTAR EN BD
    $stmt_insert = $pdo->prepare("
        INSERT INTO socios (
            nombre, alias, fecha_nac, celular, email, direccion, pais, region, ciudad, comuna,
            rol, genero, deporte, id_puesto, habilidad, activo, email_verified, verification_code,
            es_responsable, datos_completos, password_hash
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Si', 0, ?, 0, 1, ?)
    ");
    
    $stmt_insert->execute([
        $nombre, $alias, $fecha_nac, $celular, $email, $direccion, $pais, $region, $ciudad, $comuna,
        $rol, $genero, $deporte, $id_puesto, $habilidad, $verification_code, $password_hash
    ]);

    $id_socio = $pdo->lastInsertId();
    error_log("✅ Socio insertado ID: $id_socio");

    // 7. ENVIAR CORREO
    $mail_sent = false;
    try {
        require_once __DIR__ . '/../includes/brevo_mailer.php';
        $mailer = new BrevoMailer();
        $mailer->setTo($email, $nombre);
        $mailer->setSubject('🔐 Tu código de verificación');
        $mailer->setHtmlBody("<h1>Código: <b>$verification_code</b></h1><p>Activa tu cuenta.</p>");
        
        if ($mailer->send()) {
            $mail_sent = true;
            error_log("✅ Correo enviado");
        } else {
            error_log("⚠️ Brevo retornó false");
        }
    } catch (Exception $e_mail) {
        error_log("❌ Error Mail: " . $e_mail->getMessage());
    }

    // 8. PREPARAR RESPUESTA EXITOSA
    $final_response = [
        'success' => true,
        'id_socio' => intval($id_socio),
        'verification_code' => $verification_code, // Opcional: para debug
        'message' => 'Registro exitoso'
    ];
    http_response_code(200);

} catch (Exception $e) {
    // Manejo de Errores
    error_log("💥 ERROR: " . $e->getMessage());
    $final_response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
    http_response_code(400);
}

// 9. SALIDA FINAL DIRECTA (SIN BUFFERS)
// Imprimir JSON y morir inmediatamente.
print(json_encode($final_response));
exit;