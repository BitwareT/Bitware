<?php
// --- INICIO: Carga de PHPMailer ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';
// --- FIN: Carga de PHPMailer ---

// --- NUEVO: Incluir el archivo centralizado para la función de envío ---
require 'includes/send_email.php'; 
// --- FIN NUEVO ---

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require '/var/www/config/config.php';

// --- !! INICIO: DEFINIR ID DEL PRODUCTO VIP !! ---
// (Reemplaza '999' con el ID real de tu producto "Membresía VIP")
define('ID_PRODUCTO_VIP', 999);
// --- !! FIN: DEFINIR ID DEL PRODUCTO VIP !! ---

// 1. Obtener datos de la URL
$id_pedido = $_GET['id_pedido'] ?? null;
$estado_respuesta = $_GET['estado'] ?? null;

// CORRECCIÓN: Asegurarse de que los parámetros existan
if (!$id_pedido || !$estado_respuesta) {
    die("Error: faltan parámetros en la URL. No se puede procesar la confirmación.");
}

$nuevo_estado_db = ($estado_respuesta === 'exito') ? "Pagado" : "Fallido";
$fecha_actual = date("Y-m-d");
$email_cliente = ''; 
$nombre_cliente = ''; 
$productos_pedido_email = [];
$total_pedido_email = 0; 

