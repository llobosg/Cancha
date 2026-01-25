<!-- pages/registro_socio.php -->
<?php
require_once __DIR__ . '/../includes/config.php';
$slug = $_GET['club'] ?? '';
if (!$slug) {
    header('Location: buscar_club.php');
    exit;
}
// Validar slug (opcional: puedes hacer una consulta a la DB)
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Inscríbete - Cancha</title>
  <link rel="stylesheet" href="../assets/css/styles.css">
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
    .error {
      background: #ffebee;
      color: #c62828;
      padding: 0.8rem;
      border-radius: 6px;
      margin-bottom: 1.2rem;
    }
    .success {
      background: #e8f5e9;
      color: #2e7d32;
      padding: 0.8rem;
      border-radius: 6px;
      margin-bottom: 1.2rem;
    }
    /* Toast */
    .toast {
      position: fixed;
      bottom: 20px;
      right: 20px;
      max-width: 350px;
      padding: 1rem 1.5rem;
      border-radius: 8px;
      color: white;
      font-size: 0.95rem;
      font-weight: bold;
      box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
      z-index: 32000;
      opacity: 0;
      transform: translateY(20px);
      transition: all 0.4s ease;
      display: flex;
      align-items: center;
      gap: 0.7rem;
    }
    .toast.show {
      opacity: 1;
      transform: translateY(0);
    }
    .toast.success { background: linear-gradient(135deg, #009966, #006644); }
    .toast.error { background: linear-gradient(135deg, #cc0000, #990000); }
  </style>
</head>
<body>
  <div class="form-container">
    <h2>👤 Inscríbete al club</h2>
    <div id="mensaje"></div>
    <form id="registroForm">
      <input type="hidden" name="club_slug" value="<?= htmlspecialchars($slug) ?>">

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
        <input type="tel" id="celular" name="celular">
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

  <!-- Toast -->
  <div id="toast" class="toast" style="display:none;">
    <span id="toast-message">Mensaje</span>
  </div>

  <script>
    function mostrarToast(mensaje, tipo = 'info') {
      const toast = document.getElementById('toast');
      const msg = document.getElementById('toast-message');
      msg.textContent = mensaje;
      toast.className = `toast ${tipo}`;
      toast.style.display = 'flex';
      void toast.offsetWidth;
      toast.classList.add('show');
      setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.style.display = 'none', 400);
      }, 4000);
    }

    document.getElementById('registroForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const formData = new FormData(e.target);
      
      try {
        const response = await fetch('../api/enviar_codigo_socio.php', {
          method: 'POST',
          body: formData
        });
        const data = await response.json();
        
        if (data.success) {
          mostrarToast('Código enviado a tu correo', 'success');
          // Redirigir a verificación
          setTimeout(() => {
            window.location.href = `verificar_socio.php?id=${data.id_socio}`;
          }, 2000);
        } else {
          mostrarToast(data.message || 'Error al enviar código', 'error');
        }
      } catch (err) {
        mostrarToast('Error de conexión', 'error');
      }
    });
  </script>
</body>
</html>