<?php
// Desactivar reporte de errores para un entorno limpio (opcional)
error_reporting(0);

// -- DATOS DEL CORREO --
$destinatario = "cliente@ejemplo.com"; // Puede ser cualquier correo, MailHog lo capturará.
$asunto = "¡Prueba de correo desde Bitware!";
$mensaje = "
<html>
<head>
  <title>Confirmación de Registro</title>
</head>
<body>
  <h2>¡Hola!</h2>
  <p>Si estás viendo este mensaje, significa que tu configuración de PHP y MailHog para el proyecto Bitware funciona perfectamente.</p>
  <p>¡Buen trabajo!</p>
</body>
</html>
";

// -- CABECERAS PARA ENVIAR CORREO HTML --
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
$headers .= 'From: Bitware <no-reply@bitware.cl>' . "\r\n";

// -- ENVIAR EL CORREO --
if (mail($destinatario, $asunto, $mensaje, $headers)) {
    echo "✅ Correo de prueba enviado con éxito. ¡Revisa MailHog!";
} else {
    echo "❌ Hubo un error al enviar el correo.";
}
?>