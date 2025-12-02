<?php
session_start();
require '/var/www/config/config.php';
// --- NUEVO: Incluir el actualizador de actividad ---
require_once "check_activity.php";
// --- FIN NUEVO ---
// 1. SEGURIDAD: Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["id"])) {
    header("location: login.php");
    exit;
}
$id_usuario_actual = $_SESSION["id"];

// 2. Obtener todos los TICKETS del usuario
$tickets = [];
$sql_tickets = "SELECT id_ticket, asunto, estado, ultima_actualizacion
                FROM soporte_tickets
                WHERE id_usuario = ?
                ORDER BY ultima_actualizacion DESC";
if ($stmt = $conn->prepare($sql_tickets)) {
    $stmt->bind_param("i", $id_usuario_actual);
    $stmt->execute();
    $result = $stmt->get_result();
    $tickets = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis Tickets de Soporte - Bitware</title>
    <link rel="stylesheet" href="css/mis_pedidos.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="icon" href="images/favicon.ico" type="image/ico">
    <link rel="stylesheet" href="css/responsive.css">
    <style>
        /* Estilos de Estado (copiados de gestionar_tickets) */
        .status-badge { padding: 3px 8px; border-radius: 12px; font-size: 0.9em; font-weight: bold; color: white; }
        .status-abierto { background-color: #dc3545; } /* Rojo */
        .status-respondido-por-cliente { background-color: #ffc107; color: #333; } /* Amarillo */
        .status-respondido-por-admin { background-color: #28a745; } /* Verde */
        .status-cerrado { background-color: #6c757d; } /* Gris */

        /* CSS para la tabla (copiado de mis_solicitudes) */
        table { width: 100%; border-collapse: collapse; margin-top: 20px; } /* Añadido margen superior */
        thead tr { background-color: #f4f4f4; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        tbody tr:nth-child(even) { background-color: #f9f9f9; }
        tbody tr:hover { background-color: #f1f1f1; }
        .btn-detalle-ticket {
            display: inline-block; padding: 5px 10px; background-color: #007bff; color: white;
            text-decoration: none; border-radius: 4px; font-size: 0.9em;
        }
        .btn-detalle-ticket:hover { background-color: #0056b3; }
        /* Estilos del header y container (asegúrate que coincidan con mis_pedidos.css si es necesario) */
        .main-header { /* ... estilos ... */ }
        .header-content { /* ... estilos ... */ }
        .logo { /* ... estilos ... */ }
        .account-link { /* ... estilos ... */ }
        .container { max-width: 960px; margin: 20px auto; padding: 20px; background-color: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .orders-header { border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px; }
        .orders-header h1 { margin-top: 0; }
        .empty-state { text-align: center; padding: 40px; }
        .btn-primary { /* ... estilos ... */ }
    </style>
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
            <h1>Mis Tickets de Soporte</h1>
            <p>Aquí puedes ver el historial de tus consultas y nuestras respuestas.</p>
            <a href="contacto.php" class="btn-primary" style="margin-top: 10px; display: inline-block; text-decoration: none; padding: 8px 15px; background-color:#007bff; color:white; border-radius:4px;">Crear Nuevo Ticket</a>
        </div>

        <?php if (empty($tickets)): ?>
            <div class="order-card empty-state" style="border: 1px solid #ddd; padding: 20px; border-radius: 8px;">
                <h2>No has enviado ningún ticket.</h2>
                <a href="contacto.php" class="btn-primary" style="margin-top: 10px; display: inline-block; text-decoration: none; padding: 8px 15px; background-color:#007bff; color:white; border-radius:4px;">Crear tu primer ticket</a>
            </div>
        <?php else: ?>
            <div class="orders-list">
                <table>
                    <thead>
                        <tr>
                            <th>ID Ticket</th>
                            <th>Asunto</th>
                            <th>Estado</th>
                            <th>Última Actualización</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets as $ticket): ?>
                            <?php
                            // Mapear estado a clase CSS
                            $estado_clase = 'status-' . strtolower(str_replace(' ', '-', $ticket['estado']));
                            ?>
                            <tr>
                                <td>#<?php echo $ticket['id_ticket']; ?></td>
                                <td><?php echo htmlspecialchars($ticket['asunto']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $estado_clase; ?>">
                                        <?php echo htmlspecialchars($ticket['estado']); ?>
                                    </span>
                                </td>
                                <td><?php echo date("d/m/Y H:i", strtotime($ticket['ultima_actualizacion'])); ?></td>
                                <td>
                                    <a href="ver_mi_ticket.php?id=<?php echo $ticket['id_ticket']; ?>" class="btn-detalle-ticket">
                                        <?php echo ($ticket['estado'] == 'Respondido por Admin') ? 'Ver Respuesta' : 'Ver / Responder'; ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
