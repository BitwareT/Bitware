<?php
session_start();
require '/var/www/config/config.php';

// 1. VERIFICACIÓN DE SEGURIDAD: Solo para administradores
if (!isset($_SESSION["loggedin"]) || !isset($_SESSION["id"]) || $_SESSION["permisos"] !== 'A') {
    header("location: login.php");
    exit;
}

// 2. Validar que se haya pasado un ID
if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("location: gestionar_pedidos.php");
    exit;
}
$id_pedido = trim($_GET["id"]);

// --- 3. CONSULTA PRINCIPAL MEJORADA PARA INCLUIR EL CUPÓN ---
$sql_pedido = "SELECT 
                    p.id_pedido, p.fecha_pedido, p.estado, p.total,
                    u.nombre AS nombre_cliente, u.email AS email_cliente, u.telefono AS telefono_cliente,
                    u.direccion AS direccion_cliente, u.region AS region_cliente, u.rut,
                    c.codigo AS codigo_cupon, c.tipo_descuento, c.valor AS valor_cupon
               FROM pedidos p 
               JOIN usuario u ON p.id_usuario = u.id_usuario
               LEFT JOIN cupones c ON p.id_cupon = c.id_cupon
               WHERE p.id_pedido = ?";
$pedido = null;
if ($stmt = $conn->prepare($sql_pedido)) {
    $stmt->bind_param("i", $id_pedido);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows == 1) {
            $pedido = $result->fetch_assoc();
        } else {
            header("location: gestionar_pedidos.php"); 
            exit;
        }
    }
    $stmt->close();
}

// --- 4. OBTENER TODOS LOS PRODUCTOS DE ESTE PEDIDO ---
$productos_del_pedido = [];
$sql_productos = "SELECT p.nombre, p.imagen_principal, pp.cantidad, pp.precio_unitario
                  FROM pedidos_productos pp
                  JOIN producto p ON pp.id_producto = p.id_producto
                  WHERE pp.id_pedido = ?";
if ($stmt_prods = $conn->prepare($sql_productos)) {
    $stmt_prods->bind_param("i", $id_pedido);
    $stmt_prods->execute();
    $result_prods = $stmt_prods->get_result();
    $productos_del_pedido = $result_prods->fetch_all(MYSQLI_ASSOC);
    $stmt_prods->close();
}

// --- 5. OBTENER DATOS DE LA DEVOLUCIÓN (SI EXISTE) ---
$devolucion_motivo = null;
$devolucion_mensaje = null;
$archivos_adjuntos = [];
$id_ticket_encontrado = 0; // Para buscar adjuntos

if ($pedido['estado'] == 'En Devolución') {
    
    // 1. Obtener el motivo de la tabla 'devoluciones'
    $sql_dev = "SELECT motivo FROM devoluciones WHERE id_pedido = ? ORDER BY fecha_solicitud DESC LIMIT 1";
    if ($stmt_dev = $conn->prepare($sql_dev)) {
        $stmt_dev->bind_param("i", $id_pedido);
        $stmt_dev->execute();
        $result_dev = $stmt_dev->get_result();
        if ($dev = $result_dev->fetch_assoc()) {
            $devolucion_motivo = $dev['motivo'];
        }
        $stmt_dev->close();
    }
    
    // 2. Obtener el mensaje Y EL ID del ticket de soporte asociado
    $asunto_ticket_devolucion = "Solicitud de Devolución para Pedido #" . str_pad($id_pedido, 6, '0', STR_PAD_LEFT);
    
    $sql_msg = "SELECT m.mensaje, t.id_ticket 
                FROM soporte_mensajes m
                JOIN soporte_tickets t ON m.id_ticket = t.id_ticket
                WHERE t.asunto = ? AND m.es_admin = 0 
                ORDER BY m.fecha_envio ASC LIMIT 1";
                
    if ($stmt_msg = $conn->prepare($sql_msg)) {
        $stmt_msg->bind_param("s", $asunto_ticket_devolucion);
        $stmt_msg->execute();
        $result_msg = $stmt_msg->get_result();
        if ($msg = $result_msg->fetch_assoc()) {
            $devolucion_mensaje = $msg['mensaje'];
            $id_ticket_encontrado = $msg['id_ticket']; // <-- ¡ID del ticket guardado!
        }
        $stmt_msg->close();
    }

    // --- 3. NUEVA CONSULTA: Buscar adjuntos usando el ID del ticket ---
    if ($id_ticket_encontrado > 0) {
        $sql_adj = "SELECT ruta_archivo, nombre_archivo FROM ticket_adjuntos WHERE id_ticket = ?";
        if ($stmt_adj = $conn->prepare($sql_adj)) {
            $stmt_adj->bind_param("i", $id_ticket_encontrado);
            $stmt_adj->execute();
            $archivos_adjuntos = $stmt_adj->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt_adj->close();
        }
    }
} // <-- ¡CORCHETE '}' CORREGIDO!

// --- FIN DE LA NUEVA LÓGICA ---

$conn->close();

