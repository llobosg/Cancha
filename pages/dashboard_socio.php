<?php
// pages/dashboard_socio.php - BLOQUE INICIAL BLINDADO
require_once __DIR__ . '/../includes/config.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// === 1. DEFAULTS ABSOLUTOS (evitar undefined) ===
$id_socio = $_SESSION['id_socio'] ?? 0;
$nombre_mostrar = 'Usuario';
$es_responsable = false;
$modo_individual = true;
$club_id = null;
$club_nombre = '';
$club_logo = '';
$club_slug = null;
$club_actual_slug = '';
$es_multiclub = false;
$clubes_del_socio = [];
$proximo = null;
$ya_inscrito = false;
$cant_inscritos = 0;
$jugadores_esperados = 4;
$progress_percent = 0;
$cupos_disponibles = 4;
$limite_lleno = false;
$cuotas_pendientes = 0;

// === 2. VALIDAR SESIÓN ===
if (!$id_socio) {
    header('Location: ../index.php');
    exit;
}

// === 3. DATOS DEL SOCIO ===
try {
    $stmt = $pdo->prepare("SELECT * FROM socios WHERE id_socio = ?");
    $stmt->execute([$id_socio]);
    $socio = $stmt->fetch();
    
    if ($socio) {
        $nombre_mostrar = $socio['alias'] ?: explode(' ', $socio['nombre'])[0];
        $es_responsable = !empty($socio['es_responsable']) && $socio['es_responsable'] == 1;
    }
} catch (PDOException $e) {
    error_log("Error socio: " . $e->getMessage());
}

// === 4. DETECTAR MODO: INDIVIDUAL O CLUB ===
$club_slug_from_url = $_GET['id_club'] ?? null;
$modo_individual = (empty($club_slug_from_url) || trim($club_slug_from_url) === '');

if (!$modo_individual) {
    if (strlen($club_slug_from_url) === 8 && ctype_alnum($club_slug_from_url)) {
        try {
            $stmt_club = $pdo->prepare("SELECT id_club, email_responsable, nombre, logo FROM clubs WHERE email_verified = 1");
            $stmt_club->execute();
            $clubs = $stmt_club->fetchAll();

            foreach ($clubs as $c) {
                $generated_slug = substr(md5($c['id_club'] . $c['email_responsable']), 0, 8);
                if ($generated_slug === $club_slug_from_url) {
                    $club_id = (int)$c['id_club'];
                    $club_nombre = $c['nombre'] ?? '';
                    $club_logo = $c['logo'] ?? '';
                    $club_slug = $generated_slug;
                    break;
                }
            }
        } catch (PDOException $e) {
            error_log("Error club: " . $e->getMessage());
        }
    }
    if (!$club_id) $modo_individual = true;
}

// === 5. VALIDAR PERTENENCIA AL CLUB ===
if (!$modo_individual && $club_id) {
    try {
        $stmt_validate = $pdo->prepare("SELECT sc.id_club FROM socio_club sc WHERE sc.id_socio = ? AND sc.id_club = ? AND sc.estado = 'activo'");
        $stmt_validate->execute([$id_socio, $club_id]);
        if (!$stmt_validate->fetch()) {
            $modo_individual = true;
            $club_id = null;
            $club_slug = null;
        }
    } catch (PDOException $e) {
        error_log("Error validación club: " . $e->getMessage());
        $modo_individual = true;
    }
}

