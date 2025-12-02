<?php
session_start();
// --- NUEVO: Incluir el actualizador de actividad ---
require_once "check_activity.php";
// --- FIN NUEVO ---
// 1. VERIFICACIÓN DE SEGURIDAD
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

require '/var/www/config/config.php';

$id_usuario_actual = $_SESSION["id"];
$nombre = $email = $telefono = $direccion = $region = "";
// AÑADIDO: $delete_password_err para la validación de eliminación
$nombre_err = $email_err = $password_err = $foto_err = $delete_password_err = ""; 
$mensaje_exito = "";

// 2. PROCESAR EL FORMULARIO AL ENVIARSE (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- D. ELIMINAR CUENTA (NUEVO BLOQUE: Requiere Contraseña) ---
    if (isset($_POST['delete_account_submit'])) {
        
        $delete_password = $_POST['delete_password'] ?? '';
        
        // 1. Obtener la contraseña hasheada del usuario
        $sql_check_pass = "SELECT password FROM usuario WHERE id_usuario = ?";
        $hashed_password = null;

        if ($stmt = $conn->prepare($sql_check_pass)) {
            $stmt->bind_param("i", $id_usuario_actual);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows == 1) {
                $stmt->bind_result($hashed_password);
                $stmt->fetch();
            }
            $stmt->close();
        }

        // 2. Verificar la contraseña ingresada
        if (!empty($hashed_password) && password_verify($delete_password, $hashed_password)) {
            
            // Contraseña correcta, proceder a la eliminación
            $sql_delete = "DELETE FROM usuario WHERE id_usuario = ?";
            if ($stmt = $conn->prepare($sql_delete)) {
                $stmt->bind_param("i", $id_usuario_actual);
                
                if ($stmt->execute()) {
                    // ÉXITO: Destruir la sesión y redirigir
                    $_SESSION = array();
                    session_destroy();
                    header("location: login.php?message=account_deleted");
                    exit;
                } else {
                    $mensaje_exito = "<div class='alert-error'>Hubo un error al intentar eliminar la cuenta en la base de datos.</div>";
                }
                $stmt->close();
            }
            
        } else {
            // ERROR: Contraseña incorrecta
            $delete_password_err = "La contraseña ingresada para eliminar la cuenta es incorrecta.";
            // Limpiar la variable de éxito para evitar mensajes confusos
            $mensaje_exito = ""; 
        }
    }
    // --- FIN NUEVO BLOQUE DE ELIMINACIÓN ---


    // --- A. ACTUALIZAR INFORMACIÓN PERSONAL (Sin cambios) ---
    // NOTA: Esta sección solo se ejecuta si NO se activó la eliminación, o si la eliminación falló
    // La lógica de eliminación tiene un 'exit' si tiene éxito.
    $nombre = trim($_POST["nombre"] ?? '');
    if (empty($nombre)) {
        $nombre_err = "El nombre es obligatorio.";
    }

    $email = trim($_POST["email"] ?? '');
    if (empty($email)) {
        $email_err = "El email es obligatorio.";
    }
    
    $telefono = trim($_POST["telefono"] ?? '');
    $direccion = trim($_POST["direccion"] ?? '');
    $region = trim($_POST["region"] ?? '');

    // Solo ejecuta la actualización si no hay errores en los campos principales y no se intentó eliminar
    if (empty($nombre_err) && empty($email_err) && !isset($_POST['delete_account_submit'])) {
        $sql_update_info = "UPDATE usuario SET nombre = ?, email = ?, telefono = ?, direccion = ?, region = ? WHERE id_usuario = ?";
        if ($stmt = $conn->prepare($sql_update_info)) {
            $stmt->bind_param("sssssi", $nombre, $email, $telefono, $direccion, $region, $id_usuario_actual);
            if ($stmt->execute()) {
                $_SESSION["nombre"] = $nombre; // Actualizar el nombre en la sesión
                $mensaje_exito = "¡Tu información personal ha sido actualizada!";
            }
            $stmt->close();
        }
    }

    // --- B. CAMBIAR CONTRASEÑA (LÓGICA MODIFICADA) ---
    $current_password = $_POST["current_password"] ?? '';
    $new_password = $_POST["new_password"] ?? '';
    $confirm_password = $_POST["confirm_password"] ?? '';

    if (!empty($current_password) && !empty($new_password)) {
        
        // --- INICIO: VALIDACIÓN REFORZADA ---
        if (strlen($new_password) < 8) {
            $password_err = "La nueva contraseña debe tener al menos 8 caracteres.";
        } 
        elseif (!preg_match('/[^a-zA-Z0-9]/', $new_password)) {
            $password_err = "La nueva contraseña debe contener al menos un caracter especial (ej. #, $, %, &).";
        }
        // --- FIN: VALIDACIÓN REFORZADA ---
        
        elseif ($new_password != $confirm_password) {
            $password_err = "La nueva contraseña y la confirmación no coinciden.";
        } else {
            $sql_check_pass = "SELECT password FROM usuario WHERE id_usuario = ?";
            if ($stmt = $conn->prepare($sql_check_pass)) {
                $stmt->bind_param("i", $id_usuario_actual);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows == 1) {
                    $stmt->bind_result($hashed_password_check);
                    $stmt->fetch();
                    
                    if (password_verify($current_password, $hashed_password_check)) {
                        $sql_update_pass = "UPDATE usuario SET password = ? WHERE id_usuario = ?";
                        if ($stmt_update = $conn->prepare($sql_update_pass)) {
                            $param_new_password = password_hash($new_password, PASSWORD_DEFAULT);
                            $stmt_update->bind_param("si", $param_new_password, $id_usuario_actual);
                            if ($stmt_update->execute()) {
                                $mensaje_exito .= " ¡Tu contraseña ha sido cambiada exitosamente!";
                            }
                            $stmt_update->close();
                        }
                    } else {
                        $password_err = "La contraseña actual que ingresaste es incorrecta.";
                    }
                }
                $stmt->close();
            }
        }
    }
    
    // --- C. MANEJAR LA SUBIDA DE LA FOTO DE PERFIL (Sin cambios) ---
    if (isset($_FILES["foto"]) && $_FILES["foto"]["error"] == 0) {
        $directorio_subidas = "uploads/";
        $nombre_archivo = uniqid() . '_' . basename($_FILES["foto"]["name"]);
        $archivo_destino = $directorio_subidas . $nombre_archivo;
        $tipo_archivo = strtolower(pathinfo($archivo_destino, PATHINFO_EXTENSION));

        $check = getimagesize($_FILES["foto"]["tmp_name"]);
        if ($check === false) { $foto_err = "El archivo no es una imagen válida."; }
        elseif ($_FILES["foto"]["size"] > 2000000) { $foto_err = "La imagen es demasiado grande (Límite: 2MB)."; }
        elseif (!in_array($tipo_archivo, ["jpg", "jpeg", "png", "gif"])) { $foto_err = "Solo se permiten archivos JPG, JPEG, PNG y GIF."; }

        if (empty($foto_err)) {
            if (move_uploaded_file($_FILES["foto"]["tmp_name"], $archivo_destino)) {
                $sql_update_foto = "UPDATE usuario SET foto_perfil = ? WHERE id_usuario = ?";
                if ($stmt = $conn->prepare($sql_update_foto)) {
                    $stmt->bind_param("si", $nombre_archivo, $id_usuario_actual);
                    if ($stmt->execute()) {
                        $_SESSION["foto_perfil"] = $nombre_archivo;
                        $mensaje_exito .= " ¡Tu foto de perfil ha sido cambiada!";
                    }
                    $stmt->close();
                }
            } else { $foto_err = "Hubo un error al subir tu foto."; }
        }
    }
}

