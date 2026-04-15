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
    <title>Únete a CanchaSport ⚽🎾🏐</title>
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
        <h1>CanchaSport ⚽🎾🏐</h1>
        <p>Registro Rápido de Socio</p>
    </div>

    <div class="card">
        <form id="formRegistro" onsubmit="enviarCodigo(event)">
            <input type="hidden" id="club_slug" value="<?= htmlspecialchars($club_slug) ?>">

            <!-- FILA 1: Nombre | Género -->
            <div class="form-grid">
                <div class="input-group"> 
                    <label class="input-label">Nombre Completo</label>
                    <input type="text" id="nombre" class="input" placeholder="Ej: Nico Pérez" required autocomplete="name">
                </div>
                <div class="input-group">
                    <label class="input-label">Género</label>
                    <select id="genero" class="input" required>
                        <option value="" disabled selected>...</option>
                        <option value="masculino">Masculino</option>
                        <option value="femenino">Femenino</option>
                        <option value="otro">Otro</option>
                    </select>
                </div>
            </div>

            <!-- FILA 2: Celular | Correo -->
            <div class="form-grid">
                <div class="input-group">
                    <label class="input-label">Celular</label>
                    <input type="tel" id="celular_input" class="input" placeholder="+56 9..." required autocomplete="tel">
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
                        <option value="Fútbol">Fútbol</option>
                        <option value="Futbolito">Futbolito</option>
                        <option value="Pádel">Pádel</option>
                        <option value="Tenis">Tenis</option>
                        <option value="Vóleibol">Vóleibol</option>
                        <option value="Otro">Otro</option>
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

            <!-- FILA 4: Contraseña | Confirmar (Con Ojo) -->
            <div class="form-grid">
                <div class="input-group">
                    <label class="input-label">Contraseña <span style="cursor:pointer; float:right;" onclick="togglePassword('password', this)">👁️</span></label>
                    <input type="password" id="password" class="input" placeholder="Mín. 6 caracteres" minlength="6" required>
                </div>
                <div class="input-group">
                    <label class="input-label">Confirmar <span style="cursor:pointer; float:right;" onclick="togglePassword('password_confirm', this)">👁️</span></label>
                    <input type="password" id="password_confirm" class="input" placeholder="Repite contraseña" required>
                </div>
            </div>

            <!-- Campos Ocultos -->
            <input type="hidden" id="alias_hidden" name="alias">
            <script>
                document.getElementById('nombre').addEventListener('input', function(e) {
                    const nombres = e.target.value.trim().split(' ');
                    document.getElementById('alias_hidden').value = nombres[0] || '';
                });
            </script>
            <input type="hidden" id="rol" value="Jugador">
            <input type="hidden" id="fecha_nac" value="2000-01-01">
            <input type="hidden" name="pais" value="Chile">
            <input type="hidden" id="region" value="Metropolitana">
            <input type="hidden" id="ciudad" value="Santiago">
            <input type="hidden" id="comuna" value="Ñuñoa">
            <input type="hidden" id="direccion" value="Pendiente">
            <input type="hidden" id="habilidad" value="Intermedia">
            <input type="hidden" name="id_puesto" value="1">
            <input type="hidden" name="celular_hidden" value="+56900000000"> 

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
    // === VARIABLES GLOBALES ===
    let codigoEnviado = '';
    let emailTemp = '';

    // === FUNCIÓN 1: Toggle Ver Contraseña ===
    function togglePassword(inputId, iconElement) {
        const input = document.getElementById(inputId);
        if (!input) return;
        if (input.type === "password") {
            input.type = "text";
            iconElement.textContent = "🔒"; 
        } else {
            input.type = "password";
            iconElement.textContent = "️"; 
        }
    }

    // === FUNCIÓN 2: Actualizar Campo Deporte ===
    function actualizarCampoDeporte() {
        const deporteSelect = document.getElementById('deporte');
        if (!deporteSelect) return;

        const deporte = deporteSelect.value;
        const label = document.getElementById('label_puesto_nivel');
        const select = document.getElementById('puesto_nivel');
        
        // CORRECCIÓN: Usar los valores exactos del HTML (con mayúsculas y tildes)
        const deportesRestringidos = ['Fútbol', 'Futbolito', 'Vóleibol'];

        if (!label || !select) return;

        if (deportesRestringidos.includes(deporte)) {
            const modal = document.getElementById('modalBloqueoDeporte');
            if (modal) {
                document.getElementById('textoBloqueo').innerHTML = `Para registrarte en <strong>${deporte}</strong> debes crear un Club o unirte a uno existente ⚽.`;
                modal.classList.remove('hidden');
            }
            select.value = ""; 
        } else if (deporte === 'Pádel') {
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

    // === FUNCIÓN 3: Cerrar Modal Bloqueo ===
    function cerrarModalBloqueo() {
        const modal = document.getElementById('modalBloqueoDeporte');
        if (modal) modal.classList.add('hidden');
        const deporteSelect = document.getElementById('deporte');
        if (deporteSelect) deporteSelect.value = ""; 
    }

    // === FUNCIÓN 4: Abrir Registro Club ===
    function abrirRegistroClub() {
        cerrarModalBloqueo();
        const nombre = document.getElementById('nombre') ? encodeURIComponent(document.getElementById('nombre').value) : '';
        const email = document.getElementById('email') ? encodeURIComponent(document.getElementById('email').value) : '';
        window.location.href = 'registro_club.php?prefill_nombre=' + nombre + '&prefill_email=' + email;
    }

    // === FUNCIÓN 5: Mostrar Lista Clubes ===
    async function mostrarListaClubes() {
        cerrarModalBloqueo();
        const modalLista = document.getElementById('modalListaClubes');
        const container = document.getElementById('listaClubesContainer');
        if (!modalLista || !container) return;

        modalLista.classList.remove('hidden');
        container.innerHTML = '<div style="text-align:center; padding:10px; color:#64748b;">Cargando clubes...</div>';

        try {
            const response = await fetch('../api/listar_clubes_publicos.php');
            if (!response.ok) throw new Error('Error red');
            const clubes = await response.json();
            
            if (!clubes || clubes.length === 0) {
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
            console.error(error);
            container.innerHTML = '<div style="padding:10px; color:red;">Error al cargar clubes.</div>';
        }
    }

    // === FUNCIÓN 6: Solicitar Unión ===
    async function solicitarUnion(id_club, email_responsable) {
        const nombreInput = document.getElementById('nombre');
        const emailInput = document.getElementById('email');
        const celularInput = document.getElementById('celular_input'); // ID corregido
        const deporteInput = document.getElementById('deporte');

        if (!nombreInput || !emailInput) { showToast("Completa Nombre y Correo."); return; }
        const nombre = nombreInput.value;
        const email = emailInput.value;
        const celular = celularInput ? celularInput.value : '';
        const deporte = deporteInput ? deporteInput.value : '';

        if (!nombre || !email) { showToast("️ Completa Nombre y Correo."); return; }

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

            const res = await fetch('../api/solicitar_union_club.php', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.success) {
                showToast("✅ Solicitud enviada.");
                document.getElementById('modalListaClubes').classList.add('hidden');
            } else {
                showToast(" Error: " + data.message);
                btn.textContent = "Solicitar";
                btn.disabled = false;
            }
        } catch (error) {
            console.error(error);
            showToast("❌ Error de conexión");
            btn.textContent = "Solicitar";
            btn.disabled = false;
        }
    }

    // === FUNCIÓN 7: Enviar Código (CORREGIDA: Llave faltante arreglada) ===
    async function enviarCodigo(e) {
        e.preventDefault(); 
        
        const deporteSelect = document.getElementById('deporte');
        const deporte = deporteSelect ? deporteSelect.value : '';
        
        if (!deporte) { showToast("Selecciona un deporte"); return; }
        
        if (['Fútbol', 'Futbolito', 'Vóleibol'].includes(deporte)) {
            showToast("⚠️ Debes crear o unirte a un club.");
            actualizarCampoDeporte(); 
            return;
        }

        const passInput = document.getElementById('password');
        const passConfInput = document.getElementById('password_confirm');
        if (!passInput || !passConfInput) { showToast("Error en formulario"); return; }

        const password = passInput.value;
        const passConfirm = passConfInput.value;

        if (!passConfirm) { showToast("Confirma contraseña"); return; }
        if (password !== passConfirm) { showToast("Contraseñas no coinciden"); return; }
        if (password.length < 6) { showToast("Mínimo 6 caracteres"); return; }

        const btn = document.getElementById('btnEnviar');
        if (btn) { btn.innerHTML = 'Enviando...'; btn.disabled = true; }

        const formData = new FormData();
        formData.append('nombre', document.getElementById('nombre').value);
        formData.append('alias', document.getElementById('nombre').value.split(' ')[0]); 
        formData.append('genero', document.getElementById('genero').value);
        formData.append('celular', document.getElementById('celular_input').value); 
        formData.append('email', document.getElementById('email').value);
        formData.append('deporte', deporte);
        formData.append('puesto', document.getElementById('puesto_nivel').value); 
        formData.append('password', password);
        formData.append('password_confirm', passConfirm);
        formData.append('rol', 'Jugador');
        formData.append('fecha_nac', '2000-01-01');
        formData.append('pais', 'Chile');
        formData.append('region', 'Metropolitana');
        formData.append('ciudad', 'Santiago');
        formData.append('comuna', 'Ñuñoa');
        formData.append('direccion', 'Pendiente');
        formData.append('habilidad', 'Intermedia');
        formData.append('club_slug', document.getElementById('club_slug').value);

        try {
            const response = await fetch('../api/enviar_codigo_socio.php', { method: 'POST', body: formData });
            if (!response.ok) throw new Error('Error en la red');
            
            const data = await response.json();
            console.log("Respuesta API:", data);

            if (data.success) {
                emailTemp = document.getElementById('email').value;
                const verifyDisplay = document.getElementById('verify-email-display');
                if (verifyDisplay) verifyDisplay.textContent = emailTemp;

                const formCard = document.querySelector('#formRegistro'); 
                const containerToHide = formCard ? formCard.closest('.card') : null;
                const verifyModal = document.getElementById('verify-modal');
                
                if (containerToHide) containerToHide.classList.add('hidden');
                if (verifyModal) {
                    verifyModal.classList.remove('hidden');
                    console.log("✅ Modal mostrado");
                }
                showToast("✅ Código enviado");
            } else {
                showToast("❌ " + (data.message || 'Error'));
                if (btn) { btn.innerHTML = ' Enviar Código'; btn.disabled = false; }
            }
        } catch (error) { // <--- AQUÍ ESTABA EL ERROR DE SINTAXIS (Faltaba cerrar try antes)
            console.error("💥 Error:", error);
            showToast("❌ Error de conexión");
            if (btn) { btn.innerHTML = ' Enviar Código'; btn.disabled = false; }
        }
    }    

    // === FUNCIÓN 8: Validar y Registrar Final (CORREGIDA: Llave faltante arreglada) ===
    async function validarYRegistrar() {
        console.log(" [DEBUG] Iniciando validación...");
        
        const codigoInput = document.getElementById('codigo_verificacion');
        if (!codigoInput) return;
        
        const codigo = codigoInput.value.trim();
        
        if (codigo.length !== 4 || !/^\d{4}$/.test(codigo)) { 
            showToast("Ingresa el código de 4 dígitos"); 
            return; 
        }

        const btn = document.querySelector('#verify-modal .btn');
        if (btn) { btn.innerHTML = 'Activando...'; btn.disabled = true; }

        const payload = { codigo: codigo };

        try {
            const response = await fetch('../api/verificar_codigo_socio.php', { 
                method: 'POST', 
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            if (!response.ok) throw new Error(`Error HTTP: ${response.status}`);

            const data = await response.json();
            console.log("Respuesta Verificación:", data);

            if (data.success) {
                showToast("¡Cuenta activada!");
                setTimeout(() => window.location.href = '../index.php', 1500);
            } else {
                showToast("❌ " + (data.message || 'Código inválido'));
                if (btn) { btn.innerHTML = '✅ Activar Cuenta'; btn.disabled = false; }
            }
        } catch (error) { // <--- AQUÍ TAMBIÉN FALTABA CERRAR EL TRY
            console.error("💥 Error:", error);
            showToast("❌ Error al verificar");
            if (btn) { btn.innerHTML = '✅ Activar Cuenta'; btn.disabled = false; }
        }
    }

    function volverAlRegistro() {
        const verifyModal = document.getElementById('verify-modal');
        const formCard = document.getElementById('formRegistro').closest('.card');
        if (verifyModal) verifyModal.classList.add('hidden');
        if (formCard) formCard.classList.remove('hidden');
    }

    function showToast(msg) {
        const t = document.getElementById("toast");
        if (!t) return;
        t.textContent = msg;
        t.className = "show";
        setTimeout(() => t.className = t.className.replace("show", ""), 3000);
    }
    
    // Script para alias al escribir
    document.addEventListener('DOMContentLoaded', function() {
        const nombreInput = document.getElementById('nombre');
        const aliasHidden = document.getElementById('alias_hidden');
        if (nombreInput && aliasHidden) {
            nombreInput.addEventListener('input', function(e) {
                const nombres = e.target.value.trim().split(' ');
                aliasHidden.value = nombres[0] || '';
            });
        }
    });
</script>
</body>
</html>