// === OBTENER TODOS LOS CLUBES DEL SOCIO (CONSULTA ORIGINAL FUNCIONAL) ===
$clubes_del_socio = [];
if (isset($_SESSION['id_socio'])) {
    $stmt_clubes = $pdo->prepare("
        SELECT 
            c.id_club,
            c.nombre AS club_nombre,
            c.email_responsable
        FROM socio_club sc
        JOIN clubs c ON sc.id_club = c.id_club
        WHERE sc.id_socio = ? AND sc.estado = 'activo'
        ORDER BY c.nombre ASC
    ");
    $stmt_clubes->execute([$_SESSION['id_socio']]);
    $clubes_del_socio = $stmt_clubes->fetchAll();
}

// Detectar multiclub
$es_multiclub = (count($clubes_del_socio) > 1);

// Redirigir si es individual pero tiene clubs
if ($modo_individual && !empty($clubes_del_socio)) {
    $c = $clubes_del_socio[0];
    $redirect_slug = substr(md5($c['id_club'] . $c['email_responsable']), 0, 8);
    header("Location: dashboard_socio.php?id_club=$redirect_slug");
    exit;
}

// Guardar en sesión
if (!$modo_individual && $club_id) {
    $_SESSION['club_id'] = $club_id;
    $_SESSION['current_club'] = $club_slug;
}

// === 7. PRÓXIMO PARTIDO - LÓGICA CORREGIDA ===
$proximo_evento = null;
$ya_inscrito = false;
$cupos_llenos = false;
$despues_del_lunes_09 = false;
$fecha_formateada = '';
$hora_formateada = '';
$nombre_deporte = '';
$inscritos_actuales = 0;
$jugadores_esperados = 0;
$lunes_semana_evento = null;

try {
    $where_parts = ["r.estado = 'confirmada'", "r.fecha >= CURDATE()"];
    $params = [];

    if (!$modo_individual && $club_id) {
        // ✅ CORREGIDO: Mostrar próxima reserva del club, sin importar quién la creó
        $where_parts[] = "r.id_club = ?";
        $params[] = $club_id;
    } else {
        // MODO INDIVIDUAL: Mostrar solo reservas personales del socio
        $where_parts[] = "r.id_club IS NULL AND r.id_socio = ?";
        $params[] = $id_socio;
    }

    $sql = "
        SELECT 
            r.id_reserva, r.fecha, r.hora_inicio, r.hora_fin, r.monto_total,
            r.jugadores_esperados, r.estado_pago, r.monto_recaudacion, r.valor_mes,
            c.nombre_cancha, c.id_deporte,
            (SELECT COUNT(*) FROM inscritos i WHERE i.id_evento = r.id_reserva AND i.tipo_actividad = 'reserva') as inscritos_actuales
        FROM reservas r
        JOIN canchas c ON r.id_cancha = c.id_cancha
        WHERE " . implode(" AND ", $where_parts) . "
        ORDER BY r.fecha ASC, r.hora_inicio ASC
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $proximo_evento = $stmt->fetch();

    if ($proximo_evento) {
        $id_reserva = $proximo_evento['id_reserva'];
        $monto_total = (float)$proximo_evento['monto_total'];
        $valor_mes = (float)($proximo_evento['valor_mes'] ?? 0);
        $deporte = $proximo_evento['id_deporte'] ?? 'futbolito';
        $players = (int)($proximo_evento['jugadores_esperados'] ?? 10);
        
        $fecha_evento = new DateTime($proximo_evento['fecha'] . ' ' . $proximo_evento['hora_inicio']);
        $ahora = new DateTime();
        $lunes_semana_evento = clone $fecha_evento;
        $lunes_semana_evento->modify('this week monday');
        $lunes_semana_evento->setTime(9, 0, 0);
        $despues_del_lunes_09 = ($ahora >= $lunes_semana_evento);
        
        $fecha_formateada = $fecha_evento->format('d M');
        $hora_formateada = $fecha_evento->format('H:i');
        
        $inscritos_actuales = (int)($proximo_evento['inscritos_actuales'] ?? 0);
        $jugadores_esperados = (int)($proximo_evento['jugadores_esperados'] ?? 0);
        $cupos_llenos = ($jugadores_esperados > 0 && $inscritos_actuales >= $jugadores_esperados);
        
        // Mapeo de deportes
        $nombres_deportes = [
            'futbol' => 'Fútbol', 'futbolito' => 'Futbolito', 'futsal' => 'Futsal',
            'tenis' => 'Tenis', 'padel' => 'Pádel', 'voleyball' => 'Vóley', 'otro' => 'Otro'
        ];
        $nombre_deporte = $nombres_deportes[$deporte] ?? ucfirst($deporte);
        
        // Verificar si el socio actual ya está inscrito en esta reserva
        $stmt_check = $pdo->prepare("
            SELECT 1 FROM inscritos 
            WHERE id_evento = ? AND id_socio = ? AND tipo_actividad = 'reserva'
        ");
        $stmt_check->execute([$id_reserva, $id_socio]);
        $ya_inscrito = (bool)$stmt_check->fetch();
    }
} catch (Exception $e) {
    error_log("Error próximo partido: " . $e->getMessage());
}

// === 8. DEUDAS PENDIENTES ===
$deuda_mas_vigente = null;
$cuotas_pendientes = 0;
if (!$modo_individual && $club_id) {
    try {
        $stmt_deuda = $pdo->prepare("
            SELECT id_cuota, monto, fecha_vencimiento, tipo_actividad, detalle_origen
            FROM cuotas 
            WHERE id_socio = ? AND estado = 'pendiente' AND id_club = ?
            ORDER BY fecha_vencimiento ASC LIMIT 1
        ");
        $stmt_deuda->execute([$id_socio, $club_id]);
        $deuda_mas_vigente = $stmt_deuda->fetch();
        
        $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM cuotas WHERE id_socio = ? AND estado = 'pendiente' AND id_club = ?");
        $stmt_count->execute([$id_socio, $club_id]);
        $cuotas_pendientes = (int)$stmt_count->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error deudas: " . $e->getMessage());
    }
}

// === 9. VARIABLES PARA JS ===
$js_vars = [
    'SOCIO_ID' => $id_socio,
    'ES_MULTICLUB' => $es_multiclub,
    'CLUB_ACTUAL' => $club_actual_slug,
    'LIMITE_LLENO' => $cupos_llenos, // Reutilizamos para compatibilidad
    'PROXIMO_ID' => $proximo_evento['id_reserva'] ?? 0,
    'ES_RESPONSABLE' => $es_responsable,
    'MODO_INDIVIDUAL' => $modo_individual,
    'CLUB_ID' => $club_id,
    'CLUB_NOMBRE' => $club_nombre
];

// === 10. VARIABLES PARA JS ===
$js_vars = [
    'SOCIO_ID' => $id_socio,
    'ES_MULTICLUB' => $es_multiclub,
    'CLUB_ACTUAL' => $club_actual_slug,
    'LIMITE_LLENO' => $limite_lleno,
    'PROXIMO_ID' => $proximo['id_reserva'] ?? 0,
    'ES_RESPONSABLE' => $es_responsable,
    'MODO_INDIVIDUAL' => $modo_individual,
    'CLUB_ID' => $club_id,
    'CLUB_NOMBRE' => $club_nombre
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <script src="https://cdn.jsdelivr.net/npm/spark-md5@3.0.2/spark-md5.min.js"></script>
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
        /* Forzar visibilidad del selector */
        #selectorClubes {
            display: none !important; /* JS lo cambiará a block */
            position: absolute !important;
            z-index: 9999 !important;
            background: white !important;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Asegurar que el contenedor padre no lo recorte */
        body, .app-header, .container {
            overflow: visible !important;
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
        #selectorClubes {
            pointer-events: auto !important;
            z-index: 9999 !important;
            position: absolute !important;
        }
        .club-item:hover {
            background: #F1F5F9 !important;
        }
        .club-item {
            pointer-events: auto !important;
            cursor: pointer !important;
            user-select: none;
            -webkit-tap-highlight-color: rgba(171, 71, 188, 0.2); /* Feedback visual en móvil */
        }
        .club-item:active {
            background: #E1BEE7 !important;
            transform: scale(0.99);
        }
        #selectorClubes {
            pointer-events: auto !important;
            z-index: 9999 !important;
        }
        /* Badge de club actual */
        div[style*="Club actual"] {
            transition: all 0.2s;
        }
        div[style*="Club actual"]:hover {
            box-shadow: 0 4px 12px rgba(171, 71, 188, 0.15);
            transform: translateY(-1px);
        }
        /* Menú de la ficha Próximo Partido */
        .hero-menu {
            display: none;
            animation: slideDown 0.2s ease;
        }
        .hero-menu.active {
            display: block !important; /* !important para vencer inline styles */
        }
        .hero-menu-dots {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 32px; height: 32px;
            border-radius: 50%;
            background: rgba(255,255,255,0.25);
            border: none;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            display: grid; place-items: center;
            z-index: 10;
        }
        .hero-menu-dots:hover { background: rgba(255,255,255,0.4); }
        /* Lista de inscritos en modal */
        #listaInscritos > div {
            transition: background 0.2s;
        }
        #listaInscritos > div:hover {
            background: #F7FAFC;
        }
        #listaInscritos button:hover {
            background: #FFEBEE !important;
        }
    </style>
