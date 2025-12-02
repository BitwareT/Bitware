<?php
session_start();
require '/var/www/config/config.php';
require_once "check_activity.php";

// --- !! ID_PRODUCTO_VIP (MOVIDO ARRIBA) !! ---
// (Reemplaza 999 por tu ID real)
define('ID_PRODUCTO_VIP', 999); 

// ===================================================================
// --- !! INICIO: TODA LA LÓGICA DE ACCIONES (MOVIDA ARRIBA) !! ---
// (Este bloque ahora se ejecuta ANTES de cualquier HTML)
// ===================================================================

// --- LÓGICA DE CUPÓN ---
$mensaje_cupon = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['codigo_cupon'])) {
    $codigo_cupon = strtoupper(trim($_POST['codigo_cupon']));
    $sql = "SELECT * FROM cupones WHERE codigo = ? AND activo = 1 AND (fecha_expiracion IS NULL OR fecha_expiracion >= CURDATE())";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $codigo_cupon);
        $stmt->execute();
        $resultado = $stmt->get_result();
        if ($cupon = $resultado->fetch_assoc()) {
            $_SESSION['cupon'] = $cupon;
            $mensaje_cupon = "<p style='color: green;'>¡Cupón aplicado correctamente!</p>";
        } else {
            unset($_SESSION['cupon']);
            $mensaje_cupon = "<p style='color: red;'>El cupón no es válido o ha expirado.</p>";
        }
        $stmt->close();
    }
}
if (isset($_GET['quitar_cupon'])) {
    unset($_SESSION['cupon']);
    header('Location: carrito.php');
    exit;
}

// --- ACCIÓN 1: AGREGAR PRODUCTO AL CARRITO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_producto'])) {
    $id_producto = (int)$_POST['id_producto'];
    $cantidad_a_agregar = isset($_POST['cantidad']) ? (int)$_POST['cantidad'] : 1;
    
    if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
        $_SESSION['accion_pendiente'] = 'agregar_al_carrito';
        $_SESSION['producto_pendiente'] = $id_producto; 
        $_SESSION['mensaje_error'] = "Debes iniciar sesión para agregar productos a tu carrito.";
        header('Location: login.php');
        exit();
    }
    
    $sql_stock = "SELECT stock FROM producto WHERE id_producto = ?";
    $stmt_stock = $conn->prepare($sql_stock);
    $stmt_stock->bind_param("i", $id_producto);
    $stmt_stock->execute();
    $result_stock = $stmt_stock->get_result();
    $stock_disponible = 0;
    if ($row = $result_stock->fetch_assoc()) {
        $stock_disponible = $row['stock'];
    }
    $stmt_stock->close();
    
    $cantidad_en_carrito = $_SESSION['carrito'][$id_producto] ?? 0;
    $cantidad_total_deseada = $cantidad_en_carrito + $cantidad_a_agregar;

    if ($cantidad_total_deseada > $stock_disponible) {
        $_SESSION['mensaje_error_stock'] = "No puedes agregar más de $stock_disponible unidades de este producto.";
        if ($id_producto == ID_PRODUCTO_VIP) {
             header('Location: hacerse_vip.php');
        } else {
             header('Location: ' . $_SERVER['HTTP_REFERER']);
        }
        exit();
    }

    if (!isset($_SESSION['carrito'])) {
        $_SESSION['carrito'] = [];
    }
    if (isset($_SESSION['carrito'][$id_producto])) {
        $_SESSION['carrito'][$id_producto] += $cantidad_a_agregar;
    } else {
        $_SESSION['carrito'][$id_producto] = $cantidad_a_agregar;
    }
    
    $_SESSION['mensaje_exito'] = "Producto agregado correctamente.";

    // --- Redirección corregida (AHORA SÍ FUNCIONA) ---
    if ($id_producto == ID_PRODUCTO_VIP) {
        header('Location: carrito.php');
    } else {
        header('Location: catalogo.php');
    }
    exit();
}

// --- ACCIÓN 2: AJUSTAR CANTIDAD ---
if (isset($_GET['ajustar']) && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id_producto_ajustar = (int)$_GET['id'];
    if (isset($_SESSION['carrito'][$id_producto_ajustar])) {
        if ($_GET['ajustar'] == 'mas') {
            $sql_stock = "SELECT stock FROM producto WHERE id_producto = ?";
            $stmt_stock = $conn->prepare($sql_stock);
            $stmt_stock->bind_param("i", $id_producto_ajustar);
            $stmt_stock->execute();
            $result_stock = $stmt_stock->get_result();
            $stock_disponible = 0;
            if ($row = $result_stock->fetch_assoc()) {
                $stock_disponible = $row['stock'];
            }
            $stmt_stock->close();
            $cantidad_en_carrito = $_SESSION['carrito'][$id_producto_ajustar];
            if (($cantidad_en_carrito + 1) > $stock_disponible) {
                $_SESSION['mensaje_error_stock_carrito'] = "No puedes agregar más de $stock_disponible unidades.";
            } else {
                $_SESSION['carrito'][$id_producto_ajustar]++;
            }
        } elseif ($_GET['ajustar'] == 'menos') {
            $_SESSION['carrito'][$id_producto_ajustar]--;
            if ($_SESSION['carrito'][$id_producto_ajustar] <= 0) {
                unset($_SESSION['carrito'][$id_producto_ajustar]);
            }
        }
    }
    header('Location: carrito.php'); 
    exit();
}

