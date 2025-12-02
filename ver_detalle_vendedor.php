<?php
session_start();
require '/var/www/config/config.php';

// 1. VERIFICACIÓN DE SEGURIDAD (Vendedor y Pedido Válido)
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["permisos"] !== 'V') {
    header("location: login.php");
    exit;
}
$id_vendedor_actual = $_SESSION['id'];

// 2. Validar que se haya pasado un ID
if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("location: vendedor_pedidos.php");
    exit;
}
$id_pedido = trim($_GET["id"]);

// --- 3. VERIFICACIÓN DE PROPIEDAD (¡MUY IMPORTANTE!) ---
// Verificamos que este vendedor tenga al menos UN producto en este pedido.
$sql_check_owner = "SELECT p.id_pedido 
                    FROM pedidos p
                    JOIN pedidos_productos pp ON p.id_pedido = pp.id_pedido
                    JOIN producto pr ON pp.id_producto = pr.id_producto
                    WHERE p.id_pedido = ? AND pr.id_vendedor = ?
                    LIMIT 1";
$stmt_check = $conn->prepare($sql_check_owner);
$stmt_check->bind_param("ii", $id_pedido, $id_vendedor_actual);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows == 0) {
    // Si este pedido no tiene productos de este vendedor, ¡fuera!
    header("location: vendedor_pedidos.php");
    exit;
}
$stmt_check->close();

// --- 4. CONSULTA PRINCIPAL (SEGURA, SIN DATOS SENSIBLES) ---
// (Nota: No seleccionamos email, teléfono ni RUT del cliente)
$sql_pedido = "SELECT 
                    p.id_pedido, p.fecha_pedido, p.estado, p.total,
                    u.nombre AS nombre_cliente,
                    u.direccion AS direccion_cliente, u.region AS region_cliente,
                    c.codigo AS codigo_cupon, c.tipo_descuento, c.valor AS valor_cupon
               FROM pedidos p 
               JOIN usuario u ON p.id_usuario = u.id_usuario
               LEFT JOIN cupones c ON p.id_cupon = c.id_cupon
               WHERE p.id_pedido = ?";
$pedido = null;
if ($stmt = $conn->prepare($sql_pedido)) {
    $stmt->bind_param("i", $id_pedido);
    $stmt->execute();
    $pedido = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// --- 5. OBTENER SOLO LOS PRODUCTOS DE ESTE VENDEDOR EN ESTE PEDIDO ---
$productos_del_pedido = [];
$sql_productos = "SELECT p.nombre, p.imagen_principal, pp.cantidad, pp.precio_unitario
                  FROM pedidos_productos pp
                  JOIN producto p ON pp.id_producto = p.id_producto
                  WHERE pp.id_pedido = ? AND p.id_vendedor = ?"; // <-- Filtro de vendedor
if ($stmt_prods = $conn->prepare($sql_productos)) {
    $stmt_prods->bind_param("ii", $id_pedido, $id_vendedor_actual);
    $stmt_prods->execute();
    $result_prods = $stmt_prods->get_result();
    $productos_del_pedido = $result_prods->fetch_all(MYSQLI_ASSOC);
    $stmt_prods->close();
}

// --- 6. OBTENER DATOS DE LA DEVOLUCIÓN (SI EXISTE) ---
// (Copiado de ver_detalle_pedido.php, es relevante para el vendedor)
$devolucion_motivo = null;
$devolucion_mensaje = null;
$archivos_adjuntos = [];
$id_ticket_encontrado = 0; 

if ($pedido['estado'] == 'En Devolución') {
    $sql_dev = "SELECT motivo FROM devoluciones WHERE id_pedido = ? ORDER BY fecha_solicitud DESC LIMIT 1";
    if ($stmt_dev = $conn->prepare($sql_dev)) {
        $stmt_dev->bind_param("i", $id_pedido);
        $stmt_dev->execute();
        $result_dev = $stmt_dev->get_result();
        if ($dev = $result_dev->fetch_assoc()) $devolucion_motivo = $dev['motivo'];
        $stmt_dev->close();
    }
    
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
            $id_ticket_encontrado = $msg['id_ticket'];
        }
        $stmt_msg->close();
    }
    if ($id_ticket_encontrado > 0) {
        $sql_adj = "SELECT ruta_archivo, nombre_archivo FROM ticket_adjuntos WHERE id_ticket = ?";
        if ($stmt_adj = $conn->prepare($sql_adj)) {
            $stmt_adj->bind_param("i", $id_ticket_encontrado);
            $stmt_adj->execute();
            $archivos_adjuntos = $stmt_adj->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt_adj->close();
        }
    }
} 
$conn->close();

// --- 7. DEFINIR VARIABLES DEL HEAD ---
$titulo_pagina = 'Detalle de Mi Venta';
$css_pagina_especifica = "css/DPedidos.css"; // Reutilizamos el CSS del Admin
require 'includes/head.php';
?>

<div class="container">
    <a href="vendedor_pedidos.php">&larr; Volver a la lista de pedidos</a>
    <h1>Detalle de Venta (Pedido #<?php echo htmlspecialchars($id_pedido); ?>)</h1>

    <div class="details-section">
        <h2>Datos del Cliente</h2>
        <p><strong>Nombre:</strong> <?php echo htmlspecialchars($pedido['nombre_cliente']); ?></p>
        <p><strong>(Datos de contacto privados)</strong></p>
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
                <p><strong>Motivo:</strong> <?php echo htmlspecialchars($devolucion_motivo); ?></p>
                <p><strong>Mensaje del Cliente:</strong><br>
                <?php echo nl2br(htmlspecialchars($devolucion_mensaje)); ?></p>

                <?php if (!empty($archivos_adjuntos)): ?>
                    <p style="margin-top: 10px; margin-bottom: 5px;"><strong>Evidencia Adjunta:</strong></p>
                    <div class="evidencia-galeria" style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <?php foreach ($archivos_adjuntos as $archivo): ?>
                            <a href="<?php echo htmlspecialchars($archivo['ruta_archivo']); ?>" target="_blank" title="Ver <?php echo htmlspecialchars($archivo['nombre_archivo']); ?>">
                                <img src="<?php echo htmlspecialchars($archivo['ruta_archivo']); ?>" 
                                     alt="<?php echo htmlspecialchars($archivo['nombre_archivo']); ?>" 
                                     style="width: 100px; height: 100px; object-fit: cover; border-radius: 5px; border: 1px solid #ddd;">
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="details-section">
        <h2>Mis Productos en este Pedido</h2>
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