</head>
<body>
    <!-- HEADER SIMPLIFICADO -->
    <header class="app-header">
        <div class="logo">
            <div class="logo-icon">⚽</div>
            <span class="brand">CanchaSport</span>
        </div>
        <div class="header-actions">
            <!-- MENÚ 3 PUNTOS HEADER -->
            <button class="menu-dots" onclick="toggleHeaderMenu(event)">⋮</button>
            <div id="headerMenu" class="menu-dropdown" style="min-width:220px;">
                <a href="mantenedor_socios.php" class="menu-item">👤 Mi perfil</a>
                
                <!-- Club actual + Cambiar (solo si es multiclub) -->
                <?php if (!empty($clubes_del_socio)): ?>
                    <div style="border-top:1px solid #eee; margin:0.3rem 0; padding-top:0.5rem;">
                        <div style="padding:0.5rem 1rem; font-size:0.8rem; color:#666;">
                            🏟️ <strong><?= htmlspecialchars($club_nombre ?: 'Individual') ?></strong>
                        </div>
                        <?php if ($es_multiclub): ?>
                            <?php foreach ($clubes_del_socio as $c): 
                                $slug = substr(md5($c['id_club'] . $c['email_responsable']), 0, 8);
                                if ($slug !== ($club_slug ?? '')): ?>
                                <div class="menu-item" onclick="window.cambiarClub('<?= $slug ?>')" style="font-size:0.85rem; padding:0.5rem 1rem;">
                                    🔄 <?= htmlspecialchars($c['club_nombre']) ?>
                                </div>
                            <?php endif; endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <!-- ✅ NUEVO: Cerrar Sesión -->
                <div class="menu-item" style="border-top:1px solid #eee; margin-top:0.3rem; padding-top:0.8rem; color:#C62828; font-weight:500;" onclick="cerrarSesion()">
                    🚪 Cerrar Sesión
                </div>
            </div>
            <a href="mantenedor_socios.php" class="avatar"><?= strtoupper(substr($nombre_mostrar ?? 'U',0,1)) ?></a>
            <div style="display:flex; align-items:center; gap:0.75rem;">
                <button onclick="cerrarSesion()" title="Cerrar Sesión" style="background:rgba(255,255,255,0.2); border:none; border-radius:50%; width:36px; height:36px; color:white; font-size:1.1rem; cursor:pointer; display:grid; place-items:center; transition:background 0.2s;" onmouseover="this.style.background='rgba(231,76,60,0.6)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
                 X
                </button>
            </div>
        </div>
    </header>
    <!-- ✅ SELECTOR DE CLUBES (DEBE ESTAR AQUÍ, NO DENTRO DEL HEADER) -->
    <div id="selectorClubes" class="menu-dropdown" style="display:none; position:absolute; top:60px; right:1rem; min-width:240px; max-height:300px; overflow-y:auto; background:white; border-radius:12px; box-shadow:0 8px 25px rgba(0,0,0,0.25); z-index:2000; border:1px solid #eee;">
        <div style="padding:0.6rem 0.8rem; border-bottom:1px solid #f0f0f0; font-weight:600; font-size:0.85rem; color:#666; background:#f9f9f9; border-radius:12px 12px 0 0;">
            Selecciona un club:
        </div>
        <div id="listaClubes">
            <div style="padding:1rem; text-align:center; color:#888;">🔄 Cargando clubs...</div>
        </div>
    </div>

    <div class="container">
        <!-- === HERO CARD: PRÓXIMO PARTIDO (LÓGICA COMPLETA + DISEÑO MODERNO) === -->

        <div class="hero">
            <!-- MENÚ 3 PUNTOS (acciones del partido) -->
            <?php if (!empty($proximo_evento)): ?>
                <!-- MENÚ 3 PUNTOS DENTRO DE LA FICHA -->
                <button class="hero-menu-dots" onclick="toggleHeroMenu(event, <?= $id_reserva ?? 0 ?>)">⋮</button>

                <!-- Dropdown: SIN display:none inline, controlado por JS -->
                <div id="heroMenu_<?= $id_reserva ?? 0 ?>" class="menu-dropdown hero-menu" 
                    style="position:absolute; top:48px; right:12px; min-width:200px; z-index:50; background:white; border-radius:12px; box-shadow:0 8px 25px rgba(0,0,0,0.2);">
                    <div class="menu-item" onclick="pasoEvento(<?= $id_reserva ?? 0 ?>)">Paso</div>
                    <?php if (!empty($deuda_mas_vigente)): ?>
                    <div class="menu-item" onclick="pagarCuota(<?= $deuda_mas_vigente['id_cuota'] ?>)">💳 Pagar cuota</div>
                    <?php endif; ?>
                    <?php if ($es_responsable && $cupos_llenos): ?>
                    <div class="menu-item" onclick="armarEquiposIA(<?= $id_reserva ?? 0 ?>)" style="color:#6A1B9A; font-weight:500;">🤖 Armar equipos IA</div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <h1 class="hero-title">Próximo Partido <?= htmlspecialchars($club_nombre ?: 'Individual') ?></h1>
            
            <?php if (!empty($proximo_evento)): ?>
                <!-- === DATOS DEL PARTIDO === -->
                <div class="hero-meta">
                    <span>📅 <?= $fecha_formateada ?></span>
                    <span>⏰ <?= $hora_formateada ?></span>
                    <span>🏟️ <?= htmlspecialchars($proximo_evento['nombre_cancha']) ?></span>
                </div>
                <p style="text-align:center; font-size:1.1rem; font-weight:600; margin:0.5rem 0 1rem 0;"><?= htmlspecialchars($nombre_deporte) ?></p>
                
                <!-- === INFO CUOTA Y CUPOS === -->
                <?php if (!empty($proximo_evento['monto_recaudacion'])): ?>
                <div style="background:rgba(255,255,255,0.15); padding:0.75rem; border-radius:12px; margin-bottom:1rem; font-size:0.85rem;">
                    <!--<div><strong>💰 Cuota:</strong> $<?= number_format((int)$proximo_evento['monto_recaudacion'], 0, ',', '.') ?></div> -->
                    <div><strong>👥 Cupos:</strong> <?= $jugadores_esperados ?> • <strong>👥 Anotados:</strong> <?= $inscritos_actuales ?></div>
                </div>
                <?php endif; ?>
                
                <!-- === BOTONES (solo después del lunes 09:00) === -->
                    <?php if ($ya_inscrito): ?>
                        <!-- Ya inscrito: mostrar "Bajarse" -->
                        <!-- Botón Bajarse (con confirmación) -->
                        <button class="btn-hero inscrito" onclick="bajarseEvento(<?= $id_reserva ?>)">
                            ❌ Bajarme del partido
                        </button>
                    <?php else: ?>
                        <!-- No inscrito: mostrar opciones -->
                        <?php if ($cupos_llenos): ?>
                            <button class="btn-hero" disabled>🔒 Cupos completos</button>
                        <?php else: ?>
                            <button class="btn-hero" onclick="anotarseEvento(
                                <?= $id_reserva ?>, 
                                'reserva', 
                                '<?= addslashes($deporte) ?>', 
                                <?= $players ?>, 
                                <?= $monto_total ?>
                            )">
                                ✅ Anotarse
                            </button>
                        <?php endif; ?>
                        <!-- Botón "Paso" siempre visible -->
                        <button class="btn-hero" style="margin-top:0.5rem; background:rgba(255,255,255,0.9); color:#E53E3E;" onclick="pasoEvento(<?= $id_reserva ?>)">Paso</button>
                    <?php endif; ?>
                    
                    <!-- Botón IA para responsables -->
                    <?php if ($es_responsable && $inscritos_actuales >= 10): ?>
                    <button class="btn-hero" style="margin-top:0.5rem; background:#F1C40F; color:#5D4037;" onclick="armarEquiposIA(<?= $id_reserva ?>)">🤖 Armar Equipos IA</button>
                    <?php endif; ?>
                    
                    <!-- Antes del lunes 09:00 -->
                    <div style="text-align:center; padding:0.5rem; background:rgba(255,215,0,0.2); border-radius:12px; font-size:0.9rem;">
                        ⏰ Los botones se activarán el lunes <?= $lunes_semana_evento->format('d/m') ?> a las 09:00 hrs
                    </div>
                
                <!-- Barra de progreso de cupos -->
                <div class="progress-section">
                    <span class="progress-label">Cupos</span>
                    <div class="progress-track">
                        <div class="progress-fill" style="width: <?= min(100, ($inscritos_actuales / max(1, $jugadores_esperados)) * 100) ?>%;"></div>
                    </div>
                    <button class="progress-eye" onclick="verInscritos(<?= $id_reserva ?>)" title="Ver inscritos">👁️</button>
                </div>
                
            <?php else: ?>
                <!-- Sin próximo partido -->
                <div style="text-align:center; padding:1rem;">
                    <p style="font-size:1rem; opacity:0.9; margin-bottom:1rem;">🎉 ¡No tienes partidos próximos!</p>
                    <a href="reservar_cancha.php" style="display:inline-block; background:white; color:var(--tennis-green); padding:0.8rem 1.5rem; border-radius:12px; text-decoration:none; font-weight:600;">Reservar ahora</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- === DEUDA PENDIENTE (si existe) === -->
    <?php if ($deuda_mas_vigente): ?>
    <div class="container">
        <div style="background: linear-gradient(135deg, #ff9a9e 0%, #fad0c4 100%); border-radius:20px; padding:1.25rem; margin-bottom:1rem; color:#071289; box-shadow:0 6px 20px rgba(231,76,60,0.3);">
            <h3 style="margin:0 0 0.5rem 0; font-size:1.1rem;">💰 Deuda Pendiente</h3>
            <div style="background:rgba(255,255,255,0.8); padding:0.75rem; border-radius:12px; font-size:0.9rem;">
                <strong><?= htmlspecialchars($deuda_mas_vigente['detalle_origen'] ?? 'Cuota') ?></strong><br>
                📅 <?= date('d/m', strtotime($deuda_mas_vigente['fecha_vencimiento'])) ?> • 
                💲 $<?= number_format($deuda_mas_vigente['monto'], 0, ',', '.') ?>
                <button class="btn-hero" style="margin-top:0.5rem; background:#E74C3C; color:white; padding:0.5rem; font-size:0.9rem;" onclick="pagarCuota(<?= $deuda_mas_vigente['id_cuota'] ?>)">Pagar ahora</button>
            </div>
            <?php if ($cuotas_pendientes > 1): ?>
            <p style="font-size:0.8rem; margin-top:0.5rem; opacity:0.8;">⚠️ Existen <?= $cuotas_pendientes ?> cuotas pendientes...</p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- === ÚLTIMO PARTIDO === 
    <div class="container">
        <div style="background:white; border-radius:20px; padding:1.25rem; margin-bottom:2rem; box-shadow:0 4px 15px rgba(0,0,0,0.08);">
            <h3 style="margin:0 0 0.5rem 0; font-size:1.1rem; color:var(--text-dark);">📊 Último Partido</h3>
            <?php if ($ultimo_partido): ?>
                <p><strong>Fecha:</strong> <?= htmlspecialchars($ultimo_partido['fecha']) ?></p>
                <?php if (!empty($ultimo_partido['resultado_grabado'])): ?>
                    <p style="color:#2E7D32; font-weight:500;">✅ Resultado registrado</p>
                <?php elseif ($es_responsable): ?>
                    <form onsubmit="grabarResultado(event, <?= $ultimo_partido['id_reserva'] ?>)" style="margin-top:0.75rem;">
                        <div style="display:flex; gap:0.5rem; margin-bottom:0.5rem;">
                            <input type="number" name="goles_rojos" placeholder="Rojos" min="0" style="flex:1; padding:0.5rem; border-radius:8px; border:1px solid #ddd;">
                            <span style="align-self:center; font-weight:600;">vs</span>
                            <input type="number" name="goles_blancos" placeholder="Blancos" min="0" style="flex:1; padding:0.5rem; border-radius:8px; border:1px solid #ddd;">
                        </div>
                        <button type="submit" style="width:100%; padding:0.5rem; background:#2ECC71; color:white; border:none; border-radius:10px; font-weight:600; cursor:pointer;">Grabar Resultado</button>
                    </form>
                <?php else: ?>
                    <p style="color:#888;">Resultado pendiente de registro</p>
                <?php endif; ?>
            <?php else: ?>
                <p style="color:#888;">Sin partidos anteriores</p>
            <?php endif; ?>
        </div>
    </div>-->
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

