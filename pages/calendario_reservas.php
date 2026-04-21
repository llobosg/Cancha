<?php
require_once __DIR__ . '/../includes/config.php';

session_start();

if (!isset($_SESSION['id_recinto']) || $_SESSION['recinto_rol'] !== 'admin_recinto') {
    header('Location: ../index.php');
    exit;
}

$id_recinto = $_SESSION['id_recinto'];

// Obtener datos del recinto
$stmt = $pdo->prepare("SELECT nombre, logorecinto FROM recintos_deportivos WHERE id_recinto = ?");
$stmt->execute([$id_recinto]);
$recinto = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=0.8, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
  <title>CanchaBoard - <?= htmlspecialchars($recinto['nombre']) ?> | Cancha</title>
  <link rel="stylesheet" href="../styles.css">
  <style>
    body {
        background: linear-gradient(rgba(0, 20, 10, 0.40), rgba(0, 30, 15, 0.50)),
                url('../assets/img/cancha_pasto2.jpg') center/cover no-repeat fixed;
        background-blend-mode: multiply;
        margin: 0;
        padding: 0;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        min-height: 100vh;
        color: white;
    }

    /* Blindaje contra desbordamiento horizontal */
    body, html {
        overflow-x: hidden; /* Oculta cualquier scroll horizontal no deseado */
        width: 100%;
        margin: 0;
        padding: 0;
    }

    /* Asegurar que ningún hijo sea más ancho que la pantalla */
    * {
        box-sizing: border-box;
        max-width: 100%;
    }
    
    .dashboard-container {
        display: grid;
        grid-template-columns: 4fr 1fr;
        gap: 1rem;
        width: 100%;
        overflow-x: hidden;
        margin: 0 auto;
        padding: 1rem;
        /* Eliminamos height fija para permitir alineación natural */
    }
    
    .header {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 60px;
        background: rgba(0, 51, 102, 0.95);
        backdrop-filter: blur(10px);
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0 1.5rem;
        z-index: 1000;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    }
    
    .main-title-section {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .logo-corporativo {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        background: #FFD700;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
    }
    
    .main-title {
        color: #FFD700;
        font-size: 1.5rem;
        margin: 0;
    }
    
    .controls-section {
        display: flex;
        gap: 1rem;
        margin-bottom: 1rem;
        padding: 0.5rem;
        background: rgba(255,255,255,0.1);
        border-radius: 8px;
        position: sticky;
        top: 70px;
        z-index: 999;
    }
    
    .control-select {
        background: white;
        padding: 0.3rem;
        border-radius: 4px;
        color: #071289;
        border: none;
    }
    
    .reservas-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 1rem;
        overflow-y: auto;
        padding-right: 0.5rem;
    }
    
    .reserva-card {
        background: white;
        border-radius: 12px;
        padding: 1rem;
        cursor: pointer;
        transition: transform 0.2s, box-shadow 0.2s;
        position: relative;
        overflow: hidden;
    }
    
    .reserva-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.3);
    }
    
    .reserva-card.selected {
        border: 3px solid #071289;
    }
    
    .deporte-icon {
        font-size: 1.5rem;
        margin-bottom: 0.5rem;
    }
    
    .cancha-nombre {
        font-weight: bold;
        color: #071289;
        margin-bottom: 0.3rem;
    }
    
    .fecha-hora {
        font-size: 0.9rem;
        color: #666;
        margin-bottom: 0.5rem;
    }
    
    .estado-indicator {
        position: absolute;
        top: 10px;
        right: 10px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
    }
    
    /* === COLORES DE ESTADOS (Actualizados) === */
    .estado-disponible { background: #4CAF50 !important; } /* Verde */
    .estado-reservada { background: #2196F3 !important; }  /* Azul (Cambiado) */
    .estado-ocupada { background: #9C27B0 !important; }    /* Morado (Cambiado) */
    .estado-cancelada { background: #F44336 !important; }  /* Rojo */
    .estado-parcial { background: #FFC107 !important; }    /* Amarillo (Nuevo) */
    .estado-mantencion { background: #FF9800 !important; } /* Naranja */

    /* Opcional: Si quieres que el texto dentro de la ficha también cambie de color según el estado */
    .reserva-card[data-estado="parcial"] {
        border: 2px solid #FFC107;
    }
  
    /* Panel lateral - CORREGIDO */
        .detail-panel {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        position: sticky;
        top: 120px;
        align-self: flex-start;
        height: fit-content;
        max-height: calc(100vh - 140px);
        overflow: visible;
        }

        .detail-section {
        background: white;
        padding: 1rem;
        border-radius: 12px;
        width: 100%;
        overflow-y: auto;
        max-height: 350px; /* Aumentado para más espacio */
        }

        .actions-section {
        background: white;
        padding: 1rem;
        border-radius: 12px;
        width: 100%;
        overflow-y: auto;
        max-height: 320px; /* Aumentado para que quepan todas las opciones */
        }
    
    .detail-title {
        color: #071289;
        margin-bottom: 1rem;
        font-size: 1.2rem;
    }
    
    .detail-item {
        margin-bottom: 0.5rem;
    }
    
    .detail-label {
        font-weight: bold;
        color: #333;
    }
    
    .actions-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.5rem;
        color: black;
    }
    
    .action-btn {
        padding: 0.5rem;
        border: none;
        border-radius: 6px;
        font-weight: bold;
        cursor: pointer;
        text-align: left;
        transition: background 0.2s;
        color: #333; /* Texto negro por defecto */
        }

        .action-btn:hover {
        background: rgba(255,255,255,0.2);
        color: #000; /* Texto negro en hover */
        }
  
    .btn-anular { background: #F44336; color: white; }
    .btn-cancelar { background: #FF9800; color: white; }
    .btn-cambiar { background: #2196F3; color: white; }
    .btn-mensaje { background: #4CAF50; color: white; }
    .btn-campeonato { background: #00cc66; color: white; }
  
    /* Estilos base del Submodal */
    .submodal {
        display: none;
        position: fixed;
        z-index: 3000; /* Muy alto para estar encima de todo */
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.7); /* Fondo oscuro semitransparente */
        backdrop-filter: blur(5px); /* Efecto vidrio */
        justify-content: center;
        align-items: center;
    }

    .submodal-content {
        background-color: #fefefe;
        margin: auto;
        padding: 20px;
        border: 1px solid #888;
        width: 90%;
        max-width: 600px; /* Ancho máximo cómodo */
        border-radius: 16px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        position: relative;
        animation: slideDown 0.3s ease-out;
    }
  
    /* Botón X de cierre */
    .close-modal {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
        transition: 0.2s;
    }

    .close-modal:hover,
    .close-modal:focus {
        color: #000;
        text-decoration: none;
        cursor: pointer;
    }
    
    /* Menú desplegable dentro del modal */
    .action-dropdown-menu {
        background: white;
        border: 1px solid #ddd;
        border-radius: 8px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        z-index: 10;
    }

    .dropdown-item {
        width: 100%;
        padding: 12px;
        text-align: left;
        background: none;
        border: none;
        border-bottom: 1px solid #eee;
        cursor: pointer;
        font-size: 0.95rem;
        color: #333;
    }

    .dropdown-item:hover {
        background-color: #f1f1f1;
        color: #071289;
    }

    .btn-pay-action {
        background-color: #e8f5e9 !important;
        color: #2e7d32 !important;
        font-weight: bold;
    }
    /* Responsive Móvil */
    @media (max-width: 600px) {
        .submodal-content {
            width: 95%;
            padding: 15px;
            max-height: 90vh;
            overflow-y: auto;
        }      
        .close-modal {
            font-size: 24px;
            top: 10px;
            right: 10px;
        }
        .reservas-grid {
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        } 
        .detail-panel {
            position: static;
            top: auto;
            align-self: auto;
        }
    }
    /* Toast Notifications */
    .toast {
    position: fixed;
    bottom: 20px;
    right: 20px;
    padding: 12px 20px;
    border-radius: 8px;
    color: white;
    font-weight: bold;
    z-index: 10000;
    transform: translateX(120%);
    transition: transform 0.3s ease-in-out;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    }

    .toast.show {
    transform: translateX(0);
    }

    .toast.success {
    background: linear-gradient(135deg, #4CAF50, #2E7D32);
    }

    .toast.error {
    background: linear-gradient(135deg, #F44336, #C62828);
    }

    .toast.warning {
    background: linear-gradient(135deg, #FF9800, #EF6C00);
    }

    .toast.info {
    background: linear-gradient(135deg, #2196F3, #1565C0);
    }
    /* === AJUSTES SUBMODAL DETALLE Y ACCIONES === */

    /* Submodal: Altura dinámica y centrado vertical */
    .submodal {
        display: none; /* Se controla con JS */
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.6);
        backdrop-filter: blur(4px);
        justify-content: center;
        align-items: center; /* Centrado vertical perfecto */
        z-index: 2000;
    }

    .submodal-content {
        background: white;
        padding: 2rem;
        border-radius: 16px;
        width: 90%;
        max-width: 550px; /* Un poco más ancho para mejor lectura */
        max-height: 85vh; /* Altura máxima: 85% de la pantalla */
        overflow-y: auto; /* Scroll interno si el contenido es muy largo */
        position: relative;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        animation: fadeIn 0.3s ease-out;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Alineación de Títulos y Botón Acciones */
    .detail-header-container {
        display: flex;
        justify-content: space-between;
        align-items: center; /* Alineación vertical perfecta */
        margin-bottom: 1.5rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid #eee;
    }

    .detail-title {
        font-size: 1.3rem;
        color: #071289;
        margin: 0;
        font-weight: bold;
    }

    /* === BARRA DE FILTROS RESPONSIVE (MÓVIL/PWA) === */
    .controls-section {
        display: flex;
        gap: 0.8rem;
        padding: 0.8rem;
        background: rgba(255,255,255,0.95);
        border-radius: 10px;
        position: sticky;
        top: 70px; /* Justo debajo del header fijo */
        z-index: 999;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        flex-wrap: wrap; /* Permite que bajen si no caben */
        align-items: center;
    }

    .control-select {
        flex: 1; /* Ocupan espacio equitativo */
        min-width: 120px; /* Ancho mínimo para que se lean */
        padding: 0.6rem;
        border-radius: 6px;
        border: 1px solid #ccc;
        font-size: 0.9rem;
        background: white;
        color: #071289;
    }
    /* === AJUSTES ESPECÍFICOS MÓVIL PARA SUBMODAL DETALLE === */
    @media (max-width: 768px) {
        
        /* Forzar que los contenedores internos usen Grid de 2 columnas */
        #contenidoDetalle > div > div[style*="grid-template-columns"],
        #contenidoDetalle > div > div:nth-child(1), 
        #contenidoDetalle > div > div:nth-child(2) {
            display: grid !important;
            grid-template-columns: 1fr 1fr !important; /* 2 Columnas exactas */
            gap: 0.8rem !important;
            margin-bottom: 1rem !important;
        }

        /* Ajuste específico para la sección de "Información del Cliente" */
        /* Buscamos el div que contiene el h4 "Información del Cliente" y sus siguientes p */
        #contenidoDetalle h4 + div, 
        #contenidoDetalle h4 ~ p {
            /* Si están dentro de un contenedor padre, aplicamos grid al padre */
            display: block; /* Reset por si acaso */
        }
        
        /* Truco CSS: Convertir los párrafos del cliente en una grilla de 2 columnas */
        #contenidoDetalle h4 {
            grid-column: 1 / -1 !important; /* Título ocupa todo el ancho */
            margin-top: 1rem !important;
            border-bottom: 2px solid #e0e0e0 !important;
            padding-bottom: 0.5rem !important;
            font-size: 1rem !important;
            color: #071289 !important;
        }

        /* Seleccionamos los párrafos dentro de la sección de cliente para ponerlos en 2 columnas */
        #contenidoDetalle h4 ~ p {
            margin: 0.2rem 0 !important;
            font-size: 0.9rem !important;
            line-height: 1.4 !important;
        }
        
        /* Creamos un contenedor wrapper invisible vía JS o CSS Grid directo en los hijos */
        /* Como el HTML es generado dinámicamente, usaremos una regla general para los primeros bloques */
        
        /* REDEFINICIÓN DIRECTA DEL CONTENIDO GENERADO POR JS PARA MÓVIL */
        /* Esto sobrescribe los estilos inline del JS solo en móvil */
        
        /* 1. Bloque superior (Fecha/Hora/Cancha/Deporte) */
        #contenidoDetalle > div > div:first-child {
            display: grid !important;
            grid-template-columns: 1fr 1fr !important;
            gap: 0.6rem !important;
            background: #f0f4f8 !important;
            padding: 0.8rem !important;
            border-radius: 8px !important;
            margin-bottom: 1rem !important;
        }
        
        /* Asegurar que cada hijo ocupe una celda */
        #contenidoDetalle > div > div:first-child > div {
            width: 100% !important;
        }

        /* 2. Bloque Cliente (Nombre/Teléfono/Email/Club) */
        /* El JS genera un div contenedor para el cliente. Lo forzamos a grid */
        #contenidoDetalle > div > div:nth-child(2) {
            display: grid !important;
            grid-template-columns: 1fr 1fr !important; /* 2 Columnas */
            gap: 0.6rem !important;
            margin-bottom: 1rem !important;
            background: transparent !important; /* Sin fondo extra si no se desea */
            padding: 0 !important;
        }
        
        /* El título del cliente debe ocupar las 2 columnas */
        #contenidoDetalle > div > div:nth-child(2) h4 {
            grid-column: 1 / -1 !important;
            margin-bottom: 0.5rem !important;
            font-size: 0.95rem !important;
        }
        
        /* Los párrafos del cliente */
        #contenidoDetalle > div > div:nth-child(2) p {
            margin: 0.1rem 0 !important;
            font-size: 0.85rem !important;
        }

        /* 3. Bloque Monto y Estados (Mantener layout original pero centrado) */
        /* Este bloque suele ser el 3ro o 4to hijo dependiendo de notas */
        #contenidoDetalle > div > div[style*="grid-template-columns: 1fr 1fr"] {
            /* Ya viene en 2 columnas desde el JS, solo aseguramos que se vea bien */
            gap: 0.8rem !important;
            margin-top: 1rem !important;
        }
        
        /* Ajuste interno para que Monto y Estados se vean apilados verticalmente dentro de su celda */
        #contenidoDetalle > div > div[style*="grid-template-columns: 1fr 1fr"] > div {
            display: flex !important;
            flex-direction: column !important;
            justify-content: center !important;
            align-items: center !important;
            text-align: center !important;
            padding: 0.8rem !important;
        }
    }
    /* === AJUSTES ESPECÍFICOS PARA MÓVIL / PWA === */
    @media (max-width: 768px) {
        
        /* 1. Contenedor Principal: Que ocupe todo el ancho disponible */
        .dashboard-container {
            width: 100%;
            max-width: 100%;
            padding: 0; /* Quitamos padding lateral para aprovechar pantalla */
            grid-template-columns: 1fr !important; /* Forzar una sola columna */
            gap: 0;
        }

        /* 2. Barra de Filtros: Scroll horizontal suave y compacta */
        .controls-section {
            width: 100%;
            overflow-x: auto; /* Permite scroll horizontal si no caben */
            white-space: nowrap; /* Evita que bajen de línea */
            flex-wrap: nowrap;
            padding: 0.5rem;
            gap: 0.5rem;
            -webkit-overflow-scrolling: touch; /* Scroll suave en iOS */
            scrollbar-width: thin; /* Firefox */
        }
        
        /* Estilo de la barra de scroll en filtros (opcional, para que se vea fina) */
        .controls-section::-webkit-scrollbar {
            height: 4px;
        }
        .controls-section::-webkit-scrollbar-thumb {
            background: #ccc;
            border-radius: 4px;
        }

        .control-select {
            flex: 0 0 auto; /* No crecer ni encoger demasiado */
            width: auto;
            min-width: 130px; /* Ancho mínimo cómodo para leer */
            font-size: 0.85rem;
            padding: 0.5rem;
        }

        /* 3. Submodal Detalle: Ajuste de tamaño y fuente para móvil */
        .submodal-content {
            width: 92% !important; /* Casi todo el ancho */
            max-width: 92% !important;
            padding: 1.2rem !important; /* Menos padding interno */
            margin: 1rem auto;
            font-size: 0.85rem !important; /* Texto base más pequeño */
            max-height: 90vh; /* Altura máxima */
            overflow-y: auto;
        }

        /* Títulos más pequeños en móvil */
        .submodal-content h3 {
            font-size: 1.2rem !important;
            margin-bottom: 1rem !important;
            padding-right: 30px; /* Espacio para la X */
        }

        /* Botón X más grande y accesible en móvil */
        .close-modal {
            font-size: 32px !important;
            top: 10px !important;
            right: 10px !important;
            line-height: 1;
            padding: 5px;
            z-index: 100;
        }

        /* Grids internos del detalle: Una columna en móvil */
        .submodal-content div[style*="grid-template-columns"] {
            grid-template-columns: 1fr !important; /* Forzar 1 columna */
            gap: 0.8rem !important;
        }

        /* Inputs y Selects más grandes para tocar fácil */
        select, input[type="text"], input[type="number"] {
            font-size: 16px !important; /* Evita zoom automático en iOS */
            padding: 0.7rem !important;
        }

        /* Botones de acciones dentro del modal */
        #btnAccionesModal {
            font-size: 0.9rem;
            padding: 0.7rem;
        }
        
        .dropdown-item {
            font-size: 0.95rem;
            padding: 1rem; /* Más área de toque */
        }
    }

    /* Ajuste extra para pantallas muy pequeñas (< 360px) */
    @media (max-width: 360px) {
        .control-select {
            min-width: 110px;
            font-size: 0.8rem;
        }
        .submodal-content {
            width: 95% !important;
            padding: 1rem !important;
        }
    }

    .estado-pagado { background: #607D8B !important; } /* Gris azulado para "Pagado" */

    /* Estilos específicos para la Planilla */
    .planilla-table th, .planilla-table td {
        border: 1px solid #ddd;
        text-align: center;
        user-select: none;
    }

    .planilla-table th {
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .planilla-table td:first-child, .planilla-table th:first-child {
        position: sticky;
        left: 0;
        z-index: 5; /* Mayor que las celdas normales, menor que el corner */
        background: #f8f9fa;
        border-right: 2px solid #ccc;
    }

    .planilla-table thead th:first-child {
        z-index: 20; /* Esquina superior izquierda */
        background: #071289;
    }

    /* Hover effect */
    .planilla-table tbody td:hover {
        filter: brightness(0.95);
    }

    /* Estilos específicos para controles de fecha en planilla */
    #fechaPlanillaInput::-webkit-calendar-picker-indicator {
        cursor: pointer;
        filter: invert(0.5); /* Hace el icono del calendario visible sobre fondo claro */
    }

    button:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 8px rgba(0,0,0,0.2) !important;
    }

    /* Asegurar que las celdas de la tabla tengan cursor pointer */
    .planilla-table tbody td[style*="cursor:pointer"] {
        transition: background 0.2s;
    }
    .planilla-table tbody td[style*="cursor:pointer"]:hover {
        filter: brightness(0.9);
        z-index: 2;
        position: relative;
    }

    /* Líneas blancas para la planilla */
    .planilla-table th, 
    .planilla-table td {
        border: 1px solid rgba(255, 255, 255, 0.6) !important; /* Líneas blancas semitransparentes */
        border-collapse: collapse;
    }

    /* Color texto grafito para la columna Horario (primera columna) */
    .planilla-table td:first-child, 
    .planilla-table th:first-child {
        color: #333333 !important; /* Grafito oscuro */
        font-weight: bold;
        background: #f8f9fa !important; /* Fondo claro para contraste */
        border-right: 2px solid #ddd !important;
    }

    /* Ajuste del header de la tabla (Nombres de Canchas) */
    .planilla-table thead th {
        background: #AB47BC !important; /* Color lila para cabecera de columnas */
        color: white !important;
        padding: 10px;
    }

    /* Scrollbar personalizado para que combine */
    #vistaPlanilla div[style*="overflow:auto"]::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }
    #vistaPlanilla div[style*="overflow:auto"]::-webkit-scrollbar-track {
        background: #f1f1f1; 
    }
    #vistaPlanilla div[style*="overflow:auto"]::-webkit-scrollbar-thumb {
        background: #BA68C8; 
        border-radius: 4px;
    }
    #vistaPlanilla div[style*="overflow:auto"]::-webkit-scrollbar-thumb:hover {
        background: #8E24AA; 
    }
