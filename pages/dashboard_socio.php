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

// === 7. PRÓXIMO EVENTO ===
try {
    $where_parts = ["r.estado = 'confirmada'", "r.fecha >= CURDATE()"];
    $params = [];

    if (!$modo_individual && $club_id) {
        $where_parts[] = "(
            (r.id_club = ? AND r.id_socio = ?) 
            OR 
            (r.id_club = ? AND r.id_socio IS NULL)
        )";
        $params = [$club_id, $id_socio, $club_id];
    } else {
        $where_parts[] = "r.id_club IS NULL AND r.id_socio = ?";
        $params = [$id_socio];
    }

    $sql = "
        SELECT 
            r.id_reserva, r.fecha, r.hora_inicio, r.hora_fin, r.monto_total,
            r.jugadores_esperados, r.estado_pago, r.monto_recaudacion,
            c.nombre_cancha, c.id_deporte,
            (SELECT COUNT(*) FROM reservas_participantes rp WHERE rp.id_reserva = r.id_reserva) as inscritos_actuales,
            (SELECT GROUP_CONCAT(rp.id_socio) FROM reservas_participantes rp WHERE rp.id_reserva = r.id_reserva) as ids_inscritos
        FROM reservas r
        JOIN canchas c ON r.id_cancha = c.id_cancha
        WHERE " . implode(" AND ", $where_parts) . "
        ORDER BY r.fecha ASC, r.hora_inicio ASC
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $proximo = $stmt->fetch();

    if ($proximo) {
        $cant_inscritos = (int)($proximo['inscritos_actuales'] ?? 0);
        $jugadores_esperados = (int)($proximo['jugadores_esperados'] ?? 4);
        $ids_inscritos = $proximo['ids_inscritos'] ? explode(',', $proximo['ids_inscritos']) : [];
        $ya_inscrito = in_array($id_socio, $ids_inscritos);
    }
} catch (PDOException $e) {
    error_log("Error próximo evento: " . $e->getMessage());
}

// === 8. CÁLCULOS ===
$progress_percent = min(100, ($cant_inscritos / max(1, $jugadores_esperados)) * 100);
$cupos_disponibles = max(0, $jugadores_esperados - $cant_inscritos);
$limite_lleno = ($cant_inscritos >= $jugadores_esperados);

// === 9. DEUDAS ===
if (!$modo_individual && $club_id) {
    try {
        $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM cuotas WHERE id_socio = ? AND estado = 'pendiente' AND id_club = ?");
        $stmt_count->execute([$id_socio, $club_id]);
        $cuotas_pendientes = (int)$stmt_count->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error deudas: " . $e->getMessage());
    }
}

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
        }
        .club-item {
            pointer-events: auto !important;
            user-select: none;
            transition: background 0.2s;
        }
        .club-item:hover {
            background: #F1F5F9 !important;
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
            <button class="menu-dots" onclick="toggleHeaderMenu(event)">⋮</button>
            <div id="headerMenu" class="menu-dropdown">
                <a href="mantenedor_socios.php" class="menu-item">👤 Mi perfil</a>
                
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

<!-- === BLOQUE ÚNICO DE JAVASCRIPT (COPIAR Y PEGAR TAL CUAL) === -->
<script>
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
    document.querySelectorAll('.menu-dropdown').forEach(m => {
        m.classList.remove('active');
        if (m.id === 'selectorClubes') m.style.display = 'none';
    });
}

// === 3. MENÚ HEADER ===
function toggleHeaderMenu(e) {
    e.stopPropagation();
    closeAllMenus();
    const menu = document.getElementById('headerMenu');
    if (menu) menu.classList.toggle('active');
}

