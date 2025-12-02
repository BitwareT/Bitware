<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';


session_start();
require '/var/www/config/config.php';

// Preparamos la respuesta que enviaremos al JavaScript (AJAX)
header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Error desconocido.'];

// 1. VERIFICACIÓN DE SEGURIDAD
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["id"])) {
    $response['message'] = 'No has iniciado sesión.';
    echo json_encode($response);
    exit;
}
$id_usuario = $_SESSION["id"];
$nombre_usuario_sesion = $_SESSION['nombre'] ?? 'Cliente'; // Asumimos que tienes el nombre
$email_usuario_sesion = $_SESSION['email'] ?? '';      // Asumimos que tienes el email

// 2. OBTENER DATOS DEL FORMULARIO (POST)
$id_pedido = $_POST['id_pedido'] ?? 0;
$motivo = $_POST['motivo'] ?? '';
$mensaje_inicial = $_POST['mensaje'] ?? '';

// 3. VALIDAR DATOS BÁSICOS
if (empty($id_pedido) || empty($motivo) || empty($mensaje_inicial)) {
    $response['message'] = 'Por favor, completa todos los campos (motivo y mensaje).';
    echo json_encode($response);
    exit;
}

// 4. VERIFICAR QUE EL USUARIO ES DUEÑO DE ESE PEDIDO
$sql_check = "SELECT id_usuario FROM pedidos WHERE id_pedido = ? AND id_usuario = ?";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->bind_param("ii", $id_pedido, $id_usuario);
$stmt_check->execute();
$stmt_check->store_result();
if ($stmt_check->num_rows == 0) {
    $response['message'] = 'No tienes permiso para modificar este pedido.';
    echo json_encode($response);
    $stmt_check->close();
    exit;
}
$stmt_check->close();


// 5. LÓGICA DE SUBIDA DE ARCHIVOS
$archivos_subidos_db = []; // Para guardar en la BD
$archivos_movidos_fs = []; // Para borrar si la BD falla
$upload_dir = '/var/www/html/uploads/tickets/'; // La carpeta que creaste

if (isset($_FILES['evidencia'])) {
    // Definir límites y tipos permitidos
    $max_size = 5 * 1024 * 1024; // 5 MB
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];

    // Loop por cada archivo subido (name="evidencia[]")
    foreach ($_FILES['evidencia']['tmp_name'] as $key => $tmp_name) {
        
        if ($_FILES['evidencia']['error'][$key] !== UPLOAD_ERR_OK) {
            continue; // Ignora archivos con errores (ej. si no se subió nada)
        }

        $file_name = $_FILES['evidencia']['name'][$key];
        $file_size = $_FILES['evidencia']['size'][$key];
        $file_type = $_FILES['evidencia']['type'][$key];

        // Validaciones
        if ($file_size > $max_size) {
            $response['message'] = "El archivo '{$file_name}' es demasiado grande (Máx 5MB).";
            echo json_encode($response);
            exit;
        }
        if (!in_array($file_type, $allowed_types)) {
            $response['message'] = "El archivo '{$file_name}' tiene un formato no permitido (Solo JPG, PNG, GIF).";
            echo json_encode($response);
            exit;
        }

        // Crear nombre de archivo único y seguro
        $safe_name = uniqid('dev-', true) . '_' . preg_replace("/[^a-zA-Z0-9.\-_]/", "", basename($file_name));
        $upload_file_path = $upload_dir . $safe_name;

        if (move_uploaded_file($tmp_name, $upload_file_path)) {
            $archivos_movidos_fs[] = $upload_file_path; // Guardar ruta completa para rollback
            // Guardar ruta relativa para la BD (para usar en HTML)
            $archivos_subidos_db[] = [
                'nombre_original' => $file_name,
                'ruta_relativa' => 'uploads/tickets/' . $safe_name // Esta es la ruta que verá el admin
            ];
        } else {
            $response['message'] = 'Error al mover uno de los archivos adjuntos al servidor.';
            echo json_encode($response);
            exit;
        }
    }
}