</style>
</head>
<body>
    <div class="header">
        <div class="main-title-section">
        <div class="logo-corporativo">⚽</div>
        <h1 class="main-title">Cancha</h1>
        </div>
        <div>
        <a href="recinto_dashboard.php" style="color: #ffcc00; text-decoration: none;">← Dashboard</a>
        </div>
    </div>
    
    <!-- Contenedor Principal que ocupa toda la pantalla pero centra el contenido -->
    <div class="dashboard-container" style="display:flex; justify-content:center; align-items:flex-start; min-height:100vh; padding-top:80px; background: transparent;">
        
        <!-- Tercio Central: Aquí va todo el contenido -->
        <div style="width: 100%; max-width: 1400px; display:flex; flex-direction:column; gap:1rem;">
            
            <!-- BARRA DE FILTROS SUPERIOR (Sticky) -->
            <div style="background: rgba(20, 20, 40, 0.95); backdrop-filter: blur(10px); padding: 1rem; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); position: sticky; top: 80px; z-index: 100; border: 1px solid rgba(255,255,255,0.1);">
                <div style="display:flex; flex-wrap:wrap; gap:1rem; align-items:center; justify-content:space-between;">
                    
                    <!-- Izquierda: Filtros Deporte y Estado -->
                    <div style="display:flex; gap:0.8rem; flex:1; min-width: 200px;">
                        <!-- Filtro Deporte (Ahora opcional, default 'Todos') -->
                        <select class="control-select" id="filtroDeporte" style="flex:1; background:rgba(255,255,255,0.1); color:white; border:1px solid rgba(255,255,255,0.2);">
                            <option value="">Todos los deportes</option>
                            <option value="futbol">Fútbol</option>
                            <option value="futbolito">Futbolito</option>
                            <option value="futsal">Futsal</option>
                            <option value="tenis">Tenis</option>
                            <option value="padel">Pádel</option>
                            <option value="voleyball">Voleyball</option>
                            <option value="otro">Quincho/Otro</option>
                        </select>
                        
                        <select class="control-select" id="filtroEstado" style="flex:1; background:rgba(255,255,255,0.1); color:white; border:1px solid rgba(255,255,255,0.2);">
                            <option value="">Todos los estados</option>
                            <option value="disponible">Disponible</option>
                            <option value="reservada">Reservadas</option>
                            <option value="pagadas">Pagadas</option>
                            <option value="parcial">Pago Parcial</option>
                            <option value="ocupada">Ocupadas</option>
                            <option value="cancelada">Canceladas</option>
                        </select>
                    </div>

                    <!-- Derecha: Selector Radial -->
                    <div style="background:rgba(255,255,255,0.1); padding:0.3rem; border-radius:8px; display:flex; gap:0.5rem;">
                        <label style="display:flex; align-items:center; cursor:pointer; color:#aaa; font-weight:bold; padding:0.4rem 1rem; border-radius:6px; transition:0.3s;" id="lblFichas">
                            <input type="radio" name="vistaCalendario" value="fichas" onchange="cambiarVistaCalendario('fichas')" style="display:none;">
                            📋 Fichas
                        </label>
                        <label style="display:flex; align-items:center; cursor:pointer; color:white; font-weight:bold; padding:0.4rem 1rem; border-radius:6px; background:rgba(255,255,255,0.2); box-shadow:0 2px 5px rgba(0,0,0,0.2);" id="lblPlanilla">
                            <input type="radio" name="vistaCalendario" value="planilla" checked onchange="cambiarVistaCalendario('planilla')" style="display:none;">
                            Planilla
                        </label>
                    </div>
                </div>
            </div>

            <!-- VISTA: PLANILLA -->
            <div id="vistaPlanilla">
                
                <!-- Header Lila con Controles de Fecha CENTRADOS -->
                <div style="background: linear-gradient(90deg, #CE93D8 0%, #BA68C8 50%, #AB47BC 100%); padding: 1rem; border-radius: 12px 12px 0 0; display:flex; justify-content:center; align-items:center; color:white; box-shadow: 0 4px 10px rgba(186, 104, 200, 0.3); border-bottom: 2px solid rgba(255,255,255,0.2); position: relative;">
                    
                    <!-- Controles de Fecha (Centrados) -->
                    <div style="display:flex; align-items:center; gap:1rem; background: rgba(255,255,255,0.2); padding: 0.4rem 1.5rem; border-radius: 30px; backdrop-filter: blur(5px);">
                        
                        <span style="font-size:0.9rem; font-weight:600; margin-right:0.5rem;">Fecha:</span>
                        
                        <!-- Input Fecha -->
                        <input type="date" id="fechaPlanillaInput" value="<?= date('Y-m-d') ?>" 
                            style="background:transparent; border:none; outline:none; color:white; font-weight:bold; font-family:sans-serif; cursor:pointer; text-align:center; width: 140px;">
                        
                        <!-- Separador -->
                        <div style="width:1px; height:20px; background:rgba(255,255,255,0.5);"></div>

                        <!-- Botón Hoy -->
                        <button onclick="irAHoyPlanilla()" style="background:white; color:#8E24AA; border:none; padding:0.3rem 1rem; border-radius:20px; font-weight:bold; font-size:0.8rem; cursor:pointer; transition:0.2s;">
                            Hoy
                        </button>

                        <!-- Botones < > -->
                        <div style="display:flex; gap:0.3rem;">
                            <button onclick="cambiarDiaPlanilla(-1)" style="width:28px; height:28px; border-radius:50%; background:rgba(255,255,255,0.9); border:none; color:#6A1B9A; font-weight:bold; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:0.9rem;">&lt;</button>
                            <button onclick="cambiarDiaPlanilla(1)" style="width:28px; height:28px; border-radius:50%; background:rgba(255,255,255,0.9); border:none; color:#6A1B9A; font-weight:bold; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:0.9rem;">&gt;</button>
                        </div>
                    </div>
                </div>

                <!-- Tabla Planilla (Scrollable, Fondo Blanco) -->
                <div style="overflow:auto; background:white; border-radius:0 0 12px 12px; box-shadow:0 10px 20px rgba(0,0,0,0.1); max-height: 70vh;">
                    <table id="tablaPlanilla" class="planilla-table" style="width:100%; border-collapse:collapse; font-size:0.85rem; table-layout: fixed;">
                        <!-- Se llena con JS -->
                    </table>
                </div>
                
                <!-- Leyenda -->
                <div style="margin-top:1rem; display:flex; gap:1.5rem; color:white; justify-content:center; font-size:0.9rem; text-shadow: 0 1px 2px rgba(0,0,0,0.5);">
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <span style="width:12px; height:12px; background:#e0e0e0; border:1px solid #fff; border-radius:50%;"></span> Disponible
                    </div>
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <span style="width:12px; height:12px; background:#ffcdd2; border:1px solid #fff; border-radius:50%;"></span> Ocupado
                    </div>
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <span style="width:12px; height:12px; background:#a5d6a7; border:1px solid #fff; border-radius:50%;"></span> Pagado
                    </div>
                </div>
            </div>   
        </div>
    </div>
    
    <!-- === SUBMODAL CENTRAL DE DETALLE DE RESERVA === -->
    <div id="modalDetalleReserva" class="submodal" style="display:none;">
        <div class="submodal-content" style="max-width: 600px; padding: 2rem;">
            <!-- Botón Cerrar X -->
            <span class="close-modal" onclick="cerrarModalDetalle()" style="position:absolute; top:15px; right:15px; font-size:28px; cursor:pointer; color:#999; z-index:10;">&times;</span>
            
            <h3 style="color:#071289; margin-bottom:1.5rem; text-align:center; font-size:1.5rem;">📋 Detalle de Reserva</h3>
            
            <!-- Aquí se inyectará el contenido dinámico -->
            <div id="contenidoDetalle" style="color:#333; width: 100%; box-sizing: border-box;">
                <!-- Ejemplo de contenido dinámico -->
                <div style="margin-bottom:1rem;">
                    <strong>Cancha:</strong> Cancha 1 - Fútbol 5
                </div>
                <div style="margin-bottom:1rem;">
                    <strong>Fecha y Hora:</strong> 25 de Junio, 18:00 - 19:00
                </div>
                <div style="margin-bottom:1rem;">
                    <strong>Cliente:</strong> Juan Pérez
                </div>
                <div style="margin-bottom:1rem;">
                    <strong>Estado:</strong> Reservada
                </div>
                <div style="margin-bottom:1rem;">
                    <strong>Precio:</strong> $15.000
                </div>
                <div style="margin-bottom:1rem;">
                    <strong>Observaciones:</strong> Traer balón propio
                </div>
            </div>
            
            <!-- Botón de Acciones dentro del modal (Opcional, o usar el menú desplegable si prefieres) -->
            <div style="margin-top:2rem; border-top:1px solid #eee; padding-top:1rem; text-align:center;">
                <button id="btnAccionesModal" onclick="toggleActionMenuModal()" style="background:#071289; color:white; border:none; padding:0.6rem 1.5rem; border-radius:8px; cursor:pointer; font-weight:bold; width:100%;">
                    ⚙️ Opciones de Gestión
                </button>
                
                <!-- Menú de acciones dentro del modal -->
                <div id="actionMenuModal" class="action-dropdown-menu" style="display:none; position:relative; top:5px; left:0; right:0; margin:0 auto; width:100%; box-shadow:0 4px 10px rgba(0,0,0,0.1);">
                    <button class="dropdown-item" onclick="anularReserva()">🗑️ Anular Reserva</button>
                    <button class="dropdown-item" onclick="cancelarReserva()"> Cancelar</button>
                    <button class="dropdown-item" onclick="cambiarCancha()">🔄 Cambiar Cancha</button>
                    <button class="dropdown-item" onclick="enviarMensaje()">💬 Enviar Mensaje</button>
                    <button id="btnPagarModal" class="dropdown-item btn-pay-action" style="display:none;" onclick="abrirModalPagoDesdeDetalle()">💳 Pagar Reserva</button>
                </div>
            </div>
        </div>
    </div>

    <!-- === SUBMODAL DE PAGO (ACTUALIZADO) === -->
    <div id="modalPago" class="submodal" style="display:none;">
        <div class="submodal-content" style="max-width: 500px;">
            <!-- Botón Cerrar X -->
            <span class="close-modal" onclick="cerrarModalPago()" style="position:absolute; top:15px; right:15px; font-size:28px; cursor:pointer; color:#999;">&times;</span>
            
            <h3 style="color:#071289; margin-bottom:1rem; text-align:center;">💳 Registrar Pago</h3>
            
            <!-- Información Base (Solo lectura) -->
            <div style="margin-bottom:1rem; font-size:0.9rem; color:#555; background:#f8f9fa; padding:10px; border-radius:6px; text-align:center;">
                <strong>Reserva ID:</strong> <span id="infoIdReserva"></span><br>
                <strong>Monto Total Arriendo:</strong> <span id="infoMontoTotal" style="font-weight:bold; color:#071289;"></span>
            </div>
            
            <form id="formPago">
                <!-- CAMPO MONTO EDITABLE -->
                <div class="form-group" style="margin-bottom:1rem;">
                    <label style="font-weight:bold; display:block; margin-bottom:0.3rem; color:#333;">💰 Monto a Abonar ($)</label>
                    <input type="number" id="montoPagar" name="monto_pagar" step="100" required 
                        style="width:100%; padding:0.8rem; border-radius:6px; border:2px solid #4CAF50; font-size:1.2rem; font-weight:bold; color:#2e7d32; text-align:right;">
                    <small style="color:#666; font-size:0.8rem;">* Puedes ingresar un pago parcial (ej: $7.500)</small>
                </div>

                <!-- MÉTODO DE PAGO -->
                <div class="form-group" style="margin-bottom:1rem;">
                    <label style="font-weight:bold; display:block; margin-bottom:0.3rem; color:#333;">Método de Pago</label>
                    <select name="metodo_pago" id="metodoPago" required style="width:100%; padding:0.6rem; border-radius:6px; border:1px solid #ccc; background:white; color:#333;">
                        <option value="">Seleccionar...</option>
                        <option value="transferencia">Transferencia Bancaria</option>
                        <option value="webpay">Webpay / Tarjeta</option>
                        <option value="efectivo">Efectivo en Recinto</option>
                        <option value="convenio">Convenio Club</option>
                    </select>
                </div>
                
                <!-- ID TRANSACCIÓN (Opcional según método) -->
                <div id="campoTransaccion" class="form-group" style="display:none; margin-bottom:1rem;">
                    <label style="font-weight:bold; display:block; margin-bottom:0.3rem; color:#333;">Comprobante / ID Transacción</label>
                    <input type="text" name="transaccion_id" id="transaccionId" placeholder="Ej: 123456789" style="width:100%; padding:0.6rem; border-radius:6px; border:1px solid #ccc;">
                </div>

                <!-- CAMPO NOTAS (NUEVO) -->
                <div class="form-group" style="margin-bottom:1.5rem;">
                    <label style="font-weight:bold; display:block; margin-bottom:0.3rem; color:#333;"> Notas del Pago</label>
                    <textarea name="notas_pago" id="notasPago" rows="3" placeholder="Ej: Pago parcial de Juan Pérez (1/4). Faltan 3 socios." 
                            style="width:100%; padding:0.6rem; border-radius:6px; border:1px solid #ccc; resize:vertical; font-family:sans-serif;"></textarea>
                </div>
                
                <button type="submit" class="btn-submit" style="width:100%; background:#4CAF50; color:white; border:none; padding:0.8rem; border-radius:8px; font-weight:bold; cursor:pointer; font-size:1rem;">
                    Confirmar Registro de Pago
                </button>
            </form>
        </div>
    </div>

    <!-- NOTA: El panel lateral derecho (.detail-panel) ya NO es necesario. Puedes eliminarlo o dejarlo oculto. -->
    <!-- Si lo eliminas, recuerda ajustar el grid principal para que ocupe todo el ancho o centrar la grilla. -->

    <!-- Submodal para mensaje - CORREGIDO -->
    <div id="mensajeModal" class="submodal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); justify-content:center; align-items:center; z-index:1001;">
        <div class="submodal-content" style="background:white; padding:2rem; border-radius:16px; max-width:500px; position:relative;">
            <span class="close-modal" onclick="closeMensajeModal()" style="position:absolute; top:15px; right:15px; font-size:28px; cursor:pointer;">&times;</span>
            <h3>Enviar Mensaje</h3>
            <form id="mensajeForm">
            <div class="form-group">
                <label for="mensajeTexto">Mensaje *</label>
                <textarea id="mensajeTexto" name="mensaje" rows="4" required style="width:100%; padding:0.6rem; border:1px solid #ccc; border-radius:5px; color:#071289;"></textarea>
            </div>
            <button type="submit" class="btn-submit" style="width:100%;">Enviar Mensaje y Correo</button>
            </form>
        </div>
    </div>

  <script>
    let reservaSeleccionada = null;
    let reservasData = [];

    // Definir todas las funciones primero
    function renderizarReservas(reservas) {
        const grid = document.getElementById('reservasGrid');
        
        if (reservas.length === 0) {
            grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 2rem; color: white;">No hay disponibilidad en el período seleccionado</div>';
            return;
        }
        
        // Agrupar por fecha
        const reservasPorFecha = {};
        reservas.forEach(reserva => {
            const fecha = reserva.fecha;
            if (!reservasPorFecha[fecha]) {
                reservasPorFecha[fecha] = [];
            }
            reservasPorFecha[fecha].push(reserva);
        });
        
        grid.innerHTML = '';
        
        // Renderizar por fechas
        Object.keys(reservasPorFecha).sort().forEach(fecha => {
            const fechaDiv = document.createElement('div');
            fechaDiv.style.gridColumn = '1/-1';
            fechaDiv.style.marginTop = '1.5rem';
            fechaDiv.style.paddingBottom = '0.5rem';
            fechaDiv.style.borderBottom = '1px solid rgba(255,255,255,0.2)';
            fechaDiv.style.color = '#FFD700';
            fechaDiv.style.fontWeight = 'bold';
            fechaDiv.textContent = formatDateDisplay(fecha);
            grid.appendChild(fechaDiv);
            
            reservasPorFecha[fecha].forEach(reserva => {
                const card = document.createElement('div');
                card.className = 'reserva-card';
                card.onclick = () => selectReserva(reserva.id_disponibilidad || `${reserva.id_cancha}_${reserva.fecha}_${reserva.hora_inicio}`);
                
                const iconos = {
                    'futbol': '⚽', 'futbolito': '⚽', 'futsal': '⚽',
                    'tenis': '', 'padel': '🎾', 'voleyball': '',
                    'otro': '🏟️'
                };
                
                // Estado base (Disponible, Reservada, etc.)
                const estadoBase = reserva.estado_disponibilidad || (reserva.id_reserva ? 'reservada' : 'disponible');
                const estadoClass = getEstadoClass(estadoBase);
                const estadoTexto = getEstadoTexto(estadoBase);
                
                // === NUEVA LÓGICA: ESTADO DE PAGO Y ABONOS ===
                let badgePagoHTML = '';
                let abonoHTML = '';
                
                // Solo si es una reserva real y tiene estado de pago
                if (reserva.id_reserva && reserva.estado_pago) {
                    let colorBadge = '#6c757d'; // Gris
                    let textoBadge = reserva.estado_pago.toUpperCase();
                    let colorTextoBadge = '#fff';
                    
                    if (reserva.estado_pago === 'pagado') { 
                        colorBadge = '#28a745'; // Verde
                    } else if (reserva.estado_pago === 'parcial') { 
                        colorBadge = '#ffc107'; // Amarillo
                        colorTextoBadge = '#000'; // Texto negro para contraste
                    } else if (reserva.estado_pago === 'pendiente') { 
                        colorBadge = '#dc3545'; // Rojo
                    }
                    
                    // Badge pequeño al lado del estado
                    badgePagoHTML = `
                        <span style="
                            display: inline-block; 
                            margin-left: 6px; 
                            padding: 2px 6px; 
                            border-radius: 4px; 
                            background: ${colorBadge}; 
                            color: ${colorTextoBadge}; 
                            font-size: 0.7rem; 
                            font-weight: bold;
                            vertical-align: middle;
                            border: 1px solid rgba(0,0,0,0.1);
                        ">
                            ${textoBadge}
                        </span>
                    `;

                    // Fila de Abono si hay dinero
                    if (reserva.monto_recaudacion && parseFloat(reserva.monto_recaudacion) > 0) {
                        abonoHTML = `
                            <div style="
                                margin-top: 8px; 
                                padding-top: 6px; 
                                border-top: 1px dashed rgba(255,255,255,0.4); 
                                font-size: 0.8rem; 
                                display: flex; 
                                justify-content: space-between; 
                                align-items: center;
                                color: #e0e0e0;
                            ">
                                <span>💰 Abono:</span>
                                <span style="font-weight: bold; color: #4ECDC4;">
                                    $${parseInt(reserva.monto_recaudacion).toLocaleString()}
                                </span>
                            </div>
                        `;
                    }
                }
                // ==========================================

                card.innerHTML = `
                    <div class="deporte-icon" style="font-size: 1.5rem; margin-bottom: 0.5rem;">${iconos[reserva.id_deporte] || '️'}</div>
                    <div class="cancha-nombre" style="font-weight:bold; font-size:1rem; margin-bottom:0.1rem; color:white;">${reserva.nro_cancha || 'Cancha'}</div>
                    <div class="fecha-hora" style="font-size:0.9rem; opacity:0.9; margin-bottom:0.5rem;">
                        ${formatTimeDisplay(reserva.hora_inicio)}<br>
                        <span style="display:flex; align-items:center;">
                            ${estadoTexto}
                            ${badgePagoHTML}
                        </span>
                    </div>
                    <div class="estado-indicator ${estadoClass}" style="width:10px; height:10px; border-radius:50%; display:inline-block; margin-right:5px;"></div>
                    ${reserva.nombre_responsable ? `<div style="font-size:0.8rem; color:#aaa; margin-top:4px;">👤 ${reserva.nombre_responsable}</div>` : ''}
                    ${abonoHTML}
                `;
                
                grid.appendChild(card);
            });
        });
    }

    function formatDateDisplay(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        const options = { weekday: 'long', day: 'numeric', month: 'long' };
        return date.toLocaleDateString('es-ES', options);
    }

    function formatTimeDisplay(timeString) {
        if (!timeString) return 'N/A';
        return timeString.substring(0, 5);
    }

    function getEstadoTexto(estado) {
        const estados = {
            'disponible': 'Disponible',
            'reservada': 'Reservada',
            'ocupada': 'Ocupada',
            'cancelada': 'Cancelada',
            'mantencion': 'Mantención',
            'parcial': 'Pago Parcial',
            'pagado': 'Pagada' // Nuevo texto
        };
        return estados[estado] || estado;
    }

    function getEstadoClass(estado) {
        switch(estado) {
            case 'disponible': return 'estado-disponible'; // Verde
            case 'reservada': return 'estado-reservada';   // Azul
            case 'ocupada': return 'estado-ocupada';       // Morado
            case 'cancelada': return 'estado-cancelada';   // Rojo
            case 'parcial': return 'estado-parcial';       // Amarillo
            case 'pagado': return 'estado-pagado';         // Verde oscuro o Gris (nuevo)
            case 'mantencion': return 'estado-mantencion'; // Naranja
            default: return 'estado-disponible';
        }
    }

    // === VARIABLES GLOBALES ===
    let reservaActualSeleccionada = null; // Guardamos el objeto completo de la reserva seleccionada

    // === FUNCIÓN PARA ABRIR EL MODAL DE DETALLE ===
    function selectReserva(id) {
        // Buscar datos en el array cargado
        const selectedReserva = reservasData.find(r => 
            (r.id_disponibilidad && r.id_disponibilidad.toString() === id.toString()) || 
            (`${r.id_cancha}_${r.fecha}_${r.hora_inicio}` === id.toString())
        );
        
        if (!selectedReserva) return;
        
        reservaActualSeleccionada = selectedReserva;
        
        // Renderizar contenido
        renderizarContenidoDetalle(selectedReserva);
        
        // Mostrar Modal
        document.getElementById('modalDetalleReserva').style.display = 'flex';
    }

    // === CERRAR MODALES ===
    function cerrarModalDetalle() {
        document.getElementById('modalDetalleReserva').style.display = 'none';
        document.getElementById('actionMenuModal').style.display = 'none'; // Ocultar menú si estaba abierto
    }

    function cerrarModalPago() {
        // Ocultar modal de pago
        document.getElementById('modalPago').style.display = 'none';
        
        // Si hay una reserva seleccionada, volver a mostrar el modal de detalle
        if (reservaActualSeleccionada) {
            document.getElementById('modalDetalleReserva').style.display = 'flex';
            // Opcional: Volver a renderizar por si hubo cambios
            // renderizarContenidoDetalle(reservaActualSeleccionada); 
        } else {
            // Si no hay reserva, cerrar todo y volver al calendario
            document.getElementById('modalDetalleReserva').style.display = 'none';
        }
    }

    // === MENÚ DE ACCIONES DENTRO DEL MODAL ===
    function toggleActionMenuModal() {
        const menu = document.getElementById('actionMenuModal');
        menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
    }

    // === ABRIR MODAL DE PAGO DESDE EL DETALLE (ACTUALIZADO) ===
    function abrirModalPagoDesdeDetalle() {
        if (!reservaActualSeleccionada) return;
        
        // Ocultar menú de acciones
        document.getElementById('actionMenuModal').style.display = 'none';
        
        const idReserva = reservaActualSeleccionada.id_reserva;
        const montoTotal = parseFloat(reservaActualSeleccionada.monto_total);
        
        // Llenar información base
        document.getElementById('infoIdReserva').textContent = idReserva;
        document.getElementById('infoMontoTotal').textContent = '$' + montoTotal.toLocaleString();
        
        // PRE-LLENAR EL MONTO CON EL TOTAL (pero editable)
        const inputMonto = document.getElementById('montoPagar');
        inputMonto.value = montoTotal; 
        
        // Resetear otros campos
        document.getElementById('formPago').dataset.idReserva = idReserva;
        document.getElementById('formPago').dataset.montoOriginal = montoTotal; // Guardamos el original para comparar
        document.getElementById('formPago').reset();
        document.getElementById('montoPagar').value = montoTotal; // Restaurar valor tras el reset
        document.getElementById('campoTransaccion').style.display = 'none';
        
        // Cerrar modal detalle y abrir pago
        cerrarModalDetalle();
        document.getElementById('modalPago').style.display = 'flex';
    }

    // === LISTENER PARA MÉTODO DE PAGO (Igual que antes) ===
    document.getElementById('metodoPago')?.addEventListener('change', function() {
        const campo = document.getElementById('campoTransaccion');
        const input = document.getElementById('transaccionId');
        if (['transferencia', 'webpay'].includes(this.value)) {
            campo.style.display = 'block';
            input.required = true;
        } else {
            campo.style.display = 'none';
            input.required = false;
        }
    });

    // === SUBMIT DEL FORMULARIO DE PAGO (LÓGICA MEJORADA) ===
    document.getElementById('formPago')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const idReserva = this.dataset.idReserva;
        const montoOriginal = parseFloat(this.dataset.montoOriginal);
        const montoPagado = parseFloat(document.getElementById('montoPagar').value);
        const metodo = document.getElementById('metodoPago').value;
        const transaccion = document.getElementById('transaccionId').value;
        const notas = document.getElementById('notasPago').value;

        // Validación básica
        if (montoPagado <= 0) {
            alert("⚠️ El monto a pagar debe ser mayor a 0.");
            return;
        }
        if (montoPagado > montoOriginal) {
            if(!confirm("⚠️ El monto ingresado ($" + montoPagado + ") es mayor al total del arriendo ($" + montoOriginal + "). ¿Deseas continuar?")) {
                return;
            }
        }

        try {
            const formData = new FormData();
            formData.append('action', 'procesar_pago_parcial'); // Nueva acción para diferenciar
            formData.append('id_reserva', idReserva);
            formData.append('monto_pagado', montoPagado);
            formData.append('monto_total_original', montoOriginal);
            formData.append('metodo_pago', metodo);
            formData.append('transaccion_id', transaccion || '');
            formData.append('notas_pago', notas);

            const res = await fetch('../api/gestion_reservas.php', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.success) {
                let msg = "✅ Pago registrado correctamente.";
                if (montoPagado < montoOriginal) {
                    msg += " La reserva queda con saldo pendiente.";
                }
                alert(msg);
                cerrarModalPago();
                location.reload(); // Recargar para ver cambios
            } else {
                alert("❌ Error: " + data.message);
            }
        } catch (err) {
            console.error(err);
            alert("❌ Error de conexión al procesar pago");
        }
    });

    // === CERRAR AL HACER CLICK FUERA ===
    window.onclick = function(event) {
        if (event.target == document.getElementById('modalDetalleReserva')) {
            cerrarModalDetalle();
        }
        if (event.target == document.getElementById('modalPago')) {
            cerrarModalPago();
        }
    }

    async function cargarDetalleReserva(id_disponibilidad, id_reserva) {
        if (!id_disponibilidad) {
            document.getElementById('detalleContent').innerHTML = '<p style="color:#FF9800;">⚠️ No hay detalles disponibles para este bloque (es solo disponibilidad).</p>';
            return;
        }

        console.log("📡 Solicitando detalle para ID Disponibilidad:", id_disponibilidad);

        try {
            const formData = new FormData();
            formData.append('id_disponibilidad', id_disponibilidad);
            if (id_reserva) formData.append('id_reserva', id_reserva);
            
            const response = await fetch('../api/canchaboard.php?action=get_detalle_reserva', {
                method: 'POST',
                body: formData
            });
            
            const detalle = await response.json();
            
            if (detalle.error) {
                throw new Error(detalle.error);
            }
            
            console.log("✅ Detalle recibido:", detalle);
            mostrarDetalleReserva(detalle);
            
        } catch (error) {
            console.error(' Error al cargar detalle:', error);
            document.getElementById('detalleContent').innerHTML = `<p style="color:#F44336;">Error: ${error.message}</p>`;
        }
    }

    function mostrarDetalleDisponibilidad(reserva) {
        if (!reserva) {
            document.getElementById('detalleContent').innerHTML = '<p>Disponibilidad básica</p>';
            return;
        }
        
        document.getElementById('detalleContent').innerHTML = `
            <div class="detail-item">
                <span class="detail-label">Cancha:</span> 
                <span>${reserva.nro_cancha || 'N/A'}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Deporte:</span> 
                <span>${reserva.id_deporte || 'N/A'}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Fecha/Hora:</span> 
                <span>${formatDateDisplay(reserva.fecha)} ${formatTimeDisplay(reserva.hora_inicio)}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Estado:</span> 
                <span>${getEstadoTexto(reserva.estado_disponibilidad || 'disponible')}</span>
            </div>
            <div class="detail-item" style="margin-top: 1rem; color: #00cc66; font-weight: bold;">
                ✅ Disponible para reservar
            </div>
        `;
    }

    async function anularReserva() {
        if (!validarReservaActiva()) return;
        
        if (confirm('¿Estás seguro de anular esta reserva? Esta acción no se puede deshacer.')) {
            try {
                const formData = new FormData();
                formData.append('action', 'anular');
                formData.append('id_disponibilidad', reservaSeleccionada);
                
                const response = await fetch('../api/gestion_reservas.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('✅ Reserva anulada correctamente', 'success');
                    cargarReservasConRango(0);
                    document.getElementById('detalleContent').innerHTML = '<p>Selecciona una reserva para ver detalles</p>';
                    reservaSeleccionada = null;
                } else {
                    throw new Error(result.message || 'Error al anular');
                }
                
            } catch (error) {
                console.error('Error al anular:', error);
                showToast(`❌ Error: ${error.message}`, 'error');
            }
        }
    }

    function cancelarReserva() {
        if (!validarReservaActiva()) return;
        alert('Funcionalidad de cancelación en desarrollo');
    }

    function cambiarCancha() {
        if (!validarReservaActiva()) return;
        alert('Funcionalidad de cambio de cancha en desarrollo');
    }

    function enviarMensaje() {
        if (!validarReservaActiva()) return;
        document.getElementById('mensajeModal').style.display = 'flex';
    }

    // La acción "Crear Campeonato" NO requiere validación de reserva
    function crearCampeonato() {
        window.location.href = 'crear_campeonato.php?id_recinto=<?= $id_recinto ?>';
    }

    function closeMensajeModal() {
        document.getElementById('mensajeModal').style.display = 'none';
    }

    async function enviarMensajeYCorreo(formData) {
        try {
            // Aquí iría la lógica para enviar notificación y correo
            // Por ahora simulamos el envío
            alert('Mensaje enviado y correo de respaldo enviado');
            closeMensajeModal();
        } catch (error) {
            console.error('Error al enviar mensaje:', error);
            alert('Error al enviar el mensaje: ' + error.message);
        }
    }

    function crearCampeonato() {
        window.location.href = 'crear_campeonato.php?id_recinto=<?= $id_recinto ?>';
    }

    function aplicarFiltros() {
        const deporte = document.getElementById('filtroDeporte').value;
        const estado = document.getElementById('filtroEstado').value;
        
        let datosFiltrados = [...reservasData];
        
        if (deporte) {
            datosFiltrados = datosFiltrados.filter(r => r.id_deporte === deporte);
        }
        
        if (estado) {
            datosFiltrados = datosFiltrados.filter(r => (r.estado_disponibilidad || 'disponible') === estado);
        }
        
        renderizarReservas(datosFiltrados);
    }

    // Función para verificar si hay una reserva real (no solo disponibilidad)
    function validarReservaActiva() {
        if (!reservaSeleccionada) {
            showToast('⚠️ Debes seleccionar una reserva primero', 'warning');
            return false;
        }
        
        // Buscar la reserva en los datos cargados
        const selectedReserva = reservasData.find(r => 
            r.id_disponibilidad == reservaSeleccionada || 
            (`${r.id_cancha}_${r.fecha}_${r.hora_inicio}` == reservaSeleccionada)
        );
        
        // Verificar si es una reserva real (tiene id_disponibilidad válido)
        if (!selectedReserva || !selectedReserva.id_disponibilidad || selectedReserva.id_disponibilidad === 'null') {
            showToast('⚠️ Para ejecutar esta acción debe existir una reserva activa', 'warning');
            return false;
        }
        
        // Verificar que el estado no sea cancelado
        if (selectedReserva.estado_reserva === 'cancelada') {
            showToast('⚠️ No se pueden ejecutar acciones sobre reservas canceladas', 'warning');
            return false;
        }
        
        return true;
    }

    // Ahora sí, cargar los datos iniciales
    async function cargarReservasConRango(rangoDias = 30) {
        try {
            const response = await fetch(`../api/canchaboard.php?action=get_reservas&rango_dias=${rangoDias}`);
            const data = await response.json();
            
            if (data.error) {
                throw new Error(data.error);
            }
            
            reservasData = data;
            renderizarReservas(reservasData);
            
        } catch (error) {
            console.error('Error al cargar reservas:', error);
            document.getElementById('reservasGrid').innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 2rem; color: white;">Error al cargar las reservas</div>';
        }
    }

    // Filtro por fecha: llama a la acción correcta con POST
    //document.getElementById('filtroFecha').addEventListener('change', function() {
    //    aplicarFiltrosConAPI();
    //});

    // Filtro por deporte
    document.getElementById('filtroDeporte').addEventListener('change', aplicarFiltrosConAPI);

    // Filtro por estado
    document.getElementById('filtroEstado').addEventListener('change', aplicarFiltrosConAPI);

    document.getElementById('mensajeForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const mensaje = document.getElementById('mensajeTexto').value.trim();
        
        if (!mensaje) {
            showToast('⚠️ El mensaje no puede estar vacío', 'warning');
            return;
        }
        
        // Simular envío
        showToast('✅ Mensaje enviado y correo de respaldo enviado', 'success');
        closeMensajeModal();
        document.getElementById('mensajeTexto').value = '';
    });

    // Cerrar modal con escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeMensajeModal();
        }
    });

    // Sistema de Toast Notifications
    function showToast(message, type = 'info') {
        // Eliminar toast anterior si existe
        const existingToast = document.querySelector('.toast');
        if (existingToast) {
            existingToast.remove();
        }
        
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.textContent = message;
        document.body.appendChild(toast);
        
        // Mostrar toast
        setTimeout(() => {
            toast.classList.add('show');
        }, 100);
        
        // Ocultar y eliminar después de 3 segundos
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }, 3000);
    }

    // Función de validación para acciones
    function validarReservaSeleccionada() {
        if (!reservaSeleccionada) {
            showToast('⚠️ Debes seleccionar una reserva primero', 'warning');
            return false;
        }
        return true;
    }
    // === FUNCIONES PARA MODAL DE PAGO ===
    function abrirModalPago() {
        if (!reservaSeleccionada) {
            showToast('⚠️ Selecciona una reserva primero', 'warning');
            return;
        }
        
        // Buscar reserva en datos cargados
        const reserva = reservasData.find(r => 
            r.id_disponibilidad == reservaSeleccionada || 
            `${r.id_cancha}_${r.fecha}_${r.hora_inicio}` == reservaSeleccionada
        );
        
        if (!reserva || !reserva.id_reserva) {
            showToast('⚠️ Esta ficha no corresponde a una reserva pagable', 'warning');
            return;
        }
        
        if (reserva.estado_pago === 'pagado') {
            showToast('✅ Esta reserva ya está pagada', 'info');
            return;
        }
        
        // Mostrar info de pago
        const infoPago = document.getElementById('infoPago');
        infoPago.innerHTML = `
            <strong>Cancha:</strong> ${reserva.nro_cancha || 'N/A'}<br>
            <strong>Fecha:</strong> ${formatDateDisplay(reserva.fecha)} ${formatTimeDisplay(reserva.hora_inicio)}<br>
            <strong>Monto:</strong> $${parseInt(reserva.monto_total || 0).toLocaleString()}<br>
            <strong>Estado:</strong> <span style="color:#FF9800;">Pendiente</span>
        `;
        
        // Mostrar/ocultar campo de transacción según método
        document.getElementById('metodoPago').onchange = function() {
            const campoTrans = document.getElementById('campoTransaccion');
            if (['transferencia', 'webpay'].includes(this.value)) {
                campoTrans.style.display = 'block';
                document.getElementById('transaccionId').required = true;
            } else {
                campoTrans.style.display = 'none';
                document.getElementById('transaccionId').required = false;
            }
        };
        
        // Guardar ID de reserva en el form
        document.getElementById('formPago').dataset.idReserva = reserva.id_reserva;
        
        // Mostrar modal
        document.getElementById('modalPago').style.display = 'flex';

         const modal = document.getElementById('modalPago');
            if(modal) {
                modal.style.display = 'flex';
                // Forzar estilos si es necesario vía JS (opcional, el CSS ya lo hace)
                const content = modal.querySelector('.submodal-content');
                if(content) {
                    content.style.maxHeight = '85vh'; 
                    content.style.overflowY = 'auto';
                }
            }
    }

    // Manejar submit del form de pago
    document.getElementById('formPago')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const idReserva = this.dataset.idReserva;
        const metodoPago = document.getElementById('metodoPago').value;
        const transaccionId = document.getElementById('transaccionId').value;
        
        if (!idReserva || !metodoPago) {
            showToast('⚠️ Completa todos los campos requeridos', 'warning');
            return;
        }
        
        try {
            const formData = new FormData();
            formData.append('action', 'procesar_pago');
            formData.append('id_reserva', idReserva);
            formData.append('metodo_pago', metodoPago);
            formData.append('transaccion_id', transaccionId || null);
            
            const response = await fetch('../api/gestion_reservas.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                showToast('✅ Pago registrado correctamente', 'success');
                cerrarModalPago();
                // Recargar reservas para actualizar estado
                cargarReservasConRango(0);
                // Actualizar detalle si está visible
                if (reservaSeleccionada) {
                    const reservaActualizada = reservasData.find(r => r.id_reserva == idReserva);
                    if (reservaActualizada) {
                        mostrarDetalleReserva(reservaActualizada);
                    }
                }
            } else {
                throw new Error(result.message || 'Error al procesar pago');
            }
            
        } catch (error) {
            console.error('Error al procesar pago:', error);
            showToast(`❌ ${error.message}`, 'error');
        }
    });

    // Cerrar modal con Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            cerrarModalPago();
            closeMensajeModal();
        }
    });

    // Cerrar modal al hacer click fuera
    document.getElementById('modalPago')?.addEventListener('click', function(e) {
        if (e.target === this) {
            cerrarModalPago();
        }
    });

    // === FUNCIONES PARA EL MENÚ DESPLEGABLE ===
    function toggleActionMenu() {
        const menu = document.getElementById('actionDropdown');
        if (menu.style.display === 'block') {
            menu.style.display = 'none';
        } else {
            // Cerrar otros menús abiertos si hubiera
            document.querySelectorAll('.action-dropdown-menu').forEach(m => m.style.display = 'none');
            menu.style.display = 'block';
        }
    }

    // Cerrar menú al hacer click fuera
    document.addEventListener('click', function(event) {
        const menu = document.getElementById('actionDropdown');
        const btn = document.getElementById('btnToggleActions');
        
        if (menu && menu.style.display === 'block') {
            if (!btn.contains(event.target) && !menu.contains(event.target)) {
                menu.style.display = 'none';
            }
        }
    });

    // === ACTUALIZAR mostrarDetalleReserva PARA MOSTRAR BOTÓN PAGAR ===

    // === FUNCIÓN ÚNICA PARA MOSTRAR DETALLE (Reemplaza a las dos anteriores) ===
    function mostrarDetalleReserva(detalle) {
        console.log("🎨 Renderizando detalle con datos REALES:", detalle);

        // Funciones auxiliares
        const val = (v, def = 'N/A') => (v !== null && v !== undefined && v !== '') ? v : def;
        const money = (v) => '$' + parseInt(v || 0).toLocaleString();

        // Lógica para el botón de Pagar (si existe en el modal)
        const btnPagarModal = document.getElementById('btnPagarModal');
        if (btnPagarModal) {
            if ((parseFloat(detalle.monto_total) > 0) && detalle.estado_pago === 'pendiente') {
                btnPagarModal.style.display = 'block';
                btnPagarModal.dataset.monto = detalle.monto_total;
                btnPagarModal.dataset.idReserva = detalle.id_reserva;
            } else {
                btnPagarModal.style.display = 'none';
            }
        }

        // Construcción del HTML Dinámico
        const html = `
            <div style="font-size: 0.95rem; line-height: 1.8; color: #333;">
                
                <!-- Encabezado Fecha/Hora -->
                <div style="background: #e3f2fd; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; text-align: center; border: 1px solid #bbdefb;">
                    <h3 style="margin: 0 0 0.5rem 0; color: #0d47a1;"> ${val(detalle.fecha)}</h3>
                    <div style="font-size: 1.2rem; font-weight: bold; color: #1565c0;">
                        ${val(detalle.hora_inicio).substring(0,5)} - ${val(detalle.hora_fin).substring(0,5)}
                    </div>
                </div>

                <!-- Datos Principales -->
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                    <div>
                        <strong style="color:#071289;">🏟️ Cancha:</strong><br>
                        <span>${val(detalle.nombre_cancha)} (${val(detalle.nro_cancha)})</span>
                    </div>
                    <div>
                        <strong style="color:#071289;">⚽ Deporte:</strong><br>
                        <span>${val(detalle.id_deporte).toUpperCase()}</span>
                    </div>
                    <div style="grid-column: span 2;">
                        <strong style="color:#071289;"> Cliente:</strong><br>
                        <span>${val(detalle.nombre_responsable || detalle.email_cliente)}</span>
                        ${detalle.nombre_club ? `<br><small style="color:#666;">Club: ${val(detalle.nombre_club)}</small>` : ''}
                    </div>
                    <div style="grid-column: span 2;">
                        <strong style="color:#071289;"> Contacto:</strong><br>
                        <span>${val(detalle.telefono_cliente)}</span> | ${val(detalle.email_cliente)}
                    </div>
                </div>

                <!-- Monto y Estados -->
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1.5rem; border-top: 1px solid #eee; padding-top: 1rem;">
                    <div style="text-align: center;">
                        <div style="font-size: 0.8rem; color: #666; font-weight: bold;">💰 MONTO TOTAL</div>
                        <div style="font-size: 1.4rem; font-weight: 900; color: #2e7d32;">${money(detalle.monto_total)}</div>
                        ${detalle.monto_recaudacion ? `<div style="font-size: 0.8rem; color: #1565c0;">(Recaudado: ${money(detalle.monto_recaudacion)})</div>` : ''}
                    </div>
                    
                    <div style="display: flex; flex-direction: column; gap: 0.5rem; justify-content: center;">
                        <div style="background: #fff3e0; padding: 0.5rem; border-radius: 6px; text-align: center;">
                            <div style="font-size: 0.7rem; color: #E65100; font-weight: bold;">ESTADO RESERVA</div>
                            <div style="font-weight: bold; color: #bf360c;">${val(detalle.estado_reserva).toUpperCase()}</div>
                        </div>
                        <div style="background: #e8f5e9; padding: 0.5rem; border-radius: 6px; text-align: center;">
                            <div style="font-size: 0.7rem; color: #2E7D32; font-weight: bold;">ESTADO PAGO</div>
                            <div style="font-weight: bold; color: #1b5e20;">${val(detalle.estado_pago).toUpperCase()}</div>
                        </div>
                    </div>
                </div>
                
                ${detalle.notas ? `
                <div style="margin-top: 1.5rem; background: #fffde7; padding: 1rem; border-radius: 8px; border-left: 4px solid #fbc02d; color: #333;">
                    <strong> Observaciones:</strong> ${val(detalle.notas)}
                </div>` : ''}
            </div>
        `;
        
        // Inyectar HTML
        let container = document.getElementById('contenidoDetalle');
        if (!container) container = document.getElementById('detalleContent');
        
        if (container) {
            container.innerHTML = html;
            console.log("✅ Detalle renderizado correctamente");
        } else {
            console.error("❌ Contenedor del modal no encontrado");
        }
    }

    // === FUNCIÓN PARA ABRIR MODAL DE PAGO (Actualizada) ===
    function abrirModalPago() {
        // Ocultar menú desplegable primero
        document.getElementById('actionDropdown').style.display = 'none';

        const btnPagar = document.getElementById('btnPagarDropdown');
        if (!btnPagar || !btnPagar.dataset.idReserva) {
            alert("⚠️ No hay datos de pago disponibles.");
            return;
        }

        const idReserva = btnPagar.dataset.idReserva;
        const monto = btnPagar.dataset.monto;

        // Llenar info del modal
        document.getElementById('infoPago').innerHTML = `
            <strong>Reserva ID:</strong> ${idReserva}<br>
            <strong>Monto a Pagar:</strong> <span style="color:#2e7d32; font-weight:bold; font-size:1.1rem;">$${parseInt(monto).toLocaleString()}</span>
        `;

        // Resetear form
        document.getElementById('formPago').dataset.idReserva = idReserva;
        document.getElementById('formPago').reset();
        document.getElementById('campoTransaccion').style.display = 'none';

        // Mostrar modal
        document.getElementById('modalPago').style.display = 'flex';
    }

    // Listener para el select de método de pago (mostrar campo transacción)
    document.getElementById('metodoPago')?.addEventListener('change', function() {
        const campo = document.getElementById('campoTransaccion');
        const input = document.getElementById('transaccionId');
        if (['transferencia', 'webpay'].includes(this.value)) {
            campo.style.display = 'block';
            input.required = true;
        } else {
            campo.style.display = 'none';
            input.required = false;
        }
    });

    // Listener submit del formulario de pago
    document.getElementById('formPago')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        const idReserva = this.dataset.idReserva;
        const metodo = document.getElementById('metodoPago').value;
        const transaccion = document.getElementById('transaccionId').value;

        try {
            const formData = new FormData();
            formData.append('action', 'procesar_pago');
            formData.append('id_reserva', idReserva);
            formData.append('metodo_pago', metodo);
            formData.append('transaccion_id', transaccion || '');

            const res = await fetch('../api/gestion_reservas.php', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.success) {
                alert("✅ Pago registrado correctamente");
                cerrarModalPago();
                // Recargar datos o actualizar vista
                location.reload(); 
            } else {
                alert("❌ Error: " + data.message);
            }
        } catch (err) {
            console.error(err);
            alert("❌ Error de conexión al procesar pago");
        }
    });

    let fechaPlanillaActual = new Date().toISOString().split('T')[0];
    let deporteSeleccionadoPlanilla = '';

    // === INICIALIZACIÓN AL CARGAR LA PÁGINA ===
    document.addEventListener('DOMContentLoaded', () => {
        // Forzar vista Planilla por defecto
        const radioPlanilla = document.querySelector('input[name="vistaCalendario"][value="planilla"]');
        if (radioPlanilla) {
            radioPlanilla.checked = true;
            cambiarVistaCalendario('planilla');
        }
        
        // Cargar datos iniciales si hay deporte seleccionado (o todos)
        setTimeout(() => {
            if (document.getElementById('vistaPlanilla').style.display !== 'none') {
                cargarPlanillaReservas();
            }
        }, 100);
    });

    // === CAMBIAR VISTA (CORREGIDO CON VALIDACIONES) ===
    function cambiarVistaCalendario(vista) {
        const fichasDiv = document.getElementById('vistaFichas');
        const planillaDiv = document.getElementById('vistaPlanilla');
        
        // Usar selectores más seguros o verificar existencia antes de usar
        const lblFichas = document.getElementById('lblFichas');
        const lblPlanilla = document.getElementById('lblPlanilla');

        if (vista === 'planilla') {
            if (fichasDiv) fichasDiv.style.display = 'none';
            if (planillaDiv) planillaDiv.style.display = 'block';
            
            // Estilos solo si existen los elementos
            if (lblPlanilla) {
                lblPlanilla.style.background = 'rgba(255,255,255,0.2)';
                lblPlanilla.style.color = 'white';
                lblPlanilla.style.boxShadow = '0 2px 5px rgba(0,0,0,0.2)';
            }
            if (lblFichas) {
                lblFichas.style.background = 'transparent';
                lblFichas.style.color = '#aaa';
                lblFichas.style.boxShadow = 'none';
            }
            
            // Cargar datos
            cargarPlanillaReservas();
        } else {
            if (fichasDiv) fichasDiv.style.display = 'block';
            if (planillaDiv) planillaDiv.style.display = 'none';
            
            if (lblFichas) {
                lblFichas.style.background = 'rgba(255,255,255,0.2)';
                lblFichas.style.color = 'white';
                lblFichas.style.boxShadow = '0 2px 5px rgba(0,0,0,0.2)';
            }
            if (lblPlanilla) {
                lblPlanilla.style.background = 'transparent';
                lblPlanilla.style.color = '#aaa';
                lblPlanilla.style.boxShadow = 'none';
            }
            
            // Para fichas, llamamos a la función original si existe
            if (typeof aplicarFiltrosConAPI === 'function') {
                aplicarFiltrosConAPI();
            }
        }
    }

    // === APLICAR FILTROS (CORREGIDO: ELIMINADO REFERENCIA A filtroFecha) ===
    async function aplicarFiltrosConAPI() {
        const deporte = document.getElementById('filtroDeporte').value;
        const estado = document.getElementById('filtroEstado').value;
        // ELIMINADO: const fecha = document.getElementById('filtroFecha').value; 
        
        const vistaActual = document.querySelector('input[name="vistaCalendario"]:checked')?.value;
        
        if (vistaActual === 'planilla') {
            if (!deporte && deporte !== "") { 
                // Si es vacío ("Todos"), está bien. Solo alertar si es null/undefined raro.
            }
            deporteSeleccionadoPlanilla = deporte;
            cargarPlanillaReservas();
            return;
        }

        // Lógica original para FICHAS (si la necesitas)
        try {
            const formData = new FormData();
            formData.append('action', 'filtrar_reservas');
            formData.append('deporte', deporte);
            formData.append('estado', estado);
            // formData.append('fecha', fecha); // Eliminado también aquí si no lo usas
            
            const response = await fetch('../api/canchaboard.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            if (data.error) throw new Error(data.error);
            
            reservasData = data;
            if (typeof renderizarReservas === 'function') {
                renderizarReservas(reservasData);
            }
        } catch (error) {
            console.error('Error al aplicar filtros:', error);
        }
    }

    // === CARGAR PLANILLA (Sin cambios mayores, solo asegúrate que exista) ===
    async function cargarPlanillaReservas() {
        const deporteSelect = document.getElementById('filtroDeporte');
        const deporte = deporteSelect ? deporteSelect.value : ""; // Asegurar que sea string vacío si no existe
        
        deporteSeleccionadoPlanilla = deporte;
        
        if (!fechaPlanillaActual) {
            fechaPlanillaActual = new Date().toISOString().split('T')[0];
        }

        try {
            // Enviamos el deporte (puede ser "")
            const url = `../api/canchaboard.php?action=get_planilla_reservas&fecha=${fechaPlanillaActual}&deporte=${encodeURIComponent(deporte)}`;
            
            const response = await fetch(url);
            const data = await response.json();
            
            if (data.error) {
                // Si el error sigue siendo "Deporte requerido", es que el backend no se actualizó bien
                throw new Error(data.error);
            }
            
            const inputFecha = document.getElementById('fechaPlanillaInput');
            if (inputFecha) inputFecha.value = fechaPlanillaActual;
            
            renderizarPlanilla(data);
        } catch (error) {
            console.error("Error Planilla:", error);
            alert('Error: ' + error.message);
        }
    }

    function cambiarDiaPlanilla(dias) {
        const fecha = new Date(fechaPlanillaActual);
        fecha.setDate(fecha.getDate() + dias);
        fechaPlanillaActual = fecha.toISOString().split('T')[0];
        cargarPlanillaReservas();
    }

    function irAHoyPlanilla() {
        fechaPlanillaActual = new Date().toISOString().split('T')[0];
        cargarPlanillaReservas();
    }

    // Listener para el input de fecha nativo
    document.getElementById('fechaPlanillaInput')?.addEventListener('change', function() {
        fechaPlanillaActual = this.value;
        cargarPlanillaReservas();
    });

    // Listener para cambio de deporte (recargar planilla si estamos en esa vista)
    document.getElementById('filtroDeporte')?.addEventListener('change', function() {
        const vistaActual = document.querySelector('input[name="vistaCalendario"]:checked').value;
        if (vistaActual === 'planilla') {
            cargarPlanillaReservas();
        }
    });

    function renderizarPlanilla(data) {
        const table = document.getElementById('tablaPlanilla');
        
        // 1. Forzar layout fijo en la tabla (CRUCIAL)
        table.style.tableLayout = 'fixed'; 
        table.style.width = 'auto'; // Que no se estire al 100% forzoso si sobra espacio
        
        if (!data.canchas.length) {
            table.innerHTML = '<tr><td style="padding:2rem; text-align:center; color:#666;">No hay canchas operativas.</td></tr>';
            return;
        }
        
        // Definir anchos fijos exactos
        const anchoHorario = '110px'; // Suficiente para " Horario "
        const anchoCancha = '140px';  // Fijo para todas las canchas

        let html = `<thead><tr>`;
        
        // Header Horario
        html += `<th style="width:${anchoHorario}; min-width:${anchoHorario}; max-width:${anchoHorario}; background:#AB47BC; color:white; padding:10px; position:sticky; left:0; z-index:2; text-align:center; border-right:2px solid #fff;"> Horario </th>`;
        
        // Headers Canchas
        data.canchas.forEach(c => {
            html += `<th style="width:${anchoCancha}; min-width:${anchoCancha}; max-width:${anchoCancha}; background:#AB47BC; color:white; padding:10px; border-left:1px solid #fff; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                        ${c.nombre_cancha || 'Cancha'}
                    </th>`;
        });
        html += `</tr></thead><tbody>`;
        
        // Filas
        data.slots.forEach(slot => {
            if (slot.is_label_row) {
                html += `<tr>`;
                
                // Celda Horario
                html += `<td style="width:${anchoHorario}; min-width:${anchoHorario}; max-width:${anchoHorario}; background:#f8f9fa; font-weight:bold; text-align:center; padding:5px; border-bottom:1px solid #ddd; position:sticky; left:0; z-index:1; color:#333333; border-right:2px solid #ccc;">
                            ${slot.label}
                        </td>`;
                
                // Celdas Cancha
                data.canchas.forEach(cancha => {
                    const key = `${cancha.id_cancha}_${slot.label}`;
                    const reserva = data.reservas[key];
                    
                    let bgClass = '#e0e0e0';
                    let cellContent = '';
                    let clickEvt = '';
                    
                    if (reserva) {
                        if (reserva.estado_pago === 'pagado') bgClass = '#a5d6a7';
                        else if (reserva.estado_pago === 'parcial') bgClass = '#fff59d';
                        else bgClass = '#ffcdd2';
                        
                        const nombre = (reserva.nombre_socio || reserva.nombre_cliente || 'Reserva').substring(0, 12) + '...';
                        cellContent = `<div style="font-size:0.7rem; line-height:1.1;">${nombre}</div>`;
                        clickEvt = `onclick="abrirDetalleDesdePlanilla(${reserva.id_reserva});"`;
                    }
                    
                    html += `<td style="width:${anchoCancha}; min-width:${anchoCancha}; max-width:${anchoCancha}; background:${bgClass}; color:#333; font-weight:bold; cursor:pointer; padding:8px; height:40px; vertical-align:middle; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; border-left:1px solid #fff;" ${clickEvt}>${cellContent}</td>`;
                });
                
                html += `</tr>`;
            }
        });
        
        html += `</tbody>`;
        table.innerHTML = html;
    }

    // === LÓGICA DE FECHA PLANILLA ===
    const fechaPlanillaInput = document.getElementById('fechaPlanillaInput');

    function irAHoyPlanilla() {
        const hoy = new Date().toISOString().split('T')[0];
        fechaPlanillaInput.value = hoy;
        fechaPlanillaActual = hoy;
        cargarPlanillaReservas();
    }

    function cambiarDiaPlanilla(dias) {
        const fecha = new Date(fechaPlanillaActual);
        fecha.setDate(fecha.getDate() + dias);
        fechaPlanillaActual = fecha.toISOString().split('T')[0];
        fechaPlanillaInput.value = fechaPlanillaActual; // Sincronizar input
        cargarPlanillaReservas();
    }

    // Escuchar cambios en el input de fecha nativo
    if (fechaPlanillaInput) {
        fechaPlanillaInput.addEventListener('change', function() {
            fechaPlanillaActual = this.value;
            cargarPlanillaReservas();
        });
    }

    // === FUNCIÓN PARA ABRIR DETALLE DESDE PLANILLA (CORREGIDA) ===
    function abrirDetalleDesdePlanilla(idReserva) {
        console.log("️ Click en Reserva ID:", idReserva);

        if (!idReserva) {
            alert("Error: ID de reserva inválido");
            return;
        }

        // Enviamos id_disponibilidad=0 (para que el backend sepa que debe buscar por reserva)
        // y enviamos el id_reserva real.
        const formData = new URLSearchParams();
        formData.append('id_disponibilidad', '0'); 
        formData.append('id_reserva', idReserva);

        fetch('../api/canchaboard.php?action=get_detalle_reserva', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: formData
        })
        .then(response => response.json())
        .then(detalle => {
            if (detalle.error) throw new Error(detalle.error);
            
            if (typeof mostrarDetalleReserva === 'function') {
                mostrarDetalleReserva(detalle);
                const modal = document.getElementById('modalDetalleReserva');
                if (modal) modal.style.display = 'flex';
            } else {
                console.error("Función mostrarDetalleReserva no definida");
            }
        })
        .catch(err => {
            console.error("Error:", err);
            alert("No se pudo cargar el detalle: " + err.message);
        });
    }
  </script>
</body>
</html>