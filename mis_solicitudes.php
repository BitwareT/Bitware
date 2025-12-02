<?php
session_start();
require '/var/www/config/config.php';

// 1. SEGURIDAD: Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["id"])) {
    header("location: login.php");
    exit;
}
$id_usuario_actual = $_SESSION["id"];

// 2. Obtener todas las solicitudes de servicio del usuario
$solicitudes = [];
$sql_solicitudes = "SELECT id, tipo_servicio, fecha_solicitud, estado FROM solicitudes_servicio WHERE id_usuario = ? ORDER BY fecha_solicitud DESC";
if ($stmt_sol = $conn->prepare($sql_solicitudes)) {
    $stmt_sol->bind_param("i", $id_usuario_actual);
    $stmt_sol->execute();
    $result_sol = $stmt_sol->get_result();
    $solicitudes = $result_sol->fetch_all(MYSQLI_ASSOC);
    $stmt_sol->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis Solicitudes de Servicio - Bitware</title>
    <link rel="stylesheet" href="css/mis_pedidos.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="icon" href="images/favicon.ico" type="image/ico">
</head>
<body>

    <header class="main-header">
        <div class="header-content">
            <a href="index.php" class="logo">Bitware</a>
            <a href="dashboard.php" class="account-link"><i class="fas fa-user-circle"></i> Mi Cuenta</a>
        </div>
    </header>

    <main class="container">
        <div class="orders-header">
            <h1>Mis Solicitudes de Servicio</h1>
            <p>Aquí puedes ver el estado de tus solicitudes.</p>
        </div>

        <?php if (empty($solicitudes)): ?>
            <div class="order-card empty-state">
                <h2>No has realizado ninguna solicitud.</h2>
                <a href="servicios.php" class="btn-primary">Solicitar un Servicio</a>
            </div>
        <?php else: ?>
            <div class="orders-list">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background-color: #f4f4f4;">
                            <th style="padding: 10px; border: 1px solid #ddd;">ID</th>
                            <th style="padding: 10px; border: 1px solid #ddd;">Fecha</th>
                            <th style="padding: 10px; border: 1px solid #ddd;">Tipo de Servicio</th>
                            <th style="padding: 10px; border: 1px solid #ddd;">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($solicitudes as $sol): ?>
                            <tr>
                                <td style="padding: 10px; border: 1px solid #ddd;">#<?php echo $sol['id']; ?></td>
                                <td style="padding: 10px; border: 1px solid #ddd;"><?php echo date("d/m/Y H:i", strtotime($sol['fecha_solicitud'])); ?></td>
                                <td style="padding: 10px; border: 1px solid #ddd;"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $sol['tipo_servicio']))); ?></td>
                                <td style="padding: 10px; border: 1px solid #ddd;"><?php echo htmlspecialchars($sol['estado']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
<footer class="footer">
<?php
require 'includes/footer.php';
?>