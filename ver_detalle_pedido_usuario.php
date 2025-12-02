<?php
session_start();
require '/var/www/config/config.php';

// 1. SEGURIDAD: Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["id"])) {
    header("location: login.php");
    exit;
}
$id_usuario_actual = $_SESSION["id"];

// 2. Validar que se haya pasado un ID de pedido válido en la URL
if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    // Si no hay ID o no es número, redirige al historial de pedidos del usuario
    header("location: mis_pedidos.php");
    exit;
}
$id_pedido = trim($_GET["id"]);

// --- 3. CONSULTA PRINCIPAL: Obtener datos del pedido VERIFICANDO QUE PERTENEZCA AL USUARIO ACTUAL ---
$sql_pedido = "SELECT
                    p.id_pedido, p.fecha_pedido, p.estado, p.total,
                    u.nombre AS nombre_cliente, u.email AS email_cliente, u.telefono AS telefono_cliente,
                    u.direccion AS direccion_cliente, u.region AS region_cliente, u.rut,
                    c.codigo AS codigo_cupon, c.tipo_descuento, c.valor AS valor_cupon
               FROM pedidos p
               JOIN usuario u ON p.id_usuario = u.id_usuario
               LEFT JOIN cupones c ON p.id_cupon = c.id_cupon
               WHERE p.id_pedido = ? AND p.id_usuario = ?"; // <-- ¡VERIFICACIÓN DE USUARIO!

$pedido = null;
if ($stmt = $conn->prepare($sql_pedido)) {
    // Vinculamos el ID del pedido y el ID del usuario actual
    $stmt->bind_param("ii", $id_pedido, $id_usuario_actual);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows == 1) {
            // El pedido existe y pertenece a este usuario
            $pedido = $result->fetch_assoc();
        } else {
            // Si no se encuentra (o no pertenece al usuario), redirige a su historial
            header("location: mis_pedidos.php");
            exit;
        }
    } else {
        // Error en la ejecución de la consulta
        echo "Error al obtener los detalles del pedido.";
        exit;
    }
    $stmt->close();
} else {
    // Error al preparar la consulta
    echo "Error al preparar la consulta del pedido.";
    exit;
}

// --- 4. OBTENER LOS PRODUCTOS DE ESTE PEDIDO ---
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
$conn->close();

