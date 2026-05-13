<?php
// pages/torneo_inscripcion.php
session_start(); 
require_once __DIR__ . '/../includes/config.php';

$slug = $_GET['slug'] ?? '';
$id_param = $_GET['id'] ?? ''; // Soportar búsqueda por ID directo
$code_pareja = $_GET['code'] ?? '';

$torneo = null;
$error_message = "";
$success_message = "";

// 1. Identificar Torneo
if ($slug) {
    // Intentar buscar por SLUG
    $stmt = $pdo->prepare("SELECT t.*, r.nombre as recinto_nombre FROM torneos t JOIN recintos_deportivos r ON t.id_recinto = r.id_recinto WHERE t.slug = ? AND t.estado IN ('abierto', 'borrador')");
    $stmt->execute([$slug]);
    $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Si no encuentra por slug, intentar buscar por ID si se pasó como parámetro alternativo (fallback)
    if (!$torneo && !empty($id_param)) {
        $stmt_id = $pdo->prepare("SELECT t.*, r.nombre as recinto_nombre FROM torneos t JOIN recintos_deportivos r ON t.id_recinto = r.id_recinto WHERE t.id_torneo = ? AND t.estado IN ('abierto', 'borrador')");
        $stmt_id->execute([intval($id_param)]);
        $torneo = $stmt_id->fetch(PDO::FETCH_ASSOC);
    }
} else if (!empty($id_param)) {
    // Buscar directamente por ID
    $stmt = $pdo->prepare("SELECT t.*, r.nombre as recinto_nombre FROM torneos t JOIN recintos_deportivos r ON t.id_recinto = r.id_recinto WHERE t.id_torneo = ? AND t.estado IN ('abierto', 'borrador')");
    $stmt->execute([intval($id_param)]);
    $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
} else if ($code_pareja) {
    // Si viene por invitación de pareja, buscamos el torneo vía el código de pareja
    $stmt_pareja = $pdo->prepare("SELECT pt.id_torneo FROM parejas_torneo pt WHERE pt.codigo_pareja = ?");
    $stmt_pareja->execute([$code_pareja]);
    $pareja_data = $stmt_pareja->fetch();
    
    if ($pareja_data) {
        $stmt = $pdo->prepare("SELECT t.*, r.nombre as recinto_nombre FROM torneos t JOIN recintos_deportivos r ON t.id_recinto = r.id_recinto WHERE t.id_torneo = ?");
        $stmt->execute([$pareja_data['id_torneo']]);
        $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
        $_SESSION['invite_code'] = $code_pareja; 
    }
}

if (!$torneo) {
    die("<h3 style='text-align:center; color:red;'>❌ Enlace inválido o torneo no encontrado</h3>");
}

// 2. Verificar Cupos
$stmt_count = $pdo->prepare("SELECT COUNT(*) FROM parejas_torneo WHERE id_torneo = ?");
$stmt_count->execute([$torneo['id_torneo']]);
$inscritos = (int)$stmt_count->fetchColumn();
$cupo_lleno = ($inscritos >= $torneo['num_parejas_max']);

