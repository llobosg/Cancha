<!-- pages/mockups/home_socio_B.php -->
<?php
// Mockup B - Sport Cards Grid
$nombre_mostrar = "Kathe";
$deportes_usuario = [
    ['id'=>'padel', 'nombre'=>'Pádel', 'icon'=>'🎾', 'proximos'=>2, 'color'=>'#AB47BC'],
    ['id'=>'tenis', 'nombre'=>'Tenis', 'icon'=>'🎾', 'proximos'=>1, 'color'=>'#4CAF50'],
    ['id'=>'futbol', 'nombre'=>'Fútbol', 'icon'=>'⚽', 'proximos'=>0, 'color'=>'#2196F3']
];
$proximo_global = ['fecha'=>date('Y-m-d', strtotime('+1 day')), 'hora'=>'18:30', 'deporte'=>'Pádel'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Mockup B - Home Socio</title>
    <style>
        :root {
            --primary-start: #CE93D8; --primary-end: #AB47BC;
            --text-dark: #2D3748; --bg-light: #F7FAFC;
            --card-bg: rgba(255,255,255,0.95);
            --shadow: 0 6px 20px rgba(0,0,0,0.08);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--bg-light);
            color: var(--text-dark);
            min-height: 100vh;
            padding-bottom: 85px;
        }

        .app-header {
            background: linear-gradient(90deg, var(--primary-start), var(--primary-end));
            padding: 0.8rem 1.25rem;
            display: flex; justify-content: space-between; align-items: center;
            position: sticky; top: 0; z-index: 100;
        }
        .logo { display: flex; align-items: center; gap: 0.5rem; color: white; }
        .logo span { font-weight: 700; font-size: 1.2rem; }
        .avatar {
            width: 36px; height: 36px; border-radius: 50%;
            background: white; color: var(--primary-end);
            font-weight: 600; display: grid; place-items: center;
            text-decoration: none; font-size: 0.9rem;
        }

        .container { max-width: 560px; margin: 0 auto; padding: 1rem; }

        /* NEXT MATCH STRIP */
        .next-match {
            background: var(--card-bg); border-radius: 20px;
            padding: 1rem 1.25rem; margin-bottom: 1.5rem;
            display: flex; align-items: center; gap: 1rem;
            box-shadow: var(--shadow); border-left: 4px solid var(--primary-end);
        }
        .next-match-icon {
            font-size: 1.8rem; width: 48px; height: 48px;
            background: rgba(171,71,188,0.1); border-radius: 14px;
            display: grid; place-items: center;
        }
        .next-match-info { flex: 1; }
        .next-match-title { font-weight: 600; font-size: 1rem; }
        .next-match-meta { font-size: 0.85rem; color: #718096; }
        .next-match-btn {
            padding: 0.5rem 1rem; background: var(--primary-end);
            color: white; border: none; border-radius: 12px;
            font-weight: 500; font-size: 0.85rem; cursor: pointer;
        }

        /* SPORT CARDS GRID */
        .section-title {
            font-size: 1.1rem; font-weight: 600; margin: 1.5rem 0 0.75rem;
            display: flex; align-items: center; gap: 0.5rem;
        }
        .sport-grid {
            display: grid; grid-template-columns: repeat(2, 1fr);
            gap: 0.85rem;
        }
        .sport-card {
            background: var(--card-bg); border-radius: 18px;
            padding: 1.1rem 0.9rem; text-decoration: none;
            color: var(--text-dark); box-shadow: var(--shadow);
            display: flex; flex-direction: column; align-items: center;
            text-align: center; transition: transform 0.2s;
            border: 1px solid rgba(255,255,255,0.8);
        }
        .sport-card:hover { transform: translateY(-3px); }
        .sport-icon {
            font-size: 2rem; margin-bottom: 0.5rem;
            width: 56px; height: 56px; border-radius: 16px;
            display: grid; place-items: center;
            background: linear-gradient(135deg, rgba(255,255,255,0.8), rgba(255,255,255,0.4));
        }
        .sport-name { font-weight: 500; font-size: 0.95rem; margin-bottom: 0.25rem; }
        .sport-count { font-size: 0.8rem; color: #718096; }
        .sport-card[data-color] .sport-icon {
            background: linear-gradient(135deg, rgba(255,255,255,0.9), rgba(255,255,255,0.6));
            border: 2px solid var(--sport-color);
        }

        /* FAB */
        .fab {
            position: fixed; bottom: 24px; right: 24px;
            width: 56px; height: 56px; border-radius: 50%;
            background: white; color: var(--primary-end);
            font-size: 1.9rem; display: grid; place-items: center;
            text-decoration: none; box-shadow: 0 6px 22px rgba(0,0,0,0.15);
            border: 2px solid white; z-index: 90;
        }

        @media (max-width: 480px) {
            .sport-grid { grid-template-columns: repeat(3, 1fr); }
            .sport-card { padding: 0.9rem 0.5rem; }
            .sport-icon { width: 48px; height: 48px; font-size: 1.7rem; }
            .next-match { padding: 0.9rem 1rem; }
        }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="logo">
            <span>⚽🎾🏐</span>
            <span>CanchaSport</span>
        </div>
        <a href="#" class="avatar"><?= strtoupper(substr($nombre_mostrar,0,1)) ?></a>
    </header>

    <div class="container">
        <!-- PRÓXIMO GLOBAL -->
        <div class="next-match">
            <div class="next-match-icon">🎾</div>
            <div class="next-match-info">
                <div class="next-match-title"><?= $proximo_global['deporte'] ?></div>
                <div class="next-match-meta">
                    📅 <?= date('d M', strtotime($proximo_global['fecha'])) ?> • ⏰ <?= $proximo_global['hora'] ?>
                </div>
            </div>
            <button class="next-match-btn">Ver</button>
        </div>

        <!-- MIS DEPORTES -->
        <div class="section-title">🏅 Mis Deportes</div>
        <div class="sport-grid">
            <?php foreach($deportes_usuario as $d): ?>
            <a href="#" class="sport-card" style="--sport-color: <?= $d['color'] ?>">
                <div class="sport-icon"><?= $d['icon'] ?></div>
                <div class="sport-name"><?= $d['nombre'] ?></div>
                <div class="sport-count"><?= $d['proximos'] ?> próximos</div>
            </a>
            <?php endforeach; ?>
            <a href="#" class="sport-card" style="border-style: dashed; border-color: #CBD5E0;">
                <div class="sport-icon" style="background:transparent; border:none;">➕</div>
                <div class="sport-name">Agregar</div>
            </a>
        </div>
    </div>

    <a href="#" class="fab">+</a>
</body>
</html>