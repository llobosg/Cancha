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

// === LÓGICA PRÓXIMO PARTIDO (Igual que dashboard_socio pero simplificada) ===
$club_id_actual = $_SESSION['club_id'] ?? null;
$where_parts = ["r.estado = 'confirmada'", "r.fecha >= CURDATE()"];
$params = [];

if ($club_id_actual !== null) {
    // Modo Club
    $where_parts[] = "( (r.id_club = ? AND r.id_socio = ?) OR (r.id_club = ?) )";
    $params[] = $club_id_actual;
    $params[] = $id_socio;
    $params[] = $club_id_actual;
} else {
    // Modo Individual
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
        :root { 
            --primary-start: #CE93D8; 
            --primary-end: #AB47BC; 
            --accent: #4CAF50; 
            --text-dark: #333;
            --bg-light: #F4F6F9;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        
        body { 
            background-color: var(--bg-light); 
            color: var(--text-dark); 
            min-height: 100vh; 
            padding-bottom: 80px; 
        }

        /* HEADER DEGRADÉ LILA */
        .app-header {
            background: linear-gradient(90deg, var(--primary-start) 0%, var(--primary-end) 100%);
            padding: 0.8rem 1.5rem; /* Más fino en móvil por defecto */
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky; top: 0; z-index: 100;
            box-shadow: 0 2px 10px rgba(171, 71, 188, 0.2);
        }
        .logo-container { display: flex; align-items: center; gap: 0.5rem; }
        .brand-name { font-weight: 700; font-size: 1.3rem; color: white; letter-spacing: -0.5px; text-shadow: 0 1px 2px rgba(0,0,0,0.1); }
        .user-avatar {
            width: 36px; height: 36px;
            background: rgba(255,255,255,0.2);
            color: white;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: bold; text-decoration: none;
            border: 1px solid rgba(255,255,255,0.4);
        }

        .container { max-width: 600px; margin: 0 auto; padding: 1.5rem; }

        /* FICHA PRÓXIMO PARTIDO */
        .hero-card {
            background: linear-gradient(135deg, var(--primary-start) 0%, var(--primary-end) 100%);
            color: white;
            border-radius: 24px;
            padding: 1.8rem 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 25px rgba(171, 71, 188, 0.3);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .hero-card h2 { 
            font-size: 1.4rem; 
            font-weight: 300; /* Texto fino */
            margin-bottom: 0.5rem; 
            opacity: 0.95; 
        }
        .hero-date { 
            font-size: 1rem; 
            font-weight: 400; 
            margin-bottom: 0.5rem; 
            display: block; 
            opacity: 0.9; 
        }
        .hero-sport { 
            font-size: 0.9rem; 
            opacity: 0.8; 
            margin-bottom: 1.5rem; 
            text-transform: uppercase; 
            letter-spacing: 1px; 
            font-weight: 500;
        }
        
        .btn-main {
            background: white;
            color: var(--primary-end);
            border: none;
            padding: 0.9rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            width: 100%;
            transition: transform 0.2s;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .btn-main:active { transform: scale(0.98); }
        
        /* MENÚ 3 PUNTOS & OJO */
        .top-controls {
            position: absolute; top: 1.2rem; right: 1.2rem;
            display: flex; gap: 0.8rem;
        }
        .icon-btn {
            background: rgba(255,255,255,0.2); 
            border: none; 
            color: white;
            width: 32px; height: 32px; 
            border-radius: 50%; 
            cursor: pointer;
            font-size: 1.1rem; 
            display: flex; align-items: center; justify-content: center;
            transition: background 0.2s;
        }
        .icon-btn:hover { background: rgba(255,255,255,0.3); }

        /* ACCIONES MINIMALISTAS (Sin bloque) */
        .quick-actions {
            display: flex;
            justify-content: center;
            gap: 2.5rem;
            margin-bottom: 2.5rem;
        }
        .action-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.4rem;
            text-decoration: none;
            color: #555;
            transition: transform 0.2s;
        }
        .action-item:hover { transform: translateY(-3px); color: var(--primary-end); }
        .action-icon {
            font-size: 1.8rem;
            color: var(--primary-end);
            background: #F3E5F5;
            width: 50px; height: 50px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
        }
        .action-label { font-size: 0.85rem; font-weight: 500; }

        /* FAB (+) SUTIL */
        .fab-reserve {
            position: fixed; bottom: 25px; right: 25px;
            background: white;
            color: var(--primary-end);
            width: 56px; height: 56px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            text-decoration: none;
            z-index: 90;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border: 1px solid #eee;
        }
        .fab-reserve:hover { 
            transform: scale(1.1) rotate(90deg); 
            box-shadow: 0 6px 20px rgba(171, 71, 188, 0.3);
            background: var(--primary-end);
            color: white;
        }

        /* MODAL INSCRITOS */
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 2000;
            justify-content: center; align-items: center;
        }
        .modal-content {
            background: white; color: #333; padding: 1.5rem; border-radius: 20px;
            max-width: 350px; width: 90%; max-height: 80vh; overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .inscrito-item { padding: 0.8rem 0; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .inscrito-item:last-child { border-bottom: none; }

        /* RESPONSIVE MÓVIL */
        @media (max-width: 480px) {
            .app-header { padding: 0.6rem 1rem; } /* Header más fino */
            .brand-name { font-size: 1.1rem; }
            .hero-card { padding: 1.5rem 1rem; margin-bottom: 1.5rem; }
            .hero-card h2 { font-size: 1.2rem; } /* Título más pequeño */
            .hero-date { font-size: 0.9rem; }
            .quick-actions { gap: 1.5rem; }
            .action-icon { width: 45px; height: 45px; font-size: 1.5rem; }
            .fab-reserve { width: 50px; height: 50px; font-size: 1.8rem; bottom: 20px; right: 20px; }
        }
    </style>
</head>
<body>

    <header class="app-header">
        <div class="logo-container">
            <!-- Logo SVG Simple -->
            <svg width="32" height="32" viewBox="0 0 100 100" fill="none">
                <circle cx="50" cy="50" r="45" stroke="white" stroke-width="8"/>
                <path d="M30 50 C30 30, 70 30, 70 50 C70 70, 30 70, 30 50" stroke="white" stroke-width="6" fill="none"/>
            </svg>
            <span class="brand-name">CanchaSport</span>
        </div>
        <a href="mantenedor_socios.php" class="user-avatar">
            <?= strtoupper(substr($nombre_mostrar, 0, 1)) ?>
        </a>
    </header>

    <div class="container">
        
        <?php if ($proximo): ?>
            <div class="hero-card">
                <!-- Controles Superiores: Menú y Ojo -->
                <div class="top-controls">
                    <button class="icon-btn" onclick="verInscritosHero(<?= $proximo['id_reserva'] ?>)" title="Ver Inscritos">👁️</button>
                    <div style="position:relative;">
                        <button class="icon-btn" onclick="toggleMenu()">⋮</button>
                        <div id="reservaMenu" style="display:none; position:absolute; top:100%; right:0; background:white; border-radius:12px; min-width:160px; box-shadow:0 5px 15px rgba(0,0,0,0.2); z-index:200; overflow:hidden;">
                            <div class="dropdown-item" onclick="compartirReserva(<?= $proximo['id_reserva'] ?>)" style="padding:0.8rem; color:#333; cursor:pointer; border-bottom:1px solid #eee;">📲 Compartir Reserva</div>
                        </div>
                    </div>
                </div>

                <h2>Próximo Partido</h2>
                <span class="hero-date">📅 <?= date('d M', strtotime($proximo['fecha'])) ?> • ⏰ <?= substr($proximo['hora_inicio'], 0, 5) ?></span>
                <div class="hero-sport"><?= htmlspecialchars($nombre_deporte) ?> • <?= htmlspecialchars($proximo['nombre_cancha']) ?></div>
                
                <?php if ($ya_inscrito): ?>
                    <button class="btn-main" onclick="bajarseEvento(<?= $proximo['id_reserva'] ?>)">❌ Bajarme</button>
                <?php else: ?>
                    <button class="btn-main" onclick="anotarmeAlEvento(<?= $proximo['id_reserva'] ?>)">✅ Anotarme</button>
                <?php endif; ?>

                <div style="margin-top: 1rem; font-size: 0.85rem; opacity: 0.9; display:flex; justify-content:center; align-items:center; gap:0.5rem;">
                    <span>👥 <?= $cant_inscritos ?> inscritos</span>
                </div>
            </div>
        <?php else: ?>
            <div class="hero-card" style="background: linear-gradient(135deg, #4CAF50 0%, #2E7D32 100%);">
                <h2>¡Hola, <?= htmlspecialchars($nombre_mostrar) ?>!</h2>
                <p style="margin-bottom: 1.5rem; opacity: 0.9;">No tienes partidos próximos.</p>
                <a href="reservar_cancha.php" style="display:block; background:white; color:#2E7D32; padding:0.9rem; border-radius:50px; text-decoration:none; font-weight:bold;">🎾 Reservar Ahora</a>
            </div>
        <?php endif; ?>

        <!-- Acciones Minimalistas -->
        <div class="quick-actions">
            <a href="reservar_cancha.php" class="action-item">
                <div class="action-icon">🎾</div>
                <span class="action-label">Reservar</span>
            </a>
            <a href="#" class="action-item">
                <div class="action-icon">🏆</div>
                <span class="action-label">Torneos</span>
            </a>
            <a href="#" class="action-item">
                <div class="action-icon">📊</div>
                <span class="action-label">Ranking</span>
            </a>
        </div>

    </div>

    <!-- Botón Flotante (+) -->
    <a href="reservar_cancha.php" class="fab-reserve">+</a>

    <!-- Modal Inscritos -->
    <div id="modalInscritos" class="modal-overlay" onclick="cerrarModalInscritos(event)">
        <div class="modal-content">
            <h3 style="text-align:center; color:#AB47BC; margin-bottom:1rem; font-weight:600;">👥 Inscritos</h3>
            <div id="listaInscritos"><p style="text-align:center; color:#888;">Cargando...</p></div>
            <button onclick="document.getElementById('modalInscritos').style.display='none'" style="width:100%; margin-top:1rem; padding:0.6rem; background:#f0f0f0; border:none; border-radius:12px; font-weight:600; color:#555;">Cerrar</button>
        </div>
    </div>

    <script>
        // === MENÚ 3 PUNTOS ===
        function toggleMenu() {
            const menu = document.getElementById('reservaMenu');
            menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
        }
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.top-controls')) document.getElementById('reservaMenu').style.display = 'none';
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
                if(d.success) { alert('¡Anotado correctamente!'); location.reload(); }
                else { alert('Error: ' + d.message); }
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
                if(d.success) { alert('Te has dado de baja.'); location.reload(); }
                else { alert('Error: ' + d.message); }
            });
        }

        // === VER INSCRITOS (CORREGIDO PARA CARGAR DATOS REALES) ===
        function verInscritosHero(idReserva) {
            const modal = document.getElementById('modalInscritos');
            const lista = document.getElementById('listaInscritos');
            modal.style.display = 'flex';
            lista.innerHTML = '<p style="text-align:center; color:#888;">Cargando...</p>';
            
            // Usamos la API que ya funciona en el dashboard
            fetch(`../api/get_inscritos_torneo.php?id_torneo=${idReserva}`) // Ojo: Si es reserva, quizás necesites otra API o adaptar esta
                .then(r => r.json())
                .then(data => {
                    // Si la API devuelve error o vacío, mostramos mensaje
                    if(!data || !Array.isArray(data) || data.length === 0) {
                        // Intento fallback: Si no hay API específica de reserva, mostramos los inscritos generales si existen
                        lista.innerHTML = '<p style="text-align:center; color:#888; padding:1rem;">No se pudo cargar la lista detallada.<br><small>Total inscritos: <?= $cant_inscritos ?></small></p>';
                        return;
                    }
                    
                    let html = '';
                    data.forEach(p => {
                        // Asumiendo estructura de get_inscritos_torneo
                        const nombre = p.nombre_pareja || (p.jugador1 + ' & ' + p.jugador2);
                        html += `<div class="inscrito-item"><span>${nombre}</span></div>`;
                    });
                    lista.innerHTML = html || '<p style="text-align:center;">Sin detalles disponibles.</p>';
                })
                .catch(err => {
                    console.error(err);
                    lista.innerHTML = '<p style="text-align:center; color:red;">Error de conexión.</p>';
                });
        }
        function cerrarModalInscritos(e) { if(e.target.id === 'modalInscritos') e.target.style.display = 'none'; }

        // === COMPARTIR ===
        function compartirReserva(id) {
            navigator.clipboard.writeText(window.location.origin + '/pages/detalle_reserva.php?id=' + id)
            .then(() => alert('✅ Enlace copiado'));
        }
    </script>
</body>
</html>