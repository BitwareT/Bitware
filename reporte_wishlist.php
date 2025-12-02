<?php
session_start();
require '/var/www/config/config.php';

// --- 1. DEFINES LAS VARIABLES PARA EL HEAD ---
// (Estas líneas son las que faltaban)
$titulo_pagina = ($vista_actual == 'detalle') ? htmlspecialchars($producto['nombre']) : 'Reporte de Wishlist';
$css_pagina_especifica = "css/RWishlist.css"; // <-- ¡ESTA ES LA LÍNEA CLAVE QUE FALTA!
$body_atributos = 'data-stock="' . htmlspecialchars($producto['stock'] ?? '99') . '"';
require 'includes/head.php';

// 1. SEGURIDAD: Solo para administradores
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["permisos"] !== 'A') {
    header("location: login.php");
    exit;
}

// 2. OBTENER LOS DATOS DE LA BASE DE DATOS
$productos_deseados = [];
$sql = "SELECT 
            p.nombre,
            p.imagen_principal,
            p.stock,
            COUNT(f.id_producto) AS total_favoritos
        FROM favoritos f
        JOIN producto p ON f.id_producto = p.id_producto
        GROUP BY f.id_producto
        ORDER BY total_favoritos DESC
        LIMIT 10"; // Mostramos el Top 10

$resultado = $conn->query($sql);
if ($resultado) {
    $productos_deseados = $resultado->fetch_all(MYSQLI_ASSOC);
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<body>
    <div class="container">
        <a href="dashboard.php" style="text-decoration: none; color: #007bff;">&larr; Volver al Panel de Administrador</a>
        <h1>Top 10 Productos Más Deseados</h1>

        <div class="wishlist-list">
            <?php if (empty($productos_deseados)): ?>
                <div class="empty-state">
                    <h2>Aún no hay productos en ninguna lista de deseados.</h2>
                </div>
            <?php else: ?>
                <?php $rank = 1; ?>
                <?php foreach ($productos_deseados as $producto): ?>
                    <div class="wishlist-item">
                        <div class="rank rank-<?php echo $rank; ?>">#<?php echo $rank; ?></div>
                        <div class="product-image">
                            <img src="uploads/<?php echo htmlspecialchars($producto['imagen_principal'] ?? 'default.png'); ?>" alt="<?php echo htmlspecialchars($producto['nombre']); ?>">
                        </div>
                        <div class="product-details">
                            <p class="name"><?php echo htmlspecialchars($producto['nombre']); ?></p>
                            <p class="stock">Stock actual: <?php echo $producto['stock']; ?></p>
                        </div>
                        <div class="wishlist-count">
                            <i class="fas fa-heart"></i>
                            <span><?php echo $producto['total_favoritos']; ?></span>
                        </div>
                    </div>
                    <?php $rank++; ?>
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