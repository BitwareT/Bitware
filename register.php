<?php
// --- INICIO: Carga de PHPMailer ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';
// --- FIN: Carga de PHPMailer ---

// --- NUEVO: Incluir el archivo centralizado para la función de envío ---
require 'includes/send_email.php'; 
// --- FIN NUEVO ---

require '/var/www/config/config.php';
session_start();

// --- 1. DEFINES LAS VARIABLES PARA EL HEAD ---
$titulo_pagina = 'Registro - Bitware';
// Usamos el CSS de login.css para el panel izquierdo y register.css para el derecho
$estilos_especificos = [
    "css/login.css",
    "css/register.css"
];
require 'includes/head2.php';
// --- FIN HEAD ---

// --- 2. LÓGICA DE USUARIOS EN TIEMPO REAL (Igual que en login) ---
$total_usuarios_activos = 0;
$sql_count = "SELECT COUNT(*) as total FROM usuario WHERE activo = 1";
if ($result_count = $conn->query($sql_count)) {
    $data_count = $result_count->fetch_assoc();
    $total_usuarios_activos = $data_count['total'] ?? 0;
}
// --- FIN LÓGICA USUARIOS ---

$nombre = $email = $password = $confirm_password = ""; // AÑADIDO: $confirm_password
$nombre_err = $email_err = $password_err = $confirm_password_err = ""; // AÑADIDO: $confirm_password_err
$registro_exitoso = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- (Validaciones de Nombre y Email sin cambios) ---
    if (empty(trim($_POST["nombre"]))) {
        $nombre_err = "Por favor, ingresa tu nombre.";
    } else {
        $nombre = trim($_POST["nombre"]);
    }
    if (empty(trim($_POST["email"]))) {
        $email_err = "Por favor, ingresa tu email.";
    } else {
        $email_candidato = strtolower(trim($_POST["email"]));
        $sql_check = "SELECT id_usuario FROM usuario WHERE email = ?";
        if ($stmt_check = mysqli_prepare($conn, $sql_check)) {
            mysqli_stmt_bind_param($stmt_check, "s", $email_candidato);
            if (mysqli_stmt_execute($stmt_check)) {
                mysqli_stmt_store_result($stmt_check);
                if (mysqli_stmt_num_rows($stmt_check) == 1) {
                    $email_err = "Este email ya está en uso.";
                } else {
                    $email = $email_candidato;
                }
            } else { $email_err = "Error al verificar el email."; }
            mysqli_stmt_close($stmt_check);
        } else { $email_err = "Error de preparación de consulta."; }
    }
    
    // --- NUEVA VALIDACIÓN DE CONTRASEÑA ---
    if (empty(trim($_POST["password"]))) {
        $password_err = "Por favor, ingresa una contraseña.";
    } else {
        $password_candidata = trim($_POST["password"]);
        
        if (strlen($password_candidata) < 8) {
            $password_err = "La contraseña debe tener al menos 8 caracteres.";
        } 
        elseif (!preg_match('/[^a-zA-Z0-9]/', $password_candidata)) {
            $password_err = "La contraseña debe contener al menos un caracter especial (ej. #, $, %, &).";
        }
        else {
            $password = $password_candidata;
        }
    }

    // --- NUEVA VALIDACIÓN DE CONFIRMACIÓN DE CONTRASEÑA ---
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Por favor, confirma la contraseña.";
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Las contraseñas no coinciden.";
        }
    }
    // --- FIN: VALIDACIÓN DE CONTRASEÑA Y CONFIRMACIÓN ---
    
    
    if (empty($nombre_err) && empty($email_err) && empty($password_err) && empty($confirm_password_err)) { // SE INCLUYE $confirm_password_err
        
        $permiso_default = 'U';
        $hashed_password = password_hash($password, PASSWORD_DEFAULT); 
        $codigo_verificacion = bin2hex(random_bytes(16));
        
        $conn->begin_transaction();
        try {
            // 1. Insertar usuario
            $sql_insert = "INSERT INTO usuario (nombre, email, password, permisos, codigo_verificacion) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql_insert);
            $stmt->bind_param("sssss", $nombre, $email, $hashed_password, $permiso_default, $codigo_verificacion);
            $stmt->execute();
            $stmt->close();

            // 2. Definir contenido del correo
            $url_verificacion = "https://bitware.site/verificar.php?code=" . $codigo_verificacion;
            $asunto_correo = "Verifica tu cuenta en Bitware";
            $mensaje_correo = "
            <html><body>
            <img src='cid:logo_bitware' alt='Logo de Bitware' width='150'> 
            <h2>¡Hola, " . htmlspecialchars($nombre) . "!</h2>
            <p>Gracias por registrarte en Bitware. Para activar tu cuenta, haz clic en el siguiente enlace:</p>
            <p><a href='" . $url_verificacion . "'>" . $url_verificacion . "</a></p>
            <p>Si no te registraste, puedes ignorar este correo.</p>
            <br><p><em>El equipo de Bitware</em></p>
            </body></html>";
            
            // --- BLOQUE PHPMailer (REEMPLAZADO POR LA FUNCIÓN) ---
            // El tercer argumento ('Bitware (Verificación...)') es el nombre del remitente.
            sendEmail($email, $nombre, $asunto_correo, $mensaje_correo, 'Bitware (Verificación de Cuenta)');
            // --- FIN: BLOQUE PHPMailer ---

            // Si todo fue bien (SQL y Correo), confirmar
            $conn->commit();
            $registro_exitoso = true; 

        } catch (Exception $e) {
            $conn->rollback();
            $password_err = "Hubo un problema al enviar el correo de verificación. Intenta de nuevo.";
            error_log("Error de envío de correo (register.php): " . $e->getMessage());
        }
    }
    mysqli_close($conn);
}
?>

