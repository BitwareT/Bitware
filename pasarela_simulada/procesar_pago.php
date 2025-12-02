<?php
// --- INICIO: Carga de PHPMailer ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// RUTA CORREGIDA: Le decimos que suba un nivel (..)
require __DIR__ . '/../vendor/autoload.php'; 
// --- FIN: Carga de PHPMailer ---

session_start();
// Esta ruta estaba bien porque es absoluta
require '/var/www/config/config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_pedido = $_POST['id_pedido'] ?? '0';
    $numero_tarjeta = $_POST['card_number'] ?? '';
    
    $url_retorno = "http://bitware.site/confirmacion_pago.php"; 

    // Simulación: Tarjeta '4...' es éxito
    if (substr($numero_tarjeta, 0, 1) === '4') {
        $estado = "exito";

        // Usamos una transacción
        $conn->begin_transaction();
        try {
            
            // 1. ACTUALIZAR ESTADO DEL PEDIDO a 'pagado'
            $sql_update_pedido = "UPDATE pedidos SET estado = 'pagado' WHERE id_pedido = ?";
            $stmt_pedido = $conn->prepare($sql_update_pedido);
            $stmt_pedido->bind_param("i", $id_pedido);
            $stmt_pedido->execute();
            $stmt_pedido->close();

            // 2. DESCONTAR STOCK DE PRODUCTOS
            $sql_get_prods = "SELECT id_producto, cantidad FROM pedidos_productos WHERE id_pedido = ?";
            $stmt_get = $conn->prepare($sql_get_prods);
            $stmt_get->bind_param("i", $id_pedido);
            $stmt_get->execute();
            $productos_comprados = $stmt_get->get_result();
            $stmt_get->close();

            $sql_update_stock = "UPDATE producto SET stock = stock - ? WHERE id_producto = ?";
            $stmt_stock = $conn->prepare($sql_update_stock);
            while ($producto = $productos_comprados->fetch_assoc()) {
                $stmt_stock->bind_param("ii", $producto['cantidad'], $producto['id_producto']);
                $stmt_stock->execute();
            }
            $stmt_stock->close();

            // 3. OBTENER DATOS DEL USUARIO PARA EL CORREO
            $nombre_usuario = "";
            $email_usuario = "";
            $sql_get_user = "SELECT u.nombre, u.email 
                             FROM usuario u 
                             JOIN pedidos p ON u.id_usuario = p.id_usuario 
                             WHERE p.id_pedido = ?";
            $stmt_user = $conn->prepare($sql_get_user);
            $stmt_user->bind_param("i", $id_pedido);
            $stmt_user->execute();
            $result_user = $stmt_user->get_result();
            if($user = $result_user->fetch_assoc()) {
                $nombre_usuario = $user['nombre'];
                $email_usuario = $user['email'];
            }
            $stmt_user->close();
            
            // Confirma cambios en la BD
            $conn->commit();

            // 4. VACIAR EL CARRITO DE LA SESIÓN
            unset($_SESSION['carrito']);

            // 5. ENVIAR CORREO DE CONFIRMACIÓN
            if (!empty($email_usuario)) {
                
                $asunto_correo = "¡Tu pedido en Bitware ha sido confirmado! (Pedido #" . $id_pedido . ")";
                $mensaje_correo = "
                <html><body>
                <img src='cid:logo_bitware' alt='Logo de Bitware' width='150'> 
                <h2>¡Gracias por tu compra, " . htmlspecialchars($nombre_usuario) . "!</h2>
                <p>Tu pedido con el ID <strong>#" . $id_pedido . "</strong> ha sido procesado y pagado con éxito.</p>
                <p>Pronto recibirás información sobre el envío.</p>
                <p>Puedes revisar el estado de todos tus pedidos en tu panel de usuario.</p>
                <br><p><em>El equipo de Bitware</em></p>
                </body></html>";
                
                $mail = new PHPMailer(true);
                try {
                    // Configuración SMTP
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.hostinger.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'contacto@bitware.site';
                    $mail->Password   = 'Rocky26..';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
                    $mail->Port       = 465;                      

                    // Remitente y Destinatario
                    $mail->setFrom('contacto@bitware.site', 'Bitware - Pedidos');
                    $mail->addAddress($email_usuario, $nombre_usuario);

                    // Contenido
                    $mail->isHTML(true);
                    $mail->Subject = $asunto_correo;
                    $mail->Body    = $mensaje_correo;
                    $mail->CharSet = 'UTF-8';
                    
                    // RUTA CORREGIDA: Le decimos que suba un nivel (..)
                    $mail->AddEmbeddedImage(__DIR__ . '/../images/Favicon.png', 'logo_bitware'); 

                    $mail->send();

                } catch (Exception $e) {
                    error_log("PHPMailer Error (procesar_pago.php): " . $mail->ErrorInfo);
                }
            }
            
        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            $estado = "fallido_db"; 
            error_log("Error en transacción de BD (procesar_pago.php): " . $exception->getMessage());
        }

    } else {
        $estado = "fallido";
    }

    // Cerrar la conexión
    $conn->close();

    // Espera simulada
    sleep(3);

    // Redirigir de vuelta a la tienda
    header("Location: " . $url_retorno . "?id_pedido=" . urlencode($id_pedido) . "&estado=" . urlencode($estado));
    exit();
}
?>