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
// Detectar si el socio tiene un club activo principal (o usar el de la sesión si existe)
$club_id_actual = $_SESSION['club_id'] ?? null;

$where_parts = ["r.estado = 'confirmada'", "r.fecha >= CURDATE()"];
$params = [];

if ($club_id_actual !== null) {
    // Modo Club: Reservas del club donde el socio participa
    $where_parts[] = "(
        (r.id_club = ? AND r.id_socio = ?) 
        OR 
        (r.id_club = ?)
    )";
    $params[] = $club_id_actual;
    $params[] = $id_socio;
    $params[] = $club_id_actual;
} else {
    // Modo Individual: Solo reservas personales sin club
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

// Verificar si YA está inscrito en este próximo evento
$ya_inscrito = false;
$cant_inscritos = 0;
if ($proximo) {
    $stmt_check = $pdo->prepare("SELECT 1 FROM inscritos WHERE id_evento = ? AND id_socio = ?");
    $stmt_check->execute([$proximo['id_reserva'], $id_socio]);
    $ya_inscrito = $stmt_check->fetch();
    
    $cant_inscritos = $proximo['inscritos_actuales'] ?? 0;
}

// Mapeo de deportes
$nombres_deportes = [
    'futbol' => 'Fútbol', 'futbolito' => 'Futbolito', 'futsal' => 'Futsal',
    'tenis' => 'Tenis', 'padel' => 'Pádel', 'voleyball' => 'Vóley'
];
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
            --primary: #BA68C8;
            --primary-dark: #AB47BC;
            --accent: #4CAF50;
            --bg-glass: rgba(255, 255, 255, 0.85);
            --text: #333;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        
        body { 
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); 
            color: var(--text); 
            min-height: 100vh; 
            padding-bottom: 80px; 
        }

        /* HEADER CON LOGO */
        .app-header {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(10px);
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky; top: 0; z-index: 100;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .logo-container { display: flex; align-items: center; gap: 0.5rem; }
        .logo-svg { width: 40px; height: 40px; }
        .brand-name { font-weight: 900; font-size: 1.4rem; color: var(--primary-dark); letter-spacing: -0.5px; }
        
        .user-avatar {
            width: 40px; height: 40px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: bold; text-decoration: none;
        }

        .container { max-width: 600px; margin: 0 auto; padding: 1.5rem; }

        /* FICHA PRÓXIMO PARTIDO (GLASSMORPHISM) */
        .hero-card {
            background: linear-gradient(135deg, var(--primary) 0%, #8E24AA 100%);
            color: white;
            border-radius: 24px;
            padding: 2rem 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 15px 35px rgba(186, 104, 200, 0.4);
            text-align: center;
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        .hero-card.empty {
            background: linear-gradient(135deg, #4CAF50 0%, #2E7D32 100%);
            box-shadow: 0 15px 35px rgba(76, 175, 80, 0.4);
        }

        .hero-date { font-size: 1.2rem; font-weight: 600; margin-bottom: 0.5rem; display: block; opacity: 0.9; }
        .hero-sport { font-size: 1rem; opacity: 0.8; margin-bottom: 1.5rem; text-transform: uppercase; letter-spacing: 1px; }
        
        .btn-main {
            background: white;
            color: var(--primary-dark);
            border: none;
            padding: 1rem 2rem;
            border-radius: 50px;
            font-weight: bold;
            font-size: 1.1rem;
            cursor: pointer;
            width: 100%;
            transition: transform 0.2s;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .btn-main:active { transform: scale(0.98); }
        .btn-secondary {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.4);
            margin-top: 1rem;
        }

        /* ACCIONES RÁPIDAS */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .action-pill {
            background: var(--bg-glass);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.5);
            border-radius: 20px;
            padding: 1.2rem 0.5rem;
            text-align: center;
            text-decoration: none;
            color: var(--text);
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
            transition: all 0.2s;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
        }
        .action-pill:hover { transform: translateY(-3px); box-shadow: 0 8px 15px rgba(0,0,0,0.05); }
        .icon-box {
            width: 50px; height: 50px;
            background: #F3E5F5;
            color: var(--primary);
            border-radius: 15px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem;
        }

        /* FAB */
        .fab-reserve {
            position: fixed; bottom: 25px; right: 25px;
            background: var(--accent);
            color: white;
            width: 65px; height: 65px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 2.5rem;
            box-shadow: 0 4px 20px rgba(76, 175, 80, 0.5);
            text-decoration: none;
            z-index: 90;
            transition: transform 0.2s;
        }
        .fab-reserve:hover { transform: scale(1.1); }

    </style>
</head>
<body>

    <header class="app-header">
        <div class="logo-container">
            <!-- LOGO SVG CANCHASPORT -->
            <svg class="logo-svg" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="50" cy="50" r="45" stroke="#BA68C8" stroke-width="8"/>
                <path d="M30 50 C30 30, 70 30, 70 50 C70 70, 30 70, 30 50" stroke="#AB47BC" stroke-width="6" fill="none"/>
                <circle cx="50" cy="50" r="10" fill="#BA68C8"/>
                <path d="M50 5 L50 20 M50 80 L50 95 M5 50 L20 50 M80 50 L95 50" stroke="#BA68C8" stroke-width="4"/>
            </svg>
            <span class="brand-name">CanchaSport</span>
        </div>
        <a href="mantenedor_socios.php" class="user-avatar">
            <?= strtoupper(substr($nombre_mostrar, 0, 1)) ?>
        </a>
    </header>

    <div class="container">
        
        <?php if ($proximo): ?>
            <!-- FICHA MORADA: CON RESERVA ACTIVA -->
            <div class="hero-card">
                <h2 style="font-size: 1.8rem; margin-bottom: 0.5rem;">Próximo Partido</h2>
                <span class="hero-date">📅 <?= date('d M', strtotime($proximo['fecha'])) ?> • ⏰ <?= substr($proximo['hora_inicio'], 0, 5) ?></span>
                <div class="hero-sport"><?= htmlspecialchars($nombre_deporte) ?> • <?= htmlspecialchars($proximo['nombre_cancha']) ?></div>
                
                <?php if ($ya_inscrito): ?>
                    <!-- YA INSCRITO -->
                    <button class="btn-main" onclick="compartirReserva(<?= $proximo['id_reserva'] ?>)">
                        📲 Compartir Reserva
                    </button>
                    <div style="margin-top: 1rem; font-size: 0.9rem; opacity: 0.9;">
                        👥 <?= $cant_inscritos ?> inscritos
                        <?php if ($proximo['id_deporte'] == 'futbolito'): ?>
                            <button onclick="verInscritosHero(<?= $proximo['id_reserva'] ?>)" style="background:none; border:none; color:white; cursor:pointer; margin-left:5px;">👁️</button>
                        <?php endif; ?>
                    </div>
                    <button class="btn-main btn-secondary" onclick="bajarseEvento(<?= $proximo['id_reserva'] ?>)">
                        ❌ Bajarme
                    </button>
                <?php else: ?>
                    <!-- NO INSCRITO AÚN -->
                    <button class="btn-main" onclick="anotarmeAlEvento(<?= $proximo['id_reserva'] ?>)">
                        ✅ Anotarme
                    </button>
                    <div style="margin-top: 1rem; font-size: 0.9rem; opacity: 0.9;">
                        👥 <?= $cant_inscritos ?> inscritos
                        <?php if ($proximo['id_deporte'] == 'futbolito'): ?>
                            <button onclick="verInscritosHero(<?= $proximo['id_reserva'] ?>)" style="background:none; border:none; color:white; cursor:pointer; margin-left:5px;">👁️</button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- FICHA VERDE: SIN RESERVAS -->
            <div class="hero-card empty">
                <h2 style="font-size: 1.8rem; margin-bottom: 0.5rem;">¡Hola, <?= htmlspecialchars($nombre_mostrar) ?>!</h2>
                <p style="margin-bottom: 1.5rem; opacity: 0.9; font-size: 1.1rem;">No tienes partidos próximos. ¿Jugamos hoy?</p>
                <a href="reservar_cancha.php" style="display:block; background:white; color:#2E7D32; padding:1rem; border-radius:50px; text-decoration:none; font-weight:bold; font-size: 1.1rem; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                    🎾 Reservar Ahora
                </a>
            </div>
        <?php endif; ?>

        <!-- ACCIONES RÁPIDAS -->
        <div class="quick-actions">
            <a href="reservar_cancha.php" class="action-pill">
                <div class="icon-box" style="background:#E8F5E9; color:#2E7D32;">🎾</div>
                <span style="font-weight: 600; font-size: 0.9rem;">Reservar</span>
            </a>
            <a href="torneos_mis_inscripciones.php" class="action-pill">
                <div class="icon-box" style="background:#FFF3E0; color:#EF6C00;">🏆</div>
                <span style="font-weight: 600; font-size: 0.9rem;">Mis Torneos</span>
            </a>
            <a href="ranking_publico.php" class="action-pill">
                <div class="icon-box" style="background:#E3F2FD; color:#1565C0;">📊</div>
                <span style="font-weight: 600; font-size: 0.9rem;">Ranking</span>
            </a>
        </div>

    </div>

    <!-- BOTÓN FLOTANTE -->
    <a href="reservar_cancha.php" class="fab-reserve">+</a>

    <script>
        // === ANOTARSE AL EVENTO (Con generación de cuota) ===
        function anotarmeAlEvento(idReserva) {
            if(!confirm('¿Quieres anotarte a este partido? Se generará tu cuota correspondiente.')) return;
            
            const formData = new FormData();
            formData.append('action', 'anotarse');
            formData.append('id_actividad', idReserva);
            formData.append('tipo_actividad', 'reserva');
            
            fetch('../api/gestion_eventos.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if(data.success) {
                        alert('✅ ¡Te has anotado correctamente! Revisa tu sección de pagos.');
                        location.reload();
                    } else {
                        alert('❌ Error: ' + data.message);
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('❌ Error de conexión');
                });
        }

        // === BAJARSE DEL EVENTO ===
        function bajarseEvento(idReserva) {
            if(!confirm('¿Seguro que deseas bajarte?')) return;
            const formData = new FormData();
            formData.append('action', 'bajarse');
            formData.append('id_reserva', idReserva);
            fetch('../api/gestion_eventos.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if(data.success) {
                        alert('✅ Te has dado de baja.');
                        location.reload();
                    } else {
                        alert('❌ Error: ' + data.message);
                    }
                });
        }

        // === COMPARTIR Y VER INSCRITOS (Funciones auxiliares) ===
        function compartirReserva(id) {
            const url = window.location.origin + '/pages/detalle_reserva.php?id=' + id;
            navigator.clipboard.writeText(url).then(() => alert('✅ Enlace copiado'));
        }

        function verInscritosHero(idReserva) {
            alert('👁️ Función de ver lista de inscritos: En desarrollo para Home. Usa el Dashboard completo.');
        }
    </script>
</body>
</html>