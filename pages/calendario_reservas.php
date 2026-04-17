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
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
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
    
    .dashboard-container {
        display: grid;
        grid-template-columns: 4fr 1fr;
        gap: 1rem;
        max-width: 1400px;
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
    
    .estado-disponible { background: #FFD700; } /* Amarillo */
    .estado-reservada { background: #9C27B0; }  /* Morado */
    .estado-ocupada { background: #4CAF50; }    /* Verde */
    .estado-cancelada { background: #F44336; }  /* Rojo */
    .estado-mantencion { background: #FF9800; } /* Naranja */
  
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
  
  <div class="dashboard-container" style="margin-top: 70px;">
    <div>
      <div class="controls-section">
        <select class="control-select" id="filtroDeporte">
          <option value="">Todos los deportes</option>
          <option value="futbol">Fútbol</option>
          <option value="futbolito">Futbolito</option>
          <option value="futsal">Futsal</option>
          <option value="tenis">Tenis</option>
          <option value="padel">Pádel</option>
          <option value="voleyball">Voleyball</option>
          <option value="otro">Quincho/Otro</option>
        </select>
        
        <select class="control-select" id="filtroEstado">
          <option value="">Todos los estados</option>
          <option value="disponible">Disponible</option>
          <option value="reservada">Reservada</option>
          <option value="ocupada">Ocupada</option>
          <option value="cancelada">Cancelada</option>
        </select>
        
        <select class="control-select" id="filtroFecha">
          <option value="">Últimos 30 días</option>
          <option value="hoy">Hoy</option>
          <option value="mañana">Mañana</option>
          <option value="semana">Esta semana</option>
          <option value="mes">Este mes</option>
        </select>
      </div>
      
      <div class="reservas-grid" id="reservasGrid">
        <div style="grid-column: 1/-1; text-align: center; padding: 2rem; color: white;">
          Cargando disponibilidad...
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

    <!-- === SUBMODAL DE PAGO (Ya existente, solo aseguramos la X) === -->
    <div id="modalPago" class="submodal" style="display:none;">
        <div class="submodal-content" style="max-width: 500px;">
            <!-- Botón Cerrar X -->
            <span class="close-modal" onclick="cerrarModalPago()" style="position:absolute; top:15px; right:15px; font-size:28px; cursor:pointer; color:#999;">&times;</span>
            
            <h3 style="color:#071289; margin-bottom:1rem; text-align:center;">💳 Pagar Reserva</h3>
            <div id="infoPago" style="margin-bottom:1rem; font-size:0.9rem; color:#333; background:#f8f9fa; padding:10px; border-radius:6px; text-align:center;"></div>
            
            <form id="formPago">
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
                
                <div id="campoTransaccion" class="form-group" style="display:none; margin-bottom:1rem;">
                    <label style="font-weight:bold; display:block; margin-bottom:0.3rem; color:#333;">ID Transacción / Comprobante</label>
                    <input type="text" name="transaccion_id" id="transaccionId" placeholder="Ej: 123456789" style="width:100%; padding:0.6rem; border-radius:6px; border:1px solid #ccc;">
                </div>
                
                <button type="submit" class="btn-submit" style="width:100%; background:#4CAF50; color:white; border:none; padding:0.8rem; border-radius:8px; font-weight:bold; cursor:pointer; font-size:1rem;">Confirmar Pago</button>
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
        
        // Agrupar por fecha para mejor visualización
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
                    'tenis': '🎾', 'padel': '🎾', 'voleyball': '🏐',
                    'otro': '🏟️'
                };
                
                const estadoClass = getEstadoClass(reserva.estado_disponibilidad || 'disponible');
                
                card.innerHTML = `
                    <div class="deporte-icon">${iconos[reserva.id_deporte] || '🏟️'}</div>
                    <div class="cancha-nombre">${reserva.nro_cancha || 'Sin nombre'}</div>
                    <div class="fecha-hora">
                        ${formatTimeDisplay(reserva.hora_inicio)}<br>
                        ${getEstadoTexto(reserva.estado_disponibilidad || 'disponible')}
                    </div>
                    <div class="estado-indicator ${estadoClass}"></div>
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
            'mantencion': 'Mantención'
        };
        return estados[estado] || estado;
    }

    function getEstadoClass(estado) {
        switch(estado) {
            case 'disponible': return 'estado-disponible';
            case 'reservada': return 'estado-reservada';
            case 'ocupada': return 'estado-ocupada';
            case 'cancelada': return 'estado-cancelada';
            case 'mantencion': return 'estado-mantencion';
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

    // === RENDERIZAR CONTENIDO DENTRO DEL MODAL ===
    function renderizarContenidoDetalle(detalle) {
        const val = (v, def = 'N/A') => (v !== null && v !== undefined && v !== '') ? v : def;
        const money = (v) => '$' + parseInt(v || 0).toLocaleString();
        
        // Controlar visibilidad del botón Pagar en el modal
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

        // Dentro de renderizarContenidoDetalle, cambia el HTML generado así:

        const html = `
            <div style="font-size: 0.95rem; line-height: 1.8; color: #333;">
                
                <!-- BLOQUE 1: DATOS PRINCIPALES (Clase: .info-grid-2col) -->
                <div class="info-grid-2col" style="display:grid; grid-template-columns: 1fr 1fr; gap: 0.8rem; margin-bottom: 1rem; background: #f0f4f8; padding: 0.8rem; border-radius: 8px;">
                    <div><strong style="color:#071289;">📅 Fecha:</strong> ${val(detalle.fecha)}</div>
                    <div><strong style="color:#071289;"> Hora:</strong> ${val(detalle.hora_inicio).substring(0,5)} - ${val(detalle.hora_fin).substring(0,5)}</div>
                    <div><strong style="color:#071289;">🏟️ Cancha:</strong> ${val(detalle.nombre_cancha)}</div>
                    <div><strong style="color:#071289;"> Deporte:</strong> ${val(detalle.id_deporte).toUpperCase()}</div>
                </div>

                <!-- BLOQUE 2: CLIENTE (Clase: .client-grid-2col) -->
                <div class="client-grid-2col" style="margin-bottom: 1rem;">
                    <h4 style="color:#071289; border-bottom:2px solid #e0e0e0; padding-bottom:0.5rem; margin-bottom:0.8rem; grid-column: 1/-1;">👤 Información del Cliente</h4>
                    <p style="margin:0.2rem 0;"><strong>Nombre:</strong> ${val(detalle.nombre_responsable || detalle.email_cliente)}</p>
                    <p style="margin:0.2rem 0;"><strong>Teléfono:</strong> ${val(detalle.telefono_cliente)}</p>
                    <p style="margin:0.2rem 0;"><strong>Email:</strong> ${val(detalle.email_cliente)}</p>
                    ${detalle.nombre_club ? `<p style="margin:0.2rem 0;"><strong>Club:</strong> ${val(detalle.nombre_club)}</p>` : ''}
                </div>

                <!-- BLOQUE 3: MONTO Y ESTADOS (Clase: .status-blocks) -->
                <div class="status-blocks" style="display:grid; grid-template-columns: 1fr 1fr; gap: 0.8rem;">
                    <div style="background: #e3f2fd; padding: 1rem; border-radius: 10px; text-align: center; border: 1px solid #bbdefb; display:flex; flex-direction:column; justify-content:center;">
                        <div style="font-size: 0.8rem; color: #1565C0; font-weight: bold; margin-bottom: 0.5rem;">💰 MONTO TOTAL</div>
                        <div style="font-size: 1.5rem; color: #0d47a1; font-weight: 900;">${money(detalle.monto_total)}</div>
                    </div>
                    
                    <div style="background: #fff3e0; padding: 1rem; border-radius: 10px; display: flex; flex-direction: column; justify-content: center; border: 1px solid #ffe0b2; gap: 0.5rem;">
                        <div>
                            <div style="font-size: 0.75rem; color: #E65100; font-weight: bold;">ESTADO RESERVA</div>
                            <div style="font-size: 1.1rem; font-weight: bold; color: #bf360c;">${val(detalle.estado_reserva).toUpperCase()}</div>
                        </div>
                        <div style="border-top: 1px dashed #ffcc80; margin: 0.3rem 0;"></div>
                        <div>
                            <div style="font-size: 0.75rem; color: #E65100; font-weight: bold;">ESTADO PAGO</div>
                            <div style="font-size: 1.1rem; font-weight: bold; color: #bf360c;">${val(detalle.estado_pago).toUpperCase()}</div>
                        </div>
                    </div>
                </div>
                
                ${detalle.notas ? `<div style="margin-top:1rem; background:#fffde7; padding:1rem; border-radius:8px; border-left:4px solid #fbc02d; color:#333;"><strong>📝 Notas:</strong> ${val(detalle.notas)}</div>` : ''}
            </div>
        `;
        
        document.getElementById('contenidoDetalle').innerHTML = html;
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

    // === ABRIR MODAL DE PAGO DESDE EL DETALLE ===
    function abrirModalPagoDesdeDetalle() {
        if (!reservaActualSeleccionada) return;
        
        // Ocultar menú de acciones
        document.getElementById('actionMenuModal').style.display = 'none';
        
        const monto = reservaActualSeleccionada.monto_total;
        const idReserva = reservaActualSeleccionada.id_reserva;
        
        document.getElementById('infoPago').innerHTML = `
            <strong>Reserva ID:</strong> ${idReserva}<br>
            <strong>Monto a Pagar:</strong> <span style="color:#2e7d32; font-weight:bold; font-size:1.2rem;">$${parseInt(monto).toLocaleString()}</span>
        `;
        
        document.getElementById('formPago').dataset.idReserva = idReserva;
        document.getElementById('formPago').reset();
        document.getElementById('campoTransaccion').style.display = 'none';
        
        // Cerrar modal detalle y abrir pago
        cerrarModalDetalle();
        document.getElementById('modalPago').style.display = 'flex';
    }

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

    function mostrarDetalleReserva(detalle) {
        console.log("🎨 Renderizando detalle con datos:", detalle);

        // Función auxiliar segura
        const val = (v, def = 'N/A') => (v !== null && v !== undefined && v !== '') ? v : def;
        const money = (v) => '$' + parseInt(v || 0).toLocaleString();
        
        // HTML con estilos inline forzados para contraste (Texto oscuro #333 sobre fondo claro/blanco)
        const html = `
            <div style="font-size: 0.9rem; line-height: 1.6; color: #333333;">
                
                <!-- Encabezado -->
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 0.8rem; margin-bottom: 1rem; background: #f8f9fa; padding: 1rem; border-radius: 8px;">
                    <div><strong style="color:#071289;">📅 Fecha:</strong> <span style="color:#333;">${val(detalle.fecha)}</span></div>
                    <div><strong style="color:#071289;"> Hora:</strong> <span style="color:#333;">${val(detalle.hora_inicio).substring(0,5)} - ${val(detalle.hora_fin).substring(0,5)}</span></div>
                    <div><strong style="color:#071289;">🏟️ Cancha:</strong> <span style="color:#333;">${val(detalle.nombre_cancha)} (Nro ${val(detalle.nro_cancha)})</span></div>
                    <div><strong style="color:#071289;">🎾 Deporte:</strong> <span style="color:#333;">${val(detalle.id_deporte).toUpperCase()}</span></div>
                </div>

                <!-- Información Cliente -->
                <div style="margin-bottom: 1rem; background: #fff; padding: 1rem; border-radius: 8px; border: 1px solid #eee;">
                    <div style="margin-bottom: 0.5rem;"><strong style="color:#071289;">👤 Cliente:</strong> <span style="color:#333;">${val(detalle.nombre_responsable || detalle.email_cliente)}</span></div>
                    <div style="margin-bottom: 0.5rem;"><strong style="color:#071289;">📞 Teléfono:</strong> <span style="color:#333;">${val(detalle.telefono_cliente)}</span></div>
                    <div><strong style="color:#071289;">📧 Email:</strong> <span style="color:#333;">${val(detalle.email_cliente)}</span></div>
                    ${detalle.nombre_club ? `<div style="margin-top:0.5rem;"><strong style="color:#071289;">🏢 Club:</strong> <span style="color:#333;">${val(detalle.nombre_club)}</span></div>` : ''}
                </div>

                <!-- Información Económica y Estado -->
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <!-- Columna Izquierda: Monto -->
                    <div style="background: #e3f2fd; padding: 1rem; border-radius: 8px; text-align: center;">
                        <div style="font-size: 0.8rem; color: #1565C0; font-weight: bold; margin-bottom: 0.5rem;">💰 MONTO TOTAL</div>
                        <div style="font-size: 1.4rem; color: #0d47a1; font-weight: 900;">${money(detalle.monto_total)}</div>
                    </div>

                    <!-- Columna Derecha: Estados -->
                    <div style="background: #fff3e0; padding: 1rem; border-radius: 8px; display: flex; flex-direction: column; justify-content: center; gap: 0.5rem;">
                        <div>
                            <div style="font-size: 0.75rem; color: #E65100; font-weight: bold;">ESTADO RESERVA</div>
                            <div style="font-size: 1rem; font-weight: bold; color: #bf360c;">${val(detalle.estado_reserva).toUpperCase()}</div>
                        </div>
                        <div style="border-top: 1px dashed #ffcc80; margin: 0.5rem 0;"></div>
                        <div>
                            <div style="font-size: 0.75rem; color: #E65100; font-weight: bold;">ESTADO PAGO</div>
                            <div style="font-size: 1rem; font-weight: bold; color: #bf360c;">${val(detalle.estado_pago).toUpperCase()}</div>
                        </div>
                    </div>
                </div>

                <!-- Notas -->
                ${detalle.notas ? `
                <div style="margin-top: 1rem; background: #fffde7; padding: 0.8rem; border-radius: 8px; border-left: 4px solid #fbc02d; color: #333;">
                    <strong style="color:#f57f17;">📝 Notas:</strong> ${val(detalle.notas)}
                </div>` : ''}
                
                ${detalle.id_convenio ? `
                <div style="margin-top: 0.8rem; font-size: 0.8rem; color: #555; text-align: right;">
                    ID Convenio: <strong>${detalle.id_convenio}</strong>
                </div>` : ''}
                
                <!-- Tipo Reserva -->
                <div style="margin-top: 0.8rem; text-align: center; font-size: 0.85rem; color: #666; font-style: italic;">
                    Tipo de Arriendo: <strong>${val(detalle.tipo_reserva).toUpperCase()}</strong>
                </div>
            </div>
        `;

        // Inyectar HTML
        const container = document.getElementById('detalleContent');
        if (container) {
            // Asegurar que el contenedor tenga fondo blanco para que el texto oscuro se vea
            container.style.backgroundColor = '#ffffff'; 
            container.style.color = '#333333';
            container.style.padding = '1rem';
            container.style.borderRadius = '12px';
            container.innerHTML = html;
            console.log("✅ Detalle renderizado con contraste corregido");
        } else {
            console.error("❌ No se encontró el contenedor #detalleContent");
        }
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
    document.getElementById('filtroFecha').addEventListener('change', function() {
        aplicarFiltrosConAPI();
    });

    // Filtro por deporte
    document.getElementById('filtroDeporte').addEventListener('change', aplicarFiltrosConAPI);

    // Filtro por estado
    document.getElementById('filtroEstado').addEventListener('change', aplicarFiltrosConAPI);

    // Función que llama a la API de filtrado con POST
    async function aplicarFiltrosConAPI() {
    const deporte = document.getElementById('filtroDeporte').value;
    const estado = document.getElementById('filtroEstado').value;
    const fecha = document.getElementById('filtroFecha').value;
    
    console.log('🔍 Filtros enviados:', { deporte, estado, fecha });

    try {
        const formData = new FormData();
        formData.append('action', 'filtrar_reservas');
        formData.append('deporte', deporte);
        formData.append('estado', estado);
        formData.append('fecha', fecha);
        
        // Log de lo que se envía
        for (let pair of formData.entries()) {
            console.log(pair[0]+ ', ' + pair[1]); 
        }

        const response = await fetch('../api/canchaboard.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        console.log('📡 Respuesta API (Total items):', data.length);
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        reservasData = data;
        renderizarReservas(reservasData);
        
    } catch (error) {
        console.error('Error al aplicar filtros:', error);
        showToast('❌ Error al filtrar reservas', 'error');
    }
}

    // Cargar datos iniciales con "Hoy" por defecto
    document.addEventListener('DOMContentLoaded', function() {
        // Establecer valores por defecto en los selects
        document.getElementById('filtroFecha').value = 'hoy';
        document.getElementById('filtroDeporte').value = '';
        document.getElementById('filtroEstado').value = '';
        
        // Cargar reservas de hoy
        cargarReservasConRango(0);
    });

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

    // Reemplaza tu función mostrarDetalleReserva actual por esta versión que activa el botón
    function mostrarDetalleReserva(detalle) {
        console.log("🎨 Renderizando detalle...", detalle);

        const val = (v, def = 'N/A') => (v !== null && v !== undefined && v !== '') ? v : def;
        const money = (v) => '$' + parseInt(v || 0).toLocaleString();
        
        // Lógica para mostrar/ocultar botón de pagar
        const btnPagar = document.getElementById('btnPagarDropdown');
        if (btnPagar) {
            // Mostrar solo si hay monto > 0 y estado de pago es pendiente
            if ((parseFloat(detalle.monto_total) > 0) && detalle.estado_pago === 'pendiente') {
                btnPagar.style.display = 'block';
                // Guardar datos en el botón para usarlos luego
                btnPagar.dataset.monto = detalle.monto_total;
                btnPagar.dataset.idReserva = detalle.id_reserva;
            } else {
                btnPagar.style.display = 'none';
            }
        }

        // Construcción del HTML (igual que antes pero simplificado para el ejemplo)
        const html = `
            <div style="font-size: 0.9rem; line-height: 1.6; color: #333333;">
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 0.8rem; margin-bottom: 1rem; background: #f8f9fa; padding: 1rem; border-radius: 8px;">
                    <div><strong style="color:#071289;">📅 Fecha:</strong> <span>${val(detalle.fecha)}</span></div>
                    <div><strong style="color:#071289;">⏰ Hora:</strong> <span>${val(detalle.hora_inicio).substring(0,5)} - ${val(detalle.hora_fin).substring(0,5)}</span></div>
                    <div><strong style="color:#071289;">🏟️ Cancha:</strong> <span>${val(detalle.nombre_cancha)}</span></div>
                    <div><strong style="color:#071289;"> Deporte:</strong> <span>${val(detalle.id_deporte).toUpperCase()}</span></div>
                </div>
                <div style="margin-bottom: 1rem; background: #fff; padding: 1rem; border-radius: 8px; border: 1px solid #eee;">
                    <div style="margin-bottom: 0.5rem;"><strong style="color:#071289;">👤 Cliente:</strong> <span>${val(detalle.nombre_responsable || detalle.email_cliente)}</span></div>
                    <div><strong style="color:#071289;"> Teléfono:</strong> <span>${val(detalle.telefono_cliente)}</span></div>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div style="background: #e3f2fd; padding: 1rem; border-radius: 8px; text-align: center;">
                        <div style="font-size: 0.8rem; color: #1565C0; font-weight: bold; margin-bottom: 0.5rem;">💰 MONTO</div>
                        <div style="font-size: 1.4rem; color: #0d47a1; font-weight: 900;">${money(detalle.monto_total)}</div>
                    </div>
                    <div style="background: #fff3e0; padding: 1rem; border-radius: 8px; display: flex; flex-direction: column; justify-content: center;">
                        <div style="font-size: 0.75rem; color: #E65100; font-weight: bold;">ESTADO PAGO</div>
                        <div style="font-size: 1rem; font-weight: bold; color: #bf360c;">${val(detalle.estado_pago).toUpperCase()}</div>
                    </div>
                </div>
            </div>
        `;

        const container = document.getElementById('detalleContent');
        if (container) {
            container.style.backgroundColor = '#ffffff'; 
            container.style.color = '#333333';
            container.innerHTML = html;
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
  </script>
</body>
</html>