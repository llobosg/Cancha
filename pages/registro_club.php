<?php
//-- pages/registro_club.php --

require_once __DIR__ . '/../includes/config.php';
$error = $_GET['error'] ?? '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Registrar Club - Cancha</title>
  <link rel="stylesheet" href="../styles.css">
</head>
<body>
  <div class="form-container">
    <h2>Registra tu Club</h2>

    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form id="registroForm" enctype="multipart/form-data">
      <input type="hidden" name="MAX_FILE_SIZE" value="2097152">

      <div class="form-grid">
        <!-- Fila 1 -->
        <div class="form-group">
          <label for="nombre">Nombre club</label>
          <input type="text" id="nombre" name="nombre" required>
        </div>
        <div class="form-group"></div>
        <div class="form-group">
          <label for="fecha_fundacion">Fecha Fund.</label>
          <input type="date" id="fecha_fundacion" name="fecha_fundacion">
        </div>
        <div class="form-group"></div>
        <div class="form-group">
          <label for="deporte">Deporte</label>
          <select id="deporte" name="deporte" required>
            <option value="">Seleccionar</option>
            <option value="futbol">Fútbol</option>
            <option value="futbolito">Futbolito</option>
            <option value="futsal">Futsal</option>
            <option value="futbol11">Fútbol 11</option>
            <option value="tenis">Tenis</option>
            <option value="padel">Pádel</option>
            <option value="otro">Otro</option>
          </select>
        </div>
        <div class="form-group"></div>

        <!-- Fila 2 -->
        <div class="form-group">
          <label for="pais">País</label>
          <input type="text" id="pais" name="pais" value="Chile" required>
        </div>
        <div class="form-group">
          <label for="ciudad">Ciudad</label>
          <input type="text" id="ciudad" name="ciudad" required>
        </div>
        <div class="form-group">
          <label for="comuna">Comuna</label>
          <input type="text" id="comuna" name="comuna" required>
        </div>
        <div class="form-group"></div>
        <div class="form-group"></div>
        <div class="form-group"></div>

        <!-- Fila 3 -->
        <div class="form-group">
          <label for="responsable">Responsable</label>
          <input type="text" id="responsable" name="responsable" required>
        </div>
        <div class="form-group">
          <label for="email_responsable">Correo</label>
          <input type="email" id="email_responsable" name="email_responsable" required>
        </div>
        <div class="form-group">
          <label for="jugadores_por_lado">Jugadores por lado</label>
          <input type="number" id="jugadores_por_lado" name="jugadores_por_lado" min="1" max="20" value="5" required>
        </div>
        <div class="form-group"></div>
        <div class="form-group"></div>
        <div class="form-group"></div>

        <!-- Fila 4: Logo -->
        <div class="form-group form-full">
          <label for="logo">Logo del club</label>
          <input type="file" id="logo" name="logo" accept="image/*">
        </div>
      </div>

      <button type="submit" class="btn-submit">Enviar código de verificación</button>
    </form>
  </div>

  <script>
    document.getElementById('registroForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      
      const formData = new FormData(e.target);
      const btn = e.submitter;
      const originalText = btn.innerHTML;
      
      btn.innerHTML = 'Enviando...';
      btn.disabled = true;

      try {
        const response = await fetch('../api/enviar_codigo_club.php', {
          method: 'POST',
          body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
          window.location.href = data.redirect;
        } else {
          alert('Error: ' + (data.message || 'No se pudo enviar el código'));
        }
      } catch (err) {
        console.error('Error:', err);
        alert('Error de conexión. Revisa la consola.');
      } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
      }
    });
  </script>
</body>
</html>