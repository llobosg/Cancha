<?php
// pages/registro_socio_v2.php
session_start();
require_once __DIR__ . '/../includes/config.php';

// Si ya está logueado, redirigir
if (isset($_SESSION['id_socio'])) {
    header('Location: dashboard_socio.php');
    exit;
}

$club_slug = $_GET['club'] ?? '';
$club_nombre = "CanchaSport"; // Default

// Intentar obtener nombre del club si hay slug
if ($club_slug) {
    try {
        $stmt = $pdo->prepare("SELECT nombre FROM clubs WHERE email_verified = 1 LIMIT 1"); // Simplificado para el ejemplo, idealmente buscar por slug
        // Aquí deberías buscar por el slug generado, pero para el registro rápido asumimos contexto
        $club_nombre = "Club Deportivo"; 
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Únete a <?= htmlspecialchars($club_nombre) ?></title>
    <link rel="stylesheet" href="../styles.css"> <!-- Asegúrate de que styles.css tenga el nuevo CSS o usa el inline abajo -->
    
    <!-- Estilos Inline para asegurar el look & feel solicitado -->
    <style>
        /* Copia exacta de tu styles.css proporcionado para garantizar el diseño */
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        body {
            background-color: #0f172a;
            background-image: url('/assets/img/cancha_pasto2.jpg'); /* Usa tu imagen de fondo */
            background-size: cover;
            background-position: center;
            color: #f1f5f9;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 20px 0;
        }
        body::before {
            content: ''; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.85) 0%, rgba(15, 23, 42, 0.95) 100%);
            pointer-events: none; z-index: -1;
        }
        .app-container { width: 100%; max-width: 420px; padding-bottom: 40px; }
        
        /* Logo Header */
        .logo-header { text-align: center; margin: 20px 0 10px; }
        .logo-header h1 { 
            font-size: 1.8rem; 
            background: linear-gradient(135deg, #4ade80, #3b82f6); 
            -webkit-background-clip: text; 
            -webkit-text-fill-color: transparent; 
            font-weight: 900;
            letter-spacing: -1px;
        }
        .logo-header p { color: #cbd5e1; font-size: 0.9rem; margin-top: 5px; }

        /* Card Glassmorphism */
        .card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 20px;
            padding: 25px;
            margin: 0 16px 16px;
            border: 1px solid rgba(255,255,255,0.1);
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        }

        .input-group { margin-bottom: 15px; position: relative; }
        .input-label { display: block; color: #94a3b8; font-size: 0.8rem; margin-bottom: 5px; font-weight: 600; }
        
        .input {
            width: 100%;
            padding: 12px 15px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.15);
            background: rgba(15,23,42,0.6);
            color: white;
            font-size: 1rem;
            transition: all 0.3s;
        }
        .input:focus {
            outline: none;
            border-color: #3b82f6;
            background: rgba(15,23,42,0.9);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }

        .btn {
            width: 100%;
            padding: 14px;
            border-radius: 12px;
            border: none;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            font-weight: bold;
            font-size: 1rem;
            cursor: pointer;
            margin-top: 10px;
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.4);
            transition: transform 0.2s;
        }
        .btn:active { transform: scale(0.98); }
        .btn-secondary {
            background: rgba(255,255,255,0.1);
            color: #cbd5e1;
            box-shadow: none;
            margin-top: 10px;
        }

        .hidden { display: none !important; }
        
        /* Toast Notification */
        #toast {
            visibility: hidden;
            min-width: 250px;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 8px;
            padding: 16px;
            position: fixed;
            z-index: 1000;
            left: 50%;
            bottom: 30px;
            transform: translateX(-50%);
            font-size: 0.9rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        #toast.show { visibility: visible; animation: fadein 0.5s, fadeout 0.5s 2.5s; }
        @keyframes fadein { from {bottom: 0; opacity: 0;} to {bottom: 30px; opacity: 1;} }
        @keyframes fadeout { from {bottom: 30px; opacity: 1;} to {bottom: 0; opacity: 0;} }

        .divider {
            display: flex; align-items: center; text-align: center; color: #64748b; margin: 20px 0; font-size: 0.8rem;
        }
        .divider::before, .divider::after { content: ''; flex: 1; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .divider span { padding: 0 10px; }
    </style>
</head>
<body>

<div class="app-container">
    <!-- Logo Header -->
    <div class="logo-header">
        <h1>CanchaSport </h1>
        <p>Únete a <?= htmlspecialchars($club_nombre) ?></p>
    </div>

    <!-- Formulario de Registro Simplificado -->
    <div id="register-form" class="card">
        <form id="formRegistro" onsubmit="enviarCodigo(event)">
            
            <!-- Campo Oculto: Club Slug -->
            <input type="hidden" id="club_slug" value="<?= htmlspecialchars($club_slug) ?>">

            <!-- 1. Nombre Completo (De aquí sacamos Alias) -->
            <div class="input-group">
                <label class="input-label">Nombre Completo</label>
                <input type="text" id="nombre" class="input" placeholder="Ej: Juan Pérez" required autocomplete="name">
            </div>

            <!-- 2. Género -->
            <div class="input-group">
                <label class="input-label">Género</label>
                <select id="genero" class="input" required>
                    <option value="" disabled selected>Seleccionar...</option>
                    <option value="masculino">Masculino</option>
                    <option value="femenino">Femenino</option>
                    <option value="otro">Otro</option>
                </select>
            </div>

            <!-- 3. Celular (Optimizado para móvil) -->
            <div class="input-group">
                <label class="input-label">Celular</label>
                <div style="display:flex; gap:10px;">
                    <input type="tel" id="celular" class="input" placeholder="+56 9..." required style="flex:1;" autocomplete="tel">
                    <!-- Botón opcional para intentar rellenar -->
                    <button type="button" onclick="rellenarTelefono()" style="background:rgba(255,255,255,0.1); border:none; border-radius:10px; color:white; padding:0 10px; cursor:pointer;" title="Autocompletar">📱</button>
                </div>
            </div>

            <!-- 4. Correo -->
            <div class="input-group">
                <label class="input-label">Correo Electrónico</label>
                <input type="email" id="email" class="input" placeholder="tu@email.com" required autocomplete="email">
            </div>

            <!-- 5. Deporte Principal -->
            <div class="input-group">
                <label class="input-label">Deporte Principal</label>
                <select id="deporte" class="input" required>
                    <option value="" disabled selected>¿Qué juegas?</option>
                    <option value="futbol">Fútbol / Futbolito</option>
                    <option value="padel">Pádel</option>
                    <option value="tenis">Tenis</option>
                    <option value="voleyball">Vóleibol</option>
                    <option value="otro">Otro</option>
                </select>
            </div>

            <!-- 6. Puesto (Opcional pero útil) -->
            <div class="input-group">
                <label class="input-label">Puesto Favorito</label>
                <select id="puesto" class="input">
                    <option value="">Indistinto</option>
                    <option value="Arquero">Arquero</option>
                    <option value="Defensa">Defensa</option>
                    <option value="Mediocampo">Mediocampo</option>
                    <option value="Delantero">Delantero</option>
                    <option value="Volante">Volante</option>
                </select>
            </div>

            <div class="divider"><span>Seguridad</span></div>

            <!-- 7. Contraseña -->
            <div class="input-group">
                <label class="input-label">Contraseña</label>
                <input type="password" id="password" class="input" placeholder="Mínimo 6 caracteres" minlength="6" required>
            </div>

            <!-- 8. Confirmar Contraseña -->
            <div class="input-group">
                <label class="input-label">Confirmar Contraseña</label>
                <input type="password" id="password_confirm" class="input" placeholder="Repite tu contraseña" required>
            </div>

            <!-- Campos Ocultos con Valores por Defecto -->
            <input type="hidden" id="alias_hidden">
            <input type="hidden" id="rol" value="Jugador">
            <input type="hidden" id="fecha_nac" value="2000-01-01">
            <input type="hidden" id="region" value="Metropolitana">
            <input type="hidden" id="ciudad" value="Santiago">
            <input type="hidden" id="comuna" value="Comuna">
            <input type="hidden" id="direccion" value="Pendiente">
            <input type="hidden" id="habilidad" value="Intermedia">

            <button type="submit" class="btn" id="btnEnviar">🚀 Enviar Código de Verificación</button>
            
            <p style="text-align: center; margin-top: 20px; color: #94a3b8; font-size: 0.85rem;">
                ¿Ya tienes cuenta? <a href="../index.php" style="color: #60a5fa; text-decoration: none; font-weight: bold;">Iniciar Sesión</a>
            </p>
        </form>
    </div>
</div>

<!-- Modal de Verificación (Oculto inicialmente) -->
<div id="verify-modal" class="card hidden" style="position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); width:90%; max-width:350px; z-index:2000; text-align:center;">
    <h3 style="color:white; margin-bottom:15px;">Verifica tu correo</h3>
    <p style="color:#cbd5e1; font-size:0.9rem; margin-bottom:20px;">Hemos enviado un código de 6 dígitos a:</p>
    <p id="verify-email-display" style="color:#4ade80; font-weight:bold; font-size:1.1rem; margin-bottom:20px;"></p>
    
    <input type="text" id="codigo_verificacion" class="input" placeholder="000000" maxlength="6" style="text-align:center; letter-spacing:5px; font-size:1.5rem; font-weight:bold;" required>
    
    <button class="btn" onclick="validarYRegistrar()">✅ Activar Cuenta</button>
    <button class="btn btn-secondary" onclick="reenviarCodigo()">Reenviar Código</button>
    <button class="btn btn-secondary" onclick="volverAlRegistro()" style="margin-top:10px; background:transparent; border:1px solid rgba(255,255,255,0.2);">Cancelar</button>
</div>

<div id="toast">Mensaje de notificación</div>

<script>
    let codigoEnviado = '';
    let emailTemp = '';

    // Función para autocompletar alias al escribir nombre
    document.getElementById('nombre').addEventListener('input', function(e) {
        const nombres = e.target.value.trim().split(' ');
        if (nombres.length > 0) {
            document.getElementById('alias_hidden').value = nombres[0];
        }
    });

    // Intentar rellenar teléfono (depende del navegador/dispositivo)
    function rellenarTelefono() {
        // Nota: Esto es limitado en web pura sin permisos especiales, pero ayuda a enfocar el input
        const input = document.getElementById('celular');
        input.focus();
        showToast("Escribe tu número o usa el teclado numérico 📱");
    }

    // Paso 1: Enviar Código
    async function enviarCodigo(e) {
        e.preventDefault();
        
        const password = document.getElementById('password').value;
        const passConfirm = document.getElementById('password_confirm').value;

        if (password !== passConfirm) {
            showToast("❌ Las contraseñas no coinciden");
            return;
        }
        if (password.length < 6) {
            showToast("❌ La contraseña debe tener al menos 6 caracteres");
            return;
        }

        const btn = document.getElementById('btnEnviar');
        btn.innerHTML = ' Enviando...';
        btn.disabled = true;

        // Preparar datos (incluyendo los ocultos)
        const formData = new FormData();
        formData.append('nombre', document.getElementById('nombre').value);
        formData.append('alias', document.getElementById('alias_hidden').value || 'Usuario');
        formData.append('genero', document.getElementById('genero').value);
        formData.append('celular', document.getElementById('celular').value);
        formData.append('email', document.getElementById('email').value);
        formData.append('deporte', document.getElementById('deporte').value);
        formData.append('puesto', document.getElementById('puesto').value);
        formData.append('password', password);
        formData.append('rol', document.getElementById('rol').value);
        formData.append('fecha_nac', document.getElementById('fecha_nac').value);
        formData.append('region', document.getElementById('region').value);
        formData.append('ciudad', document.getElementById('ciudad').value);
        formData.append('comuna', document.getElementById('comuna').value);
        formData.append('direccion', document.getElementById('direccion').value);
        formData.append('habilidad', document.getElementById('habilidad').value);
        formData.append('club_slug', document.getElementById('club_slug').value);

        try {
            const response = await fetch('../api/enviar_codigo_socio.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                emailTemp = document.getElementById('email').value;
                document.getElementById('verify-email-display').textContent = emailTemp;
                
                // Mostrar modal de verificación
                document.getElementById('register-form').classList.add('hidden');
                document.getElementById('verify-modal').classList.remove('hidden');
                showToast("✅ Código enviado a tu correo");
            } else {
                showToast("❌ " + data.message);
                btn.innerHTML = '🚀 Enviar Código de Verificación';
                btn.disabled = false;
            }
        } catch (error) {
            console.error(error);
            showToast("❌ Error de conexión. Intenta nuevamente.");
            btn.innerHTML = '🚀 Enviar Código de Verificación';
            btn.disabled = false;
        }
    }

    // Paso 2: Validar Código y Registrar
    async function validarYRegistrar() {
        const codigo = document.getElementById('codigo_verificacion').value;
        if (codigo.length !== 6) {
            showToast("️ Ingresa el código de 6 dígitos");
            return;
        }

        const btn = document.querySelector('#verify-modal .btn');
        btn.innerHTML = ' Activando...';
        btn.disabled = true;

        const formData = new FormData();
        formData.append('email', emailTemp);
        formData.append('codigo', codigo);
        // Reenviamos todos los datos necesarios para el registro final
        formData.append('nombre', document.getElementById('nombre').value);
        formData.append('alias', document.getElementById('alias_hidden').value);
        formData.append('genero', document.getElementById('genero').value);
        formData.append('celular', document.getElementById('celular').value);
        formData.append('deporte', document.getElementById('deporte').value);
        formData.append('puesto', document.getElementById('puesto').value);
        formData.append('password', document.getElementById('password').value);
        formData.append('rol', document.getElementById('rol').value);
        formData.append('fecha_nac', document.getElementById('fecha_nac').value);
        formData.append('region', document.getElementById('region').value);
        formData.append('ciudad', document.getElementById('ciudad').value);
        formData.append('comuna', document.getElementById('comuna').value);
        formData.append('direccion', document.getElementById('direccion').value);
        formData.append('habilidad', document.getElementById('habilidad').value);
        formData.append('club_slug', document.getElementById('club_slug').value);

        try {
            const response = await fetch('../api/verificar_y_registrar_socio.php', { // Asegúrate de tener este archivo o usar el existente
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                showToast(" ¡Registro Exitoso! Redirigiendo...");
                setTimeout(() => {
                    window.location.href = '../index.php'; // O al dashboard si se loguea auto
                }, 2000);
            } else {
                showToast("❌ " + data.message);
                btn.innerHTML = '✅ Activar Cuenta';
                btn.disabled = false;
            }
        } catch (error) {
            showToast("❌ Error al verificar");
            btn.innerHTML = '✅ Activar Cuenta';
            btn.disabled = false;
        }
    }

    function reenviarCodigo() {
        showToast(" Reenviando código...");
        enviarCodigo({ preventDefault: () => {} }); // Reutilizamos lógica de envío
    }

    function volverAlRegistro() {
        document.getElementById('verify-modal').classList.add('hidden');
        document.getElementById('register-form').classList.remove('hidden');
    }

    function showToast(message) {
        const toast = document.getElementById("toast");
        toast.textContent = message;
        toast.className = "show";
        setTimeout(function(){ toast.className = toast.className.replace("show", ""); }, 3000);
    }
</script>

</body>
</html>