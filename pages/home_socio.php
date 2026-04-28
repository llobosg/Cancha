<?php
// pages/home_socio.php
require_once __DIR__ . '/../includes/config.php';

// Validar sesión socio
if (!isset($_SESSION['id_socio'])) {
    header('Location: ../index.php');
    exit;
}

$id_socio = $_SESSION['id_socio'];

// Obtener datos básicos del socio
$stmt = $pdo->prepare("SELECT alias, nombre, foto_url FROM socios WHERE id_socio = ?");
$stmt->execute([$id_socio]);
$socio = $stmt->fetch();
$nombre_mostrar = $socio['alias'] ?: explode(' ', $socio['nombre'])[0];

// === LÓGICA PRÓXIMO PARTIDO ===
$club_id_actual = $_SESSION['club_id'] ?? null;
$where_parts = ["r.estado = 'confirmada'", "r.fecha >= CURDATE()"];
$params = [];

if ($club_id_actual !== null) {
    $where_parts[] = "( (r.id_club = ? AND r.id_socio = ?) OR (r.id_club = ?) )";
    $params[] = $club_id_actual;
    $params[] = $id_socio;
    $params[] = $club_id_actual;
} else {
    $where_parts[] = "r.id_club IS NULL AND r.id_socio = ?";
    $params[] = $id_socio;
}

$sql_next = "
    SELECT 
        r.id_reserva, r.fecha, r.hora_inicio, r.monto_total, 
        r.jugadores_esperados, r.monto_recaudacion,
        c.nombre_cancha, c.id_deporte,
        (SELECT COUNT(*) FROM inscritos i WHERE i.id_evento = r.id_reserva AND i.tipo_actividad = 'reserva') as inscritos_actuales
    FROM reservas r
    JOIN canchas c ON r.id_cancha = c.id_cancha
    WHERE " . implode(" AND ", $where_parts) . "
    ORDER BY r.fecha ASC, r.hora_inicio ASC
    LIMIT 1
";

$stmt_next = $pdo->prepare($sql_next);
$stmt_next->execute($params);
$proximo = $stmt_next->fetch();

// Verificar inscripción y contar
$ya_inscrito = false;
$cant_inscritos = 0;
if ($proximo) {
    $stmt_check = $pdo->prepare("SELECT 1 FROM inscritos WHERE id_evento = ? AND id_socio = ?");
    $stmt_check->execute([$proximo['id_reserva'], $id_socio]);
    $ya_inscrito = $stmt_check->fetch();
    $cant_inscritos = $proximo['inscritos_actuales'] ?? 0;
}