$conn->begin_transaction();
try {
    // 2. Actualizar tabla 'pedidos'
    $sql_pedido = "UPDATE pedidos SET estado = ? WHERE id_pedido = ?";
    if ($stmt_pedido = $conn->prepare($sql_pedido)) {
        $stmt_pedido->bind_param("si", $nuevo_estado_db, $id_pedido);
        $stmt_pedido->execute();
        $stmt_pedido->close();
    } else {
        throw new Exception("Error al preparar la actualización de pedidos.");
    }

    // 3. Obtener id_pago asociado al pedido (si aplica)
    $id_pago_a_actualizar = 0;
    $sql_buscar_pago = "SELECT id_pago FROM pedidos WHERE id_pedido = ?";
     if ($stmt_buscar = $conn->prepare($sql_buscar_pago)) {
        $stmt_buscar->bind_param("i", $id_pedido);
        $stmt_buscar->execute();
        $result_buscar = $stmt_buscar->get_result();
        if($row_buscar = $result_buscar->fetch_assoc()){
            $id_pago_a_actualizar = $row_buscar['id_pago'];
        }
        $stmt_buscar->close();
    } else {
         throw new Exception("Error al preparar la búsqueda del ID de pago.");
     }
    if($id_pago_a_actualizar > 0){
        $sql_pago_update = "UPDATE pagos SET estado = ?, fecha_pago = ? WHERE id_pago = ?";
        if ($stmt_pago = $conn->prepare($sql_pago_update)) {
            $stmt_pago->bind_param("ssi", $nuevo_estado_db, $fecha_actual, $id_pago_a_actualizar);
            $stmt_pago->execute();
            $stmt_pago->close();
        } else {
             throw new Exception("Error al preparar la actualización de pagos.");
         }
    }
    // --- Fin de la lógica de 'pagos' ---


    // =======================================================
    // ==== LÓGICA PARA ENVIAR CORREO Y ACTUALIZAR VIP ====
    // =======================================================
    if ($estado_respuesta === 'exito') {
        
        // 5. Vaciar el carrito
        unset($_SESSION['carrito']);

        // 6. Obtener datos necesarios (Cliente y Productos)
        $id_usuario_pedido = 0;
        $sql_cliente = "SELECT u.id_usuario, u.email, u.nombre, p.total FROM pedidos p JOIN usuario u ON p.id_usuario = u.id_usuario WHERE p.id_pedido = ?";
        if($stmt_cliente = $conn->prepare($sql_cliente)){
            $stmt_cliente->bind_param("i", $id_pedido);
            $stmt_cliente->execute();
            $res_cliente = $stmt_cliente->get_result();
            if($row_cliente = $res_cliente->fetch_assoc()){
                $id_usuario_pedido = $row_cliente['id_usuario']; 
                $email_cliente = $row_cliente['email'];
                $nombre_cliente = $row_cliente['nombre'];
                $total_pedido_email = $row_cliente['total'];
            }
            $stmt_cliente->close();
        }

        $sql_prods = "SELECT pr.nombre, pp.cantidad, pp.precio_unitario
                      FROM pedidos_productos pp
                      JOIN producto pr ON pp.id_producto = pr.id_producto
                      WHERE pp.id_pedido = ?";
        if($stmt_prods = $conn->prepare($sql_prods)){
            $stmt_prods->bind_param("i", $id_pedido);
            $stmt_prods->execute();
            $res_prods = $stmt_prods->get_result();
            while($row_prod = $res_prods->fetch_assoc()){
                $productos_pedido_email[] = $row_prod;
            }
            $stmt_prods->close();
        }


        // --- !! INICIO: LÓGICA DE ACTUALIZACIÓN VIP (PASO 6) !! ---
        if ($id_usuario_pedido > 0) {
            
            // PATH A: Verificar si compró el producto VIP
            $compro_vip = false;
            $sql_check_vip = "SELECT COUNT(*) as total FROM pedidos_productos WHERE id_pedido = ? AND id_producto = ?";
            if ($stmt_check = $conn->prepare($sql_check_vip)) {
                
                $id_producto_vip_const = ID_PRODUCTO_VIP;
                $stmt_check->bind_param("ii", $id_pedido, $id_producto_vip_const);
                
                $stmt_check->execute();
                $res_check = $stmt_check->get_result();
                if ($res_check->fetch_assoc()['total'] > 0) {
                    $compro_vip = true;
                }
                $stmt_check->close();
            }

            if ($compro_vip) {
                // El usuario compró la membresía (darle 1 año)
                $sql_update_vip = "UPDATE usuario SET vip_status = 'Active', vip_expiry_date = DATE_ADD(NOW(), INTERVAL 1 YEAR) WHERE id_usuario = ?";
                if ($stmt_vip = $conn->prepare($sql_update_vip)) {
                    $stmt_vip->bind_param("i", $id_usuario_pedido);
                    $stmt_vip->execute();
                    $stmt_vip->close();
                }
            } else {
                // PATH B: Si no compró, verificar si calificó por N° de compras
                $sql_count_orders = "SELECT COUNT(id_pedido) as total_compras
                                     FROM pedidos 
                                     WHERE id_usuario = ? 
                                       AND estado IN ('Pagado', 'Enviado', 'Entregado')
                                       AND fecha_pedido >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                
                if ($stmt_count = $conn->prepare($sql_count_orders)) {
                    $stmt_count->bind_param("i", $id_usuario_pedido);
                    $stmt_count->execute();
                    $compras_mes = $stmt_count->get_result()->fetch_assoc()['total_compras'] ?? 0;
                    $stmt_count->close();

                    if ($compras_mes >= 15) {
                        // Calificó por compras (darle 1 mes)
                        $sql_update_vip = "UPDATE usuario 
                                           SET vip_status = 'Active', 
                                               vip_expiry_date = DATE_ADD(NOW(), INTERVAL 1 MONTH) 
                                           WHERE id_usuario = ?";
                        if ($stmt_vip = $conn->prepare($sql_update_vip)) {
                            $stmt_vip->bind_param("i", $id_usuario_pedido);
                            $stmt_vip->execute();
                            $stmt_vip->close();
                        }
                    }
                }
            }

            // Actualizar la sesión del usuario (si es el que está logueado)
            if (isset($_SESSION['id']) && $_SESSION['id'] == $id_usuario_pedido) {
                $sql_check_session = "SELECT vip_status, vip_expiry_date FROM usuario WHERE id_usuario = ?";
                if ($stmt_s = $conn->prepare($sql_check_session)) {
                    $stmt_s->bind_param("i", $id_usuario_pedido);
                    $stmt_s->execute();
                    $res_s = $stmt_s->get_result()->fetch_assoc();
                    if ($res_s && $res_s['vip_status'] == 'Active' && (is_null($res_s['vip_expiry_date']) || $res_s['vip_expiry_date'] >= date('Y-m-d'))) {
                         $_SESSION['is_vip'] = true;
                    }
                    $stmt_s->close();
                }
            }
        }
        // --- !! FIN: LÓGICA DE ACTUALIZACIÓN VIP !! ---
        
        
        // 7. Construir y enviar el correo
        if (!empty($email_cliente) && !empty($productos_pedido_email)) {
            $asunto = "¡Confirmación de tu pedido #" . $id_pedido . " en Bitware!";
            
            // Construcción del mensaje HTML
            $mensaje = "<html><head><title>Confirmación de Pedido</title></head><body style='font-family: Arial, sans-serif; line-height: 1.6;'>";
            $mensaje .= "<img src='cid:logo_bitware' alt='Logo de Bitware' width='150'>";
            $mensaje .= "<h2>¡Hola, " . htmlspecialchars($nombre_cliente) . "!</h2>";
            $mensaje .= "<p>Tu pedido <strong>#" . $id_pedido . "</strong> ha sido pagado y está siendo procesado.</p>";
            $mensaje .= "<h3>Detalles del Pedido:</h3><table style='width: 100%; border-collapse: collapse;'><thead><tr style='background-color: #f4f4f4;'><th style='padding: 8px; border: 1px solid #ddd;'>Producto</th><th style='padding: 8px; border: 1px solid #ddd;'>Cantidad</th><th style='padding: 8px; border: 1px solid #ddd;'>Precio</th></tr></thead><tbody>";
            
            foreach($productos_pedido_email as $prod_email) {
                $mensaje .= "<tr>";
                $mensaje .= "<td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($prod_email['nombre']) . "</td>";
                $mensaje .= "<td style='padding: 8px; border: 1px solid #ddd; text-align: center;'>" . $prod_email['cantidad'] . "</td>";
                $mensaje .= "<td style='padding: 8px; border: 1px solid #ddd; text-align: right;'>$" . number_format($prod_email['precio_unitario'] * $prod_email['cantidad'], 0, ',', '.') . "</td>";
                $mensaje .= "</tr>";
            }
            
            $mensaje .= "</tbody><tfoot><tr style='background-color: #f4f4f4;'><td colspan='2' style='padding: 8px; border: 1px solid #ddd; text-align: right; font-weight: bold;'>Total Pagado:</td><td style='padding: 8px; border: 1px solid #ddd; text-align: right; font-weight: bold;'>$" . number_format($total_pedido_email, 0, ',', '.') . "</td></tr></tfoot></table>";
            $mensaje .= "<p>Puedes ver el estado de tu pedido en cualquier momento desde tu panel de usuario.</p>";
            $mensaje .= "<br><p><em>El equipo de Bitware</em></p>";
            $mensaje .= "</body></html>";
            
            // --- INICIO: REEMPLAZO POR LA FUNCIÓN CENTRALIZADA ---
            try {
                sendEmail($email_cliente, $nombre_cliente, $asunto, $mensaje, 'Bitware (Pedidos)');
            } catch (Exception $e) {
                // No detenemos la transacción si el correo falla, solo lo registramos.
                error_log("PHPMailer Error (confirmacion_pago.php): " . $e->getMessage() . " para Pedido #" . $id_pedido);
            }
            // --- FIN: REEMPLAZO POR LA FUNCIÓN CENTRALIZADA ---
        }
    }
    // =======================================================

    $conn->commit();

} catch (Exception $e) {
    $conn->rollback();
    error_log("Error en transacción de confirmacion_pago.php: " . $e->getMessage());
    // (Opcional) Mostrar un error genérico al usuario
    $estado_respuesta = 'fallido'; // Forzar error si la transacción de BD falla
    $id_pedido = $id_pedido ?? '0'; // Asegurarse de que $id_pedido tenga un valor
}