// --- ACCIÓN 3: ELIMINAR UN PRODUCTO ---
if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
    $id_producto_a_eliminar = (int)$_GET['eliminar'];
    if (isset($_SESSION['carrito']) && isset($_SESSION['carrito'][$id_producto_a_eliminar])) {
        unset($_SESSION['carrito'][$id_producto_a_eliminar]);
    }
    header('Location: carrito.php');
    exit();
}
// ===================================================================
// --- !! FIN: LÓGICA DE ACCIONES !! ---
// ===================================================================


// --- 1. DEFINES LAS VARIABLES PARA EL HEAD ---
// (Ahora esto se ejecuta DESPUÉS de las redirecciones)
$titulo_pagina = 'Carrito de Compras - Bitware';
$css_pagina_especifica = "css/carrito.css"; 
require 'includes/head.php'; // <-- EL HTML EMPIEZA AQUÍ


// --- CÁLCULO DE DATOS Y VISUALIZACIÓN ---
// (Esta lógica se queda aquí, ya que solo prepara datos para el HTML)
$productos_en_carrito = [];
$subtotal_productos = 0.00; 

if (isset($_SESSION['carrito']) && !empty($_SESSION['carrito'])) {
    $ids_productos = implode(',', array_keys($_SESSION['carrito']));
    $sql = "SELECT id_producto, nombre, precio, imagen_principal FROM producto WHERE id_producto IN ($ids_productos)";
    $resultado = mysqli_query($conn, $sql);
    if ($resultado) {
        $productos_db = mysqli_fetch_all($resultado, MYSQLI_ASSOC);
        foreach ($productos_db as $producto) {
            $id = $producto['id_producto'];
            $cantidad = $_SESSION['carrito'][$id];
            $productos_en_carrito[] = [
                'id' => $id, 'nombre' => $producto['nombre'], 'precio' => $producto['precio'],
                'imagen' => $producto['imagen_principal'], 'cantidad' => $cantidad, 
                'subtotal_item' => $producto['precio'] * $cantidad
            ];
            $subtotal_productos += $producto['precio'] * $cantidad;
        }
    }
}

$descuento_cupon = 0;
if (isset($_SESSION['cupon'])) {
    $cupon_aplicado = $_SESSION['cupon'];
    if ($cupon_aplicado['tipo_descuento'] == 'porcentaje') {
        $descuento_cupon = $subtotal_productos * ($cupon_aplicado['valor'] / 100);
    } else { 
        $descuento_cupon = $cupon_aplicado['valor'];
    }
}
$subtotal_despues_cupon = $subtotal_productos - $descuento_cupon;

$descuento_vip = 0;
$porcentaje_vip = 15; // <-- Tu descuento VIP

if (isset($_SESSION['is_vip']) && $_SESSION['is_vip'] === true) {
    $descuento_vip = $subtotal_despues_cupon * ($porcentaje_vip / 100);
}

$total_final = $subtotal_despues_cupon - $descuento_vip;

$_SESSION['subtotal_original'] = $subtotal_productos; 
$_SESSION['subtotal_productos'] = $total_final; 
$numero_productos = array_sum($_SESSION['carrito'] ?? []);
?>

