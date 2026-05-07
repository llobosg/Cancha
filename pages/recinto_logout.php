<?php
// pages/recinto_logout.php

// 1. Iniciar sesión si no está iniciada (para poder destruirla)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Destruir todas las variables de sesión
$_SESSION = array();

// 3. Si se usa una cookie de sesión, borrarla también
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Destruir la sesión
session_destroy();

// 5. Redirigir al login
header('Location: index.php');
exit;
?>