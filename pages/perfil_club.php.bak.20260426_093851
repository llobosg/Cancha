<?php
require_once __DIR__ . '/../includes/config.php';
session_start();

// Verificar que sea responsable de club
if (!isset($_SESSION['club_id']) || !isset($_SESSION['current_club'])) {
    header('Location: dashboard_socio.php');
    exit;
}

$club_id = $_SESSION['club_id'];

// Obtener datos del club actual
$stmt = $pdo->prepare("
    SELECT id_club, nombre, pais, ciudad, comuna, email_responsable, telefono 
    FROM clubs 
    WHERE id_club = ?
");
$stmt->execute([$club_id]);
$club = $stmt->fetch();

if (!$club) {
    header('Location: dashboard_socio.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Perfil del Club - Cancha</title>
  <link rel="stylesheet" href="../styles.css">
  <style>
    body {
      background: 
        linear-gradient(rgba(0, 20, 10, 0.40), rgba(0, 30, 15, 0.50)),
        url('../assets/img/cancha_pasto2.jpg') center/cover no-repeat fixed;
      background-blend-mode: multiply;
      margin: 0;
      padding: 0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      min-height: 100vh;
      color: white;
    }

    .container {
      width: 95%;
      max-width: 600px;
      margin: 0 auto;
      padding: 2rem;
    }

    .back-btn {
      color: white;
      text-decoration: none;
      margin-bottom: 1.5rem;
      display: inline-block;
      font-weight: bold;
    }

    .back-btn:hover {
      text-decoration: underline;
    }

    .section-title {
      color: #003366;
      margin: 1.5rem 0;
      font-size: 1.4rem;
    }

    /* Formulario de perfil */
    .profile-section {
      background: white;
      padding: 1.5rem;
      border-radius: 12px;
    }

    .form-group {
      margin-bottom: 1.5rem;
    }

    .form-group label {
      display: block;
      font-weight: bold;
      color: #333;
      margin-bottom: 0.5rem;
    }

    .form-group input, .form-group select {
      width: 100%;
      padding: 0.6rem;
      border: 1px solid #ccc;
      border-radius: 5px;
      color: #071289;
    }

    .btn-submit {
      background: #071289;
      color: white;
      border: none;
      padding: 0.8rem 2rem;
      border-radius: 6px;
      font-weight: bold;
      cursor: pointer;
      width: 100%;
    }

    /* Responsive móvil */
    @media (max-width: 768px) {
      .container {
        padding: 1rem;
      }
      
      .profile-section {
        padding: 1.5rem;
        margin: 1rem;
      }
      
      .form-group input, .form-group select {
        padding: 0.5rem;
      }
      
      .btn-submit {
        padding: 0.7rem;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <a href="dashboard_socio.php?id_club=<?= htmlspecialchars($_SESSION['current_club']) ?>" class="back-btn">← Volver al Dashboard</a>
    
    <h1>Perfil del Club</h1>
    
    <div class="section-title">Datos del Club</div>
    <div class="profile-section">
      <form id="clubForm" onsubmit="saveClub(event)">
        <input type="hidden" id="clubId" name="id_club" value="<?= $club['id_club'] ?>">
        <input type="hidden" id="actionType" name="action" value="update">
        
        <div class="form-group">
          <label for="clubNombre">Nombre *</label>
          <input type="text" id="clubNombre" name="nombre" value="<?= htmlspecialchars($club['nombre']) ?>" required>
        </div>
        
        <div class="form-group">
          <label for="clubPais">País *</label>
          <input type="text" id="clubPais" name="pais" value="<?= htmlspecialchars($club['pais']) ?>" required>
        </div>
        
        <div class="form-group">
          <label for="clubCiudad">Ciudad *</label>
          <input type="text" id="clubCiudad" name="ciudad" value="<?= htmlspecialchars($club['ciudad']) ?>" required>
        </div>
        
        <div class="form-group">
          <label for="clubComuna">Comuna *</label>
          <input type="text" id="clubComuna" name="comuna" value="<?= htmlspecialchars($club['comuna']) ?>" required>
        </div>
        
        <div class="form-group">
          <label for="clubEmail">Email Responsable *</label>
          <input type="email" id="clubEmail" name="email_responsable" value="<?= htmlspecialchars($club['email_responsable']) ?>" required>
        </div>
        
        <div class="form-group">
          <label for="clubTelefono">Teléfono</label>
          <input type="text" id="clubTelefono" name="telefono" value="<?= htmlspecialchars($club['telefono']) ?>">
        </div>
        
        <button type="submit" class="btn-submit">Guardar Cambios</button>
      </form>
    </div>
  </div>

  <script>
    function saveClub(event) {
        event.preventDefault();
        
        const formData = new FormData();
        formData.append('action', document.getElementById('actionType').value);
        formData.append('id_club', document.getElementById('clubId').value);
        formData.append('nombre', document.getElementById('clubNombre').value);
        formData.append('pais', document.getElementById('clubPais').value);
        formData.append('ciudad', document.getElementById('clubCiudad').value);
        formData.append('comuna', document.getElementById('clubComuna').value);
        formData.append('email_responsable', document.getElementById('clubEmail').value);
        formData.append('telefono', document.getElementById('clubTelefono').value);
        
        fetch('../api/gestion_clubs.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ Club actualizado');
                window.location.href = 'dashboard_socio.php?id_club=<?= htmlspecialchars($_SESSION['current_club']) ?>';
            } else {
                alert('❌ ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al guardar el club');
        });
    }
  </script>
</body>
</html>