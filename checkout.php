<?php
session_start();
require '/var/www/config/config.php';
require_once "check_activity.php";

// --- !! 1. DEFINIR ID DEL PRODUCTO VIP Y VERIFICAR CARRITO !! ---
// (Reemplaza '999' con el ID real de tu producto "Membresía VIP")
define('ID_PRODUCTO_VIP', 999); 

$solo_vip_en_carrito = false;
if (!empty($_SESSION['carrito'])) {
    $keys = array_keys($_SESSION['carrito']);
    // Es "solo VIP" si hay 1 producto en el carrito Y ese producto es el VIP
    if (count($keys) == 1 && $keys[0] == ID_PRODUCTO_VIP) {
        $solo_vip_en_carrito = true;
    }
}
// --- !! FIN DE LA VERIFICACIÓN !! ---


// 2. SEGURIDAD: Redirigir si no está logueado o el carrito está vacío
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["id"]) || empty($_SESSION['carrito'])) {
    header("location: login.php");
    exit;
}

$id_usuario = $_SESSION["id"];
$direccion_actual = $region_actual = $telefono_actual = $rut_actual = '';

// 3. OBTENER DATOS (Solo si no es un pedido digital)
if (!$solo_vip_en_carrito) {
    $sql_user = "SELECT direccion, region, telefono, rut FROM usuario WHERE id_usuario = ?";
    if ($stmt_user = $conn->prepare($sql_user)) {
        $stmt_user->bind_param("i", $id_usuario);
        $stmt_user->execute();
        $result_user = $stmt_user->get_result();
        if ($user = $result_user->fetch_assoc()) {
            $direccion_actual = $user['direccion'];
            $region_actual = $user['region'];
            $telefono_actual = $user['telefono'];
            $rut_actual = $user['rut'];
        }
        $stmt_user->close();
    }
}

// 4. OBTENER PRODUCTOS DEL CARRITO (Sin cambios)
$productos_en_carrito = [];
$subtotal = $_SESSION['subtotal_productos'] ?? 0;
if ($subtotal > 0 && !empty($_SESSION['carrito'])) {
    $ids_productos = implode(',', array_keys($_SESSION['carrito']));
    $sql_prods = "SELECT id_producto, nombre, precio, imagen_principal FROM producto WHERE id_producto IN ($ids_productos)";
    $resultado_prods = mysqli_query($conn, $sql_prods);
    if ($resultado_prods) {
        $productos_db = mysqli_fetch_all($resultado_prods, MYSQLI_ASSOC);
        foreach ($productos_db as $producto) {
            $productos_en_carrito[] = [
                'nombre' => $producto['nombre'],
                'cantidad' => $_SESSION['carrito'][$producto['id_producto']],
                'precio_total' => $producto['precio'] * $_SESSION['carrito'][$producto['id_producto']],
                'imagen' => $producto['imagen_principal']
            ];
        }
    }
}

// 5. LÓGICA DE MÉTODOS DE ENVÍO (MODIFICADA)
$metodos_envio = [
    'normal' => ['nombre' => 'Envío Normal (3-5 días hábiles)', 'costo' => ($subtotal > 50000) ? 0 : 4990],
    'express' => ['nombre' => 'Envío Express (1-2 días hábiles)', 'costo' => 9990]
];

if ($solo_vip_en_carrito) {
    // Si es solo VIP, el envío es digital y gratis
    $metodo_seleccionado = 'digital';
    $costo_envio_actual = 0;
} else {
    $metodo_seleccionado = $_SESSION['metodo_envio_seleccionado'] ?? 'normal';
    $costo_envio_actual = $metodos_envio[$metodo_seleccionado]['costo'];
}

// 6. PROCESAR FORMULARIO (MODIFICADO)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    if ($solo_vip_en_carrito) {
        // Es un producto digital, no necesitamos dirección.
        $_SESSION['nombre_envio_pedido'] = $_SESSION['nombre'];
        $_SESSION['metodo_envio_seleccionado'] = 'digital';
    } else {
        // Es un producto físico, obtenemos los datos del formulario
        $direccion = trim($_POST['direccion']);
        $region = trim($_POST['region']);
        $telefono = trim($_POST['telefono']);
        $rut = trim($_POST['rut']);
        $_SESSION['nombre_envio_pedido'] = trim($_POST['nombre_envio']);
        $_SESSION['metodo_envio_seleccionado'] = $_POST['metodo_envio'] ?? 'normal';
        
        // Actualizamos la dirección del usuario
        $sql_update = "UPDATE usuario SET direccion = ?, region = ?, telefono = ?, rut = ? WHERE id_usuario = ?";
        if ($stmt_update = $conn->prepare($sql_update)) {
            $stmt_update->bind_param("ssssi", $direccion, $region, $telefono, $rut, $id_usuario);
            $stmt_update->execute();
            $stmt_update->close();
        }
    }
    
    header("location: realizar_compra.php");
    exit;
}

$total_final_display = $subtotal + $costo_envio_actual;

// --- 7. DEFINIR VARIABLES PARA EL HEAD (MOVIDO AL FINAL) ---
$titulo_pagina = 'Información de Envío - Bitware';
$css_pagina_especifica = "css/checkout.css"; 
require 'includes/head.php';
?>

<header class="header" style="padding: 10px 40px; background: white; border-bottom: 1px solid #ddd;">
    <a href="index.php"><img class="navbar-logo" src="images/Favicon.png" alt="Bitware" style="height: 50px;"></a>
</header>

