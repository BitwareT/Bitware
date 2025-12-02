<?php
session_start();
require '/var/www/config/config.php';

$token = $_GET['token'] ?? '';
$password = $confirm_password = "";
$password_err = $token_err = $mensaje_exito = "";
$token_valido = false;
$id_usuario = 0;

if (empty($token)) {
    $token_err = "No se proporcionó un token de restablecimiento.";
} else {
    // Validar el token
    $sql = "SELECT id_usuario, reset_token_expiry FROM usuario WHERE reset_token = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows == 1) {
            $usuario = $result->fetch_assoc();
            // Verificar si el token ha expirado
            if (strtotime($usuario['reset_token_expiry']) > time()) {
                $token_valido = true;
                $id_usuario = $usuario['id_usuario'];
            } else {
                $token_err = "El token ha expirado. Solicita un nuevo restablecimiento.";
            }
        } else {
            $token_err = "El token no es válido.";
        }
        $stmt->close();
    }
}

// Procesar el formulario de nueva contraseña
if ($_SERVER["REQUEST_METHOD"] == "POST" && $token_valido) {
    if (empty(trim($_POST["password"]))) {
        $password_err = "Ingresa la nueva contraseña.";
    } else {
        $password_candidata = trim($_POST["password"]);
        
        // --- INICIO: NUEVA VALIDACIÓN REFORZADA ---
        if (strlen($password_candidata) < 8) {
            $password_err = "La contraseña debe tener al menos 8 caracteres.";
        } 
        // Verifica si tiene al menos un caracter especial (no alfanumérico)
        elseif (!preg_match('/[^a-zA-Z0-9]/', $password_candidata)) {
            $password_err = "La contraseña debe contener al menos un caracter especial (ej. #, $, %, &).";
        }
        // --- FIN: NUEVA VALIDACIÓN REFORZADA ---
        else {
            $password = $password_candidata;
        }
    }

    if (empty(trim($_POST["confirm_password"]))) {
        $password_err = $password_err ?: "Confirma la contraseña.";
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($password_err) && ($password != $confirm_password)) {
            $password_err = "Las contraseñas no coinciden.";
        }
    }

    if (empty($password_err)) {
        // Actualizar la contraseña y limpiar el token
        $sql_update = "UPDATE usuario SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id_usuario = ?";
        if ($stmt_update = $conn->prepare($sql_update)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt_update->bind_param("si", $hashed_password, $id_usuario);
            if ($stmt_update->execute()) {
                $mensaje_exito = "¡Tu contraseña ha sido restablecida con éxito!";
            } else {
                $password_err = "Algo salió mal. Inténtalo de nuevo.";
            }
            $stmt_update->close();
        }
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Restablecer Contraseña - Bitware</title>
    <link rel="stylesheet" href="css/login.css">
    <link rel="icon" href="images/favicon.ico" type="image/ico">
</head>
<body>
     <div class="left-container">
         <img src="Recursos/Recursos_login/imagen-principal.png" class="main-image" alt="">
    </div>
    <div class="login-container">
        <img src="Recursos\Recursos_login\img-contenedor.png" alt="Logo Bitware">
        <h2>Nueva Contraseña</h2>

        <?php if (!empty($token_err)): ?>
            <div class="alert alert-danger" style="color:#b30000; background:#ffe6e6; padding:10px; border-radius:6px; margin-bottom:15px;">
                <?php echo htmlspecialchars($token_err); ?>
            </div>
            <p><a href="forgot_password.php">Solicitar nuevo enlace</a></p>
        <?php elseif (!empty($mensaje_exito)): ?>
             <div style="color: green; background: #e6ffe6; padding: 10px; border-radius: 6px; margin-bottom: 15px;">
                <?php echo htmlspecialchars($mensaje_exito); ?>
            </div>
            <p><a href="login.php">Ir a Iniciar Sesión</a></p>
        <?php elseif ($token_valido): ?>
            <p class="subtitle">Ingresa tu nueva contraseña a continuación. Debe tener mínimo 8 caracteres y 1 especial.</p>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?token=' . urlencode($token); ?>" method="post">
                <input type="password" name="password" placeholder="Nueva Contraseña" required>
                <span class="invalid-feedback" style="color: red;"><?php echo $password_err; ?></span>
                
                <input type="password" name="confirm_password" placeholder="Confirmar Nueva Contraseña" required>
                
                <button type="submit">Restablecer Contraseña</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>