<?php
//!-- pages/registro_club.php --

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
          <label for="nombre">Nombre Club</label>
        </div>
        <div class="form-group">
          <input type="text" id="nombre" name="nombre" required style="padding: 0.5rem; border: 1px solid #ccc; border-radius: 6px; font-size: 0.9rem;">
        </div>
        <div class="form-group">
          <label for="fecha_fundacion">Fecha Fundación</label>
        </div>
        <div class="form-group">
          <input type="date" id="fecha_fundacion" name="fecha_fundacion" style="padding: 0.5rem; border: 1px solid #ccc; border-radius: 6px; font-size: 0.9rem;">
        </div>
        <div class="form-group">
          <label for="deporte">Deporte</label>
        </div>
        <div class="form-group">
          <select id="deporte" name="deporte" required style="padding: 0.5rem; border: 1px solid #ccc; border-radius: 6px; font-size: 0.9rem;">
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

        <!-- Fila 2 -->
        <div class="form-group">
          <label for="pais">País</label>
        </div>
        <div class="form-group">
          <input type="text" id="pais" name="pais" value="Chile" required style="padding: 0.5rem; border: 1px solid #ccc; border-radius: 6px; font-size: 0.9rem;">
        </div>
        <div class="form-group">
          <label for="ciudad">Ciudad</label>
        </div>
        <div class="form-group">
          <input type="text" id="ciudad" name="ciudad" required style="padding: 0.5rem; border: 1px solid #ccc; border-radius: 6px; font-size: 0.9rem;">
        </div>
        <div class="form-group">
          <label for="comuna">Comuna</label>
        </div>
        <div class="form-group">
          <input type="text" id="comuna" name="comuna" required style="padding: 0.5rem; border: 1px solid #ccc; border-radius: 6px; font-size: 0.9rem;">
        </div>

        <!-- Fila 3 -->
        <div class="form-group">
          <label for="responsable">Responsable</label>
        </div>
        <div class="form-group">
          <input type="text" id="responsable" name="responsable" required style="padding: 0.5rem; border: 1px solid #ccc; border-radius: 6px; font-size: 0.9rem;">
        </div>
        <div class="form-group">
          <label for="email_responsable">Correo</label>
        </div>
        <div class="form-group col-span-2">
          <input type="email" id="email_responsable" name="email_responsable" required style="padding: 0.5rem; border: 1px solid #ccc; border-radius: 6px; font-size: 0.9rem;">
        </div>
        <div class="form-group">
          <label for="jugadores_por_lado">Jugadores por lado</label>
        </div>
        <div class="form-group">
          <input type="number" id="jugadores_por_lado" name="jugadores_por_lado" min="1" max="20" value="5" required style="padding: 0.5rem; border: 1px solid #ccc; border-radius: 6px; font-size: 0.9rem;">
        </div>

        <!-- Logo -->
        <div class="form-group logo-section">
          <label for="logo">Logo del club</label>
          <input type="file" id="logo" name="logo" accept="image/*">
        </div>

        <!-- Botón -->
        <div class="submit-section">
          <button type="submit" class="btn-submit">Enviar código de verificación</button>
        </div>
      </div>
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