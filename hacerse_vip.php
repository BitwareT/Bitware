<?php
session_start();
require_once "check_activity.php"; 
require '/var/www/config/config.php';

// 1. SEGURIDAD: Redirigir si no ha iniciado sesión.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    $_SESSION['redirect_url'] = 'hacerse_vip.php';
    header("location: login.php?mensaje=vip");
    exit;
}

// --- !! INICIO: DEFINIR ID DEL PRODUCTO VIP !! ---
// !! REEMPLAZA '999' con el ID real de tu producto "Membresía VIP" !!
define('ID_PRODUCTO_VIP', 999);
// --- !! FIN: DEFINIR ID DEL PRODUCTO VIP !! ---

// 2. DEFINIR VARIABLES PARA EL HEAD
$titulo_pagina = 'Hazte VIP - Bitware';
$css_pagina_especifica = "css/servicio.css"; 
require 'includes/head.php';

// Definimos los detalles de la membresía (los leemos del producto)
$precio_vip = 10000; // Valor por defecto
$descripcion_membresia = "Membresía VIP Anual Bitware";
$sql_prod = "SELECT precio, nombre FROM producto WHERE id_producto = " . ID_PRODUCTO_VIP;
if ($result_prod = $conn->query($sql_prod)) {
    if ($prod_data = $result_prod->fetch_assoc()) {
        $precio_vip = $prod_data['precio'];
        $descripcion_membresia = $prod_data['nombre'];
    }
}

// 3. Revisar si el producto VIP ya está en el carrito
$en_carrito = false;
if (isset($_SESSION['carrito']) && isset($_SESSION['carrito'][ID_PRODUCTO_VIP])) {
    $en_carrito = true;
}
?>

<div class="container">
    <h1><i class="bi bi-patch-check-fill" style="color: #0d6efd;"></i> Conviértete en VIP</h1>
    <p>Únete a nuestro programa de membresía VIP y obtén acceso a beneficios exclusivos.</p>

    <div class="card" style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
        <?php if (isset($_SESSION['is_vip']) && $_SESSION['is_vip'] === true): ?>
            <h3 style="color: #198754; margin-top: 0;">¡Ya eres VIP!</h3>
            <p style="margin-bottom: 0;">Actualmente disfrutas de todos los beneficios.</p>
        <?php elseif ($en_carrito): ?>
            <h3 style="color: #0d6efd; margin-top: 0;">¡Ya casi!</h3>
            <p style="margin-bottom: 0;">La membresía VIP ya está en tu carrito. Ve a pagar para activar tus beneficios.</p>
            <a href="carrito.php" class="btn-submit" style="background-color: #198754; color: white; text-decoration: none; padding: 10px 15px; border-radius: 5px; display: inline-block; margin-top: 10px;">
                <i class="bi bi-cart-fill"></i> Ir al Carrito
            </a>
        <?php else: ?>
            <h3 style="margin-top: 0;">Beneficios Principales:</h3>
            <ul>
                <li><strong>15% de descuento EXTRA</strong> en todas tus compras.</li>
                <li>Acceso prioritario a preventas de hardware.</li>
                <li>Soporte técnico premium.</li>
            </ul>
        <?php endif; ?>
    </div>


    <?php if (!isset($_SESSION['is_vip']) || $_SESSION['is_vip'] !== true): ?>
        <?php if (!$en_carrito): ?>
            <h2>Comprar Membresía (1 Año)</h2>
            <p>
                Paga una sola vez y disfruta de los beneficios durante 12 meses.
                Precio: <strong>$<?php echo number_format($precio_vip, 0, ',', '.'); ?> CLP</strong>
            </p>

            <form action="carrito.php" method="POST">
                <input type="hidden" name="id_producto" value="<?php echo ID_PRODUCTO_VIP; ?>">
                <input type="hidden" name="cantidad" value="1">

                <button type="submit" style="font-size: 1.2em; width: 100%;">
                    <i class="bi bi-cart-plus-fill"></i> Añadir al carrito y Comprar
                </button>
            </form>
        <?php endif; ?>
    <?php endif; ?>

    <a href="dashboard.php" class="btn-volver" style="width: 100%; box-sizing: border-box;">Volver a mi Panel</a>
</div>

<?php 
// --- SE LLAMA AL FOOTER ---
require 'includes/footer.php'; 
?>
<?php $conn->close(); ?>