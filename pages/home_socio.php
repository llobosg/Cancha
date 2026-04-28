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
$nombre_mostrar = $socio['alias'] ?: explode(' ', $socio['nombre'])[0]; // Usar alias o primer nombre

// Obtener Próximo Partido (Simplificado)
$stmt_next = $pdo->prepare("
    SELECT r.id_reserva, r.fecha, r.hora_inicio, c.nombre_cancha, c.id_deporte
    FROM reservas r
    JOIN canchas c ON r.id_cancha = c.id_cancha
    JOIN inscritos i ON r.id_reserva = i.id_evento
    WHERE i.id_socio = ? AND r.fecha >= CURDATE()
    ORDER BY r.fecha ASC, r.hora_inicio ASC
    LIMIT 1
");
$stmt_next->execute([$id_socio]);
$proximo = $stmt_next->fetch();

// Obtener Último Resultado (Para motivación)
$stmt_last = $pdo->prepare("
    SELECT p.juegos_pareja_1, p.juegos_pareja_2, t.nombre as torneo_nombre
    FROM partidos_torneo p
    JOIN parejas_torneo pt ON (p.id_pareja_1 = pt.id_pareja OR p.id_pareja_2 = pt.id_pareja)
    JOIN torneos t ON pt.id_torneo = t.id_torneo
    WHERE (pt.id_socio_1 = ? OR pt.id_socio_2 = ?) AND p.estado = 'finalizado'
    ORDER BY p.fecha_hora_programada DESC
    LIMIT 1
");
$stmt_last->execute([$id_socio, $id_socio]);
$ultimo_resultado = $stmt_last->fetch();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Home - CanchaSport</title>
    <style>
        :root {
            --primary: #BA68C8; /* Morado CanchaSport */
            --primary-dark: #AB47BC;
            --accent: #4CAF50; /* Verde Acción */
            --bg: #F4F6F9;
            --text: #333;
            --card-bg: #FFFFFF;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        
        body { background-color: var(--bg); color: var(--text); padding-bottom: 80px; }

        /* HEADER MINIMALISTA */
        .app-header {
            background: white;
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: sticky; top: 0; z-index: 100;
        }
        .logo { font-weight: 900; font-size: 1.4rem; color: var(--primary-dark); letter-spacing: -0.5px; }
        .user-avatar {
            width: 40px; height: 40px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: bold; font-size: 1.1rem;
            text-decoration: none;
        }

        /* CONTENEDOR PRINCIPAL */
        .container { max-width: 600px; margin: 0 auto; padding: 1.5rem; }

        /* TARJETA HÉROE: PRÓXIMO PARTIDO */
        .hero-card {
            background: linear-gradient(135deg, var(--primary) 0%, #8E24AA 100%);
            color: white;
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 25px rgba(186, 104, 200, 0.3);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .hero-card h2 { font-size: 1.5rem; margin-bottom: 0.5rem; opacity: 0.9; }
        .hero-date { font-size: 1.1rem; font-weight: 600; margin-bottom: 1rem; display: block; }
        .hero-sport { font-size: 0.9rem; opacity: 0.8; margin-bottom: 1.5rem; text-transform: uppercase; letter-spacing: 1px; }
        
        .btn-share {
            background: white;
            color: var(--primary-dark);
            border: none;
            padding: 0.8rem 2rem;
            border-radius: 50px;
            font-weight: bold;
            font-size: 1rem;
            cursor: pointer;
            width: 100%;
            transition: transform 0.2s;
        }
        .btn-share:active { transform: scale(0.98); }

        /* ACCIONES RÁPIDAS (PÍLDORAS) */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .action-pill {
            background: white;
            border: 1px solid #eee;
            border-radius: 16px;
            padding: 1rem 0.5rem;
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
        .action-pill:hover { transform: translateY(-3px); box-shadow: 0 8px 15px rgba(0,0,0,0.05); border-color: var(--primary); }
        .icon-box {
            width: 45px; height: 45px;
            background: #F3E5F5;
            color: var(--primary);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem;
        }
        .action-label { font-size: 0.85rem; font-weight: 600; }

        /* SECCIÓN ACTIVIDAD RECIENTE */
        .section-title { font-size: 1.2rem; font-weight: 700; margin-bottom: 1rem; color: #444; }
        .activity-list { background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .activity-item {
            padding: 1rem;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .activity-item:last-child { border-bottom: none; }
        .match-info { display: flex; flex-direction: column; }
        .match-tournament { font-size: 0.8rem; color: #888; text-transform: uppercase; }
        .match-score { font-weight: bold; font-size: 1.1rem; color: var(--text); }
        .match-result { 
            padding: 0.3rem 0.8rem; 
            border-radius: 20px; 
            font-size: 0.8rem; 
            font-weight: bold; 
        }
        .win { background: #E8F5E9; color: #2E7D32; }
        .loss { background: #FFEBEE; color: #C62828; }

        /* BOTÓN FLOTANTE RESERVAR (Opcional si se quiere más énfasis) */
        .fab-reserve {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--accent);
            color: white;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.4);
            text-decoration: none;
            z-index: 90;
        }

        @media (max-width: 400px) {
            .quick-actions { grid-template-columns: 1fr; }
            .action-pill { flex-direction: row; justify-content: flex-start; padding: 1rem; }
        }
    </style>
</head>
<body>

    <!-- Header Minimalista -->
    <header class="app-header">
        <div class="logo">CanchaSport</div>
        <a href="mantenedor_socios.php" class="user-avatar">
            <?= strtoupper(substr($nombre_mostrar, 0, 1)) ?>
        </a>
    </header>

    <div class="container">
        
        <!-- Hero: Próximo Partido -->
        <?php if ($proximo): ?>
            <div class="hero-card">
                <h2>Próximo Partido</h2>
                <span class="hero-date">📅 <?= date('d M', strtotime($proximo['fecha'])) ?> • ⏰ <?= substr($proximo['hora_inicio'], 0, 5) ?></span>
                <div class="hero-sport"><?= htmlspecialchars($proximo['nombre_cancha']) ?></div>
                <button class="btn-share" onclick="compartirReserva(<?= $proximo['id_reserva'] ?>)">
                    📲 Compartir Reserva
                </button>
            </div>
        <?php else: ?>
            <div class="hero-card" style="background: linear-gradient(135deg, #4CAF50 0%, #2E7D32 100%);">
                <h2>¡Hola, <?= htmlspecialchars($nombre_mostrar) ?>!</h2>
                <p style="margin-bottom: 1.5rem; opacity: 0.9;">No tienes partidos próximos. ¿Jugamos hoy?</p>
                <a href="reservar_cancha.php" style="display:block; background:white; color:#2E7D32; padding:0.8rem; border-radius:50px; text-decoration:none; font-weight:bold;">
                    🎾 Reservar Ahora
                </a>
            </div>
        <?php endif; ?>

        <!-- Acciones Rápidas -->
        <div class="quick-actions">
            <a href="reservar_cancha.php" class="action-pill">
                <div class="icon-box" style="background:#E8F5E9; color:#2E7D32;">🎾</div>
                <span class="action-label">Reservar</span>
            </a>
            <a href="torneos_mis_inscripciones.php" class="action-pill">
                <div class="icon-box" style="background:#FFF3E0; color:#EF6C00;">🏆</div>
                <span class="action-label">Mis Torneos</span>
            </a>
            <a href="ranking_publico.php" class="action-pill">
                <div class="icon-box" style="background:#E3F2FD; color:#1565C0;">📊</div>
                <span class="action-label">Ranking</span>
            </a>
        </div>

        <!-- Actividad Reciente / Ranking Personal -->
        <h3 class="section-title">Tu Actividad Reciente</h3>
        <div class="activity-list">
            <?php if ($ultimo_resultado): ?>
                <div class="activity-item">
                    <div class="match-info">
                        <span class="match-tournament"><?= htmlspecialchars($ultimo_resultado['torneo_nombre']) ?></span>
                        <span class="match-score">Resultado Final</span>
                    </div>
                    <div style="text-align:right;">
                        <span class="match-score"><?= $ultimo_resultado['juegos_pareja_1'] ?> - <?= $ultimo_resultado['juegos_pareja_2'] ?></span>
                        <!-- Lógica simple para determinar victoria basada en id_socio (mejorar con JOIN real) -->
                        <span class="match-result win">Ver Detalle</span>
                    </div>
                </div>
            <?php else: ?>
                <div class="activity-item" style="justify-content:center; color:#888; padding:2rem;">
                    Aún no has jugado torneos recientes.
                </div>
            <?php endif; ?>
            
            <!-- Item estático de ejemplo para mostrar diseño -->
            <div class="activity-item" style="opacity:0.6;">
                <div class="match-info">
                    <span class="match-tournament">Liga Interna Pádel</span>
                    <span class="match-score">Semana Pasada</span>
                </div>
                <div style="text-align:right;">
                    <span class="match-score">6 - 4</span>
                    <span class="match-result win">Ganaste</span>
                </div>
            </div>
        </div>

    </div>

    <!-- Botón Flotante de Acción Principal -->
    <a href="reservar_cancha.php" class="fab-reserve">+</a>

    <script>
        function compartirReserva(id) {
            // Lógica simple de compartir
            const url = window.location.origin + '/pages/detalle_reserva.php?id=' + id;
            navigator.clipboard.writeText(url).then(() => {
                alert('✅ Enlace de reserva copiado al portapapeles');
            }).catch(err => {
                console.error('Error al copiar: ', err);
            });
        }
    </script>
</body>
</html>