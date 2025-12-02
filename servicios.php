<?php
session_start();
require '/var/www/config/config.php';

// 1. SEGURIDAD: Redirigir si el usuario no ha iniciado sesión.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php?redirect=servicios.php");
    exit;
}

$mensaje_exito = "";
$mensaje_error = "";

// 2. PROCESAR EL FORMULARIO CUANDO SE ENVÍA
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recoger datos del formulario
    $tipo_servicio = $_POST['tipo_servicio'];
    $descripcion = trim($_POST['descripcion_solicitud']);
    $presupuesto = $_POST['presupuesto_estimado'];
    
    // Bandera para controlar si los datos son válidos
    $es_valido = true;

    // --- NUEVA VALIDACIÓN ---
    // Si el presupuesto no está vacío y es menor que 0, es un error.
    if (!empty($presupuesto) && (!is_numeric($presupuesto) || $presupuesto < 0)) {
        $mensaje_error = "El presupuesto no puede ser un número negativo.";
        $es_valido = false;
    }
    
    // Validar que los campos obligatorios no estén vacíos
    if (empty($tipo_servicio) || empty($descripcion)) {
        $mensaje_error = "Por favor, completa todos los campos obligatorios.";
        $es_valido = false;
    }
    
    // Si todas las validaciones pasaron, proceder a insertar en la BD
    if ($es_valido) {
        $id_usuario = $_SESSION['id'];
        $nombre_cliente = $_SESSION['nombre'];
        $email_cliente = "";

        // Obtener email actualizado del usuario
        $sql_email = "SELECT email FROM usuario WHERE id_usuario = ?";
        if ($stmt_email = $conn->prepare($sql_email)) {
            $stmt_email->bind_param("i", $id_usuario);
            $stmt_email->execute();
            $result_email = $stmt_email->get_result();
            $user_data = $result_email->fetch_assoc();
            $email_cliente = $user_data['email'];
            $stmt_email->close();
        }

        // INSERTAR EN LA BASE DE DATOS
        $sql = "INSERT INTO solicitudes_servicio (id_usuario, nombre_cliente, email_cliente, tipo_servicio, descripcion_solicitud, presupuesto_estimado) VALUES (?, ?, ?, ?, ?, ?)";
        
        if ($stmt = $conn->prepare($sql)) {
            // Si el presupuesto está vacío, lo guardamos como NULL
            $presupuesto_final = !empty($presupuesto) ? $presupuesto : null;
            $stmt->bind_param("issssd", $id_usuario, $nombre_cliente, $email_cliente, $tipo_servicio, $descripcion, $presupuesto_final);
            
            if ($stmt->execute()) {
                $mensaje_exito = "¡Tu solicitud de servicio ha sido enviada! Nos pondremos en contacto contigo pronto.";
            } else {
                $mensaje_error = "Hubo un error al procesar tu solicitud.";
            }
            $stmt->close();
        }
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nuestros Servicios - Bitware</title>
    <link rel="stylesheet" href="css/servicio.css">
    <link rel="icon" href="images/favicon.ico" type="image/ico">
</head>
<body>
    <div class="container">
        <h1>Nuestros Servicios</h1>
        <p>En Bitware, ofrecemos más que solo componentes. Brindamos soluciones completas para entusiastas y profesionales.</p>
        
        <h2>Solicita un Servicio Personalizado</h2>
        <p>¿Necesitas un PC ensamblado a medida o soporte técnico? Completa el formulario y nuestro equipo de expertos se pondrá en contacto contigo.</p>
        
        <?php if(!empty($mensaje_exito)): ?>
            <div class="alert-success"><?php echo $mensaje_exito; ?></div>
        <?php endif; ?>
        
        <form action="servicios.php" method="post">
            <div class="form-group">
                <label for="tipo_servicio">Tipo de Servicio</label>
                <select id="tipo_servicio" name="tipo_servicio" required>
                    <option value="">-- Selecciona un servicio --</option>
                    <option value="ensamblaje_pc">Ensamblaje de PC Personalizado</option>
                    <option value="soporte_tecnico">Soporte Técnico y Diagnóstico</option>
                    <option value="actualizacion_componentes">Actualización de Componentes</option>
                    <option value="otro">Otro</option>
                </select>
            </div>
            <div class="form-group">
                <label for="descripcion_solicitud">Describe lo que necesitas (Componentes, uso del PC, etc.)</label>
                <textarea id="descripcion_solicitud" name="descripcion_solicitud" rows="6" required></textarea>
            </div>
            <div class="form-group">
                <label for="presupuesto_estimado">Tu presupuesto estimado (CLP)</label>
                <input type="number" id="presupuesto_estimado" name="presupuesto_estimado" placeholder="Ej: 800000" min="0">    
            </div>
            <button type="submit">Enviar Solicitud</button>
        </form>
            <br>
            <a href="index.php"><button>Volver Al Inicio</button></a>
    </div>
</body>
</html>
<footer class="footer">
<?php
require 'includes/footer.php';
?>