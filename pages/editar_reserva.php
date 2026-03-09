<?php
require_once __DIR__ . '/../includes/config.php';
session_start();

if (!isset($_SESSION['id_socio']) || !isset($_GET['id_reserva'])) {
    header('Location: dashboard_socio.php');
    exit;
}

$id_reserva = (int)$_GET['id_reserva'];
$stmt = $pdo->prepare("
    SELECT r.*, c.nombre_cancha, ca.id_deporte
    FROM reservas r
    JOIN canchas ca ON r.id_cancha = ca.id_cancha
    JOIN recintos_deportivos c ON ca.id_recinto = c.id_recinto
    WHERE r.id_reserva = ? AND r.id_club = ?
");
$stmt->execute([$id_reserva, $_SESSION['club_id']]);
$reserva = $stmt->fetch();

if (!$reserva) {
    header('Location: dashboard_socio.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Editar Reserva - Cancha</title>
  <link rel="stylesheet" href="../styles.css">
  <style>
    body { background: white; padding: 2rem; }
    .form-container { max-width: 600px; margin: 0 auto; }
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; margin-bottom: 0.3rem; font-weight: bold; }
    .form-group input, .form-group select { width: 100%; padding: 0.5rem; }
    .btn-submit { background: #071289; color: white; padding: 0.6rem; border: none; border-radius: 4px; cursor: pointer; }
  </style>
</head>
<body>
  <div class="form-container">
    <h2>Editar Reserva</h2>
    <form id="editarReservaForm">
      <input type="hidden" name="id_reserva" value="<?= $id_reserva ?>">
      
      <div class="form-group">
        <label>Fecha</label>
        <input type="date" name="fecha" value="<?= $reserva['fecha'] ?>" required>
      </div>
      
      <div class="form-group">
        <label>Hora Inicio</label>
        <input type="time" name="hora_inicio" value="<?= substr($reserva['hora_inicio'], 0, 5) ?>" required>
      </div>
      
      <div class="form-group">
        <label>Hora Fin</label>
        <input type="time" name="hora_fin" value="<?= substr($reserva['hora_fin'], 0, 5) ?>" required>
      </div>
      
      <div class="form-group">
        <label>Lugar</label>
        <input type="text" value="<?= htmlspecialchars($reserva['nombre_cancha']) ?>" disabled>
        <input type="hidden" name="id_cancha" value="<?= $reserva['id_cancha'] ?>">
      </div>
      
      <div class="form-group">
        <label>Cuota por socio ($)</label>
        <input type="number" name="monto_recaudacion" value="<?= (int)$reserva['monto_recaudacion'] ?>" min="0">
      </div>
      
      <div class="form-group">
        <label>Cupos disponibles</label>
        <input type="number" name="jugadores_esperados" value="<?= (int)$reserva['jugadores_esperados'] ?>" min="1" max="30">
      </div>
      
      <button type="submit" class="btn-submit">Guardar Cambios</button>
    </form>
  </div>

  <script>
    document.getElementById('editarReservaForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        
        try {
            const res = await fetch('../api/editar_reserva.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            
            if (data.success) {
                alert('Reserva actualizada correctamente');
                window.location.href = 'dashboard_socio.php?id_club=<?= htmlspecialchars($_SESSION['current_club']) ?>';
            } else {
                alert('Error: ' + data.message);
            }
        } catch (err) {
            alert('Error al guardar');
        }
    });
  </script>
</body>
</html>