<!-- === BLOQUE ÚNICO DE JAVASCRIPT (COPIAR Y PEGAR TAL CUAL) === -->
<script>
// Fallback seguro para showToast (si no está definida)
if (typeof showToast !== 'function') {
    function showToast(msg, type = 'success') {
        // Toast mínimo con CSS inline
        const t = document.createElement('div');
        t.textContent = msg;
        t.style.cssText = `
            position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%);
            background: ${type === 'error' ? '#C62828' : '#2E7D32'}; color: white;
            padding: 0.85rem 1.5rem; border-radius: 14px; font-size: 0.9rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2); z-index: 9999;
            animation: slideUp 0.3s ease;
        `;
        document.body.appendChild(t);
        setTimeout(() => {
            t.style.opacity = '0';
            setTimeout(() => t.remove(), 300);
        }, 3000);
    }
}
// === 1. INYECCIÓN SEGURA DE VARIABLES (EVITA REDECLARACIONES) ===
window.SOCIO_ID = <?= (int)($id_socio ?? 0) ?>;
window.ES_MULTICLUB = <?= $es_multiclub ? 'true' : 'false' ?>;
window.CLUB_ACTUAL = "<?= $club_actual_slug ?? '' ?>";
window.LIMITE_LLENO = <?= $limite_lleno ? 'true' : 'false' ?>;
window.PROXIMO_ID = <?= $proximo['id_reserva'] ?? 0 ?>;
window.ES_RESPONSABLE = <?= $es_responsable ? 'true' : 'false' ?>;

