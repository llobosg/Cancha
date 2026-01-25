<?php
//-- pages/registro_socio.php --

require_once __DIR__ . '/../includes/config.php';
$slug = $_GET['club'] ?? '';
if (!$slug) {
    header('Location: buscar_club.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Inscríbete - Cancha</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    body {
      background: linear-gradient(135deg, #e3f2fd, #bbdefb);
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      padding: 2rem;
    }
    .form-container {
      max-width: 600px;
      margin: 2rem auto;
      background: white;
      padding: 2rem;
      border-radius: 12px;
      box-shadow: 0 6px 20px rgba(0,0,0,0.1);
    }
    h2 {
      text-align: center;
      color: #3a4f63;
      margin-bottom: 1.5rem;
    }
    .form-group {
      margin-bottom: 1.2rem;
    }
    .form-group label {
      display: block;
      margin-bottom: 0.4rem;
      font-weight: 600;
      color: #444;
    }
    .form-group input,
    .form-group select {
      width: 100%;
      padding: 0.7rem;
      border: 1px solid #ccc;
      border-radius: 6px;
      font-size: 1rem;
    }
    .btn-submit {
      width: 100%;
      padding: 0.9rem;
      background: #009966;
      color: white;
      border: none;
      border-radius: 6px;
      font-size: 1.1rem;
      font-weight: bold;
      cursor: pointer;
    }
    .btn-submit:hover {
      background: #007a52;
    }
    .btn-submit:disabled {
      opacity: 0.7;
      cursor: not-allowed;
    }
    .error {
      background: #ffebee;
      color: #c62828;
      padding: 0.8rem;
      border-radius: 6px;
      margin-bottom: 1.2rem;
    }
  </style>
</head>
<body>
  <div class="form-container">
    <h2>👤 Inscríbete al club</h2>

    <!-- Formulario SIN action ni method → manejado por JS -->
    <form id="registroForm" enctype="multipart/form-data">
      <input type="hidden" name="club_slug" value="<?= htmlspecialchars($slug) ?>">
      <input type="hidden" name="MAX_FILE_SIZE" value="2097152">

      <div class="form-group">
        <label for="nombre">Nombre completo *</label>
        <input type="text" id="nombre" name="nombre" required>
      </div>

      <div class="form-group">
        <label for="alias">Alias *</label>
        <input type="text" id="alias" name="alias" required>
      </div>

      <div class="form-group">
        <label for="fecha_nac">Fecha de nacimiento</label>
        <input type="date" id="fecha_nac" name="fecha_nac">
      </div>

      <div class="form-group">
        <label for="celular">Celular</label>
        <input type="tel" id="celular" name="celular" placeholder="+56 9 1234 5678">
      </div>

      <div class="form-group">
        <label for="email">Correo electrónico *</label>
        <input type="email" id="email" name="email" required>
      </div>

      <div class="form-group">
        <label for="direccion">Dirección</label>
        <input type="text" id="direccion" name="direccion">
      </div>

      <div class="form-group">
        <label for="genero">Género *</label>
        <select id="genero" name="genero" required>
          <option value="">Seleccionar</option>
          <option value="Femenino">Femenino</option>
          <option value="Masculino">Masculino</option>
          <option value="Otro">Otro</option>
        </select>
      </div>

      <div class="form-group">
        <label for="foto">Foto (opcional)</label>
        <input type="file" id="foto" name="foto" accept="image/*">
      </div>

      <button type="submit" class="btn-submit">Enviar código de verificación</button>
    </form>
  </div>

  <script>
    // Manejo del formulario con AJAX
    document.getElementById('registroForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      
      const formData = new FormData(e.target);
      const btn = e.submitter;
      const originalText = btn.innerHTML;
      
      btn.innerHTML = 'Enviando...';
      btn.disabled = true;

      try {
        const response = await fetch('../api/enviar_codigo_socio.php', {
          method: 'POST',
          body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
          // Redirigir a verificación con ID del socio
          window.location.href = `verificar_socio.php?id=${data.id_socio}`;
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