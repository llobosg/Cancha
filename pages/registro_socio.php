<?php
// pages/registro_socio_v2.php
session_start();
require_once __DIR__ . '/../includes/config.php';

if (isset($_SESSION['id_socio'])) {
    header('Location: dashboard_socio.php');
    exit;
}

$club_slug = $_GET['club'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Únete a CanchaSport</title>
    <!-- Usamos tu CSS base, pero agregamos estilos específicos inline para este layout -->
    <link rel="stylesheet" href="../styles.css">
    <style>
        /* Estilos Base (Similares a tu styles.css) */
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        body {
            background-color: #0f172a;
            background-image: url('/assets/img/cancha_pasto2.jpg');
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
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.9) 0%, rgba(15, 23, 42, 0.95) 100%);
            pointer-events: none; z-index: -1;
        }
        .app-container { width: 100%; max-width: 480px; padding-bottom: 40px; }
        
        .logo-header { text-align: center; margin: 20px 0 15px; }
        .logo-header h1 { 
            font-size: 1.8rem; 
            background: linear-gradient(135deg, #4ade80, #3b82f6); 
            -webkit-background-clip: text; 
            -webkit-text-fill-color: transparent; 
            font-weight: 900;
        }

        .card {
            background: rgba(30, 41, 59, 0.85);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            padding: 25px;
            margin: 0 16px;
            border: 1px solid rgba(255,255,255,0.1);
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        }

        /* GRID LAYOUT 2 COLUMNAS */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .full-width {
            grid-column: span 2;
        }

        .input-group {
            display: flex;
            flex-direction: column;
        }

        .input-label {
            color: #94a3b8;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input, select {
            width: 100%;
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.15);
            background: rgba(15,23,42,0.6);
            color: white;
            font-size: 0.95rem;
            transition: all 0.3s;
        }
        .input:focus, select:focus {
            outline: none;
            border-color: #3b82f6;
            background: rgba(15,23,42,0.9);
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
        }
        .btn:active { transform: scale(0.98); }
        
        .btn-secondary {
            background: rgba(255,255,255,0.1);
            color: #cbd5e1;
            margin-top: 10px;
        }

        .hidden { display: none !important; }
        
        /* Toast */
        #toast {
            visibility: hidden; min-width: 250px; background-color: #333; color: #fff;
            text-align: center; border-radius: 8px; padding: 16px; position: fixed;
            z-index: 1000; left: 50%; bottom: 30px; transform: translateX(-50%);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        #toast.show { visibility: visible; animation: fadein 0.5s, fadeout 0.5s 2.5s; }
        @keyframes fadein { from {bottom: 0; opacity: 0;} to {bottom: 30px; opacity: 1;} }
        @keyframes fadeout { from {bottom: 30px; opacity: 1;} to {bottom: 0; opacity: 0;} }

        /* Mini Submodal Style */
        .mini-modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.8); z-index: 3000;
            display: flex; justify-content: center; align-items: center;
            backdrop-filter: blur(5px);
        }
        .mini-modal-content {
            background: #1e293b;
            width: 90%; max-width: 350px;
            padding: 25px;
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.1);
            text-align: center;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
            animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        @keyframes popIn { from {transform: scale(0.8); opacity: 0;} to {transform: scale(1); opacity: 1;} }
        
        .modal-title { color: #f1f5f9; font-size: 1.2rem; margin-bottom: 10px; font-weight: bold; }
        .modal-text { color: #94a3b8; font-size: 0.9rem; margin-bottom: 20px; line-height: 1.5; }
        
        .btn-group { display: flex; flex-direction: column; gap: 10px; }
        .btn-primary-action { background: #4ade80; color: #0f172a; }
        .btn-outline-action { background: transparent; border: 1px solid #4ade80; color: #4ade80; }

        /* Lista de Clubes (dentro del mini modal o nuevo modal) */
        .club-list-container {
            max-height: 200px; overflow-y: auto; text-align: left;
            background: rgba(0,0,0,0.2); border-radius: 10px; padding: 10px;
            margin-bottom: 15px;
        }
        .club-item {
            padding: 10px; border-bottom: 1px solid rgba(255,255,255,0.05);
            cursor: pointer; display: flex; justify-content: space-between; align-items: center;
        }
        .club-item:hover { background: rgba(255,255,255,0.05); }
        .club-name { font-weight: bold; color: #e2e8f0; }
        .club-btn { font-size: 0.7rem; background: #3b82f6; padding: 4px 8px; border-radius: 4px; color: white; border: none; }
    </style>
</head>
<body>

<div class="app-container">
    <div class="logo-header">
        <h1>CanchaSport ⚽</h1>
        <p>Registro Rápido de Socio</p>
    </div>

    <div class="card">
        <form id="formRegistro" onsubmit="enviarCodigo(event)">
            <input type="hidden" id="club_slug" value="<?= htmlspecialchars($club_slug) ?>">

            <!-- FILA 1: Nombre | Género -->
            <div class="form-grid">
                <div class="input-group full-width"> <!-- Nombre ocupa todo o mitad? El prompt dice 2 por fila. Nombre suele ser largo, lo dejo full o half. Prompt: "Nombre Género". Asumo mitad/mitad. -->
                    <label class="input-label">Nombre Completo</label>
                    <input type="text" id="nombre" class="input" placeholder="Ej: Juan Pérez" required autocomplete="name">
                </div>
                <div class="input-group">
                    <label class="input-label">Género</label>
                    <select id="genero" class="input" required>
                        <option value="" disabled selected>...</option>
                        <option value="masculino">Masc.</option>
                        <option value="femenino">Fem.</option>
                        <option value="otro">Otro</option>
                    </select>
                </div>
            </div>

            <!-- FILA 2: Celular | Correo -->
            <div class="form-grid">
                <div class="input-group">
                    <label class="input-label">Celular</label>
                    <input type="tel" id="celular" class="input" placeholder="+56 9..." required autocomplete="tel">
                </div>
                <div class="input-group">
                    <label class="input-label">Correo</label>
                    <input type="email" id="email" class="input" placeholder="tu@email.com" required autocomplete="email">
                </div>
            </div>

            <!-- FILA 3: Deporte | Puesto/Nivel -->
            <div class="form-grid">
                <div class="input-group">
                    <label class="input-label">Deporte Principal</label>
                    <select id="deporte" class="input" required onchange="actualizarCampoDeporte()">
                        <option value="" disabled selected>Seleccionar</option>
                        <option value="futbol">Fútbol</option>
                        <option value="futbolito">Futbolito</option>
                        <option value="padel">Pádel</option>
                        <option value="tenis">Tenis</option>
                        <option value="voleyball">Vóleibol</option>
                        <option value="otro">Otro</option>
                    </select>
                </div>
                <div class="input-group">
                    <label class="input-label" id="label_puesto_nivel">Puesto</label>
                    <select id="puesto_nivel" class="input">
                        <!-- Se llena dinámicamente -->
                        <option value="">Indistinto</option>
                        <option value="Arquero">Arquero</option>
                        <option value="Defensa">Defensa</option>
                        <option value="Mediocampo">Mediocampo</option>
                        <option value="Delantero">Delantero</option>
                    </select>
                </div>
            </div>

            <!-- FILA 4: Contraseña | Confirmar -->
            <div class="form-grid">
                <div class="input-group">
                    <label class="input-label">Contraseña</label>
                    <input type="password" id="password" class="input" placeholder="Mín. 6 caracteres" minlength="6" required>
                </div>
                <div class="input-group">
                    <label class="input-label">Confirmar</label>
                    <input type="password" id="password_confirm" class="input" placeholder="Repite contraseña" required>
                </div>
            </div>

            <!-- Campos Ocultos -->
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
                ¿Ya tienes cuenta? <a href="../index.php" style="color: #60a5fa; text-decoration: none; font-weight: bold;">Iniciar sesión</a>
            </p>
        </form>
    </div>
</div>

<!-- MINI SUBMODAL PARA FÚTBOL/VÓLEY -->
<div id="modalBloqueoDeporte" class="mini-modal-overlay hidden">
    <div class="mini-modal-content">
        <div class="modal-title">⚠️ Información Importante</div>
        <p class="modal-text" id="textoBloqueo">
            Para registrarte en <strong>Fútbol/Futbolito</strong> o <strong>Vóleibol</strong> debes pertenecer a un club.
        </p>
        
        <div class="btn-group">
            <button class="btn btn-primary-action" onclick="abrirRegistroClub()">➕ Crear Club de Amigos</button>
            <button class="btn btn-outline-action" onclick="mostrarListaClubes()">🔍 Buscar Club para Unirme</button>
            <button class="btn btn-secondary" onclick="cerrarModalBloqueo()" style="background:transparent; border:none; color:#64748b; font-size:0.8rem; margin-top:5px;">Cancelar y cambiar deporte</button>
        </div>
    </div>
</div>

<!-- MODAL LISTA DE CLUBES (Dentro del overlay o separado) -->
<div id="modalListaClubes" class="mini-modal-overlay hidden">
    <div class="mini-modal-content" style="max-height: 80vh; display:flex; flex-direction:column;">
        <div class="modal-title">Clubes Disponibles</div>
        <p class="modal-text">Selecciona un club para enviar tu solicitud de incorporación.</p>
        
        <div id="listaClubesContainer" class="club-list-container">
            <!-- Se llena con JS -->
            <div style="text-align:center; padding:10px; color:#64748b;">Cargando clubes...</div>
        </div>

        <button class="btn btn-secondary" onclick="document.getElementById('modalListaClubes').classList.add('hidden')">Cerrar</button>
    </div>
</div>

<!-- Modal de Verificación (Simple) -->
<div id="verify-modal" class="mini-modal-overlay hidden">
    <div class="mini-modal-content">
        <div class="modal-title">Verifica tu correo</div>
        <p class="modal-text">Código enviado a: <span id="verify-email-display" style="color:#4ade80; font-weight:bold;"></span></p>
        <input type="text" id="codigo_verificacion" class="input" placeholder="000000" maxlength="6" style="text-align:center; letter-spacing:5px; font-size:1.5rem; font-weight:bold; margin-bottom:15px;">
        <button class="btn" onclick="validarYRegistrar()">✅ Activar Cuenta</button>
        <button class="btn btn-secondary" onclick="volverAlRegistro()">Cancelar</button>
    </div>
</div>

<div id="toast">Mensaje</div>

<script>
    // Lógica Dinámica de Deporte
    function actualizarCampoDeporte() {
        const deporte = document.getElementById('deporte').value;
        const label = document.getElementById('label_puesto_nivel');
        const select = document.getElementById('puesto_nivel');
        const deportesRestringidos = ['futbol', 'futbolito', 'voleyball'];

        if (deportesRestringidos.includes(deporte)) {
            // Mostrar Mini Modal de Bloqueo
            document.getElementById('textoBloqueo').innerHTML = `Para registrarte en <strong>${deporte.toUpperCase()}</strong> debes crear un Club de amigos o unirte a uno existente.`;
            document.getElementById('modalBloqueoDeporte').classList.remove('hidden');
            // Resetear select visualmente para forzar elección
            select.value = ""; 
        } else if (deporte === 'padel') {
            // Cambiar a Niveles de Pádel
            label.textContent = "Nivel";
            select.innerHTML = `
                <option value="">Seleccionar Nivel</option>
                <option value="Sexta">Sexta Categoría</option>
                <option value="Quinta">Quinta Categoría</option>
                <option value="Cuarta">Cuarta Categoría</option>
                <option value="Tercera">Tercera Categoría</option>
                <option value="Segunda">Segunda Categoría</option>
                <option value="Primera">Primera Categoría</option>
            `;
        } else {
            // Volver a Puestos normales (Fútbol genérico u otros)
            label.textContent = "Puesto";
            select.innerHTML = `
                <option value="">Indistinto</option>
                <option value="Arquero">Arquero</option>
                <option value="Defensa">Defensa</option>
                <option value="Mediocampo">Mediocampo</option>
                <option value="Delantero">Delantero</option>
                <option value="Volante">Volante</option>
            `;
        }
    }

    function cerrarModalBloqueo() {
        document.getElementById('modalBloqueoDeporte').classList.add('hidden');
        document.getElementById('deporte').value = ""; // Resetear selección
    }

    // Acción 1: Crear Club
    function abrirRegistroClub() {
        cerrarModalBloqueo();
        // Redirigir al registro de club pasando los datos actuales si es posible, o simplemente redirigir
        window.location.href = 'registro_club.php?prefill_nombre=' + encodeURIComponent(document.getElementById('nombre').value) + '&prefill_email=' + encodeURIComponent(document.getElementById('email').value);
    }

    // Acción 2: Buscar Club
    async function mostrarListaClubes() {
        document.getElementById('modalBloqueoDeporte').classList.add('hidden');
        const modalLista = document.getElementById('modalListaClubes');
        const container = document.getElementById('listaClubesContainer');
        
        modalLista.classList.remove('hidden');
        container.innerHTML = '<div style="text-align:center; padding:10px;">Cargando...</div>';

        try {
            const response = await fetch('../api/listar_clubes_publicos.php');
            const clubes = await response.json();
            
            if (clubes.length === 0) {
                container.innerHTML = '<div style="padding:10px; text-align:center;">No hay clubes registrados aún.</div>';
                return;
            }

            container.innerHTML = '';
            clubes.forEach(club => {
                const div = document.createElement('div');
                div.className = 'club-item';
                div.innerHTML = `
                    <span class="club-name">${club.nombre}</span>
                    <button class="club-btn" onclick="solicitarUnion(${club.id_club}, '${club.email_responsable}')">Solicitar</button>
                `;
                container.appendChild(div);
            });

        } catch (error) {
            container.innerHTML = '<div style="padding:10px; color:red;">Error al cargar clubes.</div>';
        }
    }

    // Enviar Solicitud de Unión
    async function solicitarUnion(id_club, email_responsable) {
        const nombre = document.getElementById('nombre').value;
        const email = document.getElementById('email').value;
        const celular = document.getElementById('celular').value;
        const deporte = document.getElementById('deporte').value;

        if (!nombre || !email) {
            showToast("⚠️ Completa Nombre y Correo primero.");
            return;
        }

        const btn = event.target;
        btn.textContent = "Enviando...";
        btn.disabled = true;

        try {
            const formData = new FormData();
            formData.append('id_club', id_club);
            formData.append('email_responsable', email_responsable);
            formData.append('nombre_socio', nombre);
            formData.append('email_socio', email);
            formData.append('celular_socio', celular);
            formData.append('deporte', deporte);

            const res = await fetch('../api/solicitar_union_club.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (data.success) {
                showToast("✅ Solicitud enviada al responsable del club.");
                document.getElementById('modalListaClubes').classList.add('hidden');
                // Opcional: Redirigir o dejar en espera
            } else {
                showToast("❌ Error: " + data.message);
                btn.textContent = "Solicitar";
                btn.disabled = false;
            }
        } catch (error) {
            showToast("❌ Error de conexión");
            btn.textContent = "Solicitar";
            btn.disabled = false;
        }
    }

    // Lógica de Registro Normal (Email/Pass)
    async function enviarCodigo(e) {
        e.preventDefault();
        const deporte = document.getElementById('deporte').value;
        if (!deporte) { showToast("Selecciona un deporte"); return; }
        
        // Si es fútbol/voley y no se resolvió el modal, bloquear
        if (['futbol', 'futbolito', 'voleyball'].includes(deporte)) {
            showToast("⚠️ Debes crear o unirte a un club para este deporte.");
            actualizarCampoDeporte(); // Reabre modal
            return;
        }

        if (document.getElementById('password').value !== document.getElementById('password_confirm').value) {
            showToast("❌ Las contraseñas no coinciden");
            return;
        }

        const btn = document.getElementById('btnEnviar');
        btn.innerHTML = 'Enviando...';
        btn.disabled = true;

        const formData = new FormData();
        formData.append('nombre', document.getElementById('nombre').value);
        formData.append('alias', document.getElementById('nombre').value.split(' ')[0]); // Alias simple
        formData.append('genero', document.getElementById('genero').value);
        formData.append('celular', document.getElementById('celular').value);
        formData.append('email', document.getElementById('email').value);
        formData.append('deporte', deporte);
        formData.append('puesto', document.getElementById('puesto_nivel').value); // Guarda puesto o nivel
        formData.append('password', document.getElementById('password').value);
        formData.append('rol', 'Jugador');
        formData.append('fecha_nac', '2000-01-01');
        formData.append('region', 'Metropolitana');
        formData.append('ciudad', 'Santiago');
        formData.append('comuna', 'Comuna');
        formData.append('direccion', 'Pendiente');
        formData.append('habilidad', 'Intermedia');
        formData.append('club_slug', document.getElementById('club_slug').value);

        try {
            const response = await fetch('../api/enviar_codigo_socio.php', { method: 'POST', body: formData });
            const data = await response.json();

            if (data.success) {
                document.getElementById('verify-email-display').textContent = document.getElementById('email').value;
                document.getElementById('formRegistro').closest('.card').classList.add('hidden');
                document.getElementById('verify-modal').classList.remove('hidden');
                showToast("✅ Código enviado");
            } else {
                showToast("❌ " + data.message);
                btn.innerHTML = '🚀 Enviar Código';
                btn.disabled = false;
            }
        } catch (error) {
            showToast("❌ Error de conexión");
            btn.innerHTML = '🚀 Enviar Código';
            btn.disabled = false;
        }
    }

    async function validarYRegistrar() {
        const codigo = document.getElementById('codigo_verificacion').value;
        if (codigo.length !== 6) { showToast("Ingresa el código"); return; }

        const btn = document.querySelector('#verify-modal .btn');
        btn.innerHTML = 'Activando...';
        btn.disabled = true;

        // Reenviar datos completos para registro final
        const formData = new FormData();
        formData.append('email', document.getElementById('verify-email-display').textContent);
        formData.append('codigo', codigo);
        formData.append('nombre', document.getElementById('nombre').value);
        formData.append('alias', document.getElementById('nombre').value.split(' ')[0]);
        formData.append('genero', document.getElementById('genero').value);
        formData.append('celular', document.getElementById('celular').value);
        formData.append('deporte', document.getElementById('deporte').value);
        formData.append('puesto', document.getElementById('puesto_nivel').value);
        formData.append('password', document.getElementById('password').value);
        formData.append('rol', 'Jugador');
        formData.append('fecha_nac', '2000-01-01');
        formData.append('region', 'Metropolitana');
        formData.append('ciudad', 'Santiago');
        formData.append('comuna', 'Comuna');
        formData.append('direccion', 'Pendiente');
        formData.append('habilidad', 'Intermedia');
        formData.append('club_slug', document.getElementById('club_slug').value);

        try {
            const response = await fetch('../api/verificar_y_registrar_socio.php', { method: 'POST', body: formData });
            const data = await response.json();
            if (data.success) {
                showToast("¡Bienvenido a CanchaSport!");
                setTimeout(() => window.location.href = '../index.php', 2000);
            } else {
                showToast("❌ " + data.message);
                btn.innerHTML = '✅ Activar Cuenta';
                btn.disabled = false;
            }
        } catch (error) {
            showToast("❌ Error");
            btn.innerHTML = '✅ Activar Cuenta';
            btn.disabled = false;
        }
    }

    function volverAlRegistro() {
        document.getElementById('verify-modal').classList.add('hidden');
        document.getElementById('formRegistro').closest('.card').classList.remove('hidden');
    }

    function showToast(msg) {
        const t = document.getElementById("toast");
        t.textContent = msg;
        t.className = "show";
        setTimeout(() => t.className = t.className.replace("show", ""), 3000);
    }
</script>
</body>
</html>