// --- FUNCIÓN PARA FORMATEAR EL RUT ---
function formatearRut($rut) {
    $rut = preg_replace('/[^k0-9]/i', '', $rut);
    if (empty($rut)) return '';
    $dv = substr($rut, -1);
    $cuerpo = substr($rut, 0, -1);
    $cuerpo = number_format($cuerpo, 0, ',', '.');
    return $cuerpo . '-' . strtoupper($dv);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle del Pedido #<?php echo htmlspecialchars($id_pedido); ?></title>
    <link rel="stylesheet" href="css/DPedidos.css">
    <link rel="icon" href="images/favicon.ico" type="image/ico"> </head>
<body>
<div class="container">
    <a href="gestionar_pedidos.php">&larr; Volver a la lista de pedidos</a>
    <h1>Detalle del Pedido #<?php echo htmlspecialchars($id_pedido); ?></h1>

    <div class="details-section">
        <h2>Datos del Cliente</h2>
        <p><strong>Nombre:</strong> <?php echo htmlspecialchars($pedido['nombre_cliente']); ?></p>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($pedido['email_cliente']); ?></p>
        <p><strong>Teléfono:</strong> <?php echo !empty($pedido['telefono_cliente']) ? htmlspecialchars($pedido['telefono_cliente']) : '<em>No especificado</em>'; ?></p>
        <p><strong>RUT Cliente:</strong> <?php echo !empty($pedido['rut']) ? formatearRut($pedido['rut']) : '<em>No especificado</em>'; ?></p>
    </div>

    <div class="details-section">
        <h2>Información de Envío</h2>
        <p><strong>Dirección:</strong> <?php echo !empty($pedido['direccion_cliente']) ? htmlspecialchars($pedido['direccion_cliente']) : '<em>No especificada</em>'; ?></p>
        <p><strong>Región:</strong> <?php echo !empty($pedido['region_cliente']) ? htmlspecialchars($pedido['region_cliente']) : '<em>No especificada</em>'; ?></p>
    </div>
    
    <div class="details-section">
        <h2>Detalles del Pedido</h2>
        <p><strong>Fecha del Pedido:</strong> <?php echo date("d/m/Y H:i", strtotime($pedido['fecha_pedido'])); ?></p>
        <p><strong>Estado Actual:</strong> <?php echo htmlspecialchars($pedido['estado']); ?></p>
        
        <?php if ($pedido['estado'] == 'En Devolución'): ?>
            <div class="devolucion-info" style="border: 1px solid #ffc107; background-color: #fffaf0; padding: 10px 15px; border-radius: 5px; margin-top: 10px;">
                <h4 style="color: #856404; margin-top: 0;">Detalles de la Devolución</h4>
                
                <?php if ($devolucion_motivo): ?>
                    <p><strong>Motivo:</strong> <?php echo htmlspecialchars($devolucion_motivo); ?></p>
                <?php endif; ?>
                
                <?php if ($devolucion_mensaje): ?>
                    <p><strong>Mensaje del Cliente:</strong><br>
                    <?php echo nl2br(htmlspecialchars($devolucion_mensaje)); ?></p>
                <?php endif; ?>

                <?php if (!empty($archivos_adjuntos)): ?>
                    <p style="margin-top: 10px; margin-bottom: 5px;"><strong>Evidencia Adjunta:</strong></p>
                    <div class="evidencia-galeria" style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <?php foreach ($archivos_adjuntos as $archivo): ?>
                            <div class="evidencia-item">
                                <a href="<?php echo htmlspecialchars($archivo['ruta_archivo']); ?>" target="_blank" title="Ver <?php echo htmlspecialchars($archivo['nombre_archivo']); ?>">
                                    
                                    <img src="<?php echo htmlspecialchars($archivo['ruta_archivo']); ?>" 
                                         alt="<?php echo htmlspecialchars($archivo['nombre_archivo']); ?>" 
                                         style="width: 100px; height: 100px; object-fit: cover; border-radius: 5px; border: 1px solid #ddd; cursor: pointer;">
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                </div>
        <?php endif; ?>
        <?php if (!empty($pedido['codigo_cupon'])): ?>
            <?php
                $descuento_texto = '';
                if ($pedido['tipo_descuento'] == 'porcentaje') {
                    $descuento_texto = $pedido['valor_cupon'] . '%';
                } else {
                    $descuento_texto = '$' . number_format($pedido['valor_cupon']);
                }
            ?>
            <p><strong>Cupón Utilizado:</strong> <?php echo htmlspecialchars($pedido['codigo_cupon']); ?> (<?php echo $descuento_texto; ?> de descuento)</p>
        <?php endif; ?>

        <p><strong>Monto Total Pagado:</strong> <strong>$<?php echo number_format($pedido['total'], 0, ',', '.'); ?></strong></p>
    </div>

    <div class="details-section">
        <h2>Productos Incluidos</h2>
        <?php foreach($productos_del_pedido as $producto_item): ?>
            <div class="product-card" style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
                <img src="uploads/<?php echo htmlspecialchars($producto_item['imagen_principal']); ?>" alt="<?php echo htmlspecialchars($producto_item['nombre']); ?>">
                <div class="product-card-info">
                    <p><strong>Producto:</strong> <?php echo htmlspecialchars($producto_item['nombre']); ?></p>
                    <p><strong>Cantidad:</strong> <?php echo $producto_item['cantidad']; ?></p>
                    <p><strong>Precio Unitario:</strong> $<?php echo number_format($producto_item['precio_unitario'], 0, ',', '.'); ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
</body>
</html>
<footer class="footer">
<?php
require 'includes/footer.php';
?>