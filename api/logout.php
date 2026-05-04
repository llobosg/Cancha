<?php
// api/logout.php
session_start();

// 1. Limpiar todas las variables de sesión
$_SESSION = [];

// 2. Destruir la cookie de sesión
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"] ?? false, $params["httponly"] ?? true
    );
}

// 3. Limpiar cookies específicas de la app
setcookie('cancha_login_type', '', time() - 3600, '/');
setcookie('cancha_session_id', '', time() - 3600, '/');
setcookie('cancha_id_socio', '', time() - 3600, '/');

// 4. Destruir sesión en servidor
session_destroy();

// 5. Redirigir al inicio
header('Location: ../index.php');
exit;
?>