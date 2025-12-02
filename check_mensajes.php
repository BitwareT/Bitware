<?php
session_start();
require '/var/www/config/config.php';
header('Content-Type: application/json');

// 1. Verificar seguridad (que el usuario esté logueado)
if (!isset($_SESSION["loggedin"]) || !isset($_SESSION["id"])) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}
$id_usuario = $_SESSION["id"];

// 2. Obtener los parámetros del JavaScript
$id_ticket = $_GET['id_ticket'] ?? 0;
$total_mensajes_actual = $_GET['total_mensajes'] ?? 0; 

if (empty($id_ticket) || !is_numeric($id_ticket)) {
    echo json_encode(['error' => 'ID de ticket no válido']);
    exit;
}

// 3. Verificar que el usuario tenga permiso para ver este ticket
$es_admin = ($_SESSION["permisos"] === 'A');
$id_propietario = 0;
$sql_check = "SELECT id_usuario FROM soporte_tickets WHERE id_ticket = ?";
if($stmt_check = $conn->prepare($sql_check)){
    $stmt_check->bind_param("i", $id_ticket);
    $stmt_check->execute();
    $stmt_check->bind_result($id_propietario);
    $stmt_check->fetch();
    $stmt_check->close();
}

if (!$es_admin && $id_usuario != $id_propietario) {
    echo json_encode(['error' => 'Permiso denegado para este ticket']);
    exit;
}

// 4. Buscar mensajes nuevos (contando cuántos hay)
$mensajes_nuevos = [];
$sql_mensajes = "SELECT m.mensaje, m.fecha_envio, m.es_admin, u.nombre AS nombre_remitente
                 FROM soporte_mensajes m
                 JOIN usuario u ON m.id_remitente = u.id_usuario
                 WHERE m.id_ticket = ?
                 ORDER BY m.fecha_envio ASC"; // Siempre ASC para el chat

if ($stmt_mensajes = $conn->prepare($sql_mensajes)) {
    $stmt_mensajes->bind_param("i", $id_ticket);
    $stmt_mensajes->execute();
    $result_mensajes = $stmt_mensajes->get_result();
    $todos_los_mensajes = $result_mensajes->fetch_all(MYSQLI_ASSOC);
    $stmt_mensajes->close();

    // Comparamos el total de mensajes con los que el cliente ya tiene
    if (count($todos_los_mensajes) > $total_mensajes_actual) {
        // Si hay más, enviamos solo los nuevos
        $mensajes_nuevos = array_slice($todos_los_mensajes, $total_mensajes_actual);
    }
}
$conn->close();

// 5. Devolver los mensajes nuevos (o un array vacío)
echo json_encode($mensajes_nuevos);
exit;
?>