<main class="checkout-wrapper <?php if ($solo_vip_en_carrito) echo 'digital-only'; ?>">
    <div class="checkout-form-section">
        <a href="carrito.php" style="text-decoration: none; color: #007bff; margin-bottom: 20px; display: inline-block;"><i class="bi bi-arrow-left"></i> Volver al Carrito</a>
        
        <form id="checkout-form" action="checkout.php" method="POST">

            <?php if (!$solo_vip_en_carrito): ?>
            
                <h2>Información de Contacto y Envío</h2>
                <div class="form-group">
                    <label for="rut">RUT</label>
                    <input type="text" id="rut" name="rut" value="<?php echo htmlspecialchars($rut_actual); ?>" required pattern="[0-9]{7,8}-[0-9Kk]{1}" title="RUT inválido (ej: 12345678-K)" maxlength="10">
                </div>
                <div class="form-group">
                    <label for="telefono">Teléfono (9 dígitos)</label>
                    <input type="tel" id="telefono" name="telefono" value="<?php echo htmlspecialchars($telefono_actual); ?>" required pattern="[0-9]{9}" title="Debe contener 9 dígitos (ej: 912345678)" maxlength="9">
                </div>

                <div class="form-group">
                    <label for="nombre_envio">Nombre del Destinatario</label>
                    <input type="text" id="nombre_envio" name="nombre_envio" value="<?php echo htmlspecialchars($_SESSION['nombre'] ?? ''); ?>" required>
                </div>
                
                <h2>Dirección de Despacho</h2>
                <div class="form-group">
                    <label for="direccion">Dirección (Calle y Número)</label>
                    <input type="text" id="direccion" name="direccion" value="<?php echo htmlspecialchars($direccion_actual); ?>" required>
                </div>
                <div class="form-group">
                    <label for="region">Región</label>
                    <input type="text" id="region" name="region" value="<?php echo htmlspecialchars($region_actual); ?>" required>
                </div>
                
                <div class="shipping-options">
                    <h2>Método de Envío</h2>
                    <?php foreach ($metodos_envio as $key => $metodo): ?>
                    <div class="shipping-option <?php echo ($metodo_seleccionado == $key) ? 'selected' : ''; ?>" onclick="document.getElementById('envio-<?php echo $key; ?>').click();">
                        <input type="radio" name="metodo_envio" value="<?php echo $key; ?>" id="envio-<?php echo $key; ?>" <?php echo ($metodo_seleccionado == $key) ? 'checked' : ''; ?> data-costo="<?php echo $metodo['costo']; ?>">
                        <label for="envio-<?php echo $key; ?>" class="shipping-info">
                            <span class="name"><?php echo $metodo['nombre']; ?></span>
                        </label>
                        <span class="shipping-cost"><?php echo ($metodo['costo'] == 0) ? 'Gratis' : '$' . number_format($metodo['costo']); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

            <?php else: ?>
                <h2>Confirmar Compra</h2>
                <p>Estás a punto de comprar un producto digital. No se requiere dirección de envío.</p>
            <?php endif; ?>
            <button type="submit" class="btn-submit-checkout" style="margin-top: 20px;">
                <?php echo $solo_vip_en_carrito ? 'Continuar al Pago' : 'Guardar y Continuar al Pago'; ?>
            </button>
        </form>
    </div>

    <div class="order-summary-section">
        <h2>Resumen de Compra</h2>
            <div class="summary-product-list">
                <?php foreach ($productos_en_carrito as $item): ?>
                <div class="summary-product-item">
                    <img src="uploads/<?php echo htmlspecialchars($item['imagen']); ?>" alt="<?php echo htmlspecialchars($item['nombre']); ?>">
                    <div class="summary-product-info">
                        <p class="name"><?php echo htmlspecialchars($item['nombre']); ?></p>
                        <p class="qty">Cantidad: <?php echo $item['cantidad']; ?></p>
                    </div>
                    <span class="summary-product-price">$<?php echo number_format($item['precio_total'], 0, ',', '.'); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="summary-totals">
                <div class="resumen-row">
                    <span>Subtotal</span>
                    <span id="summary-subtotal">$<?php echo number_format($subtotal, 0, ',', '.'); ?></span>
                </div>
                <div class="resumen-row">
                    <span>Envío</span>
                    <span id="summary-shipping" class="<?php echo ($costo_envio_actual == 0) ? 'gratis' : ''; ?>">
                        <?php echo ($costo_envio_actual == 0) ? 'Gratis' : '$' . number_format($costo_envio_actual); ?>
                    </span>
                </div>
                <div class="resumen-row total">
                    <span>Total</span>
                    <span id="summary-total">$<?php echo number_format($total_final_display, 0, ',', '.'); ?></span>
                </div>
            </div>
    </div>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // (El JavaScript no necesita cambios, ya que si $solo_vip_en_carrito es true,
        // el formulario de envío no existe y este script no se ejecutará)
        const subtotal = <?php echo $subtotal; ?>;
        const shippingOptions = document.querySelectorAll('input[name="metodo_envio"]');
        
        shippingOptions.forEach(radio => {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.shipping-option').forEach(opt => opt.classList.remove('selected'));
                this.parentElement.classList.add('selected');
                const shippingCost = parseInt(this.dataset.costo);
                const total = subtotal + shippingCost;
                const shippingEl = document.getElementById('summary-shipping');
                shippingEl.textContent = shippingCost === 0 ? 'Gratis' : '$' + shippingCost.toLocaleString('es-CL');
                shippingEl.className = shippingCost === 0 ? 'gratis' : '';
                document.getElementById('summary-total').textContent = '$' + total.toLocaleString('es-CL');
            });
        });
    });
</script>

<?php 
// --- SE LLAMA AL FOOTER ---
require 'includes/footer.php'; 
?>
<?php $conn->close(); ?>