// 3. OBTENER DATOS ACTUALES DEL USUARIO PARA MOSTRAR EN EL FORMULARIO
$sql_select = "SELECT nombre, email, telefono, direccion, region, foto_perfil FROM usuario WHERE id_usuario = ?";
if ($stmt = $conn->prepare($sql_select)) {
    $stmt->bind_param("i", $id_usuario_actual);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows == 1) {
            $row = $result->fetch_assoc();
            // Actualizar variables en caso de que la actualización POST no las haya cambiado (ej. si hubo errores)
            $nombre = $row['nombre'];
            $email = $row['email'];
            $telefono = $row['telefono'];
            $direccion = $row['direccion'];
            $region = $row['region'];
            $foto_actual = $row['foto_perfil'];
        }
    }
    $stmt->close();
}
$conn->close();

// Determinar la ruta de la foto de perfil
$foto_perfil_url = 'uploads/default.png'; // Imagen por defecto
if (!empty($foto_actual) && file_exists('uploads/' . $foto_actual)) {
    $foto_perfil_url = 'uploads/' . $foto_actual;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Perfil - Bitware</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css\EPerfil.css">
    <link rel="icon" href="images\favicon.ico" type="image/ico"> </head>
<body>
    <div class="main-container">
        <aside class="sidebar">
            <div class="profile-picture-wrapper">
                <img src="<?php echo htmlspecialchars($foto_perfil_url); ?>" alt="Foto de Perfil" class="profile-picture">
            </div>
            <h2><?php echo htmlspecialchars($_SESSION["nombre"]); ?></h2>
            <p><?php echo htmlspecialchars($email); ?></p>
            <a href="dashboard.php" class="back-link">&larr; Volver a mi panel</a>
        </aside>

        <main class="main-content">
            <h1>Editar Perfil</h1>
            <p>Aquí puedes actualizar tu información personal y de seguridad.</p>

            <?php if (!empty($mensaje_exito)): ?>
                <div class="alert-success"><?php echo $mensaje_exito; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($delete_password_err)): ?>
                <div class="alert-error"><?php echo $delete_password_err; ?></div>
            <?php endif; ?>


            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
                <div class="card">
                    <h3>Información de Contacto</h3>
                    <div class="form-group">
                        <label for="nombre">Nombre Completo</label>
                        <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($nombre); ?>" required>
                        <span class="text-danger"><?php echo $nombre_err; ?></span>
                    </div>
                    <div class="form-group">
                        <label for="email">Correo Electrónico</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                        <span class="text-danger"><?php echo $email_err; ?></span>
                    </div>
                    <div class="form-group">
                        <label for="telefono">Teléfono (Opcional)</label>
                        <input type="text" id="telefono" name="telefono" value="<?php echo htmlspecialchars($telefono ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="direccion">Dirección (Opcional)</label>
                        <input type="text" id="direccion" name="direccion" value="<?php echo htmlspecialchars($direccion ?? ''); ?>">
                    </div>
                     <div class="form-group">
                        <label for="region">Región (Opcional)</label>
                        <input type="text" id="region" name="region" value="<?php echo htmlspecialchars($region ?? ''); ?>">
                    </div>
                </div>

                <div class="card">
                    <h3>Foto de Perfil</h3>
                     <div class="form-group">
                        <label for="foto">Cambiar foto (Opcional, Límite 2MB)</label>
                        <input type="file" id="foto" name="foto">
                        <span class="text-danger"><?php echo $foto_err; ?></span>
                    </div>
                </div>

                <div class="card">
                    <h3>Cambiar Contraseña</h3>
                    <p style="font-size: 0.9em; color: #6c757d;">
                        Deja los siguientes campos en blanco si no deseas cambiar tu contraseña. 
                        La nueva contraseña debe tener **mínimo 8 caracteres y al menos un carácter especial**.
                    </p>
                    <div class="form-group">
                        <label for="current_password">Contraseña Actual</label>
                        <input type="password" id="current_password" name="current_password">
                    </div>
                    <div class="form-group">
                        <label for="new_password">Nueva Contraseña</label>
                        <input type="password" id="new_password" name="new_password">
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirmar Nueva Contraseña</label>
                        <input type="password" id="confirm_password" name="confirm_password">
                        <span class="text-danger"><?php echo $password_err; ?></span>
                    </div>
                </div>
                
                <div class="card delete-card">
                    <h3>Zona de Peligro</h3>
                    <p style="font-size: 0.9em; color: #dc3545;">
                        Esta acción es irreversible. Para confirmar la eliminación de tu cuenta, ingresa tu contraseña actual y haz clic en el botón.
                    </p>
                    
                    <div class="form-group">
                        <label for="delete_password">Ingresa tu Contraseña Actual</label>
                        <input type="password" id="delete_password" name="delete_password">
                        <span class="text-danger"><?php echo $delete_password_err; ?></span> 
                    </div>

                    <button type="submit" name="delete_account_submit" value="true" class="btn btn-danger" onclick="return confirmDeletionPrompt()">
                        Eliminar Cuenta Permanentemente
                    </button>
                </div>
                <div class="form-group" style="margin-top: 30px;">
                    <button type="submit" class="btn">Guardar Cambios</button>
                </div>
            </form>
        </main>
    </div>
    
    <script>
        // Esta función simplemente pide una confirmación final en el navegador.
        // La verificación de seguridad real (la contraseña) la hace el PHP.
        function confirmDeletionPrompt() {
            return confirm("ADVERTENCIA: ¿Estás absolutamente seguro de que deseas eliminar tu cuenta? Esta acción no se puede deshacer.");
        }
    </script>
    <script>
        document.querySelector("button[name='delete_account_submit']").addEventListener("click", function(e) {
            const field = document.getElementById("delete_password");
            if (field.value.trim() === "") {
                e.preventDefault();
                alert("Debes ingresar tu contraseña para eliminar tu cuenta.");
                field.focus();
                return false;
            }
        });
    </script>
</body>
</html>