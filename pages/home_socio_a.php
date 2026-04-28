<!-- pages/mockups/home_socio_A.php -->
<?php
// Mockup A - Minimal Hero
// Requiere: require_once __DIR__ . '/../includes/config.php';
// Nota: Este es un archivo de prueba, no conecta a BD real para el mockup
$nombre_mostrar = "Kathe";
$proximo = [
    'fecha' => date('Y-m-d', strtotime('+2 days')),
    'hora_inicio' => '19:00:00',
    'nombre_cancha' => 'Cancha 1',
    'id_deporte' => 'padel',
    'monto_total' => 15000,
    'jugadores_esperados' => 4,
    'inscritos_actuales' => 2
];
$ya_inscrito = false;
$cant_inscritos = $proximo['inscritos_actuales'] ?? 0;
$nombre_deporte = ['padel'=>'Pádel','tenis'=>'Tenis','futbol'=>'Fútbol'][$proximo['id_deporte']] ?? 'Deporte';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Mockup A - Home Socio</title>
    <style>
        :root {
            --primary-start: #CE93D8; --primary-end: #AB47BC;
            --accent: #4CAF50; --warning: #FF9800;
            --text-dark: #2D3748; --text-light: #718096;
            --bg-light: #F7FAFC; --card-bg: rgba(255,255,255,0.9);
            --shadow-soft: 0 4px 20px rgba(171,71,188,0.15);
            --shadow-float: 0 8px 30px rgba(0,0,0,0.12);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--bg-light);
            color: var(--text-dark);
            min-height: 100vh;
            padding-bottom: 90px;
            background-image: 
                radial-gradient(circle at 20% 80%, rgba(206,147,216,0.08) 0%, transparent 40%),
                radial-gradient(circle at 80% 20%, rgba(171,71,188,0.06) 0%, transparent 40%);
        }

        /* HEADER */
        .app-header {
            background: linear-gradient(90deg, var(--primary-start), var(--primary-end));
            padding: 0.75rem 1.25rem;
            display: flex; justify-content: space-between; align-items: center;
            position: sticky; top: 0; z-index: 100;
            box-shadow: 0 2px 12px rgba(171,71,188,0.25);
        }
        .logo { display: flex; align-items: center; gap: 0.6rem; }
        .logo-icon {
            width: 34px; height: 34px;
            background: rgba(255,255,255,0.2);
            border-radius: 12px;
            display: grid; place-items: center;
            font-size: 1.1rem;
        }
        .brand { font-weight: 700; font-size: 1.25rem; color: white; letter-spacing: -0.3px; }
        .avatar {
            width: 38px; height: 38px; border-radius: 50%;
            background: white; color: var(--primary-end);
            font-weight: 600; font-size: 0.95rem;
            display: grid; place-items: center;
            border: 2px solid rgba(255,255,255,0.6);
            text-decoration: none;
        }

        .container { max-width: 560px; margin: 0 auto; padding: 1.25rem; }

        /* HERO CARD MINIMAL */
        .hero {
            background: var(--card-bg);
            border-radius: 28px;
            padding: 1.75rem 1.5rem;
            margin-bottom: 1.75rem;
            box-shadow: var(--shadow-float);
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.6);
        }
        .hero::before {
            content: ''; position: absolute; top: -50%; right: -20%;
            width: 180px; height: 180px;
            background: radial-gradient(circle, rgba(206,147,216,0.15) 0%, transparent 70%);
            border-radius: 50%; pointer-events: none;
        }
        .hero-badge {
            display: inline-flex; align-items: center; gap: 0.4rem;
            background: linear-gradient(135deg, var(--primary-start), var(--primary-end));
            color: white; padding: 0.35rem 0.85rem;
            border-radius: 20px; font-size: 0.8rem; font-weight: 500;
            margin-bottom: 1rem;
        }
        .hero-title { font-size: 1.3rem; font-weight: 600; margin-bottom: 0.4rem; }
        .hero-meta {
            display: flex; gap: 1rem; color: var(--text-light);
            font-size: 0.9rem; margin-bottom: 1.25rem; flex-wrap: wrap;
        }
        .hero-meta span { display: flex; align-items: center; gap: 0.3rem; }
        
        .hero-actions { display: flex; gap: 0.75rem; }
        .btn-hero {
            flex: 1; padding: 0.85rem; border-radius: 16px;
            font-weight: 600; font-size: 0.95rem;
            border: none; cursor: pointer; transition: all 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 0.4rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-start), var(--primary-end));
            color: white; box-shadow: 0 4px 14px rgba(171,71,188,0.3);
        }
        .btn-primary:active { transform: scale(0.98); }
        .btn-outline {
            background: transparent; color: var(--primary-end);
            border: 2px solid var(--primary-end);
        }
        .btn-outline:active { background: rgba(171,71,188,0.08); }

        .hero-footer {
            margin-top: 1.25rem; padding-top: 1rem;
            border-top: 1px solid rgba(0,0,0,0.06);
            display: flex; justify-content: space-between; align-items: center;
            font-size: 0.85rem; color: var(--text-light);
        }
        .progress-bar {
            flex: 1; height: 6px; background: #EDF2F7;
            border-radius: 3px; margin: 0 0.75rem; overflow: hidden;
        }
        .progress-fill {
            height: 100%; width: <?= min(100, ($cant_inscritos / ($proximo['jugadores_esperados'] ?? 4)) * 100) ?>%;
            background: linear-gradient(90deg, var(--accent), #66BB6A);
            border-radius: 3px; transition: width 0.4s ease;
        }

        /* QUICK ACTIONS - Sport Icons */
        .quick-actions {
            display: grid; grid-template-columns: repeat(3, 1fr);
            gap: 1rem; margin-bottom: 2rem;
        }
        .action-card {
            background: var(--card-bg); border-radius: 20px;
            padding: 1.25rem 0.75rem; text-align: center;
            text-decoration: none; color: var(--text-dark);
            box-shadow: var(--shadow-soft);
            transition: transform 0.2s, box-shadow 0.2s;
            border: 1px solid rgba(255,255,255,0.7);
        }
        .action-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 28px rgba(171,71,188,0.2);
        }
        .action-icon {
            font-size: 1.75rem; margin-bottom: 0.5rem;
            display: grid; place-items: center;
            width: 52px; height: 52px; margin: 0 auto 0.5rem;
            background: linear-gradient(135deg, rgba(206,147,216,0.15), rgba(171,71,188,0.1));
            border-radius: 16px;
        }
        .action-label { font-size: 0.85rem; font-weight: 500; }

        /* FAB */
        .fab {
            position: fixed; bottom: 28px; right: 28px;
            width: 60px; height: 60px; border-radius: 50%;
            background: white; color: var(--primary-end);
            font-size: 2rem; font-weight: 300;
            display: grid; place-items: center;
            text-decoration: none;
            box-shadow: var(--shadow-float);
            border: 2px solid rgba(255,255,255,0.9);
            transition: all 0.25s cubic-bezier(0.175,0.885,0.32,1.275);
            z-index: 90;
        }
        .fab:hover {
            transform: scale(1.08) rotate(5deg);
            box-shadow: 0 10px 35px rgba(171,71,188,0.35);
            background: var(--primary-end); color: white;
        }

        /* TOAST */
        .toast {
            position: fixed; bottom: 100px; left: 50%; transform: translateX(-50%) translateY(20px);
            background: #2D3748; color: white; padding: 0.85rem 1.5rem;
            border-radius: 14px; font-size: 0.9rem; font-weight: 500;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
            opacity: 0; visibility: hidden; transition: all 0.3s;
            z-index: 1000; max-width: 90%; text-align: center;
        }
        .toast.show {
            opacity: 1; visibility: visible; transform: translateX(-50%) translateY(0);
        }

        /* RESPONSIVE */
        @media (max-width: 480px) {
            .app-header { padding: 0.6rem 1rem; }
            .brand { font-size: 1.15rem; }
            .hero { padding: 1.5rem 1.25rem; border-radius: 24px; }
            .hero-title { font-size: 1.2rem; }
            .quick-actions { gap: 0.75rem; }
            .action-card { padding: 1rem 0.5rem; }
            .action-icon { width: 46px; height: 46px; font-size: 1.5rem; }
            .fab { width: 54px; height: 54px; font-size: 1.8rem; bottom: 22px; right: 22px; }
        }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="logo">
            <div class="logo-icon">⚽</div>
            <span class="brand">CanchaSport</span>
        </div>
        <a href="#" class="avatar"><?= strtoupper(substr($nombre_mostrar,0,1)) ?></a>
    </header>

    <div class="container">
        <!-- HERO CARD -->
        <div class="hero">
            <div class="hero-badge">🔜 Próximo Partido</div>
            <h1 class="hero-title"><?= $nombre_deporte ?></h1>
            <div class="hero-meta">
                <span>📅 <?= date('d M', strtotime($proximo['fecha'])) ?></span>
                <span>⏰ <?= substr($proximo['hora_inicio'],0,5) ?></span>
                <span>🏟️ <?= $proximo['nombre_cancha'] ?></span>
            </div>
            
            <div class="hero-actions">
                <?php if($ya_inscrito): ?>
                    <button class="btn-hero btn-outline" onclick="bajarse()">❌ Bajarme</button>
                <?php else: ?>
                    <button class="btn-hero btn-primary" onclick="anotarse()">✅ Anotarme</button>
                <?php endif; ?>
                <button class="btn-hero btn-outline" onclick="verInscritos()">👥 <?= $cant_inscritos ?>/<?= $proximo['jugadores_esperados'] ?></button>
            </div>

            <div class="hero-footer">
                <span>💰 $<?= number_format($proximo['monto_total'],0,',','.') ?></span>
                <div class="progress-bar"><div class="progress-fill"></div></div>
                <span>Cupos</span>
            </div>
        </div>

        <!-- QUICK ACTIONS -->
        <div class="quick-actions">
            <a href="#" class="action-card">
                <div class="action-icon">🎾</div>
                <span class="action-label">Reservar</span>
            </a>
            <a href="#" class="action-card">
                <div class="action-icon">🏆</div>
                <span class="action-label">Torneos</span>
            </a>
            <a href="#" class="action-card">
                <div class="action-icon">📈</div>
                <span class="action-label">Mis Stats</span>
            </a>
        </div>
    </div>

    <!-- FAB -->
    <a href="#" class="fab">+</a>

    <!-- TOAST -->
    <div id="toast" class="toast">✅ Acción realizada</div>

    <script>
        function showToast(msg, type='success') {
            const t = document.getElementById('toast');
            t.textContent = msg;
            t.style.background = type==='error' ? '#E53E3E' : '#2D3748';
            t.classList.add('show');
            setTimeout(()=>t.classList.remove('show'), 3000);
        }
        function anotarse() { showToast('✅ ¡Anotado correctamente!'); }
        function bajarse() { showToast('❌ Te has dado de baja'); }
        function verInscritos() { showToast('👥 Cargando inscritos...'); }
    </script>
</body>
</html>