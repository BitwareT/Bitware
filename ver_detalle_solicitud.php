<?php
session_start();
require '/var/www/config/config.php';

// 1. SEGURIDAD: VERIFICAR QUE SEA UN ADMINISTRADOR
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["permisos"] !== 'A') {
    header("location: login.php");
    exit;
}

// 2. VALIDAR EL ID QUE VIENE POR LA URL
if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("location: gestionar_solicitudes.php");
    exit;
}
$id_solicitud = $_GET["id"];

// 3. CONSULTAR LA BASE DE DATOS PARA OBTENER TODOS LOS DETALLES
$sql = "SELECT * FROM solicitudes_servicio WHERE id = ?";
$solicitud = null;

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $id_solicitud);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows == 1) {
            $solicitud = $result->fetch_assoc();
        } else {
            // Si no se encuentra la solicitud, redirigir
            header("location: gestionar_solicitudes.php");
            exit;
        }
    }
    $stmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle de Solicitud #<?php echo htmlspecialchars($id_solicitud); ?></title>
    <link rel="stylesheet" href="css/DSolicitudes.css">
    <link rel="icon" href="images\favicon.ico" type="image/ico"> <!--Copiar y pegar en todas las paginas-->
</head>
<body>
    <div class="container">
        <a href="gestionar_solicitudes.php">&larr; Volver a la lista de solicitudes</a>
        <h1>Detalle de Solicitud #<?php echo htmlspecialchars($solicitud['id']); ?></h1>

        <div class="details-section">
            <h2>Datos del Cliente</h2>
            <p><strong>Nombre:</strong> <?php echo htmlspecialchars($solicitud['nombre_cliente']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($solicitud['email_cliente']); ?></p>
        </div>

        <div class="details-section">
            <h2>Detalles de la Solicitud</h2>
            <p><strong>Fecha de Solicitud:</strong> <?php echo date("d/m/Y H:i", strtotime($solicitud['fecha_solicitud'])); ?></p>
            <p><strong>Tipo de Servicio:</strong> <?php echo htmlspecialchars($solicitud['tipo_servicio']); ?></p>
            <p><strong>Presupuesto Estimado:</strong> $<?php echo number_format($solicitud['presupuesto_estimado'], 0, ',', '.'); ?></p>
            <p><strong>Estado Actual:</strong> <?php echo htmlspecialchars($solicitud['estado']); ?></p>
        </div>

        <div class="details-section">
            <h2>Descripción Completa</h2>
            <div class="descripcion-box">
                <?php echo nl2br(htmlspecialchars($solicitud['descripcion_solicitud'])); ?>
            </div>
        </div>
    </div>
</body>
</html>
<footer class="footer">
<?php
require 'includes/footer.php';
?>