<?php
require '/var/www/config/config.php';
session_start(); // Inicia sesión para posibles mensajes

$mensaje = "";
$exito = false;

// 1. Obtener y limpiar el código de la URL
if (isset($_GET['code'])) {
    $codigo = trim($_GET['code']);

    // 2. Buscar el código en la base de datos
    $sql = "SELECT id_usuario, verificado FROM usuario WHERE codigo_verificacion = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $codigo);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $usuario = $result->fetch_assoc();
            
            // 3. Verificar si la cuenta ya está verificada
            if ($usuario['verificado'] == 1) {
                $mensaje = "Esta cuenta ya ha sido verificada anteriormente.";
                $exito = true; // Considerarlo éxito para mostrar enlace a login
            } else {
                // 4. Actualizar el estado a verificado y limpiar el código
                $sql_update = "UPDATE usuario SET verificado = 1, codigo_verificacion = NULL WHERE id_usuario = ?";
                if ($stmt_update = $conn->prepare($sql_update)) {
                    $stmt_update->bind_param("i", $usuario['id_usuario']);
                    if ($stmt_update->execute()) {
                        $mensaje = "¡Tu cuenta ha sido verificada con éxito!";
                        $exito = true;
                    } else {
                        $mensaje = "Error al actualizar tu cuenta. Intenta de nuevo más tarde.";
                    }
                    $stmt_update->close();
                }
            }
        } else {
            $mensaje = "El código de verificación no es válido o ha expirado.";
        }
        $stmt->close();
    } else {
        $mensaje = "Error al conectar con la base de datos.";
    }
} else {
    $mensaje = "No se proporcionó un código de verificación.";
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Verificación de Cuenta - Bitware</title>
    <link rel="stylesheet" href="css/verificar.css">
    <link rel="icon" href="images\favicon.ico" type="image/ico">

</head>
<body>
    <div class="container">
        <?php if ($exito): ?>
            <h1 class="success">✅ Verificación Completada</h1>
            <p><?php echo htmlspecialchars($mensaje); ?></p>
            <p>Ya puedes iniciar sesión con tu cuenta.</p>
            <p><a href="login.php">Ir a Iniciar Sesión</a></p>
        <?php else: ?>
            <h1 class="error">❌ Error de Verificación</h1>
            <p><?php echo htmlspecialchars($mensaje); ?></p>
            <p>Si tienes problemas, contacta con nuestro <a href="soporte.php">soporte</a>.</p>
        <?php endif; ?>
    </div>
</body>
</html>