// 5. Determinar a dónde redirigir
$redirect_url = ($estado_respuesta === 'exito') ? 'mis_pedidos.php' : 'carrito.php';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Confirmación de Pago - Bitware</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="icon" href="images/favicon.ico" type="image/ico">
    <link rel="stylesheet" href="css/CPago.css">
</head>
<body>
<div class="container <?php echo ($estado_respuesta === 'exito') ? 'success' : 'failure'; ?>">
        <?php if ($estado_respuesta === 'exito'): ?>
            <div class="icon"><i class="fas fa-check-circle"></i></div>
            <h1>¡Pago Aprobado!</h1>
            <p>Tu pedido <span class="order-id">#<?php echo htmlspecialchars($id_pedido); ?></span> ha sido pagado correctamente.</p>
            <p>Hemos enviado un correo de confirmación a <strong><?php echo htmlspecialchars($email_cliente); ?></strong>. Puedes ver el estado de tu pedido en tu panel.</p>
            <a href="mis_pedidos.php" class="button-link">Ver Mis Pedidos</a>
        <?php else: ?>
            <div class="icon"><i class="fas fa-times-circle"></i></div>
            <h1>¡Pago Rechazado!</h1>
            <p>Hubo un problema al procesar el pago para tu pedido <span class="order-id">#<?php echo htmlspecialchars($id_pedido); ?></span>.</p>
            <p>No se ha realizado ningún cargo a tu tarjeta. Por favor, revisa los datos e intenta nuevamente.</p>
            <a href="carrito.php" class="button-link">Volver al Carrito</a>
        <?php endif; ?>

        <p class="redirect-message">Serás redirigido automáticamente en <span id="countdown">10</span> segundos... <i class="fas fa-spinner spinner"></i></p>
    </div>

    <script>
        (function() {
            let seconds = 10;
            const countdownElement = document.getElementById('countdown');
            const redirectUrl = '<?php echo $redirect_url; ?>'; 

            const interval = setInterval(function() {
                seconds--;
                countdownElement.textContent = seconds;
                if (seconds <= 0) {
                    clearInterval(interval);
                    window.location.href = redirectUrl;
                }
            }, 1000);
        })();
    </script>
</body>
</html>