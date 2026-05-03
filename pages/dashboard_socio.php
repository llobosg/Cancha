<!-- === INYECCIÓN SEGURA DE VARIABLES PHP (EVITA DUPLICADOS) === -->
<script>
// Variables PHP → JS (solo si no existen ya)
if (typeof SOCIO_ID === 'undefined') {
    const SOCIO_ID = <?= (int)($id_socio ?? 0) ?>;
    const ES_MULTICLUB = <?= $es_multiclub ? 'true' : 'false' ?>;
    const CLUB_ACTUAL = "<?= $club_actual_slug ?? '' ?>";
    const LIMITE_LLENO = <?= $limite_lleno ?? false ? 'true' : 'false' ?>;
    const PROXIMO_ID = <?= $proximo['id_reserva'] ?? 0 ?>;
    const ES_RESPONSABLE = <?= $es_responsable ? 'true' : 'false' ?>;
    const MODO_INDIVIDUAL = <?= $modo_individual ? 'true' : 'false' ?>;
    const CLUB_ID = <?= $club_id ?? 'null' ?>;
    const CLUB_NOMBRE = <?= json_encode($club_nombre ?? '') ?>;
    
    console.log('✅ Variables cargadas | SOCIO_ID:', SOCIO_ID, 'ES_MULTICLUB:', ES_MULTICLUB);
}
</script>

<!-- === FUNCIONES GLOBALES (para onclick) === -->
<script>
// ============================================================================
// === 1. UTILITARIAS GLOBALES ===
// ============================================================================

function showToast(msg, type = 'success') {
    const t = document.getElementById('toast');
    if (!t) return;
    t.textContent = msg;
    const colors = { success: '#2E7D32', error: '#C62828', warning: '#EF6C00', info: '#1976D2' };
    t.style.background = colors[type] || colors.success;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}

function closeAllMenus() {
    document.querySelectorAll('.menu-dropdown').forEach(menu => {
        menu.classList.remove('active');
        if (menu.id === 'selectorClubes') menu.style.display = 'none';
    });
}

// ============================================================================
// === 2. MENÚ HEADER (Perfil + Cambiar Club) ===
// ============================================================================

function toggleHeaderMenu(e) {
    e.stopPropagation();
    closeAllMenus();
    const menu = document.getElementById('headerMenu');
    if (menu) menu.classList.toggle('active');
}

async function abrirSelectorClubes(e) {
    e.stopPropagation();
    console.log('🔍 abrirSelectorClubes | SOCIO_ID:', typeof SOCIO_ID !== 'undefined' ? SOCIO_ID : 'NO DEFINIDO');
    
    const selector = document.getElementById('selectorClubes');
    const lista = document.getElementById('listaClubes');
    
    if (!selector || !lista) {
        console.error('❌ Faltan elementos #selectorClubes o #listaClubes');
        showToast('❌ Error de interfaz', 'error');
        return;
    }
    
    selector.style.display = 'block';
    selector.classList.add('active');
    lista.innerHTML = '<div style="padding:0.8rem; text-align:center; color:#888;">🔄 Cargando clubs...</div>';
    
    try {
        const socioId = typeof SOCIO_ID !== 'undefined' ? SOCIO_ID : 0;
        if (!socioId) throw new Error('SOCIO_ID no definido');
        
        const res = await fetch(`../api/get_clubs_socio.php?id_socio=${socioId}`);
        const clubs = await res.json();
        
        if (!Array.isArray(clubs) || clubs.length === 0) {
            lista.innerHTML = '<div style="padding:0.8rem; text-align:center; color:#888;">Sin clubs disponibles</div>';
            return;
        }
        
        let html = '';
        clubs.forEach(club => {
            const esActual = club.slug === (typeof CLUB_ACTUAL !== 'undefined' ? CLUB_ACTUAL : '');
            html += `<div onclick="cambiarClub('${club.slug}')" 
                 style="padding:0.8rem 1rem; cursor:pointer; display:flex; justify-content:space-between; align-items:center; transition:background 0.2s; ${esActual ? 'background:#E8F5E9; font-weight:600;' : ''}"
                 onmouseover="this.style.background='${esActual ? '#C8E6C9' : '#F7FAFC'}'"
                 onmouseout="this.style.background='${esActual ? '#E8F5E9' : 'white'}'">
                <span>${club.nombre}</span>
                ${esActual ? '<span style="font-size:0.75rem; color:#2E7D32; background:#C8E6C9; padding:2px 8px; border-radius:10px;">Actual</span>' : ''}
            </div>`;
        });
        lista.innerHTML = html;
    } catch (err) {
        console.error('❌ Error cargando clubs:', err);
        lista.innerHTML = '<div style="padding:0.8rem; text-align:center; color:#C62828;">Error al cargar</div>';
        showToast('❌ Error al cargar clubs', 'error');
    }
}

