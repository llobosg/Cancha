<?php
// includes/email_helper.php

/**
 * Genera el HTML del correo con estilo CanchaSport
 * @param string $titulo Título principal
 * @param string $mensaje Contenido
 * @param string $boton_texto (Opcional) Texto del botón
 * @param string $boton_link (Opcional) Enlace del botón
 */
function generarEmailHTML($titulo, $mensaje, $boton_texto = null, $boton_link = null) {
    // Colores de la marca
    $color_primario = "#AB47BC"; // Morado CanchaSport
    $color_boton = "#4ECDC4";    // Verde/Turquesa para resaltar acción
    
    $html_boton = "";
    if ($boton_texto && $boton_link) {
        $html_boton = "<div style='text-align:center; margin: 25px 0;'>
            <a href='{$boton_link}' style='background-color: {$color_boton}; color: #ffffff; padding: 12px 25px; text-decoration: none; border-radius: 25px; font-weight: bold; display: inline-block;'>
                {$boton_texto} &rarr;
            </a>
        </div>";
    }

    return "<!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    </head>
    <body style='margin:0; padding:0; background-color:#f4f4f4; font-family: sans-serif;'>
        <table width='100%' cellpadding='0' cellspacing='0' style='max-width: 600px; margin: 20px auto; background:#ffffff; border-radius:10px; overflow:hidden; box-shadow:0 4px 10px rgba(0,0,0,0.1);'>
            <!-- Header -->
            <tr>
                <td style='background: linear-gradient(135deg, #CE93D8 0%, #AB47BC 100%); padding: 30px; text-align: center; color: white;'>
                    <h1 style='margin:0; font-size: 24px;'>CanchaSport</h1>
                </td>
            </tr>
            <!-- Body -->
            <tr>
                <td style='padding: 30px; color: #333333; line-height: 1.6;'>
                    <h2 style='color: {$color_primario}; margin-top:0;'>{$titulo}</h2>
                    {$mensaje}
                    {$html_boton}
                    <p style='font-size: 12px; color: #999999; border-top: 1px solid #eee; padding-top: 20px; margin-top: 30px;'>
                        Si tienes dudas, contacta a tu recinto deportivo.<br>
                        © CanchaSport - Sistema de Gestión.
                    </p>
                </td>
            </tr>
        </table>
    </body>
    </html>";
}
?>