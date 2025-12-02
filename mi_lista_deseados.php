<?php
session_start();
require '/var/www/config/config.php';

// Seguridad: Solo usuarios logueados pueden ver su lista
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// Obtener los productos de la lista de deseados del usuario actual
$id_usuario = $_SESSION['id'];
$productos_favoritos = [];

$sql = "SELECT p.id_producto, p.nombre, p.precio, p.imagen_principal 
        FROM favoritos f
        JOIN producto p ON f.id_producto = p.id_producto
        WHERE f.id_usuario = ? AND p.activo = 1";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    $productos_favoritos = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
$conn->close();
?>
<?php
// --- 1. DEFINES LAS VARIABLES PARA EL HEAD ---
$titulo_pagina = ($vista_actual == 'detalle') ? htmlspecialchars($producto['nombre']) : 'Mis Ventas - Vendedor';
$css_pagina_especifica = "css/LDeseados.css"; 
// --- 2. LLAMAS AL HEAD ---
require 'includes/head.php'; // o head.php, el que estés usando
?>
<body>
    <header class="header">
        <div class="logo">
            <a href="index.php"><img class="navbar-logo" src="images/Favicon.png" alt=""></a> 
        </div>
        <div class="header-icons" style="margin-left: auto;">
             <a href="dashboard.php" class="user-icon" title="Mi Panel"><i class="fas fa-user"></i></a>
            <a href="carrito.php" class="cart-icon" title="Carrito"><i class="fas fa-shopping-cart"></i></a>
        </div>
    </header>

    <div class="wishlist-container">
        <div class="wishlist-header">
            <h1>Mi Lista de Deseados</h1>
            <p>Aquí encontrarás los productos que has guardado.</p>
        </div>

        <?php if (empty($productos_favoritos)): ?>
            <div style="text-align:center; padding: 50px;">
                <h2>Tu lista de deseados está vacía.</h2>
                <p>Explora nuestro <a href="catalogo.php">catálogo</a> y guarda los productos que te interesan.</p>
            </div>
        <?php else: ?>
            <div class="productos-grid-container">
                <?php foreach ($productos_favoritos as $prod): ?>
                    <div class="product-card-style">
                        <a href="catalogo.php?id=<?php echo $prod['id_producto']; ?>" class="product-link">
                            <img src="uploads/<?php echo htmlspecialchars($prod['imagen_principal'] ?? 'default.png'); ?>" alt="<?php echo htmlspecialchars($prod['nombre']); ?>">
                            <div class="product-info-wrapper">
                                <h3 class="product-name"><?php echo htmlspecialchars($prod['nombre']); ?></h3>
                            </div>
                        </a>
                        <div class="product-pricing">
                            <p class="product-price-new">$<?php echo number_format($prod['precio'], 0, ',', '.'); ?></p>
                        </div>
                        <form action="carrito.php" method="POST" class="add-to-cart-form">
                            <input type="hidden" name="id_producto" value="<?php echo $prod['id_producto']; ?>">
                            <button type="submit" class="add-to-cart-btn"><i class="fas fa-shopping-cart"></i> Agregar al Carrito</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
<footer class="footer">
<?php
require 'includes/footer.php';
?>