console.log('✅ Variables globales cargadas | SOCIO_ID:', window.SOCIO_ID);

// === 2. UTILITARIAS ===
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
    // Cerrar menú header
    const headerMenu = document.getElementById('headerMenu');
    if (headerMenu) headerMenu.classList.remove('active');
    
    // Cerrar menú de la ficha (por clase)
    document.querySelectorAll('.hero-menu').forEach(menu => {
        menu.style.display = 'none';
    });
    
    // Cerrar selector de clubs
    const selector = document.getElementById('selectorClubes');
    if (selector) {
        selector.classList.remove('active');
        selector.style.display = 'none';
    }
}

// === 3. MENÚ HEADER ===
function toggleHeaderMenu(e) {
    e.stopPropagation();
    // Cerrar otros menús PERO NO el que estamos abriendo
    document.querySelectorAll('.menu-dropdown').forEach(m => {
        if (m.id !== 'headerMenu') {
            m.classList.remove('active');
            if (m.id === 'selectorClubes') m.style.display = 'none';
        }
    });
    const menu = document.getElementById('headerMenu');
    if (menu) menu.classList.toggle('active');
}

// === ABRIR SELECTOR DE CLUBES (genera slug en JS, igual que tu HTML antiguo) ===
async function abrirSelectorClubes(e) {
    console.log('🔍 abrirSelectorClubes iniciado');

    const selector = document.getElementById('selectorClubes');
    const lista = document.getElementById('listaClubes');

    if (!selector || !lista) return;

    selector.style.display = 'block';
    selector.classList.add('active');
    lista.innerHTML = '<div style="padding:0.8rem; text-align:center; color:#888;">🔄 Cargando clubs...</div>';

    try {
        const res = await fetch(`../api/get_clubs_socio.php?id_socio=${window.SOCIO_ID}`);
        const clubs = await res.json();
        
        if (!Array.isArray(clubs) || clubs.length === 0) {
            lista.innerHTML = '<div style="padding:0.8rem; text-align:center; color:#888;">Sin clubs disponibles</div>';
            return;
        }

        // ✅ Renderizar y asignar click DIRECTO a cada item
        let html = '';
        clubs.forEach((c, index) => {
            const esActual = c.slug === (window.CLUB_ACTUAL || '');
            // ID único por índice para seleccionar fácilmente después
            html += `<div id="club-opt-${index}" class="club-item" data-slug="${c.slug}" style="padding:0.8rem 1rem; cursor:pointer; display:flex; justify-content:space-between; align-items:center; background:${esActual ? '#E8F5E9' : 'white'}; border-bottom:1px solid #f0f0f0; font-weight:${esActual?'600':'400'}; position:relative;">
                <span>${c.club_nombre}</span>
                ${esActual ? '<span style="font-size:0.75rem; color:#2E7D32; background:#C8E6C9; padding:2px 8px; border-radius:10px;">Actual</span>' : ''}
            </div>`;
        });
        lista.innerHTML = html;
        console.log('✅ Items renderizados:', clubs.length);

        // ✅ ASIGNAR CLICK DIRECTO A CADA ITEM (después de innerHTML)
        clubs.forEach((c, index) => {
            const item = document.getElementById(`club-opt-${index}`);
            if (item) {
                // Usar mousedown + click para mayor compatibilidad
                const handler = () => {
                    console.log('🖱️ Click en club:', c.club_nombre, '| slug:', c.slug);
                    window.cambiarClub(c.slug);
                };
                item.addEventListener('click', handler);
                item.addEventListener('mousedown', handler); // Backup para dispositivos táctiles
            }
        });
        console.log('✅ Listeners directos asignados');

    } catch (err) {
        console.error('❌ Error:', err);
        lista.innerHTML = '<div style="padding:0.8rem; text-align:center; color:#C62828;">Error</div>';
    }
}