<div class="form-panel-left">
    <div class="left-content">
        <div class="logo-lightning">
            <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="bi bi-lightning-fill" viewBox="0 0 16 16">
                <path d="M5.52.359A.5.5 0 0 1 6 0h4a.5.5 0 0 1 .47.659L8.636 6H13.5a.5.5 0 0 1 .395.807l-7 9a.5.5 0 0 1-.873-.418L7.361 10H3.5a.5.5 0 0 1-.395-.807l7-9a.5.5 0 0 1 .47-.659z"/>
            </svg>
        </div>
        <h1>Únete a la Comunidad</h1>
        <p>
            Crea tu cuenta para acceder a compras más rápidas,
            listas de deseos y soporte exclusivo.
        </p>
        <div class="stats-grid">
            <div>
                <span><?php echo number_format($total_usuarios_activos); ?>+</span>
                <p>Usuarios activos</p>
            </div>
            <div>
                <span>99.9%</span>
                <p>Uptime</p>
            </div>
            <div>
                <span>24/7</span>
                <p>Soporte</p>
            </div>
        </div>
    </div>
</div>
<div class="register-container">
    <?php if ($registro_exitoso): ?>
        
        <img src="Recursos/Recursos_login/img-contenedor.png" alt="Logo Bitware" style="width: 70px; height: 70px; border-radius: 50%; object-fit: cover; background-color: #007bff; padding: 10px; margin-bottom: 15px;">
        <h2 style="font-size: 26px; font-weight: 700; margin-bottom: 8px;">¡Registro Casi Completo!</h2>
        <p style="font-size: 15px; color: #555; text-align: center; margin-bottom: 25px;">Hemos enviado un correo electrónico a <strong><?php echo htmlspecialchars($email); ?></strong>.</p>
        <p style="font-size: 15px; color: #555; text-align: center; margin-bottom: 25px;">Por favor, revisa tu bandeja de entrada (y spam) y haz clic en el enlace de verificación para activar tu cuenta.</p>
        <a href="login.php" style="padding: 12px; font-size: 16px; font-weight: 600; background-color: #007bff; color: white; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; text-align: center;">Volver a Inicio de Sesión</a>
    
    <?php else: ?>
    
        <img src="Recursos/Recursos_login/img-contenedor.png" alt="Logo Bitware" style="width: 70px; height: 70px; border-radius: 50%; object-fit: cover; background-color: #007bff; padding: 10px; margin-bottom: 15px;">
        <h2>Registro de Usuario</h2>
        <p class="subtitle">Completa este formulario para crear una cuenta.</p>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            
            <div  div class="form-group">
                <label>Nombre</label>
                <input type="text" name="nombre" value="<?php echo htmlspecialchars($nombre); ?>" required>
                <span class="help-block"><?php echo $nombre_err; ?></span>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                <span class="help-block"><?php echo $email_err; ?></span>
            </div>

            <div class="form-group">
                <label>Contraseña (Mínimo 8 caracteres y 1 caracter especial)</label>
                <input 
                    type="password" 
                    name="password" 
                    id="password" 
                    required
                    onfocus="showPasswordRequirements()"
                    onblur="hidePasswordRequirements()"
                >
                <span class="help-block"><?php echo $password_err; ?></span>

                <div id="password-requirements-popup" class="password-requirements-popup">
                    <div class="popup-arrow"></div>
                    <p>La contraseña debe cumplir con los siguientes requerimientos:</p>
                    <ul class="requirements-list">
                        <li id="req-length"><span class="icon"></span>8 caracteres como mínimo</li>
                        <li id="req-lowercase"><span class="icon"></span>Al menos una letra en minúscula</li> 
                        <li id="req-uppercase"><span class="icon"></span>Al menos una letra en mayúsculas</li>
                        <li id="req-number"><span class="icon"></span>Al menos un número</li>
                        <li id="req-special"><span class="icon"></span>Al menos un carácter especial (ej. #, $, %)</li>
                    </ul>
                </div>
                <div class="password-strength-indicator">
                    <div id="strength-bar" class="strength-bar"></div>
                    <span id="strength-text"></span>
                </div>
            </div>

            <div class="form-group">
                <label>Confirmar Contraseña</label>
                <input type="password" name="confirm_password" required>
                <span class="help-block"><?php echo $confirm_password_err; ?></span>
            </div>
            <div class="form-group">
                <input type="submit" value="Crear Cuenta">
            </div>
        </form>
        <p class="register-link">¿Ya tienes una cuenta? <a href="login.php">Inicia sesión aquí</a>.</p>
        <p class="register-link"><a href="index.php">Regresar al Inicio </a></p>
        
        <?php endif; ?>
</div>
    <script src="js/register.js"></script>
</body>
</html>