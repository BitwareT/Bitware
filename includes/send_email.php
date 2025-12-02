<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// NOTA: Asume que 'vendor/autoload.php' y 'config.php' ya han sido incluidos.

/**
 * Envía un correo electrónico usando la configuración SMTP centralizada.
 *
 * @param string $destinatario_email
 * @param string $destinatario_nombre
 * @param string $asunto
 * @param string $cuerpo_html
 * @param string $remitente_nombre (opcional, por defecto 'Bitware')
 * @return bool True si el correo se envió con éxito, False en caso contrario.
 * @throws Exception si PHPMailer falla.
 */
function sendEmail($destinatario_email, $destinatario_nombre, $asunto, $cuerpo_html, $remitente_nombre = 'Bitware') {
    
    // Las credenciales y la configuración se obtienen de las constantes globales (config.php)

    $mail = new PHPMailer(true);
    
    // Configuración SMTP centralizada
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USERNAME;
    $mail->Password   = SMTP_PASSWORD; 
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
    $mail->Port       = SMTP_PORT;

    $mail->setFrom(SMTP_USERNAME, $remitente_nombre);
    $mail->addAddress($destinatario_email, $destinatario_nombre);
    $mail->isHTML(true);
    $mail->Subject = $asunto;
    $mail->Body    = $cuerpo_html;
    $mail->CharSet = 'UTF-8';
    
    // Asume que la ruta de la imagen es relativa al script principal (ej. images/Favicon.png)
    $mail->AddEmbeddedImage('images/Favicon.png', 'logo_bitware');
    
    return $mail->send();
}
?>