$nombres_deportes = ['futbol' => 'Fútbol', 'futbolito' => 'Futbolito', 'futsal' => 'Futsal', 'tenis' => 'Tenis', 'padel' => 'Pádel'];
$nombre_deporte = $nombres_deportes[$proximo['id_deporte'] ?? ''] ?? ucfirst($proximo['id_deporte'] ?? 'Deporte');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Home - CanchaSport</title>
    <style>
        :root { --primary: #BA68C8; --accent: #4CAF50; --bg-dark: rgba(0, 20, 10, 0.85); }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        
        body { 
            background: linear-gradient(var(--bg-dark), rgba(0, 30, 15, 0.9)), url('../assets/img/cancha_pasto2.jpg') center/cover no-repeat fixed;
            color: white; min-height: 100vh; padding-bottom: 80px; 
        }

        /* HEADER */
        .app-header {
            background: rgba(255,255,255,0.1); backdrop-filter: blur(10px);
            padding: 1rem 1.5rem; display: flex; justify-content: space-between; align-items: center;
            position: sticky; top: 0; z-index: 100; border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .logo-container { display: flex; align-items: center; gap: 0.5rem; }
        .brand-name { font-weight: 900; font-size: 1.4rem; letter-spacing: -0.5px; }
        .user-avatar {
            width: 40px; height: 40px; background: var(--primary); color: white;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-weight: bold; text-decoration: none;
        }

        .container { max-width: 600px; margin: 0 auto; padding: 1.5rem; }

        /* FICHA PRÓXIMO PARTIDO */
        .hero-card {
            background: linear-gradient(135deg, var(--primary) 0%, #8E24AA 100%);
            border-radius: 24px; padding: 2rem 1.5rem; margin-bottom: 2rem;
            box-shadow: 0 15px 35px rgba(0,0,0,0.3); text-align: center; position: relative;
        }
        .hero-card.empty { background: linear-gradient(135deg, #4CAF50 0%, #2E7D32 100%); }

        .hero-date { font-size: 1.2rem; font-weight: 600; margin-bottom: 0.5rem; display: block; opacity: 0.9; }
        .hero-sport { font-size: 1rem; opacity: 0.8; margin-bottom: 1.5rem; text-transform: uppercase; letter-spacing: 1px; }
        
        .btn-main {
            background: white; color: var(--primary); border: none; padding: 1rem 2rem;
            border-radius: 50px; font-weight: bold; font-size: 1.1rem; cursor: pointer;
            width: 100%; transition: transform 0.2s; box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .btn-main:active { transform: scale(0.98); }
        
        /* MENÚ 3 PUNTOS */
        .menu-dots {
            position: absolute; top: 1.5rem; right: 1.5rem;
            background: rgba(255,255,255,0.2); border: none; color: white;
            width: 32px; height: 32px; border-radius: 50%; cursor: pointer;
            font-size: 1.2rem; display: flex; align-items: center; justify-content: center;
        }
        .dropdown-menu {
            display: none; position: absolute; top: 100%; right: 0;
            background: white; border-radius: 12px; min-width: 180px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2); z-index: 200; overflow: hidden;
        }
        .dropdown-item {
            display: block; padding: 0.8rem 1rem; color: #333; text-decoration: none;
            font-size: 0.95rem; border-bottom: 1px solid #eee; cursor: pointer;
        }
        .dropdown-item:hover { background: #f5f5f5; }

        /* ACCIONES RÁPIDAS */
        .quick-actions { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 2rem; }
        .action-pill {
            background: rgba(255,255,255,0.9); border-radius: 20px; padding: 1.2rem 0.5rem;
            text-align: center; text-decoration: none; color: #333;
            display: flex; flex-direction: column; align-items: center; gap: 0.5rem;
        }
        .icon-box {
            width: 50px; height: 50px; background: #F3E5F5; color: var(--primary);
            border-radius: 15px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;
        }

        /* TOAST NOTIFICATIONS */
        #toast-container { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
        .toast {
            padding: 12px 24px; border-radius: 50px; color: white; font-weight: bold;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3); animation: slideUp 0.3s ease-out;
            display: flex; align-items: center; gap: 8px;
        }
        .toast.success { background: #4CAF50; }
        .toast.error { background: #F44336; }
        @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        /* MODAL INSCRITOS */
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.8); z-index: 2000; justify-content: center; align-items: center;
        }
        .modal-content {
            background: white; color: #333; padding: 2rem; border-radius: 16px;
            max-width: 400px; width: 90%; max-height: 80vh; overflow-y: auto;
        }
        .inscrito-item { padding: 0.8rem; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; }
    </style>
</head>
<body>

    <header class="app-header">
        <div class="logo-container">
            <!-- LOGO DEPORTIVO "C" -->
            <svg width="40" height="40" viewBox="0 0 100 100" fill="none">
                <path d="M50 10 C20 10 10 30 10 50 C10 70 20 90 50 90 C70 90 85 80 90 70" stroke="white" stroke-width="8" stroke-linecap="round"/>
                <circle cx="50" cy="50" r="15" fill="white" opacity="0.8"/>
                <path d="M50 10 L50 30 M50 70 L50 90" stroke="white" stroke-width="4"/>
            </svg>
            <span class="brand-name">CanchaSport</span>
        </div>
        <a href="mantenedor_socios.php" class="user-avatar"><?= strtoupper(substr($nombre_mostrar, 0, 1)) ?></a>
    </header>

    <div class="container">
        <?php if ($proximo): ?>
            <div class="hero-card">
                <!-- Menú 3 Puntos -->
                <div style="position:relative;">
                    <button class="menu-dots" onclick="toggleMenu()">⋮</button>
                    <div id="reservaMenu" class="dropdown-menu">
                        <div class="dropdown-item" onclick="compartirReserva(<?= $proximo['id_reserva'] ?>)">📲 Compartir Reserva</div>
                        <div class="dropdown-item" onclick="verInscritosHero(<?= $proximo['id_reserva'] ?>)">👥 Ver Inscritos</div>
                    </div>
                </div>

                <h2 style="font-size: 1.8rem; margin-bottom: 0.5rem;">Próximo Partido</h2>
                <span class="hero-date">📅 <?= date('d M', strtotime($proximo['fecha'])) ?> • ⏰ <?= substr($proximo['hora_inicio'], 0, 5) ?></span>
                <div class="hero-sport"><?= htmlspecialchars($nombre_deporte) ?> • <?= htmlspecialchars($proximo['nombre_cancha']) ?></div>
                
                <?php if ($ya_inscrito): ?>
                    <button class="btn-main" onclick="bajarseEvento(<?= $proximo['id_reserva'] ?>)">❌ Bajarme</button>
                <?php else: ?>
                    <button class="btn-main" onclick="anotarmeAlEvento(<?= $proximo['id_reserva'] ?>)">✅ Anotarme</button>
                <?php endif; ?>

                <div style="margin-top: 1rem; font-size: 0.9rem; opacity: 0.9;">
                    👥 <?= $cant_inscritos ?> inscritos
                </div>
            </div>
        <?php else: ?>
            <div class="hero-card empty">
                <h2>¡Hola, <?= htmlspecialchars($nombre_mostrar) ?>!</h2>
                <p style="margin-bottom: 1.5rem; opacity: 0.9;">No tienes partidos próximos.</p>
                <a href="reservar_cancha.php" style="display:block; background:white; color:#2E7D32; padding:1rem; border-radius:50px; text-decoration:none; font-weight:bold;">🎾 Reservar Ahora</a>
            </div>
        <?php endif; ?>

        <div class="quick-actions">
            <a href="reservar_cancha.php" class="action-pill">
                <div class="icon-box" style="background:#E8F5E9; color:#2E7D32;">🎾</div>
                <span style="font-weight: 600;">Reservar</span>
            </a>
            <a href="#" class="action-pill">
                <div class="icon-box" style="background:#FFF3E0; color:#EF6C00;">🏆</div>
                <span style="font-weight: 600;">Torneos</span>
            </a>
            <a href="#" class="action-pill">
                <div class="icon-box" style="background:#E3F2FD; color:#1565C0;">📊</div>
                <span style="font-weight: 600;">Ranking</span>
            </a>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toast-container"></div>

    <!-- Modal Inscritos -->
    <div id="modalInscritos" class="modal-overlay" onclick="cerrarModalInscritos(event)">
        <div class="modal-content">
            <h3 style="text-align:center; color:#BA68C8; margin-bottom:1rem;">👥 Inscritos</h3>
            <div id="listaInscritos"><p style="text-align:center;">Cargando...</p></div>
            <button onclick="document.getElementById('modalInscritos').style.display='none'" style="width:100%; margin-top:1rem; padding:0.5rem; background:#eee; border:none; border-radius:8px;">Cerrar</button>
        </div>
    </div>

    <script>
        // === TOAST SYSTEM ===
        function showToast(msg, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = type === 'success' ? '✅ ' + msg : '❌ ' + msg;
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }

        // === MENÚ 3 PUNTOS ===
        function toggleMenu() {
            const menu = document.getElementById('reservaMenu');
            menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
        }
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.menu-dots')) document.getElementById('reservaMenu').style.display = 'none';
        });

        // === ANOTARSE / BAJARSE ===
        function anotarmeAlEvento(idReserva) {
            if(!confirm('¿Quieres anotarte? Se generará tu cuota.')) return;
            fetch('../api/gestion_eventos.php', { 
                method: 'POST', 
                body: new URLSearchParams({ action: 'anotarse', id_actividad: idReserva, tipo_actividad: 'reserva' }) 
            })
            .then(r => r.json())
            .then(d => {
                if(d.success) { showToast('¡Anotado correctamente!', 'success'); location.reload(); }
                else { showToast(d.message, 'error'); }
            });
        }

        function bajarseEvento(idReserva) {
            if(!confirm('¿Seguro que deseas bajarte?')) return;
            fetch('../api/gestion_eventos.php', { 
                method: 'POST', 
                body: new URLSearchParams({ action: 'bajarse', id_reserva: idReserva }) 
            })
            .then(r => r.json())
            .then(d => {
                if(d.success) { showToast('Te has dado de baja.', 'success'); location.reload(); }
                else { showToast(d.message, 'error'); }
            });
        }

        // === VER INSCRITOS ===
        function verInscritosHero(idReserva) {
            const modal = document.getElementById('modalInscritos');
            const lista = document.getElementById('listaInscritos');
            modal.style.display = 'flex';
            lista.innerHTML = '<p style="text-align:center;">Cargando...</p>';
            
            fetch(`../api/get_inscritos_reserva.php?id_reserva=${idReserva}`)
                .then(r => r.json())
                .then(data => {
                    if(!data.length) { lista.innerHTML = '<p style="text-align:center;">Aún no hay nadie.</p>'; return; }
                    let html = '';
                    data.forEach(p => {
                        html += `<div class="inscrito-item"><span>${p.nombre}</span> ${p.es_yo ? '<strong>(Tú)</strong>' : ''}</div>`;
                    });
                    lista.innerHTML = html;
                });
        }
        function cerrarModalInscritos(e) { if(e.target.id === 'modalInscritos') e.target.style.display = 'none'; }

        // === COMPARTIR ===
        function compartirReserva(id) {
            navigator.clipboard.writeText(window.location.origin + '/pages/detalle_reserva.php?id=' + id)
            .then(() => showToast('Enlace copiado al portapapeles', 'success'));
        }
    </script>
</body>
</html>