<?php
    // pages/dashboard_socio.php

    // 1. Incluir config PRIMERO (esto inicia la sesión correctamente)
    require_once __DIR__ . '/../includes/config.php';

    // 2. Validar que el usuario sea socio (no admin de recinto)
    if (!isset($_SESSION['id_socio'])) {
        // Si no hay sesión de socio, redirigir al login
        header('Location: ../index.php');
        exit;
    }

    // 3. Obtener datos del socio
    $id_socio = $_SESSION['id_socio'];
    $stmt = $pdo->prepare("SELECT * FROM socios WHERE id_socio = ?");
    $stmt->execute([$id_socio]);
    $socio = $stmt->fetch();

    if (!$socio) {
        session_destroy();
        header('Location: ../index.php');
        exit;
    }
    // === DETECTAR MODO INDIVIDUAL O CLUB ===
    $club_slug_from_url = $_GET['id_club'] ?? null;
    $modo_individual = ($club_slug_from_url === null || trim($club_slug_from_url) === '');
    $pareja_activa = null;

    if (!$modo_individual) {
        if (strlen($club_slug_from_url) !== 8 || !ctype_alnum($club_slug_from_url)) {
            error_log("❌ Slug inválido, redirigiendo a index.php");
            header('Location: ../index.php');
            exit;
        }

        // Buscar club
        $stmt_club = $pdo->prepare("SELECT id_club, email_responsable, nombre, logo FROM clubs WHERE email_verified = 1");
        $stmt_club->execute();
        $clubs = $stmt_club->fetchAll();

        $club_id = null;
        $club_nombre = '';
        $club_logo = '';
        $club_slug = null;

        foreach ($clubs as $c) {
            $generated_slug = substr(md5($c['id_club'] . $c['email_responsable']), 0, 8);
            if ($generated_slug === $club_slug_from_url) {
                $club_id = (int)$c['id_club'];
                $club_nombre = $c['nombre'];
                $club_logo = $c['logo'] ?? '';
                $club_slug = $generated_slug;
                break;
            }
        }

        if (!$club_id) {
            error_log("❌ Club no encontrado, redirigiendo a index.php");
            header('Location: ../index.php');
            exit;
        }
        error_log("✅ Club cargado: " . $club_nombre);
    } else {
        error_log("✅ Modo individual detectado");
        $club_id = null;
        $club_nombre = '';
        $club_logo = null;
        $club_slug = null;
    }

    // === OBTENCIÓN DE ID_SOCIO (CORREGIDO) ===
    $id_socio = null;
    $socio_actual = null;

    if (isset($_SESSION['id_socio'])) {
        $id_socio = $_SESSION['id_socio'];
        
        if ($modo_individual) {
            $stmt_validate = $pdo->prepare("SELECT * FROM socios WHERE id_socio = ?");
            $stmt_validate->execute([$id_socio]);
        } else {
            $stmt_validate = $pdo->prepare("
                SELECT s.*
                FROM socios s
                JOIN socio_club sc ON s.id_socio = sc.id_socio
                WHERE s.id_socio = ? AND sc.id_club = ? AND sc.estado = 'activo'
            ");
            $stmt_validate->execute([$id_socio, $club_id]);
        }
        
        $socio_actual = $stmt_validate->fetch();
        if (!$socio_actual) {
            $id_socio = null;
            unset($_SESSION['id_socio']);
        }
    }

    if (!$id_socio) {
        $user_email = null;
        if (isset($_SESSION['google_email'])) {
            $user_email = $_SESSION['google_email'];
        } elseif (isset($_SESSION['user_email'])) {
            $user_email = $_SESSION['user_email'];
        }
        
        if ($user_email) {
            if ($modo_individual) {
                $stmt_socio = $pdo->prepare("
                    SELECT s.id_socio 
                    FROM socios s
                    LEFT JOIN socio_club sc ON s.id_socio = sc.id_socio AND sc.estado = 'activo'
                    WHERE s.email = ? AND sc.id_socio IS NULL
                ");
                $stmt_socio->execute([$user_email]);
            } else {
                $stmt_socio = $pdo->prepare("
                    SELECT s.id_socio
                    FROM socios s
                    JOIN socio_club sc ON s.id_socio = sc.id_socio
                    WHERE s.email = ? AND sc.id_club = ? AND sc.estado = 'activo'
                ");
                $stmt_socio->execute([$user_email, $club_id]);
            }
            
            $socio_data = $stmt_socio->fetch();
            if ($socio_data) {
                $id_socio = $socio_data['id_socio'];
                $_SESSION['id_socio'] = $id_socio;
            } else {
                // Redirigir a completar perfil
                if ($modo_individual) {
                    header('Location: completar_perfil.php?modo=individual');
                } else {
                    header('Location: completar_perfil.php?id=' . $club_slug);
                }
                exit;
            }
        } else {
            header('Location: ../index.php');
            exit;
        }
    } else {
        $_SESSION['id_socio'] = $id_socio;
    }

    // 3. Si aún no hay id_socio, redirigir a completar perfil
    if (!$id_socio) {
        if ($modo_individual) {
            header('Location: completar_perfil.php?modo=individual');
        } else {
            header('Location: completar_perfil.php?id=' . $club_slug);
        }
        exit;
    }

    // 4. Asegurar en sesión para uso posterior
    $_SESSION['id_socio'] = $id_socio;

    // Guardar en cookie para compatibilidad con Railway/FrankenPHP
    setcookie('cancha_id_socio', $_SESSION['id_socio'], [
        'expires' => time() + 86400,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    // Asegurar socio_actual
    if (!$socio_actual) {
        if ($modo_individual) {
            $stmt_fallback = $pdo->prepare("
                SELECT s.*
                FROM socios s
                LEFT JOIN socio_club sc ON s.id_socio = sc.id_socio AND sc.estado = 'activo'
                WHERE s.id_socio = ? AND sc.id_socio IS NULL
                LIMIT 1
            ");
            $stmt_fallback->execute([$_SESSION['id_socio']]);
        } else {
            $stmt_fallback = $pdo->prepare("
                SELECT s.*
                FROM socios s
                JOIN socio_club sc ON s.id_socio = sc.id_socio
                WHERE s.id_socio = ? AND sc.id_club = ? AND sc.estado = 'activo'
                LIMIT 1
            ");
            $stmt_fallback->execute([$_SESSION['id_socio'], $club_id]);
        }
        $socio_actual = $stmt_fallback->fetch() ?: ['datos_completos' => 0, 'nombre' => 'Usuario', 'es_responsable' => 0];
    }

    $es_responsable = !empty($socio_actual) && isset($socio_actual['es_responsable']) && $socio_actual['es_responsable'] == 1;

    // === OBTENER TODOS LOS CLUBES DEL SOCIO ===
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

    // === VALIDAR Y USAR EL CLUB DE LA URL ===
    if (!$modo_individual) {
        // Ya tenemos $club_id y $club_slug desde la URL
        // Verificar que el socio pertenezca a ese club
        $socio_en_club = false;
        foreach ($clubes_del_socio as $c) {
            if ((int)$c['id_club'] === $club_id) {
                $socio_en_club = true;
                break;
            }
        }
        
        if (!$socio_en_club) {
            // Si no pertenece, redirigir al primer club
            if (!empty($clubes_del_socio)) {
                $c = $clubes_del_socio[0];
                $redirect_slug = substr(md5($c['id_club'] . $c['email_responsable']), 0, 8);
                header("Location: dashboard_socio.php?id_club=$redirect_slug");
                exit;
            } else {
                // No pertenece a ningún club → modo individual
                $modo_individual = true;
            }
        }
        // Asegurar que $club_id sea un entero válido
        $club_id = (int)$club_id;
        $_SESSION['club_id'] = $club_id;
    }

    // Si es modo individual PERO tiene clubs, redirigir
    if ($modo_individual && !empty($clubes_del_socio)) {
        $c = $clubes_del_socio[0];
        $redirect_slug = substr(md5($c['id_club'] . $c['email_responsable']), 0, 8);
        header("Location: dashboard_socio.php?id_club=$redirect_slug");
        exit;
    }

    // Guardar en sesión
    if (!$modo_individual) {
        $_SESSION['club_id'] = $club_id;
        $_SESSION['current_club'] = $club_slug;
    }

    // === DETECTAR TORNEOS AMERICANOS ===
    error_log("🔍 Buscando torneos para socio ID: " . ($_SESSION['id_socio'] ?? 'NULL'));
    $torneos_americanos = [];
    $stmt_torneos = $pdo->prepare("
        SELECT 
            t.id_torneo,
            t.nombre AS torneo_nombre,
            t.fecha_inicio,
            pt.id_pareja
        FROM parejas_torneo pt
        JOIN torneos t ON pt.id_torneo = t.id_torneo
        WHERE (pt.id_socio_1 = ? OR pt.id_socio_2 = ?)
        AND t.estado IN ('abierto', 'en_progreso', 'finalizado')
        AND t.fecha_inicio >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY t.fecha_inicio DESC
        LIMIT 1
    ");
    $stmt_torneos->execute([$_SESSION['id_socio'], $_SESSION['id_socio']]);
    $torneos_americanos = $stmt_torneos->fetchAll(PDO::FETCH_ASSOC);
    $tiene_torneo = !empty($torneos_americanos);
    $torneo_actual = $torneos_americanos[0] ?? null;

    // === BLOQUE PRÓXIMO EVENTO - LÓGICA CORREGIDA PARA CLUB E INDIVIDUAL ===
    error_log("🚀 [INICIO] Bloque Próximo Evento - Socio ID: " . ($id_socio ?? 'NULO'));

    $id_socio = $_SESSION['id_socio'] ?? 0;
    $club_id_actual = $modo_individual ? null : ($_SESSION['club_id'] ?? null);

    $where_parts = ["r.estado = 'confirmada'", "r.fecha >= CURDATE()"];
    $params = [];

    // LÓGICA DE FILTRADO INTELIGENTE
    if ($club_id_actual !== null) {
        // === MODO CLUB ===
        // Buscar reservas que cumplan UNA de estas dos condiciones:
        // A) Es una reserva personal del socio dentro del club (id_socio = X AND id_club = Y)
        // B) Es una reserva grupal del club (id_club = Y), sin importar quién la creó, siempre que el socio pertenezca al club.
        
        // Usamos paréntesis para agrupar la lógica OR correctamente
        $where_parts[] = "(
            (r.id_club = ? AND r.id_socio = ?) 
            OR 
            (r.id_club = ?)
        )";
        
        // Parámetros: [club_id, socio_id, club_id]
        $params[] = $club_id_actual;
        $params[] = $id_socio;
        $params[] = $club_id_actual;
        
        error_log(" [DEBUG] Modo Club: Buscando reservas del club $club_id_actual (personales o grupales)");

    } else {
        // === MODO INDIVIDUAL ===
        // Buscar solo reservas personales donde NO haya club asociado
        $where_parts[] = "r.id_club IS NULL AND r.id_socio = ?";
        $params[] = $id_socio;
        
        error_log("🔍 [DEBUG] Modo Individual: Buscando reservas personales del socio $id_socio");
    }

    $sql = "
        SELECT 
            r.id_reserva, r.fecha, r.hora_inicio, r.monto_total, 
            r.jugadores_esperados, r.estado, r.monto_recaudacion, r.valor_mes,
            c.nombre_cancha, c.id_deporte,
            (SELECT COUNT(*) FROM inscritos i WHERE i.id_evento = r.id_reserva AND i.tipo_actividad = 'reserva') as inscritos_actuales
        FROM reservas r
        JOIN canchas c ON r.id_cancha = c.id_cancha
        WHERE " . implode(" AND ", $where_parts) . "
        ORDER BY r.fecha ASC, r.hora_inicio ASC
        LIMIT 1
    ";

    error_log(" [DEBUG] SQL REAL: " . $sql);
    error_log(" [DEBUG] PARAMS: " . print_r($params, true));

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $proximo_evento = $stmt->fetch();
        
        if ($proximo_evento) {
            error_log("✅ [QUERY] Resultado: ENCONTRADO ID:" . $proximo_evento['id_reserva'] . " (Fecha: " . $proximo_evento['fecha'] . ")");
        } else {
            error_log("⚠️ [QUERY] Resultado: VACÍO (No hay reservas futuras para este criterio)");
        }
    } catch (Exception $e) {
        error_log("❌ [QUERY] Error: " . $e->getMessage());
        $proximo_evento = null;
    }

    // Asegurar variable
    $proximo_evento = $proximo_evento ?: null;
                

    // === DEUDAS PENDIENTES ===
    $deuda_mas_vigente = null;
    $total_deudas = 0;

    if (!$modo_individual && !empty($club_id)) {

        try {
            $stmt_deudas = $pdo->prepare("
                SELECT
                    c.id_cuota,
                    c.monto,
                    c.fecha_vencimiento,
                    CASE
                        WHEN c.tipo_actividad = 'reserva' THEN rd.nombre
                        WHEN c.tipo_actividad = 'evento' THEN te.tipoevento
                        ELSE 'Sin detalle'
                    END as detalle_origen,
                    COALESCE(r.fecha, e.fecha) as fecha_evento
                FROM cuotas c
                LEFT JOIN reservas r ON c.id_evento = r.id_reserva AND c.tipo_actividad = 'reserva'
                LEFT JOIN canchas ca ON r.id_cancha = ca.id_cancha
                LEFT JOIN recintos_deportivos rd ON ca.id_recinto = rd.id_recinto
                LEFT JOIN eventos e ON c.id_evento = e.id_evento AND c.tipo_actividad = 'evento'
                LEFT JOIN tipoeventos te ON e.id_tipoevento = te.id_tipoevento
                INNER JOIN socio_club sc ON c.id_socio = sc.id_socio AND sc.estado = 'activo'
                WHERE 
                    c.id_socio = ? 
                    AND c.estado = 'pendiente'
                    AND sc.id_club = ?
                    AND (
                        (c.tipo_actividad = 'reserva' AND r.id_club = ?)
                        OR
                        (c.tipo_actividad = 'evento' AND e.id_club = ?)
                        OR
                        (c.tipo_actividad NOT IN ('reserva', 'evento'))
                    )
                ORDER BY c.fecha_vencimiento ASC
                LIMIT 1
            ");

            $stmt_deudas->execute([
                $_SESSION['id_socio'],
                $club_id,
                $club_id,
                $club_id
            ]);

            $deuda_mas_vigente = $stmt_deudas->fetch();

            $stmt_count = $pdo->prepare("
                SELECT COUNT(*) 
                FROM cuotas 
                WHERE id_socio = ? AND estado = 'pendiente'
            ");
            $stmt_count->execute([$_SESSION['id_socio']]);
            $total_deudas = (int)$stmt_count->fetchColumn();

        } catch (Exception $e) {
            error_log("❌ Error en deudas: " . $e->getMessage());
        }

    } else {
        error_log("ℹ️ Saltando bloque de deudas (modo individual)");
    }

    // === ÚLTIMO PARTIDO (solo para club) ===
    $ultimo_partido = null;
    if (!$modo_individual && isset($_SESSION['club_id'])) {
        $stmt_last = $pdo->prepare("
            SELECT
                r.id_reserva,
                r.fecha,
                r.hora_inicio,
                r.resultado_grabado
            FROM reservas r
            WHERE r.id_club = ? AND r.fecha < CURDATE()
            ORDER BY r.fecha DESC, r.hora_inicio DESC
            LIMIT 1
        ");
        $stmt_last->execute([$_SESSION['club_id']]);
        $ultimo_partido = $stmt_last->fetch();
    }
    // Filtro para tabla de eventos (se usará en JS para mostrar/ocultar columnas)
    $filtro = $_GET['filtro'] ?? $_POST['filtro'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Dashboard - <?= htmlspecialchars($club_nombre) ?> | Cancha</title>
        <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>⚽</text></svg>">
        <link rel="stylesheet" href="../styles.css">
        <link rel="manifest" href="/manifest.json">
        <style>
            body {
            background:
                linear-gradient(rgba(0, 20, 10, 0.40), rgba(0, 30, 15, 0.50)),
                url('../assets/img/cancha_pasto2.jpg') center/cover no-repeat fixed;
            background-blend-mode: multiply;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            color: white;
            }
            .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
            text-align: center;
            }
            .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid rgba(255,255,255,0.3);
            text-align: left;
            }
            .club-logo {
            width: 70px;
            height: 70px;
            border-radius: 12px;
            object-fit: cover;
            background: rgba(255,255,255,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            }
            .club-info h1 {
            margin: 0;
            font-size: 2rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
            }
            .dashboard-upper {
            display: flex;
            height: auto;
            gap: 2rem;
            margin-bottom: 1.5rem;
            }
            .upper-right {
            flex: 0 0 15%;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            overflow-y: auto;
            margin-right: 20px;
            }
            .btn-action {
            padding: 0.4rem 1rem;
            background: #00cc66;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
            min-width: 110px;
            }
            .btn-action:hover {
            background: #00aa55;
            transform: translateY(-2px);
            }
            .dashboard-lower {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            padding: 1.5rem;
            border-radius: 14px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            overflow-y: auto;
            max-height: 600px;
            margin: 0 auto 2rem auto;
            max-width: 1400px;
            }
            .dynamic-table-container {
            max-height: 500px;
            overflow-y: auto;
            }
            .dashboard-lower h3 {
            margin-bottom: 1rem;
            text-align: left;
            font-size: 1.3rem;
            }
            .filters {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            }
            .filter-btn {
            padding: 0.4rem 0.8rem;
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
            }
            .filter-btn:hover {
            background: rgba(255,255,255,0.3);
            }
            .filter-btn.active {
            background: #667eea;
            border-color: #667eea;
            }
            .dynamic-table-container {
            overflow-x: auto;
            }
            .dynamic-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            }
            .dynamic-table th,
            .dynamic-table td {
            padding: 0.6rem;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            }
            .dynamic-table th {
            background: rgba(102, 126, 234, 0.3);
            position: sticky;
            top: 0;
            }
            .share-section {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            padding: 1.5rem;
            border-radius: 14px;
            margin-top: 2rem;
            text-align: center;
            }
            .qr-code {
            margin: 1rem auto;
            width: 180px;
            height: 180px;
            background: white;
            padding: 10px;
            border-radius: 8px;
            }
            .share-link {
            background: #e9ecef;
            padding: 0.8rem;
            border-radius: 6px;
            margin: 1rem 0;
            word-break: break-all;
            font-family: monospace;
            font-size: 0.9rem;
            }
            .copy-btn {
            background: #071289;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 0.5rem;
            }
            .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
            }
            .logout-header {
            color: #ffcc00;
            text-decoration: none;
            font-weight: bold;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            }
            .logout-header:hover {
            text-decoration: underline;
            }
            .logout {
            text-align: center;
            margin-top: 2.5rem;
            }
            .logout a {
            color: #ffcc00;
            text-decoration: none;
            font-weight: bold;
            font-size: 1.1rem;
            }
            .logout a:hover {
            text-decoration: underline;
            }
            .update-profile-btn {
            background: #071289;
            color: white;
            border: none;
            padding: 0.8rem 2rem;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s;
            margin: 2rem auto;
            display: block;
            text-decoration: none;
            width: fit-content;
            }
            .update-profile-btn:hover {
            background: #050d6b;
            }
            @media (max-width: 768px) {
            .dashboard-upper {
                flex-direction: column;
                height: auto;
                margin-bottom: 1rem;
            }
            .upper-left {
                flex: 1;
                grid-template-columns: repeat(2, 1fr);
                height: auto;
                margin-left: 0;
            }
            .upper-right {
                flex: 1;
                flex-direction: row;
                flex-wrap: wrap;
                height: auto;
                margin-right: 0;
            }
            .dashboard-lower {
                height: auto;
                margin-top: 1rem;
            }
            .filters {
                justify-content: center;
            }
            }
            /* ALTURA FIJA PARA FICHAS */
            .stat-card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            padding: 1rem;
            border-radius: 14px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            height: 310px; /* Altura fija para todas las fichas */
            display: flex;
            flex-direction: column;
            }
            .stat-card h3 {
            margin-bottom: 0.5rem;
            opacity: 0.9;
            }
            .stat-card-content {
            flex: 1;
            overflow-y: auto;
            }
            .ficha-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
            margin-top: 1rem;
            }
            .ficha-buttons .btn-action {
            padding: 0.4rem;
            font-size: 0.8rem;
            min-width: auto;
            width: 100%;
            box-sizing: border-box;
            }
            @media (max-width: 768px) {
            .ficha-buttons {
                grid-template-columns: 1fr;
            }
            }
            .btn-share {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.4);
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: background 0.2s;
            }
            .btn-share:hover {
            background: rgba(255,255,255,0.3);
            }
            /* Columna Acción centrada */
            .dynamic-table td:nth-child(12),
            .dynamic-table th:nth-child(12) {
            vertical-align: middle;
            text-align: center;
            }
        </style>
        <script>
            // Desactivar Service Worker en desarrollo
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.getRegistrations().then(function(registrations) {
                    for (let registration of registrations) {
                        registration.unregister();
                    }
                });
            }
        </script>
    </head>
    <body>
        <div class="dashboard-container">
        <!-- Header -->
            <div class="header">
                <div style="display: flex; align-items: center; gap: 1.2rem;">
                    <div class="club-logo">
                    <?php if ($club_logo): ?>
                    <?php
                        $logo_path = __DIR__ . '/../uploads/logos/' . $club_logo;
                        if (file_exists($logo_path)):
                    ?>
                    <img src="../uploads/logos/<?= htmlspecialchars($club_logo) ?>" alt="Logo" style="width:100%;height:100%;border-radius:12px;">
                    <?php else: ?>
                    <img src="../assets/icons/logo2-icon-192x192.png" alt="CanchaSport" style="width:100%;height:100%;border-radius:12px;">
                    <?php endif; ?>
                    <?php else: ?>
                    <img src="../assets/icons/logo2-icon-192x192.png" alt="CanchaSport" style="width:100%;height:100%;border-radius:12px;">
                    <?php endif; ?>
                </div>
                <div class="club-info">
                <h2><?= htmlspecialchars($socio_actual['alias'] ?? $socio_actual['nombre'] ?? 'Usuario') ?> <?= htmlspecialchars($club_nombre) ?></h2>
                <p>Tu Cancha está lista</p>
            </div>
        </div>
        <div class="header-right">
        <a href="../index.php" onclick="limpiarSesion()" class="logout-header">Salir</a>
        </div>
        </div>

        <!-- MITAD SUPERIOR -->
        <div class="dashboard-upper">
            <div class="upper-left">
                <!-- === FICHAS DASHBOARD (PARA TODOS: CLUB O INDIVIDUAL) === -->
                <div class="fichas-dashboard">

                    <!-- === PRÓXIMO PARTIDO === -->
                    <?php if (!empty($proximo_evento)): ?>
                        <?php
                            // Definir variables
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
                            $fecha_formateada = $fecha_evento->format('d-m');
                            $hora_formateada = $fecha_evento->format('H:i');
  
                            // === LÓGICA DE CUPOS - ROBUSTA PARA CLUB E INDIVIDUAL ===
                            $inscritos_actuales = (int)($proximo_evento['inscritos_actuales'] ?? 0);
                            $jugadores_esperados = (int)($proximo_evento['jugadores_esperados'] ?? 0);
                            // Solo considerar "lleno" si hay un límite definido (>0) Y se alcanzó
                            // Para reservas individuales, jugadores_esperados suele ser 0 → nunca se marca como lleno
                            $cupos_llenos = ($jugadores_esperados > 0 && $inscritos_actuales >= $jugadores_esperados);
                            // Debug opcional (puedes quitarlo después)
                            // error_log("🔍 Cupos: actuales=$inscritos_actuales / esperados=$jugadores_esperados / llenos=" . ($cupos_llenos ? 'SÍ' : 'NO'));

                            // Mapeo de deportes a nombres legibles
                            $nombres_deportes = [
                                'futbol' => 'Fútbol',
                                'futbolito' => 'Futbolito',
                                'futsal' => 'Futsal',
                                'tenis' => 'Tenis',
                                'padel' => 'Pádel',
                                'voleyball' => 'Vóley',
                                'otro' => 'Otro'
                            ];
                            $nombre_deporte = $nombres_deportes[$deporte] ?? ucfirst($deporte);

                            // Verificar si ya está inscrito
                            $ya_inscrito = false;
                            if (isset($_SESSION['id_socio'])) {
                                $stmt_check = $pdo->prepare("
                                    SELECT 1 FROM inscritos 
                                    WHERE id_evento = ? AND id_socio = ? AND tipo_actividad = 'reserva'
                                ");
                                $stmt_check->execute([$id_reserva, $_SESSION['id_socio']]);
                                $ya_inscrito = (bool)$stmt_check->fetch();
                            }
                        ?>

                        <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                            <h3 style="color: white; margin-bottom: 0.2rem;">Próximo Partido</h3>
                            <p style="margin: 0 0 0.8rem 0; font-weight: bold; font-size: 1.1rem; text-align: center; opacity: 0.95;">
                                <?= htmlspecialchars($nombre_deporte) ?>
                            </p>
                            <div class="stat-card-content">
                                <p><strong><?= $fecha_formateada ?> a las <?= $hora_formateada ?></strong></p>
                                <div style="margin:0.5rem 0;font-size:0.85rem;text-align:left;">
                                    <!-- <div style="margin:0.3rem 0;"><strong>💰 Arriendo</strong> $<?= number_format((int)$monto_total, 0, ',', '.') ?></div> --->
                                    <?php if (!empty($proximo_evento['monto_recaudacion'])): ?>
                                    <div style="margin:0.3rem 0; font-size:0.8rem; color:#FFD700;">
                                        <strong>💰 Cuota:</strong> $<?= number_format((int)($proximo_evento['monto_recaudacion'] ?? 0), 0, ',', '.') ?> • <strong>Mes:</strong> $<?= number_format($valor_mes, 0, ',', '.') ?><br>
                                        <strong>👥 Cupos:</strong> <?= $jugadores_esperados ?> • <strong>👥 Anotados:</strong> <?= $inscritos_actuales ?>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <?php if ($despues_del_lunes_09): ?>
                                    <?php if ($ya_inscrito): ?>
                                    <button class="btn-action" style="background:#FF6B6B;padding:0.4rem;font-size:0.8rem;width:100%;" 
                                            onclick="bajarseEvento(<?= (int)$id_reserva ?>)">
                                        Bajarse
                                    </button>
                                                                        <?php else: ?>
                                    <?php if ($cupos_llenos): ?>
                                        <p style="color:#FF6B6B;margin-top:1rem;font-weight:bold;">❌ No se aceptan más inscripciones...</p>
                                    <?php else: ?>
                                        <?php 
                                            // === LOGS DE DEPURACIÓN ===
                                            error_log("🔍 [DEBUG BOTONES] ID Reserva: " . $id_reserva);
                                            error_log("🔍 [DEBUG BOTONES] Deporte RAW: " . var_export($deporte, true));
                                            error_log("🔍 [DEBUG BOTONES] Players: " . $players);
                                            error_log("🔍 [DEBUG BOTONES] Monto: " . $monto_total);
                                            
                                            // Sanitización extrema para JS
                                            $js_deporte = addslashes($deporte); 
                                            $js_id = (int)$id_reserva;
                                            $js_players = (int)$players;
                                            $js_monto = (float)$monto_total;
                                        ?>
                                        
                                        <!-- Botón Anotarse -->
                                        <button class="btn-action" 
                                            onclick="console.log('🔵 Click Anotarse'); anotarseEvento(<?= $js_id ?>, 'reserva', '<?= $js_deporte ?>', <?= $js_players ?>, <?= $js_monto ?>)">
                                            Anotarse
                                        </button>
                                        
                                        <!-- Botón Anotarse + Cerveza -->
                                        <button class="btn-action" 
                                            onclick="console.log('🍺 Click Cerveza'); anotarseConCerveza(true, <?= $js_id ?>, '<?= $js_deporte ?>', <?= $js_players ?>, <?= $js_monto ?>)">
                                            Anotarse + llevo 🍺🍺
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn-action" style="background:#FF6B6B;padding:0.4rem;font-size:0.8rem;margin-top:0.3rem;width:100%;" 
                                            onclick="pasoEvento(<?= (int)$id_reserva ?>)">
                                        Paso
                                    </button>
                                    <?php endif; ?>

                                    <?php if ($es_responsable && (int)($proximo_evento['inscritos_actuales'] ?? 0) >= 10): ?>
                                    <button class="btn-action" style="background:#F1C40F;padding:0.4rem;font-size:0.8rem;margin-top:0.5rem;width:100%;" 
                                            onclick="armarEquiposIA(<?= (int)$id_reserva ?>)">
                                        🤖 Armar Equipos IA
                                    </button>
                                    <?php endif; ?>

                                <?php else: ?>
                                    <p style="color:#FFD700;margin-top:1rem;font-size:0.85rem;">
                                    ⏰ Los botones se activarán el lunes <?= htmlspecialchars($lunes_semana_evento->format('d/m')) ?> a las 09:00 hrs
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php else: ?>
                            <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                                <h3 style="color: white;">Próximo Partido</h3>
                                <p style="margin-top:1rem;">📭 No hay partidos programados próximamente</p>
                            </div>
                        <?php endif; ?>


                        <!-- === DEUDAS PENDIENTES === -->
                        <?php if ($deuda_mas_vigente): ?>
                            <div class="stat-card" style="background: linear-gradient(135deg, #ff9a9e 0%, #fad0c4 100%); color: #071289;">
                                <h3>💰 Deuda Pendiente</h3>
                                <div style="margin:0.8rem 0;padding:0.6rem;background:rgba(255,255,255,0.7);border-radius:8px;font-size:0.85rem;">
                                <strong><?= htmlspecialchars($deuda_mas_vigente['detalle_origen']) ?></strong><br>
                                <strong>📅</strong> <?= date('d/m', strtotime($deuda_mas_vigente['fecha_evento'])) ?> –
                                <strong>💲</strong> $<?= number_format($deuda_mas_vigente['monto'], 0, ',', '.') ?><br>
                                <button class="btn-action" style="background:#E74C3C;margin-top:0.5rem;font-size:0.8rem;color:white;" 
                                        onclick="pagarCuota(<?= $deuda_mas_vigente['id_cuota'] ?>)">Pagar ahora</button>
                                </div>
                                <?php if ($total_deudas > 1): ?>
                                <p style="font-size:0.8rem; margin-top:0.8rem; opacity:0.8;">⚠️ Existen más cuotas pendientes...</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>


                        <!-- === ÚLTIMO PARTIDO === -->
                        <div class="stat-card">
                        <h3>📊 Último Partido</h3>
                        <div class="stat-card-content">
                            <?php if ($ultimo_partido): ?>
                            <p><strong>Fecha:</strong> <?= htmlspecialchars($ultimo_partido['fecha']) ?></p>
                            <?php if (!is_null($ultimo_partido['resultado_grabado']) && $ultimo_partido['resultado_grabado']): ?>
                                <p style="margin-top:1rem;">✅ Resultado ya registrado</p>
                            <?php elseif ($es_responsable): ?>
                                <form id="postPartidoForm" style="margin-top:1rem;">
                                <input type="hidden" name="id_reserva" value="<?= $ultimo_partido['id_reserva'] ?>">
                                <div style="display:flex;gap:1rem;margin:0.5rem 0;">
                                    <div style="flex:1;"><label style="font-weight:bold;">Rojos:</label>
                                    <input type="number" name="goles_rojos" placeholder="0" min="0" value="0" style="width:100%;padding:0.4rem;border-radius:4px;border:1px solid #ccc;"></div>
                                    <div style="flex:1;"><label style="font-weight:bold;">Blancos:</label>
                                    <input type="number" name="goles_blancos" placeholder="0" min="0" value="0" style="width:100%;padding:0.4rem;border-radius:4px;border:1px solid #ccc;"></div>
                                </div>
                                <button type="submit" class="btn-action" style="margin-top:0.5rem;background:#2ECC71;color:white;border:none;padding:0.3rem 0.6rem;border-radius:4px;width:100%;">Grabar Resultado</button>
                                </form>
                            <?php else: ?>
                                <p style="margin-top:1rem;">Resultado aún no registrado</p>
                            <?php endif; ?>
                            <?php else: ?>
                            <p style="margin-top:2rem;">Sin partidos anteriores</p>
                            <?php endif; ?>
                        </div>
                    </div>

                </div> <!-- .fichas-dashboard -->
            </div> <!-- .upper-left -->    

            <!-- Sub sección derecha -->
            <div class="upper-right">
                <?php if (!empty($clubes_del_socio) && count($clubes_del_socio) > 1): ?>
                    <div><strong>🏆 Mis Clubes</strong></div>
                    <?php foreach ($clubes_del_socio as $c): ?>
                        <?php
                            $slug_actual = substr(md5($c['id_club'] . $c['email_responsable']), 0, 8);
                            if (!$modo_individual && $club_id == $c['id_club']) continue;
                        ?>
                        <button class="btn-action" onclick="cambiarClub('<?= $slug_actual ?>')"><?= htmlspecialchars($c['club_nombre']) ?></button>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!($modo_individual && !empty($torneos_americanos))): ?>
                    <?php if ($es_responsable): ?>
                        <button class="btn-action" onclick="window.location.href='perfil_club.php'">Actualizar perfil club</button>
                        <button class="btn-action" onclick="abrirModalCompartir()">Compartir club</button>
                        <button class="btn-action" style="background:#4CAF50;" onclick="agregarOtroClub()">➕ Otro Club</button>
                    <?php endif; ?>
                        <button class="btn-action" onclick="window.location.href='reservar_cancha.php'">Reservar Cancha</button>
                        <button class="btn-action" onclick="window.location.href='eventos.php?id=<?= htmlspecialchars($club_slug) ?>'">Crear partido Pádel</button>
                        <button class="btn-action" onclick="window.location.href='mantenedor_socios.php'">Actualizar perfil socio</button>
                    <?php if ($pareja_activa): ?>
                        <button class="btn-action" style="background:#FF9800;" onclick="reemplazarCompanero(<?= $pareja_activa['id_pareja'] ?>)">➕ Reemplazar compañero</button>
                    <?php endif; ?>
                <?php endif; ?>
            </div> <!-- .upper-right -->
        </div> <!-- .dashboard-upper -->

        <!-- CSS RESPONSIVE -->
        <style>
        @media (min-width: 1024px) {
            .dashboard-upper { display: flex; gap: 1.8rem; margin-top: 1.2rem; }
            .upper-left { flex: 4; max-width: none; margin-left: 20px; }
            .upper-right { flex: 1; display: flex; flex-direction: column; gap: 0.8rem; margin-right: 20px; }
        }
        .fichas-dashboard {
            display: grid;
            gap: 1.4rem;
            width: 100%;
            grid-template-columns: 1fr;
        }
        @media (min-width: 768px) and (max-width: 1023px) {
            .fichas-dashboard { grid-template-columns: repeat(2, 1fr); }
        }
        @media (min-width: 1024px) {
            .fichas-dashboard { grid-template-columns: repeat(4, 1fr); }
        }
        .fichas-dashboard > .stat-card { width: 100%; min-width: 0; }
        .upper-left { display: block; }
        </style>

        <!-- MITAD INFERIOR -->
        <div class="dashboard-lower" style="margin-top: 8rem;">
                <h3>Detalle Eventos</h3>
                <!-- Filtros -->
                <button class="filter-btn" data-filter="inscritos">Inscritos Próximo Evento</button>
                <button class="filter-btn" data-filter="torneos">Americanos</button>
                <button class="filter-btn" data-filter="reservas">Reservas</button>               
                <button class="filter-btn" data-filter="eventos">Eventos</button>
                <?php if (!($modo_individual && !empty($torneos_americanos))): ?>
                    <button class="filter-btn" data-filter="equipos">Equipos IA</button>
                <?php endif; ?>
                    <?php if (!$modo_individual): ?>
                        <button class="filter-btn" data-filter="cuotas">Cuotas</button>
                        <button class="filter-btn" data-filter="socios">Socios</button>
                <?php endif; ?>

                <!-- Columnas Tabla Datos -->
                <div class="dynamic-table-container">
                    <table class="dynamic-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Hora</th>
                                <th>Tipo</th>
                                <th>Cancha</th>
                                <th>Costo</th>
                                <th>Nombre</th>
                                <?php if($filtro == 'cuotas') { ?>
                                    <th>Tipo</th>
                                <?php } ?>
                                <th>Puesto</th> 
                                <th>Monto</th>
                                <th>Pago Realizado</th>
                                <th>Comentario</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody id="tablaContenido">
                            <tr>
                                <td colspan="11" style="text-align: center; padding: 2rem;">Selecciona un filtro para ver los datos</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
        </div>
        
        <!-- Modal Compartir Club -->
        <div id="modalCompartir" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:1000; justify-content:center; align-items:center;">
                <div style="background:white; color:#071289; padding:2rem; border-radius:14px; max-width:400px; width:90%;">
                    <h3 style="margin-top:0;">🔗 Compartir tu club</h3>
                    <p>Envía este enlace a tus compañeros para que se inscriban fácilmente:</p>
                    <div style="background:#f1f1f1; padding:0.6rem; border-radius:6px; margin:1rem 0; word-break:break-all; font-family:monospace; font-size:0.9rem;">
                        <?= htmlspecialchars("https://canchasport.com/pages/registro_socio.php?club=" . ($club_slug ?? '')) ?>
                    </div>
                    <button onclick="copiarEnlace()" style="background:#071289; color:white; border:none; padding:0.5rem 1rem; border-radius:6px; margin-right:0.5rem;">📋 Copiar</button>
                    <button onclick="cerrarModalCompartir()" style="background:#6c757d; color:white; border:none; padding:0.5rem 1rem; border-radius:6px;">Cerrar</button>
                </div>
        </div>

        <!-- Modal Equipos IA -->
        <div id="modalEquipos" class="submodal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:1000; justify-content:center; align-items:center;">
                <div class="submodal-content" style="background:white; color:#333; padding:2rem; border-radius:16px; max-width:800px; width:90%; max-height:90vh; overflow-y:auto;">
                    <h3>🤖 Equipos Futbolito</h3>
                    <div style="display:flex;gap:2rem;margin:1.5rem 0;">
                        <div style="flex:1;background:#ffebee;padding:1rem;border-radius:8px;">
                            <h4 style="color:#e74c3c;">🔴 Rojos</h4>
                            <ul id="equipoRojos" style="list-style:none;padding:0;"></ul>
                            <button onclick="moverJugador('rojos', 'blancos')"
                                style="margin-top:0.5rem;background:#2980b9;color:white;border:none;padding:0.3rem 0.6rem;border-radius:4px;width:100%;">
                                ➡️ Mover a Blancos
                            </button>
                        </div>
                        <div style="flex:1;background:#e3f2fd;padding:1rem;border-radius:8px;">
                            <h4 style="color:#2980b9;">⚪ Blancos</h4>
                            <ul id="equipoBlancos" style="list-style:none;padding:0;"></ul>
                            <button onclick="moverJugador('blancos', 'rojos')"
                                style="margin-top:0.5rem;background:#e74c3c;color:white;border:none;padding:0.3rem 0.6rem;border-radius:4px;width:100%;">
                                ➡️ Mover a Rojos
                            </button>
                        </div>
                    </div>
                    <h4>👉 Seleccionar un jugador y dar click en barra para mover a los Rojos o Blancos</h4>
                    <button onclick="guardarEquipos()" class="btn-submit" style="margin-top:1.5rem;">Guardar Equipos</button>
                    <button onclick="cerrarModalEquipos()" style="margin-top:0.5rem;background:#6c757d;color:white;border:none;padding:0.5rem 1rem;border-radius:6px;">Cerrar</button>
                </div>
            </div>
        </div> 
        <!-- SCRIPTS COMPLETOS -->
        <script>
            // === FUNCIONES CRÍTICAS DE NAVEGACIÓN Y SESIÓN ===
            
            // 1. Limpiar Sesión
            function limpiarSesion() {
                localStorage.removeItem('cancha_session');
                localStorage.removeItem('cancha_club');
            }

            // 2. Cambiar Club (Definida explícitamente aquí para evitar ReferenceError)
            function cambiarClub(clubSlug) {
                console.log('🔄 Cambiando a club:', clubSlug);
                document.body.style.cursor = 'wait';

                fetch('../api/cambiar_club_sesion.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ club_slug: clubSlug })
                })
                .then(r => {
                    if (!r.ok) throw new Error('Error en la red');
                    return r.json();
                })
                .then(data => {
                    document.body.style.cursor = 'default';
                    if (data.success) {
                        window.location.href = `dashboard_socio.php?id_club=${clubSlug}&t=${Date.now()}`;
                    } else {
                        alert('❌ Error: ' + (data.message || 'No se pudo cambiar de club'));
                    }
                })
                .catch(err => {
                    document.body.style.cursor = 'default';
                    console.error('Error:', err);
                    alert('❌ Error de conexión al cambiar de club');
                });
            }

            // 3. Modal Compartir
            function abrirModalCompartir() {
                const modal = document.getElementById('modalCompartir');
                if (modal) modal.style.display = 'flex';
            }
            function cerrarModalCompartir() {
                const modal = document.getElementById('modalCompartir');
                if (modal) modal.style.display = 'none';
            }
            function copiarEnlace() {
                const url = "<?= htmlspecialchars("https://canchasport.com/pages/registro_socio.php?club=" . ($club_slug ?? '')) ?>";
                navigator.clipboard.writeText(url)
                .then(() => alert('✅ Enlace copiado!'))
                .catch(err => console.error('Error al copiar:', err));
            }

                        // === TOAST PERSONALIZADO ===
            function mostrarToast(mensaje, tipo = 'info') { // <--- Asegúrate que tenga 'tipo = info' o quítalo si usas ES5
                let toastContainer = document.getElementById('toast-container');
                if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.id = 'toast-container';
                toastContainer.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                z-index: 1000;
                max-width: 300px;
                `;
                document.body.appendChild(toastContainer);
                }
                const toast = document.createElement('div');
                toast.textContent = mensaje;
                toast.style.cssText = `
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 12px 16px;
                border-radius: 8px;
                margin-bottom: 10px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                animation: slideInRight 0.3s ease-out, fadeOut 0.5s ease-in 2.5s forwards;
                font-size: 14px;
                `;
                toastContainer.appendChild(toast);
                setTimeout(() => {
                if (toast.parentNode) toast.parentNode.removeChild(toast);
                }, 5000);
            } // <--- ¡ESTA LLAVE DE CIERRE ES CRÍTICA!

            // === ACCIONES DE EVENTOS ===
            function anotarseEvento(idActividad, tipoActividad, deporte, playersMax, montoTotal) {
                console.log('🔵 Intentando anotarse:', idActividad);
                if (!idActividad) { mostrarToast('❌ Error: ID inválido', 'error'); return; }

                const formData = new FormData();
                formData.append('action', 'anotarse');
                formData.append('id_actividad', idActividad);
                formData.append('tipo_actividad', tipoActividad);
                formData.append('deporte', deporte);
                formData.append('players_max', playersMax);
                formData.append('monto_total', montoTotal);

                fetch('../api/gestion_eventos.php', { method: 'POST', body: formData })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            mostrarToast(data.message, 'exito');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            mostrarToast('❌ ' + data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('❌ Error Fetch:', error);
                        mostrarToast('❌ Error de conexión', 'error');
                    });
            }

            function bajarseEvento(idReserva) {
                if(!confirm('¿Seguro que deseas bajarte de este evento?')) return;
                const formData = new FormData();
                formData.append('action', 'bajarse');
                formData.append('id_reserva', idReserva);
                fetch('../api/gestion_eventos.php', { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(data => {
                        if(data.success) {
                            mostrarToast(data.message, 'exito');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            mostrarToast('❌ ' + data.message, 'error');
                        }
                    })
                    .catch(err => mostrarToast('❌ Error de conexión', 'error'));
            }

            function pasoEvento(idReserva) {
                const btn = event.target;
                btn.textContent = 'Paso esta semana';
                btn.disabled = true;
                btn.style.opacity = '0.7';
                // Opcional: Guardar estado en BD si es necesario
            }

            function anotarseConCerveza(llevaCerveza, idReserva, deporte, playersMax, montoTotal) {
                const formData = new FormData();
                formData.append('action', 'anotarse');
                formData.append('id_actividad', idReserva);
                formData.append('tipo_actividad', 'reserva');
                formData.append('deporte', deporte);
                formData.append('players_max', playersMax);
                formData.append('monto_total', montoTotal);
                formData.append('lleva_cerveza', llevaCerveza ? '1' : '0');
                
                fetch('../api/gestion_eventos.php', { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            mostrarToast(data.message, 'exito');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            mostrarToast('❌ ' + data.message, 'error');
                        }
                    })
                    .catch(err => mostrarToast('❌ Error de conexión', 'error'));
            }

            // === ARMAR EQUIPOS IA ===
            function armarEquiposIA(idReserva) {
                fetch('../api/armar_equipos_ia.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({id_reserva: idReserva})
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) mostrarModalEquipos(data.equipos);
                    else alert('Error: ' + data.message);
                })
                .catch(err => alert('Error al armar equipos'));
            }

            // === MODAL EQUIPOS IA ===
            function mostrarModalEquipos(equipos) {
                const rojosEl = document.getElementById('equipoRojos');
                const blancosEl = document.getElementById('equipoBlancos');
                const modal = document.getElementById('modalEquipos');
                if (!rojosEl || !blancosEl || !modal) return;
                
                rojosEl.innerHTML = '';
                blancosEl.innerHTML = '';
                
                equipos.rojos.forEach(j => {
                    const li = document.createElement('li');
                    li.textContent = j.alias;
                    li.dataset.idSocio = j.id_socio;
                    li.style.padding = '0.3rem'; li.style.cursor = 'pointer';
                    li.onclick = () => seleccionarJugador(li, 'rojos');
                    rojosEl.appendChild(li);
                });
                equipos.blancos.forEach(j => {
                    const li = document.createElement('li');
                    li.textContent = j.alias;
                    li.dataset.idSocio = j.id_socio;
                    li.style.padding = '0.3rem'; li.style.cursor = 'pointer';
                    li.onclick = () => seleccionarJugador(li, 'blancos');
                    blancosEl.appendChild(li);
                });
                modal.style.display = 'flex';
            }

            let jugadorSeleccionado = null;
            function seleccionarJugador(elemento, equipo) {
                document.querySelectorAll('#equipoRojos li, #equipoBlancos li').forEach(el => {
                    el.style.border = '1px solid transparent'; el.style.backgroundColor = '';
                });
                elemento.style.border = '2px solid #3498DB'; elemento.style.backgroundColor = '#d6eaf8';
                jugadorSeleccionado = elemento.dataset.idSocio;
            }

            function moverJugador(de, a) {
                if (!jugadorSeleccionado) { alert('Selecciona un jugador primero'); return; }
                const origen = document.getElementById(`equipo${de.charAt(0).toUpperCase() + de.slice(1)}`);
                const destino = document.getElementById(`equipo${a.charAt(0).toUpperCase() + a.slice(1)}`);
                if (destino.children.length >= 7) { alert('El equipo ya tiene 7 jugadores'); return; }
                
                let elementoSeleccionado = null;
                Array.from(origen.children).forEach(li => {
                    if (li.dataset.idSocio == jugadorSeleccionado) elementoSeleccionado = li;
                });
                
                if (elementoSeleccionado) {
                    destino.appendChild(elementoSeleccionado);
                    jugadorSeleccionado = null;
                    elementoSeleccionado.style.border = '1px solid transparent';
                    elementoSeleccionado.style.backgroundColor = '';
                }
            }

            function guardarEquipos() {
                const rojos = Array.from(document.getElementById('equipoRojos').children).map(li => li.dataset.idSocio);
                const blancos = Array.from(document.getElementById('equipoBlancos').children).map(li => li.dataset.idSocio);
                if (rojos.length === 0 || blancos.length === 0) { alert('Ambos equipos deben tener jugadores'); return; }
                
                fetch('../api/guardar_equipos_manual.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ id_reserva: <?= $id_reserva ?? 0 ?>, rojos: rojos, blancos: blancos })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) { mostrarToast('✅ Equipos guardados', 'exito'); setTimeout(() => location.reload(), 1500); }
                    else { mostrarToast('❌ ' + data.message, 'error'); }
                })
                .catch(err => mostrarToast('❌ Error al guardar', 'error'));
            }

            function cerrarModalEquipos() {
                const modal = document.getElementById('modalEquipos');
                if (modal) modal.style.display = 'none';
            }

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

            // === ASIGNAR CERVEZA ===
            function asignarCerveza(idInscrito, estado) {
                fetch('../api/asignar_cerveza.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({ id_inscrito: idInscrito, lleva_cerveza: estado })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        mostrarToast(estado ? '✅ ¡Llevará cervezas!' : '✅ Asignación removida', 'exito');
                        setTimeout(() => location.reload(), 1000);
                    } else { mostrarToast('❌ ' + data.message, 'error'); }
                });
            }

            // === GUARDAR RESULTADO ÚLTIMO PARTIDO ===
            document.getElementById('postPartidoForm')?.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(e.target);
                try {
                    const response = await fetch('../api/guardar_resultado_partido.php', { method: 'POST', body: formData });
                    const data = await response.json();
                    if (data.success) { mostrarToast('✅ Resultado guardado', 'exito'); setTimeout(() => location.reload(), 1500); }
                    else { mostrarToast('❌ ' + data.message, 'error'); }
                } catch (error) { mostrarToast('❌ Error al guardar', 'error'); }
            });

            // === CARGAR TABLA DE DATOS ===
            function formatDate(dateStr) {
                if (!dateStr) return '-';
                const [y, m, d] = dateStr.split('-');
                return `${d}/${m}`;
            }

            function cargarTabla(filtro) {
                const tbody = document.getElementById('tablaContenido');
                if (!tbody) return;

                // Desactivar botones visualmente
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                document.querySelector(`[data-filter="${filtro}"]`)?.classList.add('active');

                tbody.innerHTML = '<tr><td colspan="11" style="text-align:center; padding:2rem;">Cargando...</td></tr>';

                fetch(`../api/get_tabla_datos.php?filtro=${filtro}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.error || !Array.isArray(data) || data.length === 0) {
                            tbody.innerHTML = `<tr><td colspan="11" style="text-align:center; padding:2rem;">${data.error || 'No hay datos disponibles'}</td></tr>`;
                            return;
                        }
                        
                        let html = '';
                        const esResponsable = <?= json_encode($es_responsable) ?>;
                        const miIdSocio = <?= (int)($_SESSION['id_socio'] ?? 0) ?>;

                        data.forEach(row => {
                            let botonAccion = '-';
                            
                            // Lógica específica por filtro
                            if (filtro === 'inscritos') {
                                const esMiInscripcion = (row.id_socio == miIdSocio);
                                const fechaEvento = new Date(row.fecha + ' ' + (row.hora_inicio || '00:00'));
                                const ahora = new Date();
                                let acciones = '';
                                if (esMiInscripcion || (esResponsable && fechaEvento > ahora)) {
                                    acciones += `<button class="btn-action" style="padding:0.2rem 0.4rem;font-size:0.7rem;background:#FF6B6B;margin-right:0.3rem;" onclick="bajarseEvento(${row.id_evento}, ${esResponsable && !esMiInscripcion ? row.id_socio : 'null'})">Bajar</button>`;
                                }
                                if (esResponsable && fechaEvento > ahora) {
                                    const emoji = row.lleva_cerveza ? '🍺' : '⚪';
                                    acciones += `<span style="font-size:1.2rem;cursor:pointer;" onclick="asignarCerveza(${row.id_inscrito}, ${row.lleva_cerveza ? 0 : 1})">${emoji}</span>`;
                                }
                                botonAccion = acciones || '-';
                            } else if (filtro === 'cuotas' && esResponsable) {
                                if (row.estado === 'pendiente') botonAccion = `<button class="btn-action" style="padding:0.2rem 0.4rem;font-size:0.7rem;background:#F39C12;" onclick="revisarPago(${row.id_cuota})">🔍</button>`;
                                else if (row.estado === 'en_revision') botonAccion = `<button class="btn-action" style="padding:0.2rem 0.4rem;font-size:0.7rem;background:#2ECC71;" onclick="validarPago(${row.id_cuota})">✅</button>`;
                            } else if (filtro === 'socios' && esResponsable) {
                                botonAccion = `<span style="cursor:pointer;font-size:1.2rem;" onclick="editarPerfilSocio(${row.id_socio})">✏️</span>`;
                            } else if (filtro === 'torneos') {
                                botonAccion = `<span style="cursor:pointer;" onclick="abrirDetalleTorneo(${row.id_torneo})">👁️</span>`;
                            }

                            html += `
                                <tr>
                                    <td>${formatDate(row.fecha)}</td>
                                    <td>${row.hora_inicio?.substring(0,5) || '-'}</td>
                                    <td>${row.tipo || row.id_tipoevento || '-'}</td>
                                    <td>${row.cancha || row.origen || '-'}</td>
                                    <td>$${parseInt(row.costo_evento || row.monto_total || 0).toLocaleString()}</td>
                                    <td>${row.nombre || '-'}</td>
                                    <td>${row.puesto || row.posicion_jugador || '-'}</td>
                                    <td>$${parseInt(row.cuota_monto || row.monto_esperado || 0).toLocaleString()}</td>
                                    <td>$${parseInt(row.monto_pagado || row.monto || 0).toLocaleString()}</td>
                                    <td>${row.comentario || '-'}</td>
                                    <td>${botonAccion}</td>
                                </tr>
                            `;
                        });
                        tbody.innerHTML = html;
                    })
                    .catch(err => {
                        console.error(err);
                        tbody.innerHTML = '<tr><td colspan="11" style="text-align:center;color:#FF6B6B;">Error al cargar datos</td></tr>';
                    });
            }

            // === INICIALIZAR AL CARGAR LA PÁGINA ===
            document.addEventListener('DOMContentLoaded', () => {
                // Configurar botones de filtro
                document.querySelectorAll('.filter-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const filtro = btn.getAttribute('data-filter');
                        cargarTabla(filtro);
                    });
                });
                
                // Cargar tabla por defecto
                const urlParams = new URLSearchParams(window.location.search);
                const filtroInicial = urlParams.get('filtro') || 'inscritos';
                cargarTabla(filtroInicial);
            });

            // === ANIMACIONES CSS PARA TOAST ===
            const style = document.createElement('style');
            style.textContent = `
                @keyframes slideInRight { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
                @keyframes fadeOut { from { opacity: 1; } to { opacity: 0; } }
            `;
            document.head.appendChild(style);

        </script>
    </body>
</html>