<?php
require_once __DIR__ . '/../includes/config.php';

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Obtener club desde URL
$club_slug_from_url = $_GET['club'] ?? '';

// Validar slug básico
if (!$club_slug_from_url || strlen($club_slug_from_url) !== 8 || !ctype_alnum($club_slug_from_url)) {
    header('Location: ../index.php');
    exit;
}

// Buscar el club que coincide con el slug
$stmt_club = $pdo->prepare("SELECT id_club, email_responsable, nombre FROM clubs WHERE email_verified = 1");
$stmt_club->execute();
$clubs = $stmt_club->fetchAll();

$club_id = null;
$club_nombre = '';
$club_slug = null;

foreach ($clubs as $c) {
    $generated_slug = substr(md5($c['id_club'] . $c['email_responsable']), 0, 8);
    if ($generated_slug === $club_slug_from_url) {
        $club_id = (int)$c['id_club'];
        $club_nombre = $c['nombre'];
        $club_slug = $generated_slug;
        break;
    }
}

if (!$club_id) {
    header('Location: ../index.php');
    exit;
}

// Verificar que el socio esté en sesión
$id_socio = null;

if (isset($_SESSION['id_socio'])) {
    // Caso normal: socio ya en sesión
    $id_socio = $_SESSION['id_socio'];
} else {
    // Caso especial: responsable que acaba de registrar club
    // Buscar al responsable del club actual
    $stmt = $pdo->prepare("
        SELECT s.id_socio 
        FROM socios s 
        WHERE s.id_club = ? AND s.es_responsable = 1
    ");
    $stmt->execute([$club_id]);
    $responsable = $stmt->fetch();
    
    if ($responsable) {
        // Encontramos al responsable, guardar en sesión
        $id_socio = $responsable['id_socio'];
        $_SESSION['id_socio'] = $id_socio;
        $_SESSION['club_id'] = $club_id;
        $_SESSION['current_club'] = $club_slug;
    } else {
        // No hay responsable encontrado, redirigir a registro
        header('Location: registro_socio.php?club=' . $club_slug);
        exit;
    }
}

// Verificar que el socio existe y pertenece a este club
$stmt = $pdo->prepare("SELECT * FROM socios WHERE id_socio = ? AND id_club = ?");
$stmt->execute([$id_socio, $club_id]);
$socio = $stmt->fetch();

if (!$socio) {
    header('Location: ../index.php');
    exit;
}

// Obtener puestos para el select
$stmt_puestos = $pdo->query("SELECT id_puesto, puesto FROM puestos ORDER BY puesto");
$puestos = $stmt_puestos->fetchAll();

$error = '';
$success = '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Completar Perfil - <?= htmlspecialchars($club_nombre) ?> | Cancha</title>
  <link rel="stylesheet" href="../styles.css">
  <style>
    body {
      background: 
        linear-gradient(rgba(0, 20, 10, 0.65), rgba(0, 30, 15, 0.75)),
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

    .profile-form {
      background: white;
      padding: 2rem;
      border-radius: 16px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.25);
    }

    .form-title {
      color: #003366;
      text-align: center;
      margin-bottom: 1.5rem;
      font-size: 1.5rem;
    }

    .error {
      background: #ffebee;
      color: #c62828;
      padding: 0.7rem;
      border-radius: 6px;
      margin-bottom: 1.5rem;
      text-align: center;
      font-size: 0.85rem;
    }

    .success {
      background: #e8f5e8;
      color: #2e7d32;
      padding: 0.7rem;
      border-radius: 6px;
      margin-bottom: 1.5rem;
      text-align: center;
      font-size: 0.85rem;
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

    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 0.6rem;
      border: 1px solid #ccc;
      border-radius: 5px;
      color: #071289;
    }

    .btn-submit {
      width: 100%;
      padding: 0.9rem;
      background: #071289;
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 1.1rem;
      font-weight: bold;
      cursor: pointer;
      transition: background 0.2s;
    }

    .btn-submit:hover {
      background: #050d6b;
    }

    /* Responsive móvil */
    @media (max-width: 768px) {
      .container {
        padding: 1rem;
      }
      
      .profile-form {
        padding: 1.5rem;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <a href="../index.php" class="back-btn">← Volver al inicio</a>
    
    <div class="profile-form">
      <h2 class="form-title">Completar mi Perfil</h2>
      
      <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      
      <?php if ($success): ?>
        <div class="success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <form id="perfilForm" onsubmit="savePerfil(event)">
        <input type="hidden" id="socioId" name="id_socio" value="<?= $id_socio ?>">
        <input type="hidden" id="clubSlug" name="club_slug" value="<?= $club_slug ?>">
        
        <div class="form-group">
          <label for="alias">Alias *</label>
          <input type="text" id="alias" name="alias" required>
        </div>
        
        <div class="form-group">
          <label for="fecha_nac">Fecha de Nacimiento *</label>
          <input type="date" id="fecha_nac" name="fecha_nac" required>
        </div>
        
        <div class="form-group">
          <label for="celular">Celular *</label>
          <input type="text" id="celular" name="celular" required>
        </div>
        
        <div class="form-group">
          <label for="direccion">Dirección *</label>
          <textarea id="direccion" name="direccion" rows="2" required></textarea>
        </div>
        
        <div class="form-group">
          <label for="rol">Rol en el club *</label>
          <input type="text" id="rol" name="rol" required>
        </div>
        
        <div class="form-group">
          <label for="id_puesto">Puesto *</label>
          <select id="id_puesto" name="id_puesto" required>
            <option value="">Seleccionar puesto</option>
            <?php foreach ($puestos as $puesto): ?>
            <option value="<?= $puesto['id_puesto'] ?>"><?= htmlspecialchars($puesto['puesto']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <div class="form-group">
          <label for="genero">Género *</label>
          <select id="genero" name="genero" required>
            <option value="">Seleccionar género</option>
            <option value="masculino">Masculino</option>
            <option value="femenino">Femenino</option>
            <option value="otro">Otro</option>
          </select>
        </div>
        
        <div class="form-group">
          <label for="habilidad">Nivel de habilidad *</label>
          <select id="habilidad" name="habilidad" required>
            <option value="">Seleccionar nivel</option>
            <option value="Principiante">Principiante</option>
            <option value="Intermedia">Intermedia</option>
            <option value="Avanzada">Avanzada</option>
          </select>
        </div>
        
        <button type="submit" class="btn-submit">Completar Perfil</button>
      </form>
    </div>
  </div>

  <script>
    function savePerfil(event) {
      event.preventDefault();
      
      const formData = new FormData();
      formData.append('id_socio', document.getElementById('socioId').value);
      formData.append('alias', document.getElementById('alias').value);
      formData.append('fecha_nac', document.getElementById('fecha_nac').value);
      formData.append('celular', document.getElementById('celular').value);
      formData.append('direccion', document.getElementById('direccion').value);
      formData.append('rol', document.getElementById('rol').value);
      formData.append('id_puesto', document.getElementById('id_puesto').value);
      formData.append('genero', document.getElementById('genero').value);
      formData.append('habilidad', document.getElementById('habilidad').value);
      
      fetch('../api/completar_perfil.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Redirigir al dashboard del club
          const clubSlug = document.getElementById('clubSlug').value;
          window.location.href = 'dashboard_socio.php?id_club=' + clubSlug;
        } else {
          alert('Error: ' + data.message);
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Error al completar el perfil');
      });
    }
  </script>
</body>
</html>