// --- 5. FUNCIÓN PARA FORMATEAR EL RUT (opcional, pero útil) ---
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
    <title>Detalle de Mi Pedido #<?php echo htmlspecialchars($id_pedido); ?></title>
    <link rel="stylesheet" href="css/mis_pedidos.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="icon" href="images/favicon.ico" type="image/ico">
    <style>
        /* Estilos adicionales rápidos (puedes moverlos a un CSS) */
        .container { max-width: 800px; margin: 20px auto; padding: 20px; background-color: #fff; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .details-section { margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
        .details-section h2 { margin-bottom: 15px; color: #333; font-size: 1.4em; }
        .details-section p { margin-bottom: 8px; color: #555; }
        .product-card { display: flex; align-items: center; gap: 15px; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
        .product-card:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0;}
        .product-card img { width: 60px; height: 60px; object-fit: cover; border-radius: 4px; }
        .product-card-info { flex-grow: 1; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #007bff; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
        .status-badge { padding: 5px 10px; border-radius: 15px; font-size: 0.9em; color: white; display: inline-block; }
        /* Añade clases de estado como en mis_pedidos.css si no las tienes */
        .status-pagado { background-color: #28a745; }
        .status-en-camino { background-color: #17a2b8; }
        .status-entregado { background-color: #007bff; }
        .status-pendiente { background-color: #ffc107; color: #333; }
        .status-cancelado, .status-fallido { background-color: #dc3545; }
        .status-devolucion { background-color: #6c757d; }

    </style>
</head>
<body>

<header class="main-header"> <div class="header-content">
        <a href="index.php" class="logo">Bitware</a>
        <a href="dashboard.php" class="account-link">
            <i class="fas fa-user-circle"></i> Mi Cuenta
        </a>
    </div>
</header>

<div class="container">
    <a href="mis_pedidos.php" class="back-link">&larr; Volver a Mis Pedidos</a>
    <h1>Detalle del Pedido #<?php echo htmlspecialchars($id_pedido); ?></h1>

    <div class="details-section">
        <h2>Información del Pedido</h2>
        <p><strong>Fecha:</strong> <?php echo date("d/m/Y H:i", strtotime($pedido['fecha_pedido'])); ?></p>
        <p><strong>Estado:</strong>
            <?php
                // Lógica para mostrar el estado con color (similar a mis_pedidos.php)
                $estado = strtolower($pedido['estado'] ?? 'pendiente');
                $status_class = ''; $status_icon = ''; $status_text = '';
                switch ($estado) {
                    case 'pagado': $status_class = 'status-pagado'; $status_icon = '<i class="fas fa-money-check-alt"></i>'; $status_text = 'Pagado'; break;
                    case 'enviado': $status_class = 'status-en-camino'; $status_icon = '<i class="fas fa-truck"></i>'; $status_text = 'En camino'; break;
                    case 'entregado': case 'completado': $status_class = 'status-entregado'; $status_icon = '<i class="fas fa-check-circle"></i>'; $status_text = 'Entregado'; break;
                    case 'pendiente': case 'fallido': $status_class = 'status-pendiente'; $status_icon = '<i class="fas fa-clock"></i>'; $status_text = 'Pendiente'; break;
                    case 'cancelado': $status_class = 'status-cancelado'; $status_icon = '<i class="fas fa-times-circle"></i>'; $status_text = 'Cancelado'; break;
                    case 'devolución': $status_class = 'status-devolucion'; $status_icon = '<i class="fas fa-undo-alt"></i>'; $status_text = 'En Devolución'; break;
                    default: $status_class = 'status-pendiente'; $status_icon = '<i class="fas fa-question-circle"></i>'; $status_text = htmlspecialchars(ucfirst($pedido['estado']));
                }
            ?>
            <span class="status-badge <?php echo $status_class; ?>"><?php echo $status_icon; ?> <?php echo $status_text; ?></span>
        </p>
        <?php if (!empty($pedido['codigo_cupon'])): ?>
            <?php
                $descuento_texto = ($pedido['tipo_descuento'] == 'porcentaje') ? $pedido['valor_cupon'] . '%' : '$' . number_format($pedido['valor_cupon']);
            ?>
            <p><strong>Cupón Utilizado:</strong> <?php echo htmlspecialchars($pedido['codigo_cupon']); ?> (<?php echo $descuento_texto; ?> de descuento)</p>
        <?php endif; ?>
        <p><strong>Total Pagado:</strong> <strong>$<?php echo number_format($pedido['total'], 0, ',', '.'); ?></strong></p>
    </div>

    <div class="details-section">
        <h2>Dirección de Envío</h2>
        <p><strong>Nombre:</strong> <?php echo htmlspecialchars($pedido['nombre_cliente']); ?></p>
        <p><strong>Dirección:</strong> <?php echo !empty($pedido['direccion_cliente']) ? htmlspecialchars($pedido['direccion_cliente']) : '<em>No especificada</em>'; ?></p>
        <p><strong>Región:</strong> <?php echo !empty($pedido['region_cliente']) ? htmlspecialchars($pedido['region_cliente']) : '<em>No especificada</em>'; ?></p>
        <p><strong>Teléfono:</strong> <?php echo !empty($pedido['telefono_cliente']) ? htmlspecialchars($pedido['telefono_cliente']) : '<em>No especificado</em>'; ?></p>
        <p><strong>RUT:</strong> <?php echo !empty($pedido['rut']) ? formatearRut($pedido['rut']) : '<em>No especificado</em>'; ?></p>
    </div>

    <div class="details-section">
        <h2>Productos en este Pedido</h2>
        <?php if (empty($productos_del_pedido)): ?>
            <p>No se encontraron productos para este pedido.</p>
        <?php else: ?>
            <?php foreach($productos_del_pedido as $producto_item): ?>
                <div class="product-card">
                    <img src="uploads/<?php echo htmlspecialchars($producto_item['imagen_principal'] ?? 'default.png'); ?>" alt="<?php echo htmlspecialchars($producto_item['nombre']); ?>">
                    <div class="product-card-info">
                        <p><strong><?php echo htmlspecialchars($producto_item['nombre']); ?></strong></p>
                        <p>Cantidad: <?php echo $producto_item['cantidad']; ?></p>
                        <p>Precio Unitario: $<?php echo number_format($producto_item['precio_unitario'], 0, ',', '.'); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
<footer class="footer">
<?php
require 'includes/footer.php';
?>