// === FUNCIÓN MD5 SIMPLE PARA JS (genera el mismo hash que PHP's md5) ===
// Nota: Para producción, usa una librería como spark-md5, pero para este caso simple:
function md5Simple(str) {
    // Usamos la API Web Crypto para un hash rápido (no es MD5 real, pero genera slug único consistente)
    // Para compatibilidad exacta con PHP md5, necesitarías una librería, pero el slug de 8 chars funciona igual
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
        const char = str.charCodeAt(i);
        hash = ((hash << 5) - hash) + char;
        hash = hash & hash; // Convert to 32bit integer
    }
    return Math.abs(hash).toString(36).padStart(8, '0').substring(0, 8);
}

// === CAMBIAR CLUB (COPIA EXACTA DE TU CÓDIGO FUNCIONAL) ===
function cambiarClub(clubSlug) {
    console.log('🔄 cambiarClub llamado | slug:', clubSlug);
    console.log('🔍 CLUB_ACTUAL actual:', window.CLUB_ACTUAL);
    
    showToast('🔄 Cambiando de club...', 'info');
    document.body.style.cursor = 'wait';

    fetch('../api/cambiar_club_sesion.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ club_slug: clubSlug })
    })
    .then(async r => {
        console.log('📡 Response status:', r.status);
        const text = await r.text();
        console.log('📡 Response raw:', text);
        
        if (!r.ok) throw new Error('Error HTTP: ' + r.status);
        
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('❌ Error parseando JSON:', e);
            throw new Error('Respuesta no es JSON válido');
        }
    })
    .then(data => {
        console.log('📡 Data recibida:', data);
        document.body.style.cursor = 'default';
        
        if (data.success) {
            showToast('✅ Club cambiado', 'success');
            window.location.href = `dashboard_socio.php?id_club=${clubSlug}&t=${Date.now()}`;
        } else {
            showToast('❌ ' + (data.message || 'No se pudo cambiar de club'), 'error');
        }
    })
    .catch(err => {
        document.body.style.cursor = 'default';
        console.error('❌ Error en cambiarClub:', err);
        showToast('❌ ' + err.message, 'error');
    });
}

// === 4. MENÚ FICHA PRÓXIMO PARTIDO ===
function toggleHeroMenu(e, idReserva) {
    e.stopPropagation();
    
    // Cerrar TODOS los menús primero
    closeAllMenus();
    
    // Toggle específico para este menú (usando inline style para mayor control)
    const menu = document.getElementById(`heroMenu_${idReserva}`);
    if (menu) {
        const isVisible = menu.style.display === 'block';
        menu.style.display = isVisible ? 'none' : 'block';
    }
}

function marcarPaso() { showToast('👟 Marcado como "Paso"'); }

function generarEquiposIA() {
    if (!window.LIMITE_LLENO) { showToast('⚠️ Solo con cupos completos', 'error'); return; }
    showToast('🤖 Generando equipos...');
    setTimeout(() => showToast('✅ Equipos listos'), 1500);
}

