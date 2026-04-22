<?php
require_once __DIR__ . '/../includes/config.php';
session_start();

// Validar roles permitidos
$rol_actual = $_SESSION['recinto_rol'] ?? '';
$roles_validos = ['admin', 'asistente'];

if (!isset($_SESSION['id_recinto']) || !in_array($rol_actual, $roles_validos)) {
    header('Location: login_recintos.php');
    exit;
}

$id_recinto = $_SESSION['id_recinto'];
$stmt_recinto = $pdo->prepare("SELECT nombre FROM recintos_deportivos WHERE id_recinto = ?");
$stmt_recinto->execute([$id_recinto]);
$recinto = $stmt_recinto->fetch();
$recinto_nombre = $recinto['nombre'] ?? 'Recinto Deportivo';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=0.8, maximum-scale=1.0, user-scalable=no">
  <title>Planilla - <?= htmlspecialchars($recinto_nombre) ?> | CanchaSport</title>
  <link rel="stylesheet" href="../styles.css">
  <style>
    /* Estilos específicos para la Planilla (Copiar los estilos de tu respaldo funcional de la planilla) */
    body {
        background: linear-gradient(rgba(0, 20, 10, 0.40), rgba(0, 30, 15, 0.50)), url('../assets/img/cancha_pasto2.jpg') center/cover no-repeat fixed;
        background-blend-mode: multiply;
        margin: 0; padding: 0; font-family: 'Segoe UI', sans-serif; min-height: 100vh; color: white;
    }
    .header { position: fixed; top: 0; left: 0; width: 100%; height: 60px; background: rgba(0, 51, 102, 0.95); display: flex; justify-content: space-between; align-items: center; padding: 0 1.5rem; z-index: 1000; }
    .dashboard-container { display: flex; justify-content: center; padding-top: 80px; min-height: 100vh; }
    .planilla-table th, .planilla-table td { border: 1px solid #ddd; text-align: center; }
    .planilla-table th { background: #AB47BC; color: white; position: sticky; top: 0; }
    /* ... Agrega aquí el resto de los CSS específicos de la planilla que tenías en el respaldo ... */
  </style>
</head>
<body>
    <!-- Header Simple con Volver al Dashboard -->
    <div class="header">
        <div style="color: #FFD700; font-weight: bold;"> Planilla de Reservas</div>
        <a href="recinto_dashboard.php" style="color: #fff; text-decoration: none; font-weight: bold;">← Volver al Dashboard</a>
    </div>
    
    <div class="dashboard-container">
        <div style="width: 100%; max-width: 1400px; display: flex; flex-direction: column; gap: 1rem; padding: 1rem;">
            
            <!-- VISTA: PLANILLA -->
            <div id="vistaPlanilla">
                <!-- Header Lila con Controles -->
                <div style="background: linear-gradient(90deg, #CE93D8 0%, #BA68C8 50%, #AB47BC 100%); padding: 1rem; border-radius: 12px 12px 0 0; display: flex; justify-content: center; align-items: center; color: white;">
                    <div style="display: flex; align-items: center; gap: 1rem; background: rgba(255,255,255,0.25); padding: 0.5rem 1.5rem; border-radius: 30px;">
                        <span>Fecha:</span>
                        <input type="date" id="fechaPlanillaInput" value="<?= date('Y-m-d') ?>" style="background: transparent; border: none; color: white; font-weight: bold; text-align: center;">
                        <button onclick="irAHoyPlanilla()" style="background: white; color: #8E24AA; border: none; padding: 0.4rem 1rem; border-radius: 20px; font-weight: bold; cursor: pointer;">Hoy</button>
                        <button onclick="cambiarDiaPlanilla(-1)" style="width: 30px; height: 30px; border-radius: 50%; background: white; border: none; color: #6A1B9A; font-weight: bold; cursor: pointer;">&lt;</button>
                        <button onclick="cambiarDiaPlanilla(1)" style="width: 30px; height: 30px; border-radius: 50%; background: white; border: none; color: #6A1B9A; font-weight: bold; cursor: pointer;">&gt;</button>
                        
                        <select id="filtroDeporte" style="background: rgba(255,255,255,0.9); color: #333; border: none; padding: 0.5rem; border-radius: 6px;">
                            <option value="">Todos los deportes</option>
                            <option value="futbol">Fútbol</option>
                            <option value="padel">Pádel</option>
                            <option value="tenis">Tenis</option>
                        </select>
                        
                        <select id="filtroEstado" style="background: rgba(255,255,255,0.9); color: #333; border: none; padding: 0.5rem; border-radius: 6px;">
                            <option value="">Todos los estados</option>
                            <option value="pagadas">Pagadas</option>
                            <option value="parcial">Pago Parcial</option>
                            <option value="no_pagadas">No Pagadas</option>
                        </select>
                    </div>
                </div>

                <!-- Tabla -->
                <div style="overflow: auto; background: white; border-radius: 0 0 12px 12px; max-height: 70vh;">
                    <table id="tablaPlanilla" class="planilla-table" style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                        <!-- Se llena con JS -->
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts de la Planilla (IMPORTANTE: Copiar aquí las funciones renderizarPlanilla, cargarPlanillaReservas, etc. de tu respaldo funcional) -->
    <script>
        let fechaPlanillaActual = new Date().toISOString().split('T')[0];
        let estadoSeleccionadoPlanilla = "";

        // Funciones básicas de navegación de fecha
        function cambiarDiaPlanilla(dias) {
            const fecha = new Date(fechaPlanillaActual);
            fecha.setDate(fecha.getDate() + dias);
            fechaPlanillaActual = fecha.toISOString().split('T')[0];
            document.getElementById('fechaPlanillaInput').value = fechaPlanillaActual;
            cargarPlanillaReservas();
        }

        function irAHoyPlanilla() {
            fechaPlanillaActual = new Date().toISOString().split('T')[0];
            document.getElementById('fechaPlanillaInput').value = fechaPlanillaActual;
            cargarPlanillaReservas();
        }

        document.getElementById('fechaPlanillaInput').addEventListener('change', function() {
            fechaPlanillaActual = this.value;
            cargarPlanillaReservas();
        });

        document.getElementById('filtroDeporte').addEventListener('change', cargarPlanillaReservas);
        document.getElementById('filtroEstado').addEventListener('change', function() {
            estadoSeleccionadoPlanilla = this.value;
            cargarPlanillaReservas();
        });

        // === FUNCIÓN PRINCIPAL DE CARGA ===
        async function cargarPlanillaReservas() {
            const deporte = document.getElementById('filtroDeporte').value;
            const fecha = fechaPlanillaActual;
            
            try {
                const url = `../api/canchaboard.php?action=get_planilla_reservas&fecha=${fecha}&deporte=${encodeURIComponent(deporte)}`;
                const response = await fetch(url, { credentials: 'include' }); // Importante para la sesión
                
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                
                const data = await response.json();
                if (data.error) throw new Error(data.error);
                
                renderizarPlanilla(data);
            } catch (error) {
                console.error("Error:", error);
                alert("Error al cargar la planilla: " + error.message);
            }
        }

        // === FUNCIÓN DE RENDERIZADO (Simplificada para el ejemplo, usa tu versión completa) ===
        function renderizarPlanilla(data) {
            const table = document.getElementById('tablaPlanilla');
            if (!data.canchas || !data.canchas.length) {
                table.innerHTML = '<tr><td>No hay canchas disponibles.</td></tr>';
                return;
            }

            let html = `<thead><tr><th style="position:sticky; left:0; background:#AB47BC; z-index:2;">Hora</th>`;
            data.canchas.forEach(c => {
                html += `<th>${c.nombre_cancha}</th>`;
            });
            html += `</tr></thead><tbody>`;

            data.slots.forEach(slot => {
                if (slot.is_label_row) {
                    html += `<tr><td style="position:sticky; left:0; background:#f8f9fa; font-weight:bold;">${slot.label}</td>`;
                    data.canchas.forEach(cancha => {
                        const key = `${cancha.id_cancha}_${slot.label}`;
                        const reserva = data.reservas[key];
                        let bg = '#e0e0e0'; // Disponible
                        let content = '';
                        
                        if (reserva) {
                            if (reserva.estado_pago === 'pagado') bg = '#a5d6a7'; // Verde
                            else if (reserva.estado_pago === 'parcial') bg = '#fff59d'; // Amarillo
                            else bg = '#ffcdd2'; // Rojo (No pagada/Pendiente)
                            
                            content = `<div style="font-size:0.7rem;">${reserva.nombre_cliente || 'Reserva'}</div>`;
                        }
                        
                        html += `<td style="background:${bg}; height:40px;">${content}</td>`;
                    });
                    html += `</tr>`;
                }
            });
            html += `</tbody>`;
            table.innerHTML = html;
        }

        // Cargar al inicio
        document.addEventListener('DOMContentLoaded', cargarPlanillaReservas);
    </script>
</body>
</html>