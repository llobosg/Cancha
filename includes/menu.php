<?php
// Obtener datos del usuario desde la sesión
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';
$rol_usuario = $_SESSION['rol'] ?? 'socio';
$genero_usuario = $_SESSION['genero'] ?? 'masculino'; // Asegúrate de tener este campo en tu tabla usuarios

// Definir ícono por género
if ($genero_usuario === 'femenino') {
    $avatar_icon = '👩';
} elseif (in_array($rol_usuario, ['capitan', 'tesorero', 'director'])) {
    $avatar_icon = '👑'; // Jefatura
} else {
    $avatar_icon = '👤'; // Hombre o genérico
}

// Definir etiqueta amigable por rol
$etiquetas_rol = [
    'capitan' => 'Capitán',
    'tesorero' => 'Jefe de Finanzas',
    'director' => 'Director',
    'socio' => 'Socio',
];
$rol_amigable = $etiquetas_rol[$rol_usuario] ?? ucfirst($rol_usuario);

// Clase CSS por rol
$clase_rol = 'role-' . $rol_usuario;
?>
<!-- includes/menu.php -->
<!-- Menú principal -->
<nav style="background: #3a4f63; padding: 0; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
    <ul style="list-style: none; margin: 0; padding: 0; display: flex; align-items: center;">
        <li><a href="?page=registro" style="color: white; text-decoration: none; padding: 1rem 1.2rem; display: block; font-weight: 500;">Registro</a></li>
        <li><a href="?page=inscripcion" style="color: white; text-decoration: none; padding: 1rem 1.2rem; display: block; font-weight: 500;">Inscripción</a></li>

        <?php if ($_SESSION['rol'] === 'capitan' || $_SESSION['rol'] === 'tesorero'): ?>
        <li><a href="?page=finanzas" style="color: white; text-decoration: none; padding: 1rem 1.2rem; display: block; font-weight: 500;">Finanzas</a></li>
        <?php endif; ?>

        <?php if ($_SESSION['rol'] === 'capitan' || $_SESSION['rol'] === 'tesorero'): ?>
        <li><a href="?page=eventos" style="color: white; text-decoration: none; padding: 1rem 1.2rem; display: block; font-weight: 500;">Eventos</a></li>
        <?php endif; ?>

        <?php if ($_SESSION['rol'] === 'capitan' || $_SESSION['rol'] === 'tesorero'|| $_SESSION['rol'] === 'director'): ?>
        <li style="position: relative;">
            <a href="#" id="menu-tablas" style="color: white; text-decoration: none; padding: 1rem 1.2rem; display: block; font-weight: 500; cursor: pointer;">
                Tablas <i class="fas fa-caret-down" style="margin-left: 0.4rem;"></i>
            </a>
            <div id="submenu-tablas" style="display: none; position: absolute; top: 100%; left: 0; background: white; min-width: 200px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); border-radius: 6px; z-index: 1000;">
                <?php
                $tablas = [
                    ['label' => 'Socios', 'page' => 'socios'],
                    ['label' => 'Club', 'page' => 'Club'],
                    ['label' => 'Comerciales', 'page' => 'Tipos Ingreso/Egreso'],
                    ['label' => 'Commoditys', 'page' => 'Tipos Evento'],
                    ['label' => 'Concéptos', 'page' => 'Puestos'],
                ];
                foreach ($tablas as $t) {
                    echo "<a href='?page={$t['page']}' style='display: block; padding: 0.6rem 1rem; color: #333; text-decoration: none; border-bottom: 1px solid #eee; font-size: 0.95rem;'>{$t['label']}</a>";
                }
                ?>
            </div>
        </li>
        <!-- Badge de usuario -->
        <div class="user-badge <?php echo $clase_rol; ?>">
            <div class="user-avatar"><?php echo $avatar_icon; ?></div>
            <div>
                <div><?php echo htmlspecialchars($nombre_usuario); ?></div>
                <div style="font-size: 12px; opacity: 0.9; font-weight: 400;"><?php echo $rol_amigable; ?></div>
            </div>
        </div>
        <?php endif; ?>
    </ul>
</nav>

<!-- Script para desplegar menú Tablas -->
<script>
document.getElementById('menu-tablas')?.addEventListener('click', function(e) {
    e.preventDefault();
    const submenu = document.getElementById('submenu-tablas');
    submenu.style.display = submenu.style.display === 'block' ? 'none' : 'block';
});
// Cerrar al hacer clic fuera
document.addEventListener('click', function(e) {
    const menu = document.getElementById('menu-tablas');
    const submenu = document.getElementById('submenu-tablas');
    if (menu && !menu.contains(e.target) && submenu && !submenu.contains(e.target)) {
        submenu.style.display = 'none';
    }
});
</script>

<!-- Espacio para que el contenido no quede debajo del menú fijo -->
<div style="height: 70px;"></div>

<!-- Estilos para el menú -->
<style>
.nav-link {
    color: white;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    transition: all 0.3s ease;
    position: relative;
}

.nav-link:hover {
    background: #5a6e82;
    color: #fff;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

/* Estilo para la opción activa: fondo suave + borde inferior elegante */
.nav-link.active {
    background: rgba(255, 255, 255, 0.15);
    color: #ffffff;
    font-weight: 600;
    transform: none;
    box-shadow: none;
}

.nav-link.active::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 3px;
    background: linear-gradient(90deg, #007bff, #00c6ff);
    border-radius: 2px 2px 0 0;
}

/* Estilo para nombre usuario, rol y género */
.user-badge {
    position: absolute;
    top: 0px; /* Ajusta según la altura de tu header */
    right: 20px; /* Espacio desde el borde derecho */
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-weight: 600;
    font-size: 14px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    transition: all 0.3s ease;
    cursor: default;
}

.user-badge:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

.user-avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255,255,255,0.2);
    font-size: 14px;
}

/* Colores por rol */
.role-admin_finanzas { background: linear-gradient(135deg, #ff6b6b, #ee5a24); }
.role-pricing { background: linear-gradient(135deg, #4ecdc4, #44a08d); }
.role-operaciones { background: linear-gradient(135deg, #45b7d1, #96c93d); }
.role-comercial { background: linear-gradient(135deg, #ff9ff3, #f368e0); }
.role-admin { background: linear-gradient(135deg, #feca57, #ff9ff3); }
</style>