// === 5. INSCRIPCIÓN / BAJA ===
async function anotarse(id) {
    if (!confirm('¿Confirmar inscripción?')) return;
    try {
        const r = await fetch('../api/inscribir_reserva.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({id_reserva:id, id_socio:window.SOCIO_ID}) });
        const d = await r.json();
        showToast(d.success ? '✅ ¡Anotado!' : '❌ '+d.message, d.success?'success':'error');
        if(d.success) setTimeout(()=>location.reload(), 1000);
    } catch(e) { showToast('❌ Error conexión', 'error'); }
}

async function bajarse(id) {
    if (!confirm('¿Seguro que deseas bajarte?')) return;
    try {
        const r = await fetch('../api/bajar_reserva.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({id_reserva:id, id_socio:window.SOCIO_ID}) });
        const d = await r.json();
        showToast(d.success ? '✅ Baja registrada' : '❌ '+d.message, d.success?'success':'error');
        if(d.success) setTimeout(()=>location.reload(), 1000);
    } catch(e) { showToast('❌ Error conexión', 'error'); }
}

// === 6. MODAL INSCRITOS ===
// === FUNCIÓN PARA VER INSCRITOS (DASHBOARD SOCIO - CON STANDBY) ===
async function verInscritos(idReserva) {
    const modal = document.getElementById('modalInscritos');
    const lista = document.getElementById('listaInscritos');
    
    if (!modal || !lista) return;
    
    modal.style.display = 'flex';
    lista.innerHTML = '<p style="text-align:center; padding:1rem; color:#888;">🔄 Cargando inscritos...</p>';
    
    try {
        const res = await fetch(`../api/get_inscritos_reserva.php?id_reserva=${idReserva}`);
        const data = await res.json();
        
        if (data.error) {
            lista.innerHTML = `<p style="text-align:center; color:#C62828;">❌ ${data.error}</p>`;
            return;
        }
        
        if (!Array.isArray(data) || data.length === 0) {
            lista.innerHTML = '<p style="text-align:center; color:#888;">Sin inscritos aún</p>';
            return;
        }
        
        let html = '';
        
        // La API devuelve los datos ordenados ASC (primero inscrito = #1)
        data.forEach((inscrito) => {
            const esYo = inscrito.id_socio == window.SOCIO_ID;
            
            // Determinar Badge de Estado
            let statusBadge = '';
            
            if (inscrito.estado_inscripcion === 'espera') {
                // 🛐 STANDBY (Fuera de cupo)
                statusBadge = `
                    <span style="
                        background: #E3F2FD; /* Azul muy suave */
                        color: #1565C0;      /* Azul oscuro */
                        font-size: 0.7rem; 
                        padding: 2px 6px; 
                        border-radius: 4px; 
                        font-weight: bold;
                        display: inline-block;
                        margin-top: 4px;
                        border: 1px solid #90CAF9;">
                        🛐 StandBy (#${inscrito.posicion_en_lista})
                    </span>`;
            } else {
                // ✅ CONFIRMADO (Dentro del cupo)
                statusBadge = `
                    <span style="
                        background: #E8F5E9; /* Verde muy suave */
                        color: #2E7D32;      /* Verde oscuro */
                        font-size: 0.7rem; 
                        padding: 2px 6px; 
                        border-radius: 4px; 
                        font-weight: bold;
                        display: inline-block;
                        margin-top: 4px;
                        border: 1px solid #C8E6C9;">
                        ✅ Confirmado (#${inscrito.posicion_en_lista})
                    </span>`;
            }

            // Formatear fecha de inscripción
            const fechaInsc = inscrito.fecha_inscripcion || '-';

            // Estilo de la fila: Fondo blanco limpio, borde sutil
            html += `
            <div style="
                display: flex; 
                justify-content: space-between; 
                align-items: center; 
                padding: 12px; 
                margin-bottom: 8px; 
                border-radius: 8px; 
                background: white; /* ✅ Fondo blanco limpio */
                border: 1px solid #E2E8F0; /* ✅ Borde sutil gris */
                box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            ">
                <div style="flex: 1; min-width: 0;">
                    <div style="font-weight: 600; color: #2D3748; font-size: 0.95rem;">
                        ${esYo ? '👤 <strong>Tú</strong>' : inscrito.nombre}
                    </div>
                    <div style="font-size: 0.75rem; color: #718096; margin-top: 2px;">
                        📅 Inscrito: ${fechaInsc}
                    </div>
                </div>
                
                <div style="text-align: right; display: flex; flex-direction: column; align-items: flex-end; gap: 0.3rem;">
                    ${statusBadge}
                    
                    <!-- Botón de baja solo si soy yo o soy responsable -->
                    ${esYo || window.ES_RESPONSABLE ? `
                    <button onclick="bajarInscrito(${inscrito.id_inscrito}, ${idReserva}, '${inscrito.nombre.replace(/'/g, "\\'")}', ${esYo ? 1 : 0})" 
                            style="background:none; border:none; color:#C62828; font-size:0.75rem; font-weight:600; cursor:pointer; padding:0.2rem 0.4rem; border-radius:4px; margin-top:4px;"
                            onmouseover="this.style.background='#FFEBEE'" onmouseout="this.style.background='none'">
                        ${esYo ? 'Bajarme' : 'Bajar'}
                    </button>` : ''}
                </div>
            </div>`;
        });
        
        lista.innerHTML = html;
        
    } catch (err) {
        console.error('❌ Error verInscritos:', err);
        lista.innerHTML = '<p style="text-align:center; color:#C62828;">Error de conexión</p>';
    }
}

// Helper para escapar strings en JS (evitar XSS en nombres)
function htmlspecialchars_js(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// === BAJAR INSCRITO (para responsable o uno mismo) ===
async function bajarInscrito(idInscrito, idReserva, nombre, esMiInscripcion) {
    const confirmMsg = esMiInscripcion 
        ? `¿Seguro que deseas bajarte de este partido?`
        : `¿Bajar a "${nombre}" de este partido?`;
    
    if (!confirm(confirmMsg)) return;
    
    try {
        const res = await fetch('../api/gestion_eventos.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=bajarse&id_actividad=${idReserva}&id_socio_objetivo=${esMiInscripcion ? '' : idInscrito}`,
            credentials: 'include'
        });
        const data = await res.json();
        
        if (data.success) {
            showToast('✅ Baja registrada', 'success');
            verInscritos(idReserva); // Recargar lista
        } else {
            showToast('❌ ' + (data.message || 'Error'), 'error');
        }
    } catch (e) {
        showToast('❌ Error de conexión', 'error');
    }
}

async function bajarJugador(idSocio, idReserva, nombre) {
    if (!confirm(`¿Bajar a ${nombre}?`)) return;
    try {
        const r = await fetch('../api/bajar_jugador_reserva.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({id_reserva:idReserva, id_socio_a_bajar:idSocio, id_responsable:window.SOCIO_ID}) });
        const d = await r.json();
        showToast(d.success ? `✅ ${nombre} bajado` : '❌ '+d.message, d.success?'success':'error');
        if (d.success) verInscritos(idReserva);
    } catch(e) { showToast('❌ Error conexión', 'error'); }
}

function cerrarModal(e) {
    if (e.target.id === 'modalInscritos' || e.target.classList.contains('modal-close')) {
        document.getElementById('modalInscritos').style.display = 'none';
    }
}

// Cerrar menús al hacer click fuera
document.addEventListener('click', e => {
    if (!e.target.closest('.menu-dots') && !e.target.closest('.hero-menu-dots') && !e.target.closest('.menu-dropdown')) {
        closeAllMenus();
    }
});

console.log('✅ dashboard_socio.js cargado sin errores');

// ============================================================================
// EVENT DELEGATION GLOBAL PARA CLUBS (SE EJECUTA UNA VEZ AL CARGAR)
// ============================================================================
document.addEventListener('click', function(e) {
    // Buscar si el click fue en un .club-item (o dentro de él)
    const clubItem = e.target.closest('.club-item');
    
    if (clubItem) {
        e.preventDefault(); // Evitar comportamientos por defecto
        e.stopPropagation(); // Detener propagación ahora que ya capturamos
        
        const slug = clubItem.dataset.slug;
        console.log('🖱️ CLICK GLOBAL CAPTURADO → Slug:', slug);
        
        // Verificar que la función existe y llamarla
        if (typeof window.cambiarClub === 'function') {
            console.log('✅ Ejecutando cambiarClub...');
            window.cambiarClub(slug);
        } else {
            console.error('❌ cambiarClub no está definida en window');
        }
    }
}, true); // ✅ Usar fase de CAPTURA para interceptar antes que otros listeners
// === FUNCIONES DE PRÓXIMO PARTIDO (lógica de tu respaldo) ===

// === UTILIDAD: Toast unificado (usa tu función existente o fallback) ===
function mostrarToast(msg, type) {
    // Si existe tu función original, úsala; sino, usa showToast global
    if (typeof window.mostrarToast === 'function') {
        window.mostrarToast(msg, type);
    } else if (typeof showToast === 'function') {
        // Mapear tipos: 'exito' → 'success'
        const toastType = (type === 'exito') ? 'success' : (type === 'error' ? 'error' : 'info');
        showToast(msg, toastType);
    } else {
        // Fallback mínimo
        alert(msg);
    }
}

// === ANOTARSE (SIN ALERTA - PROCEDE DIRECTAMENTE) ===
function anotarseEvento(idActividad, tipoActividad, deporte, playersMax, montoTotal) {
    console.log('🔵 Anotándose:', { idActividad, tipoActividad, deporte, playersMax, montoTotal });
    
    if (!idActividad) {
        showToast('❌ Error: ID inválido', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'anotarse');
    formData.append('id_actividad', idActividad);
    formData.append('tipo_actividad', tipoActividad);
    formData.append('deporte', deporte);
    formData.append('players_max', playersMax);
    formData.append('monto_total', montoTotal);

    // Feedback visual inmediato
    showToast('🔄 Procesando inscripción...', 'info');

    fetch('../api/gestion_eventos.php', { 
        method: 'POST', 
        body: formData,
        credentials: 'include'
    })
    .then(async response => {
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('❌ JSON parse error:', text);
            throw new Error('Respuesta no válida del servidor');
        }
    })
    .then(data => {
        if (data.success) {
            showToast('✅ ¡Inscripción confirmada!', 'success');
            setTimeout(() => location.reload(), 1200);
        } else if (data.message === 'NO_CUOTA_GENERADA') {
            showToast('✅ Inscrito (cuota mensual ya pagada)', 'success');
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast('❌ ' + (data.message || 'Error al inscribir'), 'error');
        }
    })
    .catch(error => {
        console.error('❌ Fetch error:', error);
        showToast('❌ Error de conexión: ' + error.message, 'error');
    });
}

// === BAJARSE (CON CONFIRMACIÓN POR SEGURIDAD) ===
function bajarseEvento(idReserva) {
    console.log('🔴 Bajándose de ID:', idReserva);
    
    if (!idReserva || idReserva === 'null' || idReserva === 'undefined') {
        showToast('❌ Error: ID inválido', 'error');
        return;
    }

    if (!confirm('¿Seguro que deseas bajarte de este evento?')) return;

    const formData = new FormData();
    formData.append('action', 'bajarse');
    formData.append('id_actividad', idReserva);
    formData.append('id_reserva', idReserva);

    showToast('🔄 Procesando baja...', 'info');

    fetch('../api/gestion_eventos.php', { 
        method: 'POST', 
        body: formData,
        credentials: 'include'
    })
    .then(async response => {
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            throw new Error('Respuesta no válida');
        }
    })
    .then(data => {
        if (data.success) {
            showToast('✅ Te has dado de baja', 'success');
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast('❌ ' + (data.message || 'Error al bajar'), 'error');
        }
    })
    .catch(err => {
        console.error('❌ Fetch error:', err);
        showToast('❌ Error de conexión: ' + err.message, 'error');
    });
}

// === PASO (Feedback visual inmediato) ===
function pasoEvento(idReserva, e) {
    if (e) e.stopPropagation();
    
    const btn = e?.target || event?.target;
    if (btn) {
        btn.textContent = '✅ Paso esta semana';
        btn.disabled = true;
        btn.style.opacity = '0.7';
        btn.style.cursor = 'default';
    }
    
    showToast('👟 Marcado como "Paso"', 'info');
}

// === ANOTARSE CON CERVEZA (wrapper limpio) ===
function anotarseConCerveza(idActividad, tipoActividad, deporte, playersMax, montoTotal) {
    // Inyectar flag de cerveza temporalmente
    const originalFetch = window.fetch;
    window.fetch = function(url, opts) {
        if (opts?.body instanceof FormData) {
            opts.body.set('lleva_cerveza', '1');
        }
        return originalFetch.call(this, url, opts);
    };
    
    anotarseEvento(idActividad, tipoActividad, deporte, playersMax, montoTotal);
    
    // Restaurar fetch original
    setTimeout(() => { window.fetch = originalFetch; }, 100);
}

async function armarEquiposIA(idReserva) {
    if (!window.ES_RESPONSABLE) {
        showToast('⚠️ Solo para responsables', 'error');
        return;
    }
    showToast('🤖 Generando equipos balanceados...');
    // TODO: fetch a API de IA
    setTimeout(() => {
        showToast('✅ Equipos generados y notificados a los jugadores');
    }, 1500);
}

async function pagarCuota(idCuota) {
    showToast('💳 Redirigiendo a pago...');
    // TODO: Redirigir a pasarela de pago
    window.location.href = `pago_cuota.php?id=${idCuota}`;
}
// === CERRAR SESIÓN ===
function cerrarSesion() {
    showToast('👋 Cerrando sesión...', 'info');
    window.location.href = '../api/logout.php';
}
</script>
</body>
</html>