function cambiarClub(clubSlug) {
    console.log('🔄 cambiarClub | slug:', clubSlug);
    showToast('🔄 Cambiando de club...', 'info');
    document.body.style.cursor = 'wait';
    
    fetch('../api/cambiar_club_sesion.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ club_slug: clubSlug })
    })
    .then(async r => {
        if (!r.ok) throw new Error('Error HTTP: ' + r.status);
        return r.json();
    })
    .then(data => {
        document.body.style.cursor = 'default';
        if (data.success) {
            showToast('✅ Club cambiado', 'success');
            window.location.href = `dashboard_socio.php?id_club=${clubSlug}&t=${Date.now()}`;
        } else {
            showToast('❌ ' + (data.message || 'Error'), 'error');
        }
    })
    .catch(err => {
        document.body.style.cursor = 'default';
        console.error('❌ Error:', err);
        showToast('❌ ' + err.message, 'error');
    });
}

// ============================================================================
// === 3. MENÚ FICHA PRÓXIMO PARTIDO ===
// ============================================================================

function toggleHeroMenu(e, idReserva) {
    e.stopPropagation();
    closeAllMenus();
    
    const menu = document.getElementById(`heroMenu_${idReserva}`);
    if (menu) {
        menu.classList.toggle('active');
        const itemIA = document.getElementById(`menuItemIA_${idReserva}`);
        // ✅ Verificar que LIMITE_LLENO existe antes de usarlo
        if (itemIA && typeof LIMITE_LLENO !== 'undefined' && LIMITE_LLENO) {
            itemIA.style.display = 'flex';
        }
    }
}

async function marcarPaso(idReserva) {
    showToast('👟 Marcado como "Paso"');
}

function generarEquiposIA(idReserva) {
    if (typeof LIMITE_LLENO === 'undefined' || !LIMITE_LLENO) {
        showToast('⚠️ Solo con cupos completos', 'error');
        return;
    }
    showToast('🤖 Generando equipos...');
    setTimeout(() => showToast('✅ Equipos listos'), 1500);
}

// ============================================================================
// === 4. INSCRIPCIÓN / BAJA DE RESERVA ===
// ============================================================================

async function anotarse(idReserva) {
    if (!confirm('¿Confirmas tu inscripción?')) return;
    try {
        const socioId = typeof SOCIO_ID !== 'undefined' ? SOCIO_ID : 0;
        const res = await fetch('../api/inscribir_reserva.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id_reserva: idReserva, id_socio: socioId })
        });
        const data = await res.json();
        if (data.success) {
            showToast('✅ ¡Anotado!');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('❌ ' + (data.message || 'Error'), 'error');
        }
    } catch (e) {
        showToast('❌ Error de conexión', 'error');
    }
}

async function bajarse(idReserva) {
    if (!confirm('¿Seguro que deseas bajarte?')) return;
    try {
        const socioId = typeof SOCIO_ID !== 'undefined' ? SOCIO_ID : 0;
        const res = await fetch('../api/bajar_reserva.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id_reserva: idReserva, id_socio: socioId })
        });
        const data = await res.json();
        if (data.success) {
            showToast('❌ Te has dado de baja');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('❌ ' + (data.message || 'Error'), 'error');
        }
    } catch (e) {
        showToast('❌ Error de conexión', 'error');
    }
}

// ============================================================================
// === 5. MODAL INSCRITOS ===
// ============================================================================

