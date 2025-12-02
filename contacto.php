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
require '/var/www/config/config.php';

// Variables para los mensajes de estado
$mensaje_exito = "";
$mensaje_error = "";

// Variables para pre-llenar el formulario (se llenarán si el usuario está logueado)
$nombre_form = "";
$email_form = "";
$asunto_form = "";
$mensaje_form = "";

// 1. Verificar si el usuario ha iniciado sesión
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true && isset($_SESSION["id"])) {
    $id_usuario = $_SESSION["id"];
    // (Asumimos que guardas 'nombre' y 'email' en la sesión durante el login)
    $nombre_usuario = $_SESSION['nombre'] ?? '';
    $email_usuario = $_SESSION['email'] ?? '';
    
    // Pre-llenar el formulario con los datos de sesión
    $nombre_form = $nombre_usuario;
    $email_form = $email_usuario;

} else {
    // Si no está logueado, redirigir al login
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        header("location: login.php?redirect=contacto.php");
        exit;
    }
}

// 2. Procesar el formulario si se envió (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Recoger datos (el id, nombre y email ya los tenemos de la sesión)
    $asunto = trim($_POST['asunto']);
    $mensaje_inicial = trim($_POST['mensaje']);

    // Validar que los campos no estén vacíos
    if (!empty($asunto) && !empty($mensaje_inicial)) {

        // --- Lógica para crear Ticket y Mensaje ---
        $conn->begin_transaction(); // Iniciar transacción

        try {
            // Paso 1: Crear el ticket en soporte_tickets
            $sql_ticket = "INSERT INTO soporte_tickets (id_usuario, asunto, estado) VALUES (?, ?, 'Abierto')";
            $stmt_ticket = $conn->prepare($sql_ticket);
            $stmt_ticket->bind_param("is", $id_usuario, $asunto);
            $stmt_ticket->execute();
            $id_nuevo_ticket = $conn->insert_id; // Obtener el ID del ticket recién creado
            $stmt_ticket->close();

            if ($id_nuevo_ticket > 0) {
                // Paso 2: Crear el primer mensaje en soporte_mensajes
                $es_admin = 0; // 0 = mensaje del usuario
                $sql_mensaje = "INSERT INTO soporte_mensajes (id_ticket, id_remitente, es_admin, mensaje) VALUES (?, ?, ?, ?)";
                $stmt_mensaje = $conn->prepare($sql_mensaje);
                $stmt_mensaje->bind_param("iiis", $id_nuevo_ticket, $id_usuario, $es_admin, $mensaje_inicial);
                $stmt_mensaje->execute();
                $stmt_mensaje->close();

                // Si todo fue bien, confirmar transacción
                $conn->commit();
                $mensaje_exito = "¡Tu ticket de soporte (#{$id_nuevo_ticket}) ha sido creado! Puedes seguir su estado en tu panel.";
                
                // --- INICIO: REEMPLAZO POR LA FUNCIÓN CENTRALIZADA ---
                $asunto_correo = "Ticket Creado (ID: #{$id_nuevo_ticket}) - {$asunto}";
                
                $mail_body = "
                <html><body>
                <img src='cid:logo_bitware' alt='Logo de Bitware' width='150'>
                <h2>¡Hola, " . htmlspecialchars($nombre_usuario) . "!</h2>
                <p>Hemos recibido tu consulta y hemos creado un ticket de soporte con el ID <strong>#{$id_nuevo_ticket}</strong>.</p>
                <p><strong>Asunto:</strong> " . htmlspecialchars($asunto) . "</p>
                <p>Un miembro de nuestro equipo revisará tu mensaje y te responderá lo antes posible. Puedes ver el estado de tu ticket en tu panel de usuario.</p>
                <br><p><em>El equipo de Bitware</em></p>
                </body></html>";

                try {
                    // Llama a la función centralizada
                    sendEmail($email_usuario, $nombre_usuario, $asunto_correo, $mail_body, 'Bitware - Soporte Técnico');

                } catch (Exception $e_mail) {
                    // El ticket se creó, pero el email falló.
                    error_log("PHPMailer Error (contacto.php): " . $e_mail->getMessage());
                }
                // --- FIN: REEMPLAZO POR LA FUNCIÓN CENTRALIZADA ---

            } else {
                throw new Exception("No se pudo crear el ticket principal.");
            }

        } catch (Exception $e_db) {
            // Si algo falla en la BD, deshacer cambios
            $conn->rollback();
            $mensaje_error = "Hubo un error al crear tu ticket: " . $e_db->getMessage();
            error_log("Error creando ticket (BD): " . $e_db->getMessage());
        }
        
    } else {
        $mensaje_error = "Por favor, completa el asunto y el mensaje.";
    }
    
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Contacto - Bitware</title>
    <link rel="stylesheet" href="css/contacto.css">
    <link rel="icon" href="images/favicon.ico" type="image/ico">
    <style> /* Estilos rápidos para mensajes */
        .alert-success { padding: 10px; background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 4px; margin-bottom: 15px; }
        .alert-danger { padding: 10px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 4px; margin-bottom: 15px; }
        .alert-info { padding: 10px; background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; border-radius: 4px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Contáctanos</h1>
        <p>¿Tienes alguna duda o consulta? Llena el siguiente formulario para crear un ticket de soporte.</p>

        <?php if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true): ?>
            <div class="alert-info">Necesitas <a href="login.php?redirect=contacto.php">iniciar sesión</a> para crear un ticket.</div>
        <?php endif; ?>

        <?php if(!empty($mensaje_exito)): ?>
            <div class="alert-success"><?php echo $mensaje_exito; ?></div>
            <br>
            <p><a href="mis_tickets.php">Ver mis tickets</a> o <a href="index.php">Volver al inicio</a>.</p>
        <?php elseif(!empty($mensaje_error)): ?>
            <div class="alert-danger"><?php echo $mensaje_error; ?></div>
            <br>
        <?php endif; ?>

        <?php if(empty($mensaje_exito)): ?>
            <div class="container-left">
                <div class="info-container">
                    <h2>Información de Contacto</h2>
                    <p><img src="Recursos/Recursos_contacto/img-sobre.png" alt="#"><strong>Email:</strong> contacto@bitware.com</D>
                    <p><img src="Recursos/Recursos_contacto/img-telefono.png" alt="#"><strong>Teléfono:</strong> +56 9 32490076</p>
                    <p><img src="Recursos/Recursos_contacto/img-direccion.png" alt="#"><strong>Dirección:</strong> Omar Herreras Guitierrez 1636</p>
                </div>
                <div class="info-horario">
                    <h2>Información de Horario</h2>
                    <p><strong>Lunes - Viernes:</strong> 9:00 AM - 6:00 PM</p>
                    <p><strong>Sábado:</strong> 10:00 AM - 2:00 PM</p>
                    <p><strong>Domingo:</strong> Cerrado</p>
                </div>
            </div>

            <div class="container-right">
                <div class="form-container">
                    <form action="contacto.php" method="post">
                        <div class="form-group">
                            <label for="nombre">Tu Nombre</label>
                            <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($nombre_form); ?>" required readonly>
                        </div>
                        <div class="form-group">
                            <label for="email">Tu Correo Electrónico</label>
                             <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email_form); ?>" required readonly>
                        </div>
                        <div class="form-group">
                            <label for="asunto">Asunto</label>
                            <input type="text" id="asunto" name="asunto" value="<?php echo htmlspecialchars($asunto_form); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="mensaje">Mensaje</label>
                            <textarea id="mensaje" name="mensaje" rows="5" required><?php echo htmlspecialchars($mensaje_form); ?></textarea>
                        </div>
                        <button type="submit" <?php if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) echo 'disabled'; ?>>Crear Ticket</button>
                    </form>
                </div>
            </div>
            <div>
                <br>
                <a href="index.php"><button>Volver Al Inicio</button></a>
            </div>
        <?php endif; // Fin if(empty($mensaje_exito)) ?>
    </div>
</body>
</html>