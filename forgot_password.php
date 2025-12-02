<?php
// --- INICIO: Carga de PHPMailer ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';
// --- FIN: Carga de PHPMailer ---

// --- NUEVO: Incluir el archivo centralizado para la función de envío ---
require 'includes/send_email.php'; 
// --- FIN NUEVO ---

session_start();
// NOTA: Asumo que config.php se carga aquí o antes de send_email.php
require '/var/www/config/config.php'; 

$email = "";
$email_err = $mensaje_exito = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (empty(trim($_POST["email"]))) {
        $email_err = "Por favor, ingresa tu correo electrónico.";
    } else {
        $email = trim($_POST["email"]);
    }

    if (empty($email_err)) {
        // Verificar si el correo existe
        $sql = "SELECT id_usuario, nombre FROM usuario WHERE email = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows == 1) {
                $stmt->bind_result($id_usuario, $nombre_usuario);
                $stmt->fetch();

                // Generar token y fecha de expiración (ej. 1 hora)
                $token = bin2hex(random_bytes(32));
                $expiry = date("Y-m-d H:i:s", strtotime('+1 hour'));
                
                $conn->begin_transaction();
                try {
                    // 1. Guardar token y expiración en la base de datos
                    $sql_update = "UPDATE usuario SET reset_token = ?, reset_token_expiry = ? WHERE id_usuario = ?";
                    if ($stmt_update = $conn->prepare($sql_update)) {
                        $stmt_update->bind_param("ssi", $token, $expiry, $id_usuario);
                        $stmt_update->execute();
                        $stmt_update->close();
                    } else {
                        throw new Exception("Error al preparar la actualización de la BD.");
                    }

                    // 2. Definir contenido del correo
                    $url_reset = "https://bitware.site/reset_password.php?token=" . $token; 
                    $asunto = "Restablecer tu contraseña en Bitware";
                    $mensaje = "<html><body>
                      <h2>Hola, " . htmlspecialchars($nombre_usuario) . "!</h2>
                      <p>Recibimos una solicitud para restablecer tu contraseña. Haz clic en el siguiente enlace para continuar:</p>
                      <p><a href='" . $url_reset . "'>" . $url_reset . "</a></p>
                      <p>Este enlace expirará en 1 hora. Si no solicitaste esto, ignora este correo.</p>
                      </body></html>";

                    // --- INICIO: USANDO LA FUNCIÓN CENTRALIZADA ---
                    // NOTA: Si el SMTP falla, lanza una Exception
                    sendEmail($email, $nombre_usuario, $asunto, $mensaje, 'Bitware (No Responder)');
                    // --- FIN: USANDO LA FUNCIÓN CENTRALIZADA ---
                    
                    $conn->commit();
                    $mensaje_exito = "Se han enviado las instrucciones para restablecer tu contraseña a tu correo electrónico.";

                } catch (Exception $e) {
                    $conn->rollback();
                    $email_err = "No se pudo enviar el correo. Inténtalo de nuevo."; 
                    error_log("PHPMailer Error (forgot_password.php): " . $e->getMessage());
                }

            } else {
                // Mensaje genérico por seguridad
                $mensaje_exito = "Si existe una cuenta asociada a ese correo, se han enviado las instrucciones.";
            }
            $stmt->close();
        }
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Olvidé mi Contraseña - Bitware</title>
    <link rel="stylesheet" href="css/login.css">
    <link rel="icon" href="images/favicon.ico" type="image/ico">
</head>
<body>
    <div class="left-container">
         <img src="Recursos/Recursos_login/imagen-principal.png" class="main-image" alt="">
    </div>
    <div class="login-container">
        <img src="Recursos\Recursos_login\img-contenedor.png" alt="Logo Bitware">
        <h2>Restablecer Contraseña</h2>
        <p class="subtitle">Ingresa tu correo electrónico y te enviaremos las instrucciones.</p>

        <?php if (!empty($mensaje_exito)): ?>
            <div style="color: green; background: #e6ffe6; padding: 10px; border-radius: 6px; margin-bottom: 15px;">
                <?php echo htmlspecialchars($mensaje_exito); ?>
            </div>
            <p><a href="login.php">Volver a Inicio de Sesión</a></p>
        <?php else: ?>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <input type="email" name="email" placeholder="Correo Electrónico" value="<?php echo htmlspecialchars($email); ?>" required>
                <span class="invalid-feedback"><?php echo $email_err; ?></span>
                <button type="submit">Enviar Instrucciones</button>
                <div class="register-link" style="margin-top: 15px;">
                    <a href="login.php">Cancelar y volver a Inicio de Sesión</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>