// 6. INICIAR TRANSACCIÓN DE BASE DE DATOS
$conn->begin_transaction();
try {
    
    // Paso 1: Crear la entrada en 'devoluciones'
    $estado_devolucion = "Solicitado";
    $sql_devolucion = "INSERT INTO devoluciones (id_pedido, id_usuario, motivo, estado) VALUES (?, ?, ?, ?)";
    $stmt_dev = $conn->prepare($sql_devolucion);
    $stmt_dev->bind_param("iiss", $id_pedido, $id_usuario, $motivo, $estado_devolucion);
    $stmt_dev->execute();
    $stmt_dev->close();

    // Paso 2: Crear el Ticket de Soporte
    $asunto_ticket = "Solicitud de Devolución para Pedido #" . str_pad($id_pedido, 6, '0', STR_PAD_LEFT);
    $estado_ticket = "Abierto";
    $sql_ticket = "INSERT INTO soporte_tickets (id_usuario, asunto, estado) VALUES (?, ?, ?)";
    $stmt_ticket = $conn->prepare($sql_ticket);
    $stmt_ticket->bind_param("iss", $id_usuario, $asunto_ticket, $estado_ticket);
    $stmt_ticket->execute();
    $id_nuevo_ticket = $conn->insert_id; // ¡Guardamos el ID del ticket!
    $stmt_ticket->close();

    // Paso 3: Guardar el mensaje del usuario en 'soporte_mensajes'
    $es_admin = 0; // 0 = usuario
    $sql_mensaje = "INSERT INTO soporte_mensajes (id_ticket, id_remitente, es_admin, mensaje) VALUES (?, ?, ?, ?)";
    $stmt_mensaje = $conn->prepare($sql_mensaje);
    $stmt_mensaje->bind_param("iiis", $id_nuevo_ticket, $id_usuario, $es_admin, $mensaje_inicial);
    $stmt_mensaje->execute();
    $stmt_mensaje->close();

    // Paso 4: Guardar los adjuntos en la BD (NUEVA TABLA)
    if (!empty($archivos_subidos_db)) {
        $sql_adjunto = "INSERT INTO ticket_adjuntos (id_ticket, nombre_archivo, ruta_archivo) VALUES (?, ?, ?)";
        $stmt_adjunto = $conn->prepare($sql_adjunto);
        foreach ($archivos_subidos_db as $archivo) {
            $stmt_adjunto->bind_param("iss", $id_nuevo_ticket, $archivo['nombre_original'], $archivo['ruta_relativa']);
            $stmt_adjunto->execute();
        }
        $stmt_adjunto->close();
    }
    
    // Paso 5: Actualizar el estado del pedido original
    $nuevo_estado_pedido = "En Devolución";
    $sql_update_pedido = "UPDATE pedidos SET estado = ? WHERE id_pedido = ?";
    $stmt_update = $conn->prepare($sql_update_pedido);
    $stmt_update->bind_param("si", $nuevo_estado_pedido, $id_pedido);
    $stmt_update->execute();
    $stmt_update->close();

    // Si todo salió bien, confirmar cambios
    $conn->commit();

    // 7. ENVIAR CORREO DE CONFIRMACIÓN AL USUARIO
    if (!empty($email_usuario_sesion)) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.hostinger.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'contacto@bitware.site';
            $mail->Password   = 'TuContraseñaReal'; // <-- PON TU CONTRASEÑA
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;

            $mail->setFrom('contacto@bitware.site', 'Bitware - Soporte Técnico');
            $mail->addAddress($email_usuario_sesion, $nombre_usuario_sesion);

            $mail->isHTML(true);
            $mail->Subject = $asunto_ticket;
            $mail->CharSet = 'UTF-8';
            $mail->AddEmbeddedImage('images/Favicon.png', 'logo_bitware'); // Ruta a tu logo
            
            $lista_archivos_html = "";
            if (!empty($archivos_subidos_db)) {
                $lista_archivos_html = "<p><strong>Archivos adjuntados:</strong></p><ul>";
                foreach($archivos_subidos_db as $archivo) {
                    $lista_archivos_html .= "<li>" . htmlspecialchars($archivo['nombre_original']) . "</li>";
                }
                $lista_archivos_html .= "</ul>";
            }

            $mail->Body = "
            <html><body>
            <img src='cid:logo_bitware' alt='Logo de Bitware' width='150'>
            <h2>¡Hola, " . htmlspecialchars($nombre_usuario_sesion) . "!</h2>
            <p>Hemos recibido tu solicitud de devolución para el pedido <strong>#" . str_pad($id_pedido, 6, '0', STR_PAD_LEFT) . "</strong>.</p>
            <p>Se ha creado un ticket de soporte (<strong>#{$id_nuevo_ticket}</strong>) para gestionar tu caso.</p>
            <p><strong>Motivo:</strong> " . htmlspecialchars($motivo) . "</p>
            <p><strong>Mensaje:</strong> " . nl2br(htmlspecialchars($mensaje_inicial)) . "</p>
            {$lista_archivos_html}
            <p>Revisaremos tu solicitud y te contactaremos a la brevedad.</p>
            <br><p><em>El equipo de Bitware</em></p>
            </body></html>";
            
            $mail->send();
        } catch (Exception $e_mail) {
            error_log("PHPMailer Error (solicitar_devolucion.php): " . $e_mail->getMessage());
        }
    }

    // 8. ENVIAR RESPUESTA DE ÉXITO AL JAVASCRIPT
    $response['success'] = true;
    $response['message'] = '¡Solicitud enviada! Se creó el ticket #' . $id_nuevo_ticket . '. Recargarás en 2 segundos.';

} catch (Exception $e_db) {
    // Si algo falló en la BD, deshacer todo
    $conn->rollback();
    
    // ¡Importante! Borrar los archivos que se movieron si la BD falló
    foreach ($archivos_movidos_fs as $filepath) {
        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }
    
    $response['message'] = 'Error al procesar tu solicitud: ' . $e_db->getMessage();
    error_log("Error en transacción de Devolución (BD): " . $e_db->getMessage());
}

$conn->close();
echo json_encode($response);
exit;
?>