// 3. Procesar Inscripción (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'login_socio') {
            // --- FLUJO: SOCIO EXISTENTE ---
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if (!$email || !$password) throw new Exception('Email y contraseña requeridos');
            
            $stmt_user = $pdo->prepare("SELECT * FROM socios WHERE email = ?");
            $stmt_user->execute([$email]);
            $user = $stmt_user->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['id_socio'] = $user['id_socio'];
                $_SESSION['nombre_socio'] = $user['nombre'];
                
                verificar_e_inscribir_socio($pdo, $torneo['id_torneo'], $user['id_socio'], $code_pareja);
            } else {
                throw new Exception('Credenciales incorrectas');
            }
            
        } elseif ($action === 'registro_express') {
            // --- FLUJO: REGISTRO EXPRESS (INVITADO) ---
            $nombre = trim($_POST['nombre'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (!$nombre || !$email || !$password) throw new Exception('Campos obligatorios incompletos');
            if ($password !== $confirm_password) throw new Exception('Las contraseñas no coinciden');
            
            $stmt_check = $pdo->prepare("SELECT id_socio FROM socios WHERE email = ?");
            $stmt_check->execute([$email]);
            
            if ($stmt_check->fetch()) {
                throw new Exception('Este email ya está registrado. Por favor inicia sesión.');
            }
            
            $alias = strtolower(preg_replace('/[^a-z0-9]/', '', explode(' ', $nombre)[0]));
            $hash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt_insert = $pdo->prepare("
                INSERT INTO socios (nombre, alias, email, celular, rol, activo, email_verified, password_hash, created_at) 
                VALUES (?, ?, ?, ?, 'Jugador', 'Si', 0, ?, NOW())
            ");
            $stmt_insert->execute([$nombre, $alias ?: substr($email, 0, 5), $email, $telefono, $hash]);
            $id_socio = $pdo->lastInsertId();
            
            $_SESSION['id_socio'] = $id_socio;
            $_SESSION['nombre_socio'] = $nombre;
            
            verificar_e_inscribir_socio($pdo, $torneo['id_torneo'], $id_socio, $code_pareja);
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

function verificar_e_inscribir_socio($pdo, $id_torneo, $id_socio, $code_pareja = null) {
    global $success_message, $error_message, $torneo;
    
    // 1. Verificar si YA está inscrito
    $stmt_check = $pdo->prepare("SELECT 1 FROM parejas_torneo WHERE id_torneo = ? AND (id_socio_1 = ? OR id_socio_2 = ?)");
    $stmt_check->execute([$id_torneo, $id_socio, $id_socio]);
    
    if ($stmt_check->fetch()) {
        $success_message = "✅ Ya estás inscrito en este torneo.";
        return;
    }
    
    // 2. Si tiene code_pareja, aceptar invitación
    if ($code_pareja) {
        $stmt_pareja = $pdo->prepare("SELECT pt.id_pareja FROM parejas_torneo pt WHERE pt.codigo_pareja = ? AND pt.id_socio_2 IS NULL");
        $stmt_pareja->execute([$code_pareja]);
        $pareja = $stmt_pareja->fetch();
        
        if ($pareja) {
            $stmt_update = $pdo->prepare("UPDATE parejas_torneo SET id_socio_2 = ?, estado = 'completa' WHERE id_pareja = ?");
            $stmt_update->execute([$id_socio, $pareja['id_pareja']]);
            
            $success_message = "✅ ¡Te has unido a la pareja! Revisa tu correo.";
            return;
        }
    }
    
    // 3. Inscribirse como nuevo jugador principal
    $max_parejas = $torneo['num_parejas_max'] ?? 10;
    
    if ($inscritos >= $max_parejas) {
        $error_message = "❌ Cupo lleno.";
        return;
    }

    $codigo_pareja_nuevo = substr(md5(uniqid()), 0, 8);
    
    $stmt_insert = $pdo->prepare("
        INSERT INTO parejas_torneo (id_torneo, id_socio_1, codigo_pareja, estado) 
        VALUES (?, ?, ?, 'esperando_pareja')
    ");
    
    try {
        $stmt_insert->execute([$id_torneo, $id_socio, $codigo_pareja_nuevo]);
        $success_message = "✅ ¡Inscripción exitosa! Envía el link a tu pareja.";
    } catch (Exception $e) {
        $error_message = "❌ Error al inscribirse.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscripción - <?= htmlspecialchars($torneo['nombre']) ?></title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f5f7fa; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; }
        .card { background: white; padding: 2rem; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); max-width: 400px; width: 100%; text-align: center; }
        h2 { color: #071289; margin-bottom: 0.5rem; }
        p { color: #666; margin-bottom: 1.5rem; }
        .btn-inscribir { display: block; width: 100%; padding: 1rem; background: linear-gradient(135deg, #667eea, #764ba2); color: white; text-decoration: none; border-radius: 10px; font-weight: bold; font-size: 1.1rem; transition: transform 0.2s; cursor: pointer; border: none; margin-top: 1rem; }
        .btn-inscribir:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(118, 75, 162, 0.4); }
        .info-torneo { background: #f8f9fa; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; text-align: left; font-size: 0.9rem; }
        .error-msg { background: #ffebee; color: #c62828; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .success-msg { background: #e8f5e9; color: #2e7d32; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .form-group { margin-bottom: 1rem; text-align: left; }
        .form-group label { display: block; margin-bottom: 0.3rem; font-weight: 600; color: #333; font-size: 0.9rem; }
        .form-group input { width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; }
        .tab-buttons { display: flex; gap: 10px; margin-bottom: 1.5rem; }
        .tab-btn { flex: 1; padding: 10px; border: 1px solid #ddd; background: #f9f9f9; cursor: pointer; border-radius: 8px; font-weight: 600; color: #666; }
        .tab-btn.active { background: #667eea; color: white; border-color: #667eea; }
        .hidden { display: none; }
    </style>
</head>
<body>
    <div class="card">
        <?php if ($cupo_lleno): ?>
            <div class="error-msg">
                <h3>⚠️ Cupo Lleno</h3>
                <p>Lamentablemente, este torneo ha alcanzado su máximo de parejas.</p>
            </div>
        <?php elseif ($success_message): ?>
            <div class="success-msg">
                <h3>✅ Éxito</h3>
                <p><?= htmlspecialchars($success_message) ?></p>
                <button onclick="window.location.href='/pages/dashboard_socio.php';" class="btn-inscribir">Ir a mi Dashboard</button>
            </div>
            
            <script>
                setTimeout(() => {
                    window.location.href = '/pages/dashboard_socio.php';
                }, 2000);
            </script>
        <?php else: ?>
            <h2>🎾 <?= htmlspecialchars($torneo['nombre']) ?></h2>
            <p><?= htmlspecialchars($torneo['recinto_nombre']) ?></p>
            
            <div class="info-torneo">
                <strong>📅 Fecha:</strong> <?= date('d/m/Y H:i', strtotime($torneo['fecha_inicio'])) ?><br>
                <strong>💰 Valor:</strong> $<?= number_format($torneo['valor'] ?? 0, 0, ',', '.') ?> (por pareja)<br>
                
                <!-- ✅ CORRECCIÓN: Usar ?? 0 para evitar error si $inscritos no está definida -->
                <strong>👥 Cupos restantes:</strong> <?= max(0, ($torneo['num_parejas_max'] ?? 0) - ($inscritos ?? 0)) ?>
            </div>

            <?php if ($error_message): ?>
                <div class="error-msg"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="tab-buttons">
                <button class="tab-btn active" onclick="showTab('login')">Soy Socio</button>
                <button class="tab-btn" onclick="showTab('register')">Registro Express</button>
            </div>

            <!-- Formulario Login Socio -->
            <form id="form-login" method="POST">
                <input type="hidden" name="action" value="login_socio">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required placeholder="tu@email.com">
                </div>
                <div class="form-group">
                    <label>Contraseña</label>
                    <input type="password" name="password" required placeholder="Tu contraseña">
                </div>
                <button type="submit" class="btn-inscribir">🔐 Iniciar Sesión e Inscribirse</button>
            </form>

            <!-- Formulario Registro Express -->
            <form id="form-register" method="POST" class="hidden">
                <input type="hidden" name="action" value="registro_express">
                <div class="form-group">
                    <label>Nombre Completo</label>
                    <input type="text" name="nombre" required placeholder="Tu nombre">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required placeholder="tu@email.com">
                </div>
                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="tel" name="telefono" placeholder="+56 9...">
                </div>
                <div class="form-group">
                    <label>Contraseña</label>
                    <input type="password" name="password" required placeholder="Mínimo 6 caracteres">
                </div>
                <div class="form-group">
                    <label>Confirmar Contraseña</label>
                    <input type="password" name="confirm_password" required placeholder="Repite contraseña">
                </div>
                <button type="submit" class="btn-inscribir">🚀 Registrarse e Inscribirse</button>
            </form>
        <?php endif; ?>
    </div>

    <script>
        function showTab(tab) {
            const loginForm = document.getElementById('form-login');
            const registerForm = document.getElementById('form-register');
            const btns = document.querySelectorAll('.tab-btn');
            
            if (tab === 'login') {
                loginForm.classList.remove('hidden');
                registerForm.classList.add('hidden');
                btns[0].classList.add('active');
                btns[1].classList.remove('active');
            } else {
                loginForm.classList.add('hidden');
                registerForm.classList.remove('hidden');
                btns[0].classList.remove('active');
                btns[1].classList.add('active');
            }
        }
    </script>
</body>
</html>