async function verInscritos(idReserva) {
    const modal = document.getElementById('modalInscritos');
    const lista = document.getElementById('listaInscritos');
    if (!modal || !lista) return;
    
    modal.style.display = 'flex';
    lista.innerHTML = '<p style="text-align:center; color:var(--text-light); padding:1rem;">🔄 Cargando...</p>';
    
    try {
        const res = await fetch(`../api/get_inscritos_reserva.php?id_reserva=${idReserva}`);
        const data = await res.json();
        
        if (!Array.isArray(data) || data.length === 0) {
            lista.innerHTML = '<p style="text-align:center; color:var(--text-light);">Sin inscritos aún</p>';
            return;
        }
        
        let html = '';
        const socioId = typeof SOCIO_ID !== 'undefined' ? SOCIO_ID : 0;
        data.forEach(p => {
            const esYo = p.id_socio === socioId;
            const puedeBajar = (typeof ES_RESPONSABLE !== 'undefined' && ES_RESPONSABLE) && !esYo;
            html += `<div class="inscrito-item">
                <span class="inscrito-name">${esYo ? '👤 Tú' : p.nombre}</span>
                <span class="inscrito-status">${p.estado}</span>
                ${puedeBajar ? `<button class="btn-bajar" onclick="bajarJugador(${p.id_socio}, ${idReserva}, '${p.nombre.replace(/'/g, "\\'")}')">Bajar</button>` : ''}
            </div>`;
        });
        lista.innerHTML = html;
    } catch (e) {
        console.error(e);
        lista.innerHTML = '<p style="text-align:center; color:#C62828;">Error al cargar</p>';
    }
}

async function bajarJugador(idSocioBajar, idReserva, nombre) {
    if (!confirm(`¿Bajar a "${nombre}"?`)) return;
    try {
        const socioId = typeof SOCIO_ID !== 'undefined' ? SOCIO_ID : 0;
        const res = await fetch('../api/bajar_jugador_reserva.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id_reserva: idReserva, id_socio_a_bajar: idSocioBajar, id_responsable: socioId })
        });
        const data = await res.json();
        if (data.success) {
            showToast(`✅ ${nombre} bajado`);
            verInscritos(idReserva);
        } else {
            showToast('❌ ' + (data.message || 'Error'), 'error');
        }
    } catch (e) {
        showToast('❌ Error de conexión', 'error');
    }
}

function cerrarModal(e) {
    if (e && (e.target.id === 'modalInscritos' || e.target.classList?.contains('modal-close'))) {
        document.getElementById('modalInscritos')?.style.setProperty('display', 'none');
    }
}

// ============================================================================
// === 6. EVENT LISTENERS GLOBALES ===
// ============================================================================

document.addEventListener('click', (e) => {
    if (!e.target.closest('.menu-dots') && !e.target.closest('.hero-menu-dots') && !e.target.closest('.menu-dropdown')) {
        closeAllMenus();
    }
});

// Debug al cargar
console.log('✅ dashboard_socio.js cargado');
</script>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Home - CanchaSport</title>
    <style>
        :root {
            --padel-blue: #4FC3F7;
            --tennis-green: #66BB6A;
            --gold: #FFD54F;
            --stats-blue: #42A5F5;
            --green-fluor: #76FF03;
            --text-dark: #2D3748;
            --text-light: #718096;
            --bg-transparent: transparent;
            --shadow-blue: rgba(79, 195, 247, 0.4);
            --shadow-green: rgba(102, 187, 106, 0.4);
            --overlay-dark: rgba(10, 25, 15, 0.75); /* Overlay más oscuro */
        }
        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--bg-transparent);
            background-image: url('../assets/img/cancha_pasto2.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            color: var(--text-dark);
            min-height: 100vh;
            padding-bottom: 90px;
        }
        /* Overlay más oscuro para mejor legibilidad */
        body::before {
            content: ''; position: fixed; inset: 0;
            background: var(--overlay-dark);
            z-index: -1;
        }

        /* HEADER */
        .app-header {
            background: linear-gradient(90deg, var(--padel-blue), var(--tennis-green));
            padding: 0.75rem 1.25rem;
            display: flex; justify-content: space-between; align-items: center;
            position: sticky; top: 0; z-index: 100;
            box-shadow: 0 2px 12px rgba(79, 195, 247, 0.3);
        }
        .logo { display: flex; align-items: center; gap: 0.6rem; }
        .logo-icon {
            width: 34px; height: 34px;
            background: rgba(255,255,255,0.25);
            border-radius: 12px;
            display: grid; place-items: center;
            font-size: 1.1rem;
        }
        .brand { font-weight: 700; font-size: 1.25rem; color: white; letter-spacing: -0.3px; }
        
        .header-actions { display: flex; align-items: center; gap: 0.75rem; }
        
        /* MENÚ 3 PUNTOS */
        .menu-dots {
            position: relative;
            width: 36px; height: 36px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            font-size: 1.3rem;
            cursor: pointer;
            display: grid; place-items: center;
            transition: background 0.2s;
        }
        .menu-dots:hover { background: rgba(255,255,255,0.35); }
        
        .menu-dropdown {
            display: none;
            position: absolute;
            top: 100%; right: 0;
            background: white;
            border-radius: 12px;
            min-width: 180px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
            z-index: 101;
            overflow: hidden;
            margin-top: 4px;
        }
        .menu-dropdown.active { display: block; animation: slideDown 0.2s ease; }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .menu-item {
            display: flex; align-items: center; gap: 0.6rem;
            padding: 0.8rem 1rem;
            font-size: 0.9rem;
            color: var(--text-dark);
            text-decoration: none;
            cursor: pointer;
            transition: background 0.2s;
            border-bottom: 1px solid #f0f0f0;
        }
        .menu-item:last-child { border-bottom: none; }
        .menu-item:hover { background: #F7FAFC; }
        .menu-item.danger { color: #C62828; font-weight: 500; }
        .menu-item:disabled { opacity: 0.5; cursor: not-allowed; }
        
        .avatar {
            width: 38px; height: 38px; border-radius: 50%;
            background: white; color: var(--padel-blue);
            font-weight: 600; font-size: 0.95rem;
            display: grid; place-items: center;
            border: 2px solid rgba(255,255,255,0.7);
            text-decoration: none;
        }

        .container { max-width: 560px; margin: 0 auto; padding: 1.25rem; }

        /* HERO CARD */
        .hero {
            background: linear-gradient(135deg, var(--padel-blue) 0%, var(--tennis-green) 100%);
            border-radius: 28px;
            padding: 1.75rem 1.5rem;
            margin-bottom: 1.75rem;
            box-shadow: 
                0 10px 30px rgba(79, 195, 247, 0.35),
                0 10px 30px rgba(102, 187, 106, 0.25);
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.4);
            color: white;
        }
        .hero::before {
            content: ''; position: absolute; top: -40%; right: -15%;
            width: 160px; height: 160px;
            background: radial-gradient(circle, rgba(255,255,255,0.25) 0%, transparent 70%);
            border-radius: 50%; pointer-events: none;
        }
        
        .hero-title {
            font-size: 1.1rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 0.75rem;
            opacity: 0.95;
        }
        
        .hero-meta {
            display: flex; gap: 1rem;
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            font-weight: 500;
        }
        .hero-meta span { display: flex; align-items: center; gap: 0.3rem; }
        
        .btn-hero {
            width: 100%;
            padding: 1rem;
            border-radius: 18px;
            font-weight: 700;
            font-size: 1.05rem;
            border: none;
            cursor: pointer;
            background: white;
            color: var(--tennis-green);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.2s;
            box-shadow: 0 4px 14px rgba(0,0,0,0.15);
        }
        .btn-hero:active { transform: scale(0.98); }
        .btn-hero.inscrito {
            background: rgba(255,255,255,0.95);
            color: #E53E3E;
        }
        .btn-hero:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        /* BARRA DE PROGRESO */
        .progress-section {
            margin-top: 1.25rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(255,255,255,0.5);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .progress-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: white;
            opacity: 0.95;
        }
        .progress-track {
            flex: 1;
            height: 8px;
            background: rgba(255,255,255,0.35);
            border-radius: 4px;
            overflow: hidden;
            position: relative;
        }
        .progress-fill {
            height: 100%;
            width: <?= $progress_percent ?>%;
            background: linear-gradient(90deg, 
                #66BB6A 0%, 
                #66BB6A 60%, 
                #FFB300 80%, 
                #EF5350 100%);
            border-radius: 4px;
            transition: width 0.4s ease;
        }
        .progress-eye {
            width: 32px; height: 32px;
            border-radius: 50%;
            background: rgba(255,255,255,0.25);
            border: none;
            color: white;
            font-size: 1rem;
            cursor: pointer;
            display: grid; place-items: center;
            transition: background 0.2s;
        }
        .progress-eye:hover { background: rgba(255,255,255,0.4); }

        /* QUICK ACTIONS */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .action-card {
            background: rgba(255,255,255,0.95);
            border-radius: 20px;
            padding: 1.25rem 0.75rem;
            text-align: center;
            text-decoration: none;
            color: var(--text-dark);
            box-shadow: 0 6px 20px var(--action-shadow);
            transition: transform 0.2s, box-shadow 0.2s;
            border: 1px solid rgba(255,255,255,0.8);
            position: relative;
            overflow: hidden;
        }
        .action-card::after {
            content: ''; position: absolute; inset: 0;
            background: linear-gradient(180deg, transparent 60%, var(--action-shadow) 100%);
            opacity: 0.15; pointer-events: none;
        }
        .action-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 28px var(--action-shadow);
        }
        .action-card.reservar { --action-shadow: rgba(102, 187, 106, 0.5); }
        .action-card.torneos { --action-shadow: rgba(255, 213, 79, 0.5); }
        .action-card.stats { --action-shadow: rgba(66, 165, 245, 0.5); }
        
        .action-icon {
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
            display: grid; place-items: center;
            width: 52px; height: 52px;
            margin: 0 auto 0.5rem;
            border-radius: 16px;
        }
        .action-card.reservar .action-icon { background: linear-gradient(135deg, rgba(102,187,106,0.15), rgba(76,175,80,0.1)); }
        .action-card.torneos .action-icon { background: linear-gradient(135deg, rgba(255,213,79,0.15), rgba(255,193,7,0.1)); }
        .action-card.stats .action-icon { background: linear-gradient(135deg, rgba(66,165,245,0.15), rgba(33,150,243,0.1)); }
        
        .action-label { font-size: 0.85rem; font-weight: 600; }

        /* FAB */
        .fab {
            position: fixed;
            bottom: 28px; right: 28px;
            width: 60px; height: 60px;
            border-radius: 50%;
            background: var(--green-fluor);
            color: #1B5E20;
            font-size: 2rem;
            font-weight: 700;
            display: grid; place-items: center;
            text-decoration: none;
            box-shadow: 
                0 6px 20px rgba(118, 255, 3, 0.5),
                0 0 0 4px rgba(118, 255, 3, 0.15);
            border: 2px solid white;
            transition: all 0.25s cubic-bezier(0.175,0.885,0.32,1.275);
            z-index: 90;
        }
        .fab:hover {
            transform: scale(1.08) rotate(5deg);
            box-shadow: 
                0 10px 35px rgba(118, 255, 3, 0.7),
                0 0 0 6px rgba(118, 255, 3, 0.25);
            background: #64DD17;
        }

        /* MODALES */
        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.65);
            backdrop-filter: blur(6px);
            z-index: 2000;
            justify-content: center; align-items: center;
            padding: 1rem;
        }
        .modal-content {
            background: white; color: var(--text-dark);
            padding: 1.5rem; border-radius: 20px;
            max-width: 380px; width: 100%;
            max-height: 80vh; overflow-y: auto;
            box-shadow: 0 15px 40px rgba(0,0,0,0.25);
            position: relative;
        }
        .modal-close {
            position: absolute; top: 1rem; right: 1rem;
            width: 30px; height: 30px; border-radius: 50%;
            background: #F7FAFC; border: none;
            font-size: 1.2rem; cursor: pointer;
            display: grid; place-items: center;
            color: var(--text-light);
        }
        
        /* Lista de inscritos */
        .inscrito-item {
            padding: 0.9rem 0;
            border-bottom: 1px solid #EDF2F7;
            display: flex; justify-content: space-between; align-items: center;
            gap: 0.5rem;
        }
        .inscrito-item:last-child { border-bottom: none; }
        .inscrito-name { font-weight: 500; font-size: 0.95rem; }
        .inscrito-status {
            font-size: 0.75rem; padding: 0.2rem 0.5rem;
            border-radius: 8px; background: #E8F5E9; color: #2E7D32;
        }
        .btn-bajar {
            background: none; border: none; color: #C62828;
            font-size: 0.8rem; font-weight: 600; cursor: pointer;
            padding: 0.3rem 0.6rem; border-radius: 6px;
            transition: background 0.2s;
        }
        .btn-bajar:hover { background: #FFEBEE; }
        .btn-bajar:disabled { opacity: 0.5; cursor: not-allowed; }

        /* TOAST */
        .toast {
            position: fixed; bottom: 100px; left: 50%;
            transform: translateX(-50%) translateY(20px);
            background: #2D3748; color: white;
            padding: 0.85rem 1.5rem; border-radius: 14px;
            font-size: 0.9rem; font-weight: 500;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
            opacity: 0; visibility: hidden;
            transition: all 0.3s; z-index: 3000;
            max-width: 90%; text-align: center;
        }
        .toast.show {
            opacity: 1; visibility: visible;
            transform: translateX(-50%) translateY(0);
        }

        /* RESPONSIVE */
        @media (max-width: 480px) {
            .app-header { padding: 0.6rem 1rem; }
            .brand { font-size: 1.15rem; }
            .hero { padding: 1.5rem 1.25rem; border-radius: 24px; }
            .hero-title { font-size: 1rem; }
            .hero-meta { font-size: 0.9rem; gap: 0.75rem; }
            .quick-actions { gap: 0.75rem; }
            .action-card { padding: 1rem 0.5rem; }
            .action-icon { width: 46px; height: 46px; font-size: 1.5rem; }
            .fab { width: 54px; height: 54px; font-size: 1.8rem; bottom: 22px; right: 22px; }
        }
        /* Selector de clubs - submenú */
        #selectorClubes {
            position: absolute;
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
            z-index: 102;
            border: 1px solid #eee;
            animation: slideDown 0.2s ease;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Item de club en lista */
        #listaClubes > div {
            border-bottom: 1px solid #f5f5f5;
            font-size: 0.9rem;
            color: #333;
        }

        #listaClubes > div:last-child {
            border-bottom: none;
        }

        #listaClubes > div:hover {
            background: #F7FAFC;
        }

        /* Badge "Actual" */
        #listaClubes span[style*="Actual"] {
            font-size: 0.7rem;
            background: #E8F5E9;
            color: #2E7D32;
            padding: 2px 8px;
            border-radius: 10px;
            font-weight: 500;
        }
        /* Menú 3 puntos DENTRO de la ficha Próximo Partido */
        .hero {
            position: relative; /* Necesario para posicionar el menú absoluto */
        }

        .hero-menu-dots {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(255,255,255,0.25);
            border: none;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            display: grid;
            place-items: center;
            transition: background 0.2s;
            z-index: 10;
        }

        .hero-menu-dots:hover {
            background: rgba(255,255,255,0.4);
        }

        /* Dropdown genérico para menús */
        .menu-dropdown {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 12px;
            min-width: 180px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
            z-index: 101;
            overflow: hidden;
            margin-top: 4px;
            border: 1px solid #eee;
            animation: slideDown 0.2s ease;
        }

        .menu-dropdown.active {
            display: block;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.8rem 1rem;
            font-size: 0.9rem;
            color: var(--text-dark);
            cursor: pointer;
            transition: background 0.2s;
            border-bottom: 1px solid #f0f0f0;
        }

        .menu-item:last-child {
            border-bottom: none;
        }

        .menu-item:hover {
            background: #F7FAFC;
        }

        .menu-item.danger {
            color: #C62828;
            font-weight: 500;
        }

        .menu-item:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>
<!-- === VARIABLES GLOBALES PARA JS === -->
<script>
<?php foreach($js_vars as $key => $val): ?>
const <?= $key ?> = <?= is_bool($val) ? ($val ? 'true' : 'false') : (is_int($val) ? $val : json_encode($val)) ?>;
<?php endforeach; ?>
console.log('✅ dashboard_socio.php cargado | SOCIO_ID:', SOCIO_ID, 'ES_MULTICLUB:', ES_MULTICLUB, 'LIMITE_LLENO:', LIMITE_LLENO);
</script>
</head>
<body>
    <!-- HEADER SIMPLIFICADO -->
    <header class="app-header">
        <div class="logo">
            <div class="logo-icon">⚽</div>
            <span class="brand">CanchaSport</span>
        </div>
        <div class="header-actions">
            <button class="menu-dots" onclick="toggleHeaderMenu(event)">⋮</button>
            <div id="headerMenu" class="menu-dropdown">
                <a href="mi_perfil.php" class="menu-item">👤 Mi perfil</a>
                
                <!-- ✅ Usar $es_multiclub calculado en PHP -->
                <?php if ($es_multiclub): ?>
                <div class="menu-item" style="border-top:1px solid #eee; margin-top:0.3rem; padding-top:0.8rem;" onclick="abrirSelectorClubes(event)">
                    🔄 Cambiar de Club
                </div>
                <?php endif; ?>
            </div>
            <a href="mi_perfil.php" class="avatar">
                <?= strtoupper(substr($nombre_mostrar,0,1)) ?>
            </a>
        </div>
    </header>

    <!-- ✅ CONTENEDOR SELECTOR (fuera del header) -->
    <div id="selectorClubes" class="menu-dropdown" style="display:none; position:absolute; top:100%; right:0; min-width:220px; max-height:300px; overflow-y:auto; background:white; border-radius:12px; box-shadow:0 8px 25px rgba(0,0,0,0.2); z-index:102; border:1px solid #eee; margin-top:4px;">
        <div style="padding:0.6rem 0.8rem; border-bottom:1px solid #f0f0f0; font-weight:600; font-size:0.85rem; color:#666;">Selecciona un club:</div>
        <div id="listaClubes"><div style="padding:0.8rem; text-align:center; color:#888;">Cargando clubs...</div></div>
    </div>

    <div class="container">
        <!-- HERO CARD: Próximo Partido -->
        <div class="hero">
            <!-- MENÚ 3 PUNTOS DENTRO DE LA FICHA (esquina superior derecha) -->
            <button class="hero-menu-dots" onclick="toggleHeroMenu(event, <?= $proximo['id_reserva'] ?? 0 ?>)">⋮</button>
            
            <!-- Dropdown para acciones del partido -->
            <div id="heroMenu_<?= $proximo['id_reserva'] ?? 0 ?>" class="menu-dropdown" style="display:none; position:absolute; top:48px; right:12px; min-width:200px; z-index:50;">
                <div class="menu-item" onclick="marcarPaso(<?= $proximo['id_reserva'] ?? 0 ?>)">👟 "Paso"</div>
                <div class="menu-item" onclick="pagarCuota(<?= $deuda_mas_vigente['id_cuota'] ?>)">💳 Pagar cuota</div>
                <div class="menu-item" id="menuItemIA_<?= $proximo['id_reserva'] ?? 0 ?>" onclick="generarEquiposIA(<?= $proximo['id_reserva'] ?? 0 ?>)" style="display:none; color:#6A1B9A; font-weight:500;">
                    🤖 Armar equipos IA
                </div>
            </div>

            <h1 class="hero-title">Próximo Partido</h1>
            
            <?php if($proximo): ?>
                <div class="hero-meta">
                    <span>📅 <?= date('d M', strtotime($proximo['fecha'])) ?></span>
                    <span>⏰ <?= substr($proximo['hora_inicio'],0,5) ?></span>
                    <span>🏟️ <?= htmlspecialchars($proximo['nombre_cancha']) ?></span>
                </div>
                
                <?php if($ya_inscrito): ?>
                    <button class="btn-hero inscrito" onclick="bajarse(<?= $proximo['id_reserva'] ?>)">❌ Bajarme del partido</button>
                <?php else: ?>
                    <button class="btn-hero" onclick="anotarse(<?= $proximo['id_reserva'] ?>)" <?= $limite_lleno ? 'disabled title="Cupos completos"' : '' ?>>
                        <?= $limite_lleno ? '🔒 Cupos completos' : '✅ Anotarme' ?>
                    </button>
                <?php endif; ?>

                <div class="progress-section">
                    <span class="progress-label">Cupos</span>
                    <div class="progress-track">
                        <div class="progress-fill" style="width: <?= $progress_percent ?>%;"></div>
                    </div>
                    <button class="progress-eye" onclick="verInscritos(<?= $proximo['id_reserva'] ?>)" title="Ver inscritos">👁️</button>
                </div>
            <?php else: ?>
                <div style="text-align:center; padding:1rem;">
                    <p style="font-size:1rem; opacity:0.9; margin-bottom:1rem;">🎉 ¡No tienes partidos próximos!</p>
                    <a href="reservar_cancha.php" style="display:inline-block; background:white; color:var(--tennis-green); padding:0.8rem 1.5rem; border-radius:12px; text-decoration:none; font-weight:600;">Reservar ahora</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- QUICK ACTIONS -->
        <div class="quick-actions">
            <a href="reservar_cancha.php" class="action-card reservar">
                <div class="action-icon">🎾</div>
                <span class="action-label">Reservar</span>
            </a>
            <a href="torneos_publicos.php" class="action-card torneos">
                <div class="action-icon">🏆</div>
                <span class="action-label">Torneos</span>
            </a>
            <a href="mis_estadisticas.php" class="action-card stats">
                <div class="action-icon">📈</div>
                <span class="action-label">Mis Stats</span>
            </a>
        </div>
    </div>

    <!-- FAB -->
    <a href="reservar_cancha.php" class="fab">+</a>

    <!-- MODAL INSCRITOS -->
    <div id="modalInscritos" class="modal-overlay" onclick="cerrarModal(event)">
        <div class="modal-content">
            <button class="modal-close" onclick="cerrarModal(event)">&times;</button>
            <h3 style="text-align:center; margin-bottom:1rem; color:var(--padel-blue); font-weight:600;">👥 Inscritos</h3>
            <div id="listaInscritos">
                <p style="text-align:center; color:var(--text-light); padding:1rem;">Cargando inscritos...</p>
            </div>
            <?php if($es_responsable): ?>
                <p style="font-size:0.75rem; color:#888; text-align:center; margin-top:1rem;">
                    ℹ️ Como responsable, puedes bajar a otros jugadores
                </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- TOAST -->
    <div id="toast" class="toast">✅ Acción realizada</div>
<script>
// ============================================================================
// === 1. VARIABLES GLOBALES (PRIMERO - ANTES DE CUALQUIER FUNCIÓN) ===
// ============================================================================
const SOCIO_ID = <?= (int)($id_socio ?? 0) ?>;
const ES_MULTICLUB = <?= $es_multiclub ? 'true' : 'false' ?>;
const CLUB_ACTUAL = "<?= $club_actual_slug ?? '' ?>";
const LIMITE_LLENO = <?= $limite_lleno ?? false ? 'true' : 'false' ?>;
const PROXIMO_ID = <?= $proximo['id_reserva'] ?? 0 ?>;

// ============================================================================
// === 2. FUNCIONES UTILITARIAS (Globales para onclick) ===
// ============================================================================

function showToast(msg, type = 'success') {
    const t = document.getElementById('toast');
    if (!t) return;
    t.textContent = msg;
    const colors = { success: '#2E7D32', error: '#C62828', warning: '#EF6C00', info: '#1976D2' };
    t.style.background = colors[type] || colors.success;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}

function closeAllMenus() {
    document.querySelectorAll('.menu-dropdown').forEach(menu => {
        menu.classList.remove('active');
        if (menu.id === 'selectorClubes') menu.style.display = 'none';
    });
}

// ============================================================================
// === 3. MENÚ HEADER (Perfil + Cambiar Club) ===
// ============================================================================

function toggleHeaderMenu(e) {
    e.stopPropagation();
    closeAllMenus();
    const menu = document.getElementById('headerMenu');
    if (menu) menu.classList.toggle('active');
}

async function abrirSelectorClubes(e) {
    e.stopPropagation();
    console.log('🔍 abrirSelectorClubes | SOCIO_ID:', SOCIO_ID);
    
    const selector = document.getElementById('selectorClubes');
    const lista = document.getElementById('listaClubes');
    
    if (!selector || !lista) {
        console.error('❌ Faltan elementos #selectorClubes o #listaClubes');
        showToast('❌ Error de interfaz', 'error');
        return;
    }
    
    selector.style.display = 'block';
    selector.classList.add('active');
    lista.innerHTML = '<div style="padding:0.8rem; text-align:center; color:#888;">🔄 Cargando clubs...</div>';
    
    try {
        const res = await fetch(`../api/get_clubs_socio.php?id_socio=${SOCIO_ID}`);
        const clubs = await res.json();
        
        if (!Array.isArray(clubs) || clubs.length === 0) {
            lista.innerHTML = '<div style="padding:0.8rem; text-align:center; color:#888;">Sin clubs disponibles</div>';
            return;
        }
        
        let html = '';
        clubs.forEach(club => {
            const esActual = club.slug === CLUB_ACTUAL;
            html += `<div onclick="cambiarClub('${club.slug}')" 
                 style="padding:0.8rem 1rem; cursor:pointer; display:flex; justify-content:space-between; align-items:center; transition:background 0.2s; ${esActual ? 'background:#E8F5E9; font-weight:600;' : ''}"
                 onmouseover="this.style.background='${esActual ? '#C8E6C9' : '#F7FAFC'}'"
                 onmouseout="this.style.background='${esActual ? '#E8F5E9' : 'white'}'">
                <span>${club.nombre}</span>
                ${esActual ? '<span style="font-size:0.75rem; color:#2E7D32; background:#C8E6C9; padding:2px 8px; border-radius:10px;">Actual</span>' : ''}
            </div>`;
        });
        lista.innerHTML = html;
    } catch (err) {
        console.error('❌ Error cargando clubs:', err);
        lista.innerHTML = '<div style="padding:0.8rem; text-align:center; color:#C62828;">Error al cargar</div>';
        showToast('❌ Error al cargar clubs', 'error');
    }
}

function cambiarClub(clubSlug) {
    console.log('🔄 cambiarClub | slug:', clubSlug, 'SOCIO_ID:', SOCIO_ID);
    showToast('🔄 Cambiando de club...', 'info');
    document.body.style.cursor = 'wait';
    
    fetch('../api/cambiar_club_sesion.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ club_slug: clubSlug })
    })
    .then(async r => {
        if (!r.ok) throw new Error('Error HTTP: ' + r.status);
        return r.json();
    })
    .then(data => {
        document.body.style.cursor = 'default';
        if (data.success) {
            showToast('✅ Club cambiado', 'success');
            window.location.href = `dashboard_socio.php?id_club=${clubSlug}&t=${Date.now()}`;
        } else {
            showToast('❌ ' + (data.message || 'Error'), 'error');
        }
    })
    .catch(err => {
        document.body.style.cursor = 'default';
        console.error('❌ Error:', err);
        showToast('❌ ' + err.message, 'error');
    });
}

// ============================================================================
// === 4. MENÚ FICHA PRÓXIMO PARTIDO ===
// ============================================================================

function toggleHeroMenu(e, idReserva) {
    e.stopPropagation();
    closeAllMenus();
    
    // ✅ AHORA SÍ podemos usar LIMITE_LLENO porque ya fue declarado arriba
    const menu = document.getElementById(`heroMenu_${idReserva}`);
    if (menu) {
        menu.classList.toggle('active');
        const itemIA = document.getElementById(`menuItemIA_${idReserva}`);
        if (itemIA && LIMITE_LLENO) {  // ✅ Sin error de TDZ
            itemIA.style.display = 'flex';
        }
    }
}

async function marcarPaso(idReserva) {
    showToast('👟 Marcado como "Paso"');
    // TODO: fetch a API
}

function generarEquiposIA(idReserva) {
    if (!LIMITE_LLENO) {  // ✅ Seguro
        showToast('⚠️ Solo con cupos completos', 'error');
        return;
    }
    showToast('🤖 Generando equipos...');
    setTimeout(() => showToast('✅ Equipos listos'), 1500);
}

// ============================================================================
// === 5. INSCRIPCIÓN / BAJA DE RESERVA ===
// ============================================================================

async function anotarse(idReserva) {
    if (!confirm('¿Confirmas tu inscripción?')) return;
    try {
        const res = await fetch('../api/inscribir_reserva.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id_reserva: idReserva, id_socio: SOCIO_ID })
        });
        const data = await res.json();
        if (data.success) {
            showToast('✅ ¡Anotado!');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('❌ ' + (data.message || 'Error'), 'error');
        }
    } catch (e) {
        showToast('❌ Error de conexión', 'error');
    }
}

async function bajarse(idReserva) {
    if (!confirm('¿Seguro que deseas bajarte?')) return;
    try {
        const res = await fetch('../api/bajar_reserva.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id_reserva: idReserva, id_socio: SOCIO_ID })
        });
        const data = await res.json();
        if (data.success) {
            showToast('❌ Te has dado de baja');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('❌ ' + (data.message || 'Error'), 'error');
        }
    } catch (e) {
        showToast('❌ Error de conexión', 'error');
    }
}

// ============================================================================
// === 6. MODAL INSCRITOS ===
// ============================================================================

async function verInscritos(idReserva) {
    const modal = document.getElementById('modalInscritos');
    const lista = document.getElementById('listaInscritos');
    if (!modal || !lista) return;
    
    modal.style.display = 'flex';
    lista.innerHTML = '<p style="text-align:center; color:var(--text-light); padding:1rem;">🔄 Cargando...</p>';
    
    try {
        const res = await fetch(`../api/get_inscritos_reserva.php?id_reserva=${idReserva}`);
        const data = await res.json();
        
        if (!Array.isArray(data) || data.length === 0) {
            lista.innerHTML = '<p style="text-align:center; color:var(--text-light);">Sin inscritos aún</p>';
            return;
        }
        
        let html = '';
        data.forEach(p => {
            const esYo = p.id_socio === SOCIO_ID;
            const puedeBajar = window.ES_RESPONSABLE && !esYo;
            html += `<div class="inscrito-item">
                <span class="inscrito-name">${esYo ? '👤 Tú' : p.nombre}</span>
                <span class="inscrito-status">${p.estado}</span>
                ${puedeBajar ? `<button class="btn-bajar" onclick="bajarJugador(${p.id_socio}, ${idReserva}, '${p.nombre.replace(/'/g, "\\'")}')">Bajar</button>` : ''}
            </div>`;
        });
        lista.innerHTML = html;
    } catch (e) {
        console.error(e);
        lista.innerHTML = '<p style="text-align:center; color:#C62828;">Error al cargar</p>';
    }
}

async function bajarJugador(idSocioBajar, idReserva, nombre) {
    if (!confirm(`¿Bajar a "${nombre}"?`)) return;
    try {
        const res = await fetch('../api/bajar_jugador_reserva.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id_reserva: idReserva, id_socio_a_bajar: idSocioBajar, id_responsable: SOCIO_ID })
        });
        const data = await res.json();
        if (data.success) {
            showToast(`✅ ${nombre} bajado`);
            verInscritos(idReserva);
        } else {
            showToast('❌ ' + (data.message || 'Error'), 'error');
        }
    } catch (e) {
        showToast('❌ Error de conexión', 'error');
    }
}

function cerrarModal(e) {
    if (e && (e.target.id === 'modalInscritos' || e.target.classList?.contains('modal-close'))) {
        document.getElementById('modalInscritos')?.style.setProperty('display', 'none');
    }
}

// ============================================================================
// === 7. EVENT LISTENERS GLOBALES ===
// ============================================================================

document.addEventListener('click', (e) => {
    if (!e.target.closest('.menu-dots') && !e.target.closest('.hero-menu-dots') && !e.target.closest('.menu-dropdown')) {
        closeAllMenus();
    }
});

// Debug al cargar
console.log('✅ dashboard_socio.php cargado | SOCIO_ID:', SOCIO_ID, 'LIMITE_LLENO:', LIMITE_LLENO);

// === PAGAR CUOTA ===
            function pagarCuota(idCuota) {
                window.location.href = 'pagar_cuota.php?id_cuota=' + idCuota;
            }

            // === REVISAR/VALIDAR PAGO ===
            function revisarPago(idCuota) {
                fetch('../api/revisar_pago.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({id_cuota: idCuota})
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) { mostrarToast('✅ Cuota en revisión', 'exito'); setTimeout(() => cargarTabla('cuotas'), 1000); }
                    else { mostrarToast('❌ ' + data.message, 'error'); }
                });
            }

            function validarPago(idCuota) {
                fetch('../api/validar_pago.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({id_cuota: idCuota})
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) { mostrarToast('✅ Pago validado', 'exito'); setTimeout(() => cargarTabla('cuotas'), 1000); }
                    else { mostrarToast('❌ ' + data.message, 'error'); }
                });
            }
</script>
</body>
</html>