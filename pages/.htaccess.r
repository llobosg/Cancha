# .htaccess
RewriteEngine On

# Redirigir /registro_club → /pages/registro_club.php
RewriteRule ^registro_club$ pages/registro_club.php [L]
RewriteRule ^buscar_club$ pages/buscar_club.php [L]
RewriteRule ^dashboard$ pages/dashboard.php [L]