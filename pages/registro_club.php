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
  <style>
    /* Centrar el formulario en pantalla */
    body {
      display: flex;
      justify-content: center;
      align-items: flex-start;
      min-height: 100vh;
      padding-top: 2rem;
      background: white;
    }
    .form-container {
      width: 95%;
      max-width: 900px;
      margin: 0 auto;
    }

    /* Grid 6 columnas específico para registro_club */
    .form-grid {
      display: grid;
      grid-template-columns: repeat(6, 1fr);
      gap: 0.8rem 1.2rem;
      margin-bottom: 1.8rem;
    }

    .form-group {
      margin: 0;
    }

    .form-group label {
      text-align: right;
      padding-right: 0.5rem;
      display: block;
    }

    .form-group input,
    .form-group select {
      width: 100%;
      padding: 0.65rem;
      border: 1px solid #ccc;
      border-radius: 4px;
      font-size: 0.95rem;
    }

    /* Clases para spans */
    .col-span-2 {
      grid-column: span 2;
    }

    .logo-section {
      grid-column: 1 / -1;
      margin-top: 1rem;
    }

    .submit-section {
      grid-column: 1 / -1;
      text-align: center;
      margin-top: 1.5rem;
    }

    @media (max-width: 768px) {
      .form-grid {
        grid-template-columns: 1fr 1fr;
        gap: 0.8rem;
      }
      .form-group label {
        text-align: left;
        padding-right: 0;
      }
      .logo-section,
      .submit-section {
        grid-column: 1 / -1;
      }
      .col-span-2 {
        grid-column: span 2;
      }
    }
  </style>
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
          <input type="text" id="nombre" name="nombre" required>
        </div>
        <div class="form-group">
          <label for="fecha_fundacion">Fecha Fundación</label>
        </div>
        <div class="form-group">
          <input type="date" id="fecha_fundacion" name="fecha_fundacion">
        </div>
        <div class="form-group">
          <label for="deporte">Deporte</label>
        </div>
        <div class="form-group">
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

        <!-- Fila 2 -->
        <div class="form-group">
          <label for="pais">País</label>
        </div>
        <div class="form-group">
          <input type="text" id="pais" name="pais" value="Chile" required>
        </div>
        <div class="form-group">
          <label for="ciudad">Ciudad</label>
        </div>
        <div class="form-group">
          <input type="text" id="ciudad" name="ciudad" required>
        </div>
        <div class="form-group">
          <label for="comuna">Comuna</label>
        </div>
        <div class="form-group">
          <input type="text" id="comuna" name="comuna" required>
        </div>

        <!-- Fila 3 -->
        <div class="form-group">
          <label for="responsable">Responsable</label>
        </div>
        <div class="form-group">
          <input type="text" id="responsable" name="responsable" required>
        </div>
        <div class="form-group">
          <label for="email_responsable">Correo</label>
        </div>
        <div class="form-group col-span-2">
          <input type="email" id="email_responsable" name="email_responsable" required>
        </div>
        <div class="form-group">
          <label for="jugadores_por_lado">Jugadores por lado</label>
        </div>
        <div class="form-group">
          <input type="number" id="jugadores_por_lado" name="jugadores_por_lado" min="1" max="20" value="5" required>
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