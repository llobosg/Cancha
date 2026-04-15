<?php
    // Limpiar cualquier output previo
    if (ob_get_level()) {
        ob_end_clean();
    }
    ob_start();

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
        // === LOGS DE DEPURACIÓN ===
        error_log("📥 [DEBUG] Datos recibidos en enviar_codigo_socio.php:");
        foreach ($_POST as $key => $value) {
            if ($key === 'password' || $key === 'password_confirm') continue;
            error_log("   - $key: " . ($value ?: '[VACÍO]'));
        }
        
        // Validar campos comunes
        $required_common = ['nombre', 'alias', 'email', 'genero', 'rol', 'password', 'password_confirm', 'deporte'];
        foreach ($required_common as $field) {
            if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
                error_log("❌ FALTA CAMPO OBLIGATORIO: $field");
                throw new Exception("El campo '$field' es obligatorio");
            }
        }

        // Obtener club_slug (puede estar vacío para modo individual)
        $club_slug = $_POST['club_slug'] ?? '';
        $modo_individual = empty($club_slug);

        // Validar deporte
        $deporte = $_POST['deporte'];
        
        // Verificar que el deporte sea válido según el modo
        $stmt_check = $pdo->prepare("
            SELECT 1 FROM deportes 
            WHERE TRIM(deporte) = TRIM(?) 
            AND tipo_deporte = ?
        ");
        $stmt_check->execute([$deporte, $modo_individual ? '1' : '2']);
        
        if (!$stmt_check->fetch()) {
            throw new Exception('Deporte no válido para este tipo de registro');
        }

        // Definir otras variables
        $nombre = trim($_POST['nombre']);
        $alias = trim($_POST['alias']);
        $email = trim($_POST['email']);
        $genero = $_POST['genero'];
        $rol = $_POST['rol'];
        $fecha_nac = $_POST['fecha_nac'] ?? null;
        $celular = trim($_POST['celular'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $id_puesto = !empty($_POST['id_puesto']) ? (int)$_POST['id_puesto'] : null;
        $habilidad = $_POST['habilidad'] ?? null;
        
        // Validar contraseña
        $password = $_POST['password'];
        $password_confirm = $_POST['password_confirm'];
        
        if ($password !== $password_confirm) {
            throw new Exception('Las contraseñas no coinciden');
        }
        
        if (strlen($password) < 6) {
            throw new Exception('La contraseña debe tener al menos 6 caracteres');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Correo electrónico inválido');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Correo electrónico inválido');
        }

        // === NUEVA VALIDACIÓN: VERIFICAR SI EL CORREO YA EXISTE ===
        $stmt_check_email = $pdo->prepare("SELECT id_socio FROM socios WHERE email = ? LIMIT 1");
        $stmt_check_email->execute([$email]);
        
        if ($stmt_check_email->fetch()) {
            // El correo ya existe
            throw new Exception('Este correo electrónico ya está registrado. Por favor inicia sesión o recupera tu contraseña.');
        }

        // Lógica de club (solo si no es modo individual)
        $id_club = null;
        if (!$modo_individual) {
            if (strlen($club_slug) !== 8 || !ctype_alnum($club_slug)) {
                throw new Exception('Club no encontrado');
            }

            $stmt = $pdo->prepare("SELECT id_club, email_responsable FROM clubs WHERE email_verified = 1");
            $stmt->execute();
            $clubs = $stmt->fetchAll();
            
            foreach ($clubs as $c) {
                if (substr(md5($c['id_club'] . $c['email_responsable']), 0, 8) === $club_slug) {
                    $id_club = $c['id_club'];
                    break;
                }
            }
            if (!$id_club) throw new Exception('Club no encontrado');

            // Verificar duplicados en socio_club
            $stmt = $pdo->prepare("
                SELECT sc.id_socio 
                FROM socio_club sc
                JOIN socios s ON sc.id_socio = s.id_socio
                WHERE s.email = ? AND sc.id_club = ?
            ");
            $stmt->execute([$email, $id_club]);
            if ($stmt->fetch()) throw new Exception('Ya estás inscrito en este club');
        }

        // Validar fecha de nacimiento y edad mínima
        if (!empty($fecha_nac)) {
            $fecha_nac_obj = DateTime::createFromFormat('Y-m-d', $fecha_nac);
            if (!$fecha_nac_obj || $fecha_nac_obj->format('Y-m-d') !== $fecha_nac) {
                throw new Exception('Formato de fecha de nacimiento inválido');
            }
            
            $hoy = new DateTime();
            $edad = $hoy->diff($fecha_nac_obj)->y;
            
            if ($edad < 6) {
                throw new Exception('La edad mínima para CanchaSport es de 6 años');
            }
        }

        // === Manejar subida de foto ===
        $foto_url = null;
        if (!empty($_FILES['foto']['name'])) {
            if ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Error al subir la foto. Intenta con una imagen más pequeña.');
            }

            $upload_dir = __DIR__ . '/../uploads/socios/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
            if (!in_array(strtolower($ext), $allowed_ext)) {
                throw new Exception('Formato de imagen no permitido. Usa JPG, PNG o GIF.');
            }

            $filename = 'socio_' . time() . '.' . strtolower($ext);
            $filepath = $upload_dir . $filename;

            if (move_uploaded_file($_FILES['foto']['tmp_name'], $filepath)) {
                $foto_url = $filename;
            } else {
                throw new Exception('No se pudo guardar la imagen. Contacta al administrador.');
            }
        }

        // Validar campos de ubicación
        $pais = $_POST['pais'] ?? 'Chile';
        $region = $_POST['region'] ?? null;
        $ciudad = $_POST['ciudad'] ?? null;
        $comuna = $_POST['comuna'] ?? null;

        if ($region && (!$ciudad || !$comuna)) {
            throw new Exception('Debes seleccionar región, ciudad y comuna completos');
        }

        // Hashear contraseña
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // Generar código de verificación
        $verification_code = rand(1000, 9999);

        // Insertar socio
        $stmt = $pdo->prepare("
        INSERT INTO socios (
                nombre,
                alias,
                fecha_nac,
                celular,
                email,
                direccion,
                pais,
                region,
                ciudad,
                comuna,
                rol,
                foto_url,
                genero,
                deporte,
                id_puesto,
                habilidad,
                activo,
                email_verified,
                verification_code,
                es_responsable,
                datos_completos,
                password_hash
            ) VALUES (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                0,
                ?,
                0,
                1,
                ?
            )
        ");
        $stmt->execute([
            $nombre,
            $alias,
            !empty($fecha_nac) ? $fecha_nac : null,
            !empty($celular) ? $celular : null,
            $email,
            !empty($direccion) ? $direccion : null,
            $pais ?: 'Chile',
            $region ?: 'Metropolitana',
            $ciudad ?: 'Santiago',
            $comuna ?: 'Ñuñoa',
            $rol ?: 'Jugador',
            $foto_url,
            $genero ?: 'Masculino',
            $deporte ?: 'Pádel',
            $id_puesto ?: 1,
            $habilidad ?: 'Intermedia',
            'Si',
            $verification_code,
            $password_hash
        ]);

        $id_socio = $pdo->lastInsertId();
        
        if (!empty($_POST['torneo_slug'])) {
            $_SESSION['torneo_slug_post_registro'] = $_POST['torneo_slug'];
        }

        if (!$modo_individual && $id_club) {
            $pdo->prepare("
                INSERT INTO socio_club (id_socio, id_club, estado)
                VALUES (?, ?, 'activo')
            ")->execute([$id_socio, $id_club]);
        }

        $id_socio = $pdo->lastInsertId(); // Asegúrate que esta línea esté ANTES del mail
        
        if (!empty($_POST['torneo_slug'])) {
            $_SESSION['torneo_slug_post_registro'] = $_POST['torneo_slug'];
        }

        // === CREAR RELACIÓN EN SOCIO_CLUB (si aplica) ===
        if (!$modo_individual && $id_club) {
            $pdo->prepare("INSERT INTO socio_club (id_socio, id_club, estado) VALUES (?, ?, 'activo')")
                ->execute([$id_socio, $id_club]);
        }

            // ... (Todo tu código anterior de validación e INSERT debe estar aquí arriba) ...
    
    // Asegurarnos de tener el ID
    $id_socio = $pdo->lastInsertId(); 

    // === ENVIAR CORREO (Lógica simplificada) ===
    error_log("Iniciando envío de correo a: " . $email);
    $mail_ok = false;
    try {
        require_once __DIR__ . '/../includes/brevo_mailer.php';
        $mail = new BrevoMailer();
        $mail->setTo($email, $nombre);
        $mail->setSubject('🔐 Código: ' . $verification_code);
        
        $body = "<h1>Tu código es: <b>{$verification_code}</b></h1><p>Activa tu cuenta en CanchaSport.</p>";
        $mail->setHtmlBody($body);
        
        if ($mail->send()) {
            $mail_ok = true;
            error_log("✓ Correo enviado.");
        }
    } catch (Exception $e) {
        error_log("Error mail: " . $e->getMessage());
    }

    // === SALIDA FORZADA DE JSON (TÉCNICA NUCLEAR) ===
    
    // 1. Matar cualquier buffer existente sin importar qué haya dentro
    while (@ob_end_clean()); 
    
    // 2. Definir datos
    $datos = [
        'success' => true,
        'id_socio' => intval($id_socio),
        'message' => 'Código enviado',
        'mail_status' => $mail_ok
    ];
    
    // 3. Imprimir CABECERAS frescas
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    
    // 4. Imprimir JSON directamente
    $json_str = json_encode($datos);
    
    // 5. Escribir en salida y MATAR PROCESO INMEDIATAMENTE
    print($json_str);
    flush(); 
    die(); // Die es más agresivo que exit

    } catch (Exception $e) {
        // Manejo de errores globales
        error_log("💥 ERROR CRÍTICO REGISTRO: " . $e->getMessage());
        
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
?>