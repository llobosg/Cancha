<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

try {
    $action = $_POST['action'] ?? '';
    
    // === NUEVA ACCIÓN: CREAR SOCIO EXPRESS (Para Reserva Manual) ===
    if ($action === 'crear_socio_express') {
        $nombre = trim($_POST['nombre'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $celular = trim($_POST['celular'] ?? '');
        
        if (empty($nombre) || empty($email)) {
            throw new Exception('Nombre y Email son obligatorios');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Email inválido');
        }
        
        // Verificar si ya existe
        $stmt_check = $pdo->prepare("SELECT id_socio FROM socios WHERE email = ? LIMIT 1");
        $stmt_check->execute([$email]);
        $existente = $stmt_check->fetchColumn();
        
        if ($existente) {
            echo json_encode(['success' => true, 'id_socio' => $existente, 'message' => 'Socio existente recuperado']);
            exit;
        }
        
        // Generar alias automático (ej: juan.perez)
        $alias = strtolower(preg_replace('/[^a-z0-9]/', '', explode('@', $email)[0]));
        // Asegurar unicidad del alias
        $base_alias = $alias;
        $counter = 1;
        while (true) {
            $stmt_alias = $pdo->prepare("SELECT COUNT(*) FROM socios WHERE alias = ?");
            $stmt_alias->execute([$alias]);
            if ($stmt_alias->fetchColumn() == 0) break;
            $alias = $base_alias . $counter;
            $counter++;
        }
        
        // Insertar socio mínimo
        $stmt = $pdo->prepare("
            INSERT INTO socios (nombre, alias, email, celular, created_at, email_verified) 
            VALUES (?, ?, ?, ?, NOW(), 1)
        ");
        $stmt->execute([$nombre, $alias, $email, $celular]);
        
        echo json_encode(['success' => true, 'id_socio' => $pdo->lastInsertId()]);
        exit;
    }
    
    // === LÓGICA ORIGINAL (Insert/Update/Delete) ===
    if ($action !== 'insert' && $action !== 'update' && $action !== 'delete') {
        throw new Exception('Acción no válida');
    }
    
    switch ($action) {
        case 'insert':
        case 'update':
            $alias = $_POST['alias'] ?? '';
            $fecha_nac = $_POST['fecha_nac'] ?? null;
            $celular = $_POST['celular'] ?? '';
            $email = $_POST['email'] ?? '';
            $direccion = $_POST['direccion'] ?? '';
            $rol = $_POST['rol'] ?? '';
            $genero = $_POST['genero'] ?? '';
            $id_puesto = $_POST['id_puesto'] ?? null;
            $habilidad = $_POST['habilidad'] ?? '';
            
            if (empty($alias) || empty($email)) {
                throw new Exception('Alias y email son requeridos');
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Email inválido');
            }
            
            // Manejar archivo de foto
            $foto_url = null;
            if (!empty($_FILES['foto_url']['name'])) {
                $upload_dir = __DIR__ . '/../uploads/fotos/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_name = uniqid() . '_' . basename($_FILES['foto_url']['name']);
                $file_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['foto_url']['tmp_name'], $file_path)) {
                    $foto_url = $file_name;
                }
            }
            
            if ($action === 'insert') {
                $verification_code = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
                
                $stmt = $pdo->prepare("
                    INSERT INTO socios (alias, fecha_nac, celular, email, direccion, rol, foto_url, genero, id_puesto, habilidad, verification_code, email_verified) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
                ");
                $stmt->execute([$alias, $fecha_nac, $celular, $email, $direccion, $rol, $foto_url, $genero, $id_puesto, $habilidad, $verification_code]);
                
                enviarCodigoVerificacion($email, $alias, $verification_code);
                
            } else {
                $id_socio = $_POST['id_socio'] ?? null;
                $original_email = $_POST['original_email'] ?? '';
                
                if (!$id_socio) {
                    throw new Exception('ID de socio requerido');
                }
                
                $email_changed = ($original_email !== $email);
                
                if ($email_changed) {
                    $verification_code = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
                    
                    $stmt = $pdo->prepare("
                        UPDATE socios 
                        SET alias = ?, fecha_nac = ?, celular = ?, email = ?, direccion = ?, rol = ?, 
                            genero = ?, id_puesto = ?, habilidad = ?, datos_completos = 0,
                            email_verified = 0, verification_code = ?
                        WHERE id_socio = ?
                    ");
                    $stmt->execute([$alias, $fecha_nac, $celular, $email, $direccion, $rol, $genero, $id_puesto, $habilidad, $verification_code, $id_socio]);
                    
                    enviarCodigoVerificacion($email, $alias, $verification_code);
                    
                } else {
                    if ($foto_url) {
                        $stmt = $pdo->prepare("
                            UPDATE socios 
                            SET alias = ?, fecha_nac = ?, celular = ?, email = ?, direccion = ?, rol = ?, 
                                foto_url = ?, genero = ?, id_puesto = ?, habilidad = ?
                            WHERE id_socio = ?
                        ");
                        $stmt->execute([$alias, $fecha_nac, $celular, $email, $direccion, $rol, $foto_url, $genero, $id_puesto, $habilidad, $id_socio]);
                    } else {
                        $stmt = $pdo->prepare("
                            UPDATE socios 
                            SET alias = ?, fecha_nac = ?, celular = ?, email = ?, direccion = ?, rol = ?, 
                                genero = ?, id_puesto = ?, habilidad = ?
                            WHERE id_socio = ?
                        ");
                        $stmt->execute([$alias, $fecha_nac, $celular, $email, $direccion, $rol, $genero, $id_puesto, $habilidad, $id_socio]);
                    }
                }
            }
            break;
            
        case 'delete':
            $id_socio = $_POST['id_socio'] ?? null;
            if (!$id_socio) {
                throw new Exception('ID de socio requerido');
            }
            $stmt = $pdo->prepare("DELETE FROM socios WHERE id_socio = ?");
            $stmt->execute([$id_socio]);
            break;
    }
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Función para enviar código de verificación
function enviarCodigoVerificacion($email, $nombre, $codigo) {
    try {
        require_once __DIR__ . '/../includes/brevo_mailer.php';
        $mail = new BrevoMailer();
        $mail->setTo($email, $nombre);
        $mail->setSubject('🔐 Nuevo código de verificación - Cancha');
        
        $mail->setHtmlBody("
            <h2>¡Cambio de email detectado!</h2>
            <p>Tu dirección de email ha sido actualizada en Cancha.</p>
            <p>Para verificar tu nueva dirección, usa este código:</p>
            <h1 style='color:#009966;'>$codigo</h1>
            <p>Ingresa este código en la aplicación para completar la verificación.</p>
            <p>El código tiene validez de 15 minutos.</p>
        ");
        
        $mail->send();
        error_log("Código de verificación enviado a: $email");
        
    } catch (Exception $e) {
        error_log("Error al enviar código de verificación a $email: " . $e->getMessage());
    }
}
?>