<?php
session_start();
require '/var/www/config/config.php';

// 1. SEGURIDAD Y VERIFICACIÓN
if (!isset($_SESSION["loggedin"]) || !isset($_SESSION["id"]) || empty($_SESSION['carrito'])) {
    header("location: login.php");
    exit;
}

// 2. OBTENER DATOS DE LA SESIÓN Y CALCULAR TOTALES
$id_usuario = $_SESSION["id"];
$fecha = date("Y-m-d H:i:s");
$subtotal = $_SESSION['subtotal_original'] ?? 0; // Usamos el subtotal ANTES de descuentos VIP
$metodo_envio_seleccionado = $_SESSION['metodo_envio_seleccionado'] ?? 'normal';

// --- CÁLCULO DE DESCUENTO Y TOTAL FINAL ---
$descuento = 0;
$id_cupon_usado = NULL;
if (isset($_SESSION['cupon'])) {
    $cupon = $_SESSION['cupon'];
    $id_cupon_usado = $cupon['id_cupon'];
    if ($cupon['tipo_descuento'] == 'porcentaje') {
        $descuento = $subtotal * ($cupon['valor'] / 100);
    } else {
        $descuento = $cupon['valor'];
    }
}
$subtotal_con_descuento = $subtotal - $descuento;

// --- !! INICIO: CÁLCULO DE ENVÍO CORREGIDO !! ---
$costo_envio = 0; 
// Solo calculamos envío si el método NO es 'digital'
if ($metodo_envio_seleccionado != 'digital') {
    if ($metodo_envio_seleccionado === 'express') {
        $costo_envio = 9990;
    } else {
        // (El envío gratis por >50k se basa en el subtotal DESPUÉS de cupones)
        if ($subtotal_con_descuento <= 50000) { 
            $costo_envio = 4990;
        }
    }
}
// --- !! FIN: CÁLCULO DE ENVÍO CORREGIDO !! ---

// Aplicamos descuento VIP (si existe) sobre el (subtotal + envío)
$subtotal_mas_envio = $subtotal_con_descuento + $costo_envio;
$descuento_vip = 0;
if (isset($_SESSION['is_vip']) && $_SESSION['is_vip'] === true) {
    // Definimos el ID de la membresía para excluirla del descuento
    define('ID_PRODUCTO_VIP', 999); // (Reemplaza 999 por tu ID real)
    
    // Calculamos el subtotal SIN la membresía VIP (si está)
    $subtotal_para_descuento = $subtotal_mas_envio;
    if(isset($_SESSION['carrito'][ID_PRODUCTO_VIP])) {
        $sql_vip = "SELECT precio FROM producto WHERE id_producto = " . ID_PRODUCTO_VIP;
        if($res_vip = $conn->query($sql_vip)){
            $precio_vip = $res_vip->fetch_assoc()['precio'] ?? 0;
            $subtotal_para_descuento -= $precio_vip; // Restamos el precio de la membresía
        }
    }
    
    $porcentaje_vip = 15; // (El 15% que definiste en carrito.php)
    $descuento_vip = $subtotal_para_descuento * ($porcentaje_vip / 100);
}

// Total final que pagará el cliente
$total_compra = $subtotal_mas_envio - $descuento_vip;


// 3. OBTENER PRECIOS ACTUALES DE LOS PRODUCTOS (Sin cambios)
$productos_info = [];
if (!empty($_SESSION['carrito'])) {
    $ids_productos = implode(',', array_keys($_SESSION['carrito']));
    $sql_precios = "SELECT id_producto, precio FROM producto WHERE id_producto IN ($ids_productos)";
    $resultado_precios = mysqli_query($conn, $sql_precios);
    while($row = mysqli_fetch_assoc($resultado_precios)) {
        $productos_info[$row['id_producto']] = $row['precio'];
    }
}

// 4. TRANSACCIÓN: GUARDAR PEDIDO Y DETALLES
$conn->begin_transaction();
try {
    // Insertar en 'pedidos'
    $estado_pedido_inicial = "Pendiente";
    $sql_pedido = "INSERT INTO pedidos (id_usuario, fecha_pedido, total, estado, id_cupon) VALUES (?, ?, ?, ?, ?)";
    $stmt_pedido = $conn->prepare($sql_pedido);
    $stmt_pedido->bind_param("isdsi", $id_usuario, $fecha, $total_compra, $estado_pedido_inicial, $id_cupon_usado);
    $stmt_pedido->execute();
    $id_pedido_creado = $stmt_pedido->insert_id;
    $stmt_pedido->close();

    // Insertar cada producto en 'pedidos_productos'
    $sql_detalle = "INSERT INTO pedidos_productos (id_pedido, id_producto, cantidad, precio_unitario) VALUES (?, ?, ?, ?)";
    $stmt_detalle = $conn->prepare($sql_detalle);
    foreach ($_SESSION['carrito'] as $id_producto => $cantidad) {
        $precio_unitario = $productos_info[$id_producto] ?? 0;
        $stmt_detalle->bind_param("iiid", $id_pedido_creado, $id_producto, $cantidad, $precio_unitario);
        $stmt_detalle->execute();
    }
    $stmt_detalle->close();

    $conn->commit();

    // Limpiar variables de sesión del checkout
    unset(
        $_SESSION['subtotal_productos'],
        $_SESSION['subtotal_original'], 
        $_SESSION['metodo_envio_seleccionado'], 
        $_SESSION['cupon']
    );
    
    // Redirigir a la pasarela
    header("Location: pasarela_simulada/pasarela.php?id_pedido=" . $id_pedido_creado . "&monto=" . $total_compra);
    exit();

} catch (Exception $e) {
    $conn->rollback();
    die("Error al procesar el pedido: " . $e->getMessage());
}

$conn->close();
?>