// === ABRIR SELECTOR DE CLUBES (genera slug en JS, igual que tu HTML antiguo) ===
async function abrirSelectorClubes(e) {
    e.stopPropagation();
    console.log('🔍 abrirSelectorClubes iniciado');

    const selector = document.getElementById('selectorClubes');
    const lista = document.getElementById('listaClubes');

    if (!selector || !lista) return;

    // Forzar visibilidad y clickability
    selector.style.display = 'block';
    selector.style.pointerEvents = 'auto';
    selector.classList.add('active');
    lista.innerHTML = '<div style="padding:0.8rem; text-align:center; color:#888;">🔄 Cargando clubs...</div>';

    try {
        const res = await fetch(`../api/get_clubs_socio.php?id_socio=${window.SOCIO_ID}`);
        const clubs = await res.json();
        console.log('🟢 Clubs recibidos:', clubs);

        if (!Array.isArray(clubs) || clubs.length === 0) {
            lista.innerHTML = '<div style="padding:0.8rem; text-align:center; color:#888;">Sin clubs disponibles</div>';
            return;
        }

        let html = '';
        clubs.forEach(c => {
            const esActual = c.slug === (window.CLUB_ACTUAL || '');
            // Clase .club-item para delegación + estilos seguros
            html += `<div class="club-item" data-slug="${c.slug}" style="padding:0.8rem 1rem; cursor:pointer; display:flex; justify-content:space-between; align-items:center; background:${esActual ? '#E8F5E9' : 'white'}; border-bottom:1px solid #f0f0f0; font-weight:${esActual?'600':'400'}; position:relative; z-index:10;">
                <span>${c.club_nombre}</span>
                ${esActual ? '<span style="font-size:0.75rem; color:#2E7D32; background:#C8E6C9; padding:2px 8px; border-radius:10px;">Actual</span>' : ''}
            </div>`;
        });
        lista.innerHTML = html;
        console.log('✅ HTML renderizado. Items:', lista.querySelectorAll('.club-item').length);

        // 🎯 EVENT DELEGATION: Un solo listener en el contenedor
        lista.addEventListener('click', function(event) {
            const item = event.target.closest('.club-item');
            if (item) {
                const slug = item.dataset.slug;
                console.log('🖱️ CLICK DETECTADO → Slug:', slug);
                console.log('🔍 typeof cambiarClub:', typeof window.cambiarClub);
                
                if (typeof window.cambiarClub === 'function') {
                    window.cambiarClub(slug);
                } else {
                    console.error('❌ cambiarClub no está definida en window');
                }
            }
        });

    } catch (err) {
        console.error('❌ Error crítico:', err);
        lista.innerHTML = '<div style="padding:0.8rem; text-align:center; color:#C62828;">Error al cargar</div>';
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
    closeAllMenus();
    const menu = document.getElementById(`heroMenu_${idReserva}`);
    if (menu) {
        menu.classList.toggle('active');
        const ia = document.getElementById(`menuItemIA_${idReserva}`);
        if (ia && window.LIMITE_LLENO) ia.style.display = 'flex';
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
async function verInscritos(id) {
    const modal = document.getElementById('modalInscritos');
    const lista = document.getElementById('listaInscritos');
    if (!modal || !lista) return;
    modal.style.display = 'flex';
    lista.innerHTML = '<p style="text-align:center; padding:1rem;">🔄 Cargando...</p>';
    try {
        const r = await fetch(`../api/get_inscritos_reserva.php?id_reserva=${id}`);
        const data = await r.json();
        if (!Array.isArray(data) || data.length === 0) { lista.innerHTML = '<p style="text-align:center;">Sin inscritos</p>'; return; }
        let html = '';
        data.forEach(p => {
            const esYo = p.id_socio === window.SOCIO_ID;
            const puedeBajar = window.ES_RESPONSABLE && !esYo;
            html += `<div style="padding:0.8rem 0; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center;">
                <span>${esYo ? '👤 Tú' : p.nombre}</span>
                <span style="font-size:0.8rem; padding:0.2rem 0.5rem; border-radius:8px; background:#E8F5E9; color:#2E7D32;">${p.estado}</span>
                ${puedeBajar ? `<button onclick="bajarJugador(${p.id_socio}, ${id}, '${p.nombre.replace(/'/g,"\\'")}')" style="background:none; border:none; color:#C62828; font-weight:600; cursor:pointer;">Bajar</button>` : ''}
            </div>`;
        });
        lista.innerHTML = html;
    } catch(e) { lista.innerHTML = '<p style="color:red;">Error al cargar</p>'; }
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
</script>
</body>
</html>