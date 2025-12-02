<?php
session_start();
require '/var/www/config/config.php';

// --- 1. DEFINES LAS VARIABLES PARA EL HEAD ---
// (Estas líneas son las que faltaban)
$titulo_pagina = ($vista_actual == 'detalle') ? htmlspecialchars($producto['nombre']) : 'Gestión de Tickets';
$css_pagina_especifica = "css/GTickets.css"; // <-- ¡ESTA ES LA LÍNEA CLAVE QUE FALTA!
$body_atributos = 'data-stock="' . htmlspecialchars($producto['stock'] ?? '99') . '"';
require 'includes/head.php';


// 1. SEGURIDAD: VERIFICAR QUE SEA UN ADMINISTRADOR
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["permisos"] !== 'A') {
    header("location: login.php");
    exit;
}

$mensaje_exito = "";
$mensaje_error = "";

// 2. LÓGICA PARA BORRAR UN TICKET (Y SUS MENSAJES, GRACIAS A 'ON DELETE CASCADE')
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id_ticket_a_borrar = $_GET['delete'];

    // La restricción FOREIGN KEY con ON DELETE CASCADE en la BD se encargará de borrar los mensajes asociados
    $sql_delete = "DELETE FROM soporte_tickets WHERE id_ticket = ?";
    if ($stmt_delete = $conn->prepare($sql_delete)) {
        $stmt_delete->bind_param("i", $id_ticket_a_borrar);
        if ($stmt_delete->execute()) {
            $_SESSION['mensaje_exito'] = "Ticket #" . $id_ticket_a_borrar . " y todos sus mensajes han sido eliminados.";
        } else {
            $_SESSION['mensaje_error'] = "Error al eliminar el ticket.";
        }
        $stmt_delete->close();
    } else {
        $_SESSION['mensaje_error'] = "Error al preparar la consulta de eliminación.";
    }
    header("Location: gestionar_tickets.php");
    exit;
}

// Recuperar mensajes de sesión
if(isset($_SESSION['mensaje_exito'])){
    $mensaje_exito = $_SESSION['mensaje_exito'];
    unset($_SESSION['mensaje_exito']);
}
if(isset($_SESSION['mensaje_error'])){
    $mensaje_error = $_SESSION['mensaje_error'];
    unset($_SESSION['mensaje_error']);
}

// 3. OBTENER TODOS LOS TICKETS (UNIENDO CON USUARIO PARA OBTENER NOMBRE)
// Ordenamos por estado y luego por última actualización
$sql_select = "SELECT t.id_ticket, t.asunto, t.estado, t.ultima_actualizacion, u.nombre AS nombre_cliente
               FROM soporte_tickets t
               JOIN usuario u ON t.id_usuario = u.id_usuario
               ORDER BY 
                 CASE t.estado
                   WHEN 'Abierto' THEN 1
                   WHEN 'Respondido por Cliente' THEN 2
                   WHEN 'Respondido por Admin' THEN 3
                   WHEN 'Cerrado' THEN 4
                 END, 
                 t.ultima_actualizacion DESC";
$tickets = $conn->query($sql_select);

?>

<!DOCTYPE html>
<html lang="es">
<body>
    <div class="container">
        <a href="dashboard.php">&larr; Volver al Panel de Administrador</a>
        <h1>Gestionar Tickets de Soporte</h1>

        <?php if ($mensaje_exito) echo "<div class'alert-success'>$mensaje_exito</div>"; ?>
        <?php if ($mensaje_error) echo "<div class='alert-danger'>$mensaje_error</div>"; ?>

        <table>
            <thead>
                <tr>
                    <th>ID Ticket</th>
                    <th>Cliente</th>
                    <th>Asunto</th>
                    <th>Estado</th>
                    <th>Última Actualización</th>
                    <th>Acciones</th> 
                </tr>
            </thead>
            <tbody>
                <?php if ($tickets && $tickets->num_rows > 0): ?>
                    <?php while ($ticket = $tickets->fetch_assoc()): ?>
                        <?php
                            // Mapear estado a clase CSS
                            $estado_clase = 'status-' . strtolower(str_replace(' ', '-', $ticket['estado']));
                        ?>
                        <tr>
                            <td>#<?php echo $ticket['id_ticket']; ?></td>
                            <td><?php echo htmlspecialchars($ticket['nombre_cliente']); ?></td>
                            <td><?php echo htmlspecialchars($ticket['asunto']); ?></td>
                            <td>
                                <span class="status-badge <?php echo $estado_clase; ?>">
                                    <?php echo htmlspecialchars($ticket['estado']); ?>
                                </span>
                            </td>
                            <td><?php echo date("d/m/Y H:i", strtotime($ticket['ultima_actualizacion'])); ?></td>
                            <td>
                                <a href="ver_ticket.php?id=<?php echo $ticket['id_ticket']; ?>" class="btn-detalle">Ver / Responder</a>
                                <a href="gestionar_tickets.php?delete=<?php echo $ticket['id_ticket']; ?>" class="btn-borrar" onclick="return confirm('¿Estás seguro? Esto eliminará el ticket y TODOS sus mensajes.');">Borrar</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">No hay tickets de soporte.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
<footer class="footer">
<?php
require 'includes/footer.php';
?>
<?php $conn->close(); ?>