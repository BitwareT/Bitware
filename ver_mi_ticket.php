<?php
session_start();
require '/var/www/config/config.php';
// --- NUEVO: Incluir el actualizador de actividad ---
require_once "check_activity.php";
// --- FIN NUEVO ---
// --- INICIO: LOGICA DE RESPUESTA AJAX (POST) ---
// Esto se ejecuta ANTES de cualquier HTML
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['mensaje_respuesta'])) {
    
    header('Content-Type: application/json'); // Enviar respuesta como JSON
    
    // 1. Seguridad basica
    $es_admin_post = false; // Vista de cliente
    $id_remitente = $_SESSION["id"] ?? 0;
    $permiso_requerido = 'C'; 

    if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["permisos"] !== $permiso_requerido) {
        http_response_code(403); // Prohibido
        echo json_encode(['error' => 'No autorizado']);
        exit;
    }

    $id_ticket = $_GET['id'] ?? 0; // Obtener ID del ticket de la URL de la action
    $mensaje_nuevo = trim($_POST['mensaje_respuesta']);

    if (empty($mensaje_nuevo) && (empty($_FILES['adjuntos']['tmp_name'][0]) ?? true)) {
        http_response_code(400); // Solicitud incorrecta
        echo json_encode(['error' => 'El mensaje o el adjunto es obligatorio.']);
        exit;
    }
    if (empty($id_ticket)) {
        http_response_code(400); // Solicitud incorrecta
        echo json_encode(['error' => 'ID de ticket vacio']);
        exit;
    }

    // 2. LOGICA DE SUBIDA DE ARCHIVOS
    $archivos_subidos_db = []; // Para guardar en la BD
    $upload_dir = '/var/www/html/uploads/tickets/'; 
    if (!is_dir($upload_dir)) { mkdir($upload_dir, 0775, true); }

    if (isset($_FILES['adjuntos']) && !empty($_FILES['adjuntos']['name'][0])) {
        // Definir limites y tipos permitidos
        $max_size = 5 * 1024 * 1024; // 5 MB
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        foreach ($_FILES['adjuntos']['tmp_name'] as $key => $tmp_name) {
            
            if ($_FILES['adjuntos']['error'][$key] !== UPLOAD_ERR_OK) continue;

            $file_name = $_FILES['adjuntos']['name'][$key];
            $file_size = $_FILES['adjuntos']['size'][$key];
            $file_type = $_FILES['adjuntos']['type'][$key];

            // Validaciones
            if ($file_size > $max_size) {
                $response['error'] = "El archivo '{$file_name}' es demasiado grande (Max 5MB).";
                echo json_encode($response);
                exit;
            }
            if (!in_array($file_type, $allowed_types)) {
                $response['error'] = "El archivo '{$file_name}' tiene un formato no permitido.";
                echo json_encode($response);
                exit;
            }

            // Crear nombre de archivo unico y seguro
            $extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $safe_name = uniqid('chat-') . '.' . $extension;
            $upload_file_path = $upload_dir . $safe_name;

            if (move_uploaded_file($tmp_name, $upload_file_path)) {
                $archivos_subidos_db[] = [
                    'nombre_original' => $file_name,
                    'ruta_relativa' => 'uploads/tickets/' . $safe_name // Ruta para la BD
                ];
            } else {
                $response['error'] = 'Error al mover uno de los archivos adjuntos al servidor.';
                echo json_encode($response);
                exit;
            }
        }
    }


    // 3. Logica de guardado en BD
    $conn->begin_transaction();
    try {
        // (Verificar que el ticket pertenece al usuario)
        $sql_check = "SELECT id_usuario FROM soporte_tickets WHERE id_ticket = ? AND id_usuario = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("ii", $id_ticket, $id_remitente);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows == 0) {
             throw new Exception("Permiso denegado para este ticket.");
        }
        $stmt_check->close();

        // Paso 1: Insertar el nuevo mensaje
        $es_admin_msg = 0; 
        $sql_insert = "INSERT INTO soporte_mensajes (id_ticket, id_remitente, es_admin, mensaje) VALUES (?, ?, ?, ?)";
        $stmt_insert = $conn->prepare($sql_insert);
        $stmt_insert->bind_param("iiis", $id_ticket, $id_remitente, $es_admin_msg, $mensaje_nuevo);
        $stmt_insert->execute();
        $id_nuevo_mensaje = $conn->insert_id;
        $stmt_insert->close();

        // Paso 2: Guardar los adjuntos en la tabla ticket_adjuntos
        if (!empty($archivos_subidos_db)) {
            $sql_adjunto = "INSERT INTO ticket_adjuntos (id_ticket, nombre_archivo, ruta_archivo, id_mensaje) VALUES (?, ?, ?, ?)";
            $stmt_adjunto = $conn->prepare($sql_adjunto);
            foreach ($archivos_subidos_db as $archivo) {
                // Aqui el id_mensaje apunta al mensaje que adjunto el archivo
                $stmt_adjunto->bind_param("issi", $id_ticket, $archivo['nombre_original'], $archivo['ruta_relativa'], $id_nuevo_mensaje);
                $stmt_adjunto->execute();
            }
            $stmt_adjunto->close();
        }

        // Paso 3: Actualizar el estado del ticket
        $nuevo_estado = "Respondido por Cliente";
        $sql_update = "UPDATE soporte_tickets SET estado = ? WHERE id_ticket = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("si", $nuevo_estado, $id_ticket);
        $stmt_update->execute();
        $stmt_update->close();
        
        $conn->commit();
        
        // 4. Enviar respuesta de EXITO
        echo json_encode([
            'status' => 'success',
            'id_mensaje_nuevo' => $id_nuevo_mensaje,
            'fecha_envio' => date('c') // Enviar fecha actual para mostrar
        ]);
        exit; 

    } catch (Exception $e) {
        $conn->rollback();
        // Borrar archivos si la transaccion de BD falla 
        foreach ($archivos_subidos_db as $archivo) {
             if (file_exists('/var/www/html/' . $archivo['ruta_relativa'])) {
                 unlink('/var/www/html/' . $archivo['ruta_relativa']);
             }
        }
        
        http_response_code(500); // Error de servidor
        echo json_encode(['error' => 'Error al guardar en la base de datos: ' . $e->getMessage()]);
        exit;
    }
}
// --- FIN: LOGICA DE RESPUESTA AJAX (POST) ---