<header class="header">
        <div class="logo">
            <a href="index.php"><img class="navbar-logo" src="images/Favicon.png" alt="" ></a> 
        </div>
        <form action="catalogo.php" method="GET" class="search-bar">
            <button type="submit" style="border:none; background:none; padding:0; cursor:pointer;"><i class="bi bi-search"></i></button>
            <input type="text" name="search" placeholder="Buscar componentes...">
        </form>
        <div class="header-icons">
            <?php $user_link = (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) ? "dashboard.php" : "login.php"; ?>
            <a href="<?php echo $user_link; ?>" class="user-icon" title="Mi Cuenta"><i class="bi bi-person-fill"></i></a>
            <a href="carrito.php" class="cart-icon" title="Carrito de Compras">
                <i class="bi bi-cart-fill"></i>
                <span class="cart-count"><?php echo $numero_productos; ?></span>
            </a>
        </div>
    </header>

    <main class="carrito-main-wrapper">
        <a href="catalogo.php" class="continuar-comprando-link"><i class="bi bi-arrow-left"></i> Continuar Comprando</a>
        <h1 class="carrito-title">Carrito de Compras</h1>
        
        <?php 
        if (isset($_SESSION['mensaje_error_stock_carrito'])): 
        ?>
            <div style="color: red; background: #ffe6e6; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                <?php echo htmlspecialchars($_SESSION['mensaje_error_stock_carrito']); ?>
            </div>
        <?php 
            unset($_SESSION['mensaje_error_stock_carrito']); 
        endif; 
        ?>
        
        <p class="carrito-product-count"><?php echo $numero_productos; ?> productos en tu carrito</p>

        <?php if (empty($productos_en_carrito)): ?>
            <div class="carrito-vacio">
                <h2>Tu carrito está vacío.</h2>
                <p>¡Añade productos desde nuestro <a href="catalogo.php">catálogo</a>!</p>
            </div>
        <?php else: ?>
            <div class="carrito-grid-container">
                <div class="productos-lista-wrapper">
                    <?php foreach ($productos_en_carrito as $item): ?>
                        <div class="carrito-item-card">
                            <div class="item-info-left">
                                <img src="uploads/<?php echo htmlspecialchars($item['imagen']); ?>" alt="<?php echo htmlspecialchars($item['nombre']); ?>" class="item-image">
                                <div class="item-details">
                                    <h3 class="item-name"><?php echo htmlspecialchars($item['nombre']); ?></h3>
                                    <p class="item-stock">En stock</p>
                                    <div class="item-quantity-control">
                                        <a href="carrito.php?ajustar=menos&id=<?php echo $item['id']; ?>" class="qty-btn">-</a>
                                        <span class="qty-display"><?php echo $item['cantidad']; ?></span>
                                        <a href="carrito.php?ajustar=mas&id=<?php echo $item['id']; ?>" class="qty-btn">+</a>
                                    </div>
                                </div>
                            </div>
                            <div class="item-info-right">
                                <a href="carrito.php?eliminar=<?php echo $item['id']; ?>" class="item-delete-btn" title="Eliminar producto"><i class="bi bi-trash-fill"></i></a>
                                <p class="item-price-current">$<?php echo number_format($item['subtotal_item'], 0, ',', '.'); ?></p>
                                <p class="item-price-unit">$<?php echo number_format($item['precio'], 0, ',', '.'); ?> c/u</p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="resumen-compra-card">
                    <h2>Resumen de Compra</h2>
                    <div class="resumen-row">
                        <span>Subtotal</span>
                        <span>$<?php echo number_format($subtotal_productos, 0, ',', '.'); ?></span>
                    </div>
                    <hr>
                    
                    <?php if (isset($_SESSION['cupon'])): ?>
                        <div class="resumen-row">
                            <span>Descuento (<?php echo htmlspecialchars($_SESSION['cupon']['codigo']); ?>)</span>
                            <span style="color: green;">-$<?php echo number_format($descuento_cupon, 0, ',', '.'); ?></span>
                        </div>
                        <a href="carrito.php?quitar_cupon=1" style="font-size: 0.8em; color: red; text-decoration: underline;">Quitar cupón</a>
                    <?php else: ?>
                        <form action="carrito.php" method="POST" class="form-cupon">
                            <label for="codigo_cupon" style="font-weight: bold; margin-bottom: 5px; display: block;">¿Tienes un cupón?</label>
                            <div style="display: flex;">
                                <input type="text" name="codigo_cupon" placeholder="Ingresa tu código" style="flex-grow: 1; padding: 8px; border: 1px solid #ccc; border-radius: 4px 0 0 4px;">
                                <button type="submit" style="padding: 8px 12px; border: none; background: #555; color: white; border-radius: 0 4px 4px 0; cursor: pointer;">Aplicar</button>
                            </div>
                            <?php echo $mensaje_cupon; ?>
                        </form>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['is_vip']) && $_SESSION['is_vip'] === true && $descuento_vip > 0): ?>
                        <div class="resumen-row">
                            <span>Descuento VIP (<?php echo $porcentaje_vip; ?>%)</span>
                            <span style="color: #0d6efd; font-weight: bold;">-$<?php echo number_format($descuento_vip, 0, ',', '.'); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <hr>
                    <div class="resumen-row">
                        <span>Envío</span>
                        <span>Por calcular</span>
                    </div>
                    <hr class="resumen-divider">
                    <div class="resumen-row total">
                        <span class="label">Total Estimado</span>
                        <span class="value total-value">$<?php echo number_format($total_final, 0, ',', '.'); ?></span>
                    </div>
                    
                    <a href="checkout.php" class="btn-checkout">Ir a Pagar</a>
                    <a href="catalogo.php" class="btn-continue">Continuar Comprando</a>
                </div>
            </div>
        <?php endif; ?>
    </main>

<?php 
require 'includes/footer.php'; 
?>
<?php $conn->close(); ?>