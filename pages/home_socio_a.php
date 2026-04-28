<?php
// Mockup A - Actualizado con ajustes visuales solicitados
// Datos mock para prueba visual (reemplazar con queries reales al conectar)
$nombre_mostrar = "Kathe";
$proximo = [
    'id_reserva' => 123,
    'fecha' => date('Y-m-d', strtotime('+2 days')),
    'hora_inicio' => '19:00:00',
    'nombre_cancha' => 'Cancha 1',
    'id_deporte' => 'padel',
    'jugadores_esperados' => 4,
    'inscritos_actuales' => 2
];
$ya_inscrito = false;
$cant_inscritos = $proximo['inscritos_actuales'] ?? 0;
$nombre_deporte = ['padel'=>'Pádel','tenis'=>'Tenis','futbol'=>'Fútbol'][$proximo['id_deporte']] ?? 'Deporte';
$cupos_disponibles = max(0, ($proximo['jugadores_esperados'] ?? 4) - $cant_inscritos);
$progress_percent = min(100, ($cant_inscritos / ($proximo['jugadores_esperados'] ?? 4)) * 100);
?>
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
        }
        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--bg-transparent);
            /* Background de cancha visible */
            background-image: url('../../assets/img/cancha_pasto2.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            color: var(--text-dark);
            min-height: 100vh;
            padding-bottom: 90px;
        }
        /* Overlay sutil para legibilidad */
        body::before {
            content: ''; position: fixed; inset: 0;
            background: rgba(247, 250, 252, 0.85);
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
        .avatar {
            width: 38px; height: 38px; border-radius: 50%;
            background: white; color: var(--padel-blue);
            font-weight: 600; font-size: 0.95rem;
            display: grid; place-items: center;
            border: 2px solid rgba(255,255,255,0.7);
            text-decoration: none;
        }

        .container { max-width: 560px; margin: 0 auto; padding: 1.25rem; }

        /* HERO CARD - GRADIENTE AZUL→VERDE + SOMBRA MATCH */
        .hero {
            background: linear-gradient(135deg, var(--padel-blue) 0%, var(--tennis-green) 100%);
            border-radius: 28px;
            padding: 1.75rem 1.5rem;
            margin-bottom: 1.75rem;
            /* Sombra con mismo degrade usando box-shadow múltiple */
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
        
        /* BOTÓN ÚNICO FULL-WIDTH */
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

        /* BARRA DE PROGRESO CON GRADIENTE ROJO AL FINAL */
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
            /* Gradiente: verde → amarillo → rojo para últimos cupos */
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

        /* QUICK ACTIONS - SOMBRAS CON DEGRADÉ POR COLOR */
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
            /* Sombra con degrade según color */
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

        /* FAB - VERDE FLUOR */
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

        /* MODAL INSCRITOS */
        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.55);
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
        .inscrito-item {
            padding: 0.9rem 0;
            border-bottom: 1px solid #EDF2F7;
            display: flex; justify-content: space-between; align-items: center;
        }
        .inscrito-item:last-child { border-bottom: none; }
        .inscrito-name { font-weight: 500; }
        .inscrito-status {
            font-size: 0.8rem; padding: 0.25rem 0.6rem;
            border-radius: 10px; background: #E8F5E9; color: #2E7D32;
        }

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
    </style>
</head>
<body>
    <header class="app-header">
        <div class="logo">
            <div class="logo-icon">⚽</div>
            <span class="brand">CanchaSport</span>
        </div>
        <a href="mantenedor_socios.php" class="avatar">
            <?= strtoupper(substr($nombre_mostrar,0,1)) ?>
        </a>
    </header>

    <div class="container">
        <!-- HERO CARD -->
        <div class="hero">
            <h1 class="hero-title">Próximo Partido</h1>
            <div class="hero-meta">
                <span>📅 <?= date('d M', strtotime($proximo['fecha'])) ?></span>
                <span>⏰ <?= substr($proximo['hora_inicio'],0,5) ?></span>
                <span>🏟️ <?= htmlspecialchars($proximo['nombre_cancha']) ?></span>
            </div>
            
            <?php if($ya_inscrito): ?>
                <button class="btn-hero inscrito" onclick="bajarse()">❌ Bajarme del partido</button>
            <?php else: ?>
                <button class="btn-hero" onclick="anotarse()">✅ Anotarme</button>
            <?php endif; ?>

            <div class="progress-section">
                <span class="progress-label">Cupos</span>
                <div class="progress-track">
                    <div class="progress-fill"></div>
                </div>
                <button class="progress-eye" onclick="verInscritos()" title="Ver inscritos">👁️</button>
            </div>
        </div>

        <!-- QUICK ACTIONS -->
        <div class="quick-actions">
            <a href="reservar_cancha.php" class="action-card reservar">
                <div class="action-icon">🎾</div>
                <span class="action-label">Reservar</span>
            </a>
            <a href="#" class="action-card torneos" onclick="showToast('🔜 Torneos próximamente'); return false;">
                <div class="action-icon">🏆</div>
                <span class="action-label">Torneos</span>
            </a>
            <a href="#" class="action-card stats" onclick="showToast('📊 Stats en desarrollo'); return false;">
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
        </div>
    </div>

    <!-- TOAST -->
    <div id="toast" class="toast">✅ Acción realizada</div>

    <script>
        // === FUNCIONES UI ===
        function showToast(msg) {
            const t = document.getElementById('toast');
            t.textContent = msg;
            t.classList.add('show');
            setTimeout(() => t.classList.remove('show'), 3000);
        }
        
        function cerrarModal(e) {
            if(e.target.id === 'modalInscritos' || e.target.classList.contains('modal-close')) {
                document.getElementById('modalInscritos').style.display = 'none';
            }
        }

        // === ACCIONES ===
        function anotarse() {
            if(!confirm('¿Confirmas tu inscripción? Se generará tu cuota.')) return;
            showToast('✅ ¡Anotado correctamente!');
            // Aquí iría el fetch a tu API real
        }
        
        function bajarse() {
            if(!confirm('¿Seguro que deseas bajarte?')) return;
            showToast('❌ Te has dado de baja');
        }
        
        function verInscritos() {
            const modal = document.getElementById('modalInscritos');
            const lista = document.getElementById('listaInscritos');
            modal.style.display = 'flex';
            
            // Mock data - reemplazar con fetch real a tu API
            const mockInscritos = [
                {nombre: 'Ana M.', estado: 'Confirmado'},
                {nombre: 'Luis R.', estado: 'Confirmado'},
                {nombre: 'Carla S.', estado: 'Pendiente'}
            ];
            
            let html = '';
            mockInscritos.forEach(p => {
                html += `<div class="inscrito-item">
                    <span class="inscrito-name">${p.nombre}</span>
                    <span class="inscrito-status">${p.estado}</span>
                </div>`;
            });
            lista.innerHTML = html || '<p style="text-align:center; color:var(--text-light);">Sin inscritos aún</p>';
        }
    </script>
</body>
</html>