// --- INICIO: LOGICA DE VISTA (GET) ---

// 1. Seguridad (GET)
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["id"])) {
    header("location: login.php");
    exit;
}
$id_usuario = $_SESSION["id"];

// 2. Validar ID (GET)
if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("location: mis_tickets.php");
    exit;
}
$id_ticket = $_GET["id"];

// 3. Obtener info del Ticket (Verificando propietario)
$sql_ticket = "SELECT asunto, estado FROM soporte_tickets WHERE id_ticket = ? AND id_usuario = ?";
$ticket = null;
if ($stmt_ticket = $conn->prepare($sql_ticket)) {
    $stmt_ticket->bind_param("ii", $id_ticket, $id_usuario);
    $stmt_ticket->execute();
    $result_ticket = $stmt_ticket->get_result();
    if ($result_ticket->num_rows == 0) {
        header("location: mis_tickets.php"); 
        exit;
    }
    $ticket = $result_ticket->fetch_assoc();
    $stmt_ticket->close();
} else {
    die("Error al preparar la consulta del ticket.");
}

// 4. Obtener mensajes del chat
$mensajes = [];
$sql_mensajes = "SELECT m.mensaje, m.fecha_envio, m.es_admin, u.nombre AS nombre_remitente
                 FROM soporte_mensajes m
                 JOIN usuario u ON m.id_remitente = u.id_usuario
                 WHERE m.id_ticket = ?
                 ORDER BY m.fecha_envio ASC";
if ($stmt_mensajes = $conn->prepare($sql_mensajes)) {
    $stmt_mensajes->bind_param("i", $id_ticket);
    $stmt_mensajes->execute();
    $result_mensajes = $stmt_mensajes->get_result();
    $mensajes = $result_mensajes->fetch_all(MYSQLI_ASSOC);
    $stmt_mensajes->close();
}

// 5. Obtener Adjuntos del Ticket
$adjuntos = [];
$sql_adjuntos = "SELECT nombre_archivo, ruta_archivo FROM ticket_adjuntos WHERE id_ticket = ?";
if ($stmt_adjuntos = $conn->prepare($sql_adjuntos)) {
    $stmt_adjuntos->bind_param("i", $id_ticket);
    $stmt_adjuntos->execute();
    $result_adjuntos = $stmt_adjuntos->get_result();
    $adjuntos = $result_adjuntos->fetch_all(MYSQLI_ASSOC);
    $stmt_adjuntos->close();
}


$mensaje_exito = (isset($_GET['status']) && $_GET['status'] == 'success') ? "¡Respuesta enviada exitosamente!" : "";
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Viendo Ticket #<?php echo $id_ticket; ?> - Bitware</title>
    <link rel="stylesheet" href="css/GMensajes.css"> 
    <link rel="icon" href="images\favicon.ico" type="image/ico">
    <link rel="stylesheet" href="css/chat_styles.css">
</head>
<body>
    <div class="container">
        <a href="mis_tickets.php">&larr; Volver a mis tickets</a>
        <div class="ticket-header">
            <h2>Ticket #<?php echo $id_ticket; ?>: <?php echo htmlspecialchars($ticket['asunto']); ?></h2>
            <p><strong>Estado:</strong> <?php echo htmlspecialchars($ticket['estado']); ?></p>
        </div>

        <?php if ($mensaje_exito) echo "<div class='alert-success'>$mensaje_exito</div>"; ?>
        
        <?php if (!empty($adjuntos)): ?>
            <div class="adjuntos-container">
                <h3>Archivos Adjuntos (Evidencia):</h3>
                <div class="adjuntos-lista">
                    <?php foreach ($adjuntos as $adjunto): ?>
                        <?php
                            $ruta_web = htmlspecialchars($adjunto['ruta_archivo']);
                            $nombre = htmlspecialchars($adjunto['nombre_archivo']);
                        ?>
                        <a href="<?php echo $ruta_web; ?>" target="_blank" class="adjunto-item">
                            <i class="fas fa-paperclip"></i> 
                            <?php echo $nombre; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>


        <div class="chat-container" id="chat-box">
            <?php foreach ($mensajes as $msg): ?>
                <div class="chat-message <?php echo $msg['es_admin'] ? 'msg-admin' : 'msg-cliente'; ?>">
                    <small>
                        <strong><?php echo htmlspecialchars($msg['nombre_remitente']); ?></strong>
                        (<?php echo $msg['es_admin'] ? 'Soporte Bitware' : 'Tu'; ?>) - 
                        <?php echo date("d/m/Y H:i", strtotime($msg['fecha_envio'])); ?>
                    </small>
                    <?php echo nl2br(htmlspecialchars($msg['mensaje'])); ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($ticket['estado'] != 'Cerrado'): ?>
            <form class="reply-form" id="reply-form" action="<?php echo basename($_SERVER['PHP_SELF']); ?>?id=<?php echo $id_ticket; ?>" method="post" enctype="multipart/form-data">
                <h3>Responder</h3>
                
                <div class="form-group-adjunto">
                    <input type="file" name="adjuntos[]" id="adjuntos-input" multiple accept="image/*" style="width: 100%;">
                    <small>Adjuntar imagenes (opcional, max. 5MB por archivo).</small>
                    <div id="adjuntos-preview" class="adjuntos-preview"></div>
                </div>
                
                <textarea name="mensaje_respuesta" id="reply-textarea" placeholder="Escribe tu respuesta aqui..." required></textarea>
                <button type="submit" id="reply-button">Enviar Respuesta</button>
                <div id="chat-error-msg"></div> 
            </form>
        <?php else: ?>
            <h3 style="color: #6c757d; text-align: center;">Este ticket ha sido cerrado.</h3>
        <?php endif; ?>
    </div>
    
    <script>
        var idTicketParaChat = <?php echo json_encode($id_ticket); ?>;
        var totalMensajesParaChat = <?php echo count($mensajes); ?>;
        var esVistaAdminParaChat = false; // Estamos en la vista de Cliente
        var nombreUsuarioActual = <?php echo json_encode($_SESSION['nombre'] ?? 'Tu'); ?>;
    </script>
    <script src="js/chat_poll.js"></script>

</body>
</html>
<footer class="footer">
<?php
require 'includes/footer.php';
?>