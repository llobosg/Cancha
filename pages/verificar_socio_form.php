<?php
$id_socio = $_GET['id_socio'] ?? null;
$club = $_GET['club'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Verificar Código - CanchaSport</title>
  <style>
    body {
      background: linear-gradient(rgba(0,10,20,0.4), rgba(0,15,30,0.5)),
                   url('../assets/img/cancha_pasto2.jpg') center/cover no-repeat fixed;
      background-blend-mode: multiply;
      margin: 0; padding: 0;
      font-family: 'Segoe UI', sans-serif;
      min-height: 100vh;
      display: flex; justify-content: center; align-items: center;
      color: white;
    }
    .form-container {
      width: 95%; max-width: 500px;
      background: white; padding: 2rem;
      border-radius: 14px; box-shadow: 0 10px 30px rgba(0,0,0,0.25);
      position: relative;
    }
    h2 { text-align: center; color: #071289; margin-bottom: 1.5rem; }
    .form-group { margin-bottom: 1.2rem; }
    .form-group input {
      width: 100%; padding: 0.8rem; text-align: center;
      font-size: 1.5rem; letter-spacing: 8px;
      border: 2px solid #ccc; border-radius: 8px;
    }
    .btn-submit {
      width: 100%; padding: 0.8rem;
      background: #071289; color: white; border: none;
      border-radius: 8px; font-size: 1.1rem; font-weight: bold;
    }
    .error { color: #d32f2f; text-align: center; margin-top: 1rem; }
  </style>
</head>
<body>
  <div class "form-container">
    <h2>🔐 Ingresa tu código</h2>
    <p>Te enviamos un código de 4 dígitos a tu correo.</p>
    
    <div class="form-group">
      <input type="text" id="codigo" maxlength="4" placeholder="0000" autofocus>
    </div>
    
    <button class="btn-submit" onclick="verificar()">Verificar</button>
    <div id="mensaje"></div>
  </div>

  <script>
    async function verificar() {
      const codigo = document.getElementById('codigo').value.trim();
      if (!/^\d{4}$/.test(codigo)) {
        document.getElementById('mensaje').innerHTML = '<div class="error">Ingresa un código de 4 dígitos</div>';
        return;
      }

      const datos = { codigo: codigo };
      <?php if ($id_socio): ?>
        datos.id_socio = <?= (int)$id_socio ?>;
      <?php elseif ($club): ?>
        datos.club_slug = '<?= htmlspecialchars($club) ?>';
      <?php endif; ?>

      const res = await fetch('/api/verificar_codigo_socio.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(datos)
      });
      const data = await res.json();

      if (data.success) {
        window.location.href = '/pages/dashboard_socio.php';
      } else {
        document.getElementById('mensaje').innerHTML = '<div class="error">' + data.message + '</div>';
      }
    }

    // Auto-submit al escribir 4 dígitos
    document.getElementById('codigo').addEventListener('input', function(e) {
      let val = e.target.value.replace(/\D/g, '');
      if (val.length > 4) val = val.substring(0, 4);
      e.target.value = val;
      if (val.length === 4) {
        setTimeout(verificar, 300);
      }
    });
  </script>
</body>
</html>