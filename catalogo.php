<?php
session_start();
require '/var/www/config/config.php';
require_once "check_activity.php";

// --- !! DEFINICIÓN DE CONSTANTE VIP !! ---
define('ID_PRODUCTO_VIP', 999); 
// --- !! FIN DEL AÑADIDO !! ---

$mensaje_reseña = '';

// --- FUNCIÓN DE BINDING SEGURO REUTILIZABLE ---
// Esta función resuelve el problema de "could not be passed by reference"
function bind_parameters_safely($stmt, $types, &$params) {
    if (empty($params)) {
        return;
    }
    
    // El primer argumento debe ser el string de tipos
    $bind_args = array($types);
    
    // Crea las referencias para cada elemento de $params
    foreach ($params as &$param) {
        $bind_args[] = &$param;
    }

    // Usamos ReflectionClass para llamar a bind_param de forma segura
    $stmt_ref = new ReflectionClass('mysqli_stmt');
    $bind_method = $stmt_ref->getMethod('bind_param');
    $bind_method->invokeArgs($stmt, $bind_args);
}
// --- FIN FUNCIÓN DE BINDING ---


// --- LÓGICA PARA PROCESAR EL FORMULARIO DE RESEÑA ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['accion']) && $_POST['accion'] == 'dejar_reseña') {
    if (!isset($_SESSION['id'])) {
        header("location: login.php");
        exit;
    }

    $id_usuario = $_SESSION['id'];
    $id_producto = $_POST['id_producto'];
    $calificacion = $_POST['calificacion'];
    $titulo = trim($_POST['titulo']);
    $comentario = trim($_POST['comentario']);

    $sql_insert = "INSERT INTO reseñas (id_producto, id_usuario, calificacion, titulo, comentario) VALUES (?, ?, ?, ?, ?)";
    if ($stmt_insert = $conn->prepare($sql_insert)) {
        $stmt_insert->bind_param("iiiss", $id_producto, $id_usuario, $calificacion, $titulo, $comentario);
        if ($stmt_insert->execute()) {
            $_SESSION['mensaje_exito_reseña'] = "¡Gracias por tu reseña!";
        } else {
            $_SESSION['mensaje_error_reseña'] = "Hubo un error al guardar tu reseña.";
        }
        $stmt_insert->close();
        header("Location: catalogo.php?id=" . $id_producto);
        exit;
    }
}

// --- LÓGICA PARA FILTROS Y BÚSQUEDA ---
$search_term = $_GET['search'] ?? '';
$categoria_activa = $_GET['categoria'] ?? '';
$max_precio = $_GET['max_precio'] ?? 2000000;
$orden_actual = $_GET['orden'] ?? 'relevancia';

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $vista_actual = 'detalle';
} else {
    $vista_actual = 'catalogo';
}

// --- OBTENER LA LISTA DE FAVORITOS DEL USUARIO ---
$favoritos_usuario = [];
if (isset($_SESSION['id'])) {
    $sql_favs = "SELECT id_producto FROM favoritos WHERE id_usuario = ?";
    if ($stmt_favs = $conn->prepare($sql_favs)) {
        $stmt_favs->bind_param("i", $_SESSION['id']);
        $stmt_favs->execute();
        $result_favs = $stmt_favs->get_result();
        while($row_fav = $result_favs->fetch_assoc()){
            $favoritos_usuario[] = $row_fav['id_producto'];
        }
        $stmt_favs->close();
    }
}


// --- LÓGICA PARA LA VISTA DE CATÁLOGO (CORREGIDA) ---
if ($vista_actual == 'catalogo') {
    $sql = "SELECT p.id_producto, p.nombre, p.descripcion, p.precio, p.imagen_principal, m.nombre as marca_nombre, p.stock 
            FROM producto p
            LEFT JOIN marcas m ON p.id_marca = m.id_marca
            WHERE p.activo = 1";
    $params = [];
    $types = "";

    // Excluir siempre el producto VIP
    $sql .= " AND p.id_producto != ?";
    $params[] = ID_PRODUCTO_VIP;
    $types .= "i";

    if (!empty($search_term)) { $sql .= " AND p.nombre LIKE ?"; $params[] = "%" . $search_term . "%"; $types .= "s"; }
    if (!empty($categoria_activa)) { $sql .= " AND p.categoria = ?"; $params[] = $categoria_activa; $types .= "s"; }
    if (isset($_GET['max_precio'])) { $sql .= " AND p.precio <= ?"; $params[] = $max_precio; $types .= "i"; }
    
    switch ($orden_actual) {
        case 'precio_asc': $sql .= " ORDER BY p.precio ASC"; break;
        case 'precio_desc': $sql .= " ORDER BY p.precio DESC"; break;
        case 'nombre_asc': $sql .= " ORDER BY p.nombre ASC"; break;
        default: $sql .= " ORDER BY p.stock DESC, p.nombre ASC"; break;
    }
    
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        if (!empty($params)) {
            // USO DE LA FUNCIÓN SEGURA
            bind_parameters_safely($stmt, $types, $params);
        }

        $stmt->execute();
        $resultado = $stmt->get_result();
        $productos = mysqli_fetch_all($resultado, MYSQLI_ASSOC);
        $stmt->close();
    } else {
        error_log("Error al preparar la consulta de catálogo: " . $conn->error);
        die("Error de base de datos en catálogo.php. Consulta fallida.");
    }
} 

// --- LÓGICA PARA LA VISTA DE DETALLE (CORREGIDA) ---
else {
    $id_producto = (int)$_GET['id'];
    
    // 1. Obtener la información principal del producto
    $sql = "SELECT p.*, m.nombre as marca_nombre 
            FROM producto p
            LEFT JOIN marcas m ON p.id_marca = m.id_marca
            WHERE p.id_producto = ? AND p.activo = 1";
    
    // Usamos el objeto de conexión para preparar el statement
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $id_producto);
        $stmt->execute();
        $resultado = $stmt->get_result();
        $producto = $resultado->fetch_assoc();
        $stmt->close(); // Cerramos el statement inmediatamente

        if (!$producto) { header("Location: catalogo.php"); exit; }
    } else {
        die("Error al preparar la consulta de detalle: " . $conn->error);
    }
    
    // 2. Productos Similares
    $productos_similares = [];
    $categoria_actual_prod = $producto['categoria'];
    $sql_similares = "SELECT id_producto, nombre, precio, imagen_principal 
                      FROM producto 
                      WHERE categoria = ? 
                        AND id_producto != ? 
                        AND id_producto != ?
                        AND activo = 1 
                      LIMIT 4";
    if ($stmt_similares = $conn->prepare($sql_similares)) {
        $params_similares = [$categoria_actual_prod, $id_producto, ID_PRODUCTO_VIP];
        $types_similares = "sii";
        
        // USO DE LA FUNCIÓN SEGURA (LÍNEA 154)
        bind_parameters_safely($stmt_similares, $types_similares, $params_similares);
        
        $stmt_similares->execute();
        $resultado_similares = $stmt_similares->get_result();
        $productos_similares = $resultado_similares->fetch_all(MYSQLI_ASSOC);
        $stmt_similares->close();
    }


    $categorias_map = [
        'gpu' => 'Tarjetas Gráficas', 'cpu' => 'Procesadores', 'ram' => 'Memoria RAM',
        'placa' => 'Placas Madre', 'disco' => 'Almacenamiento', 'otros' => 'Otros'
    ];
    $nombre_categoria_legible = $categorias_map[$producto['categoria']] ?? ucfirst($producto['categoria']);

    // 3. OBTENER RESEÑAS Y VERIFICAR SI EL USUARIO PUEDE COMENTAR
    $reseñas = [];
    $calificacion_promedio = 0;
    $total_reseñas = 0;
    $usuario_ha_comprado = false;

    $sql_reseñas = "SELECT r.*, u.nombre as nombre_usuario FROM reseñas r JOIN usuario u ON r.id_usuario = u.id_usuario WHERE r.id_producto = ? AND r.aprobado = 1 ORDER BY r.fecha DESC";
    if ($stmt_reseñas = $conn->prepare($sql_reseñas)) {
        $stmt_reseñas->bind_param("i", $id_producto);
        $stmt_reseñas->execute();
        $resultado_reseñas = $stmt_reseñas->get_result();
        $reseñas = $resultado_reseñas->fetch_all(MYSQLI_ASSOC);
        $stmt_reseñas->close();
    }
    
    if (!empty($reseñas)) {
        $total_reseñas = count($reseñas);
        $suma_calificaciones = array_sum(array_column($reseñas, 'calificacion'));
        $calificacion_promedio = round($suma_calificaciones / $total_reseñas, 1);
    }

    // 4. Verificar Compra (Permiso para reseñar)
    if (isset($_SESSION['id'])) {
        $id_usuario_actual = $_SESSION['id'];
        $sql_check_compra = "SELECT COUNT(*) as total FROM pedidos p JOIN pedidos_productos pp ON p.id_pedido = pp.id_pedido WHERE p.id_usuario = ? AND pp.id_producto = ? AND p.estado IN ('Pagado', 'Enviado', 'Entregado')";
        if ($stmt_check = $conn->prepare($sql_check_compra)) {
            $params_compra = [$id_usuario_actual, $id_producto];
            $types_compra = "ii";
            
            // USO DE LA FUNCIÓN SEGURA (LÍNEA 163)
            bind_parameters_safely($stmt_check, $types_compra, $params_compra);
            
            $stmt_check->execute();
            $resultado_check = $stmt_check->get_result()->fetch_assoc();
            if ($resultado_check['total'] > 0) {
                $usuario_ha_comprado = true;
            }
            $stmt_check->close();
        }
    }
}
?>
<?php
// --- 1. DEFINES LAS VARIABLES PARA EL HEAD ---
$titulo_pagina = ($vista_actual == 'detalle') ? htmlspecialchars($producto['nombre']) : 'Catálogo de Productos';
$css_pagina_especifica = "css/catalogo.css"; 
$body_atributos = 'data-stock="' . htmlspecialchars($producto['stock'] ?? '99') . '"';

// --- 2. LLAMAS AL HEAD ---
require 'includes/head.php'; // o head.php, el que estés usando
?>

<header class="header">
    <div class="logo"><a href="index.php"><img class="navbar-logo" src="images/Favicon.png" alt=""></a></div>
    <form action="catalogo.php" method="GET" class="search-bar">
        
        <i class="bi bi-search"></i>
        
        <input type="text" name="search" placeholder="Buscar componentes..." value="<?php echo htmlspecialchars($search_term); ?>">
    </form>
    
    <div class="header-icons">
        <?php
            $user_link = (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) ? "dashboard.php" : "login.php";
        ?>

        <a href="<?php echo $user_link; ?>" class="user-icon" title="Mi Cuenta"><i class="bi bi-person-fill"></i></a>
        
        <a href="carrito.php" class="cart-icon" title="Carrito de Compras">
            <i class="bi bi-cart-fill"></i>
            <span class="cart-count"><?php echo array_sum($_SESSION['carrito'] ?? []); ?></span>
        </a>
    </div>
</header>

    <?php if ($vista_actual == 'catalogo'): ?>
        <div class="main-content-wrapper">
             <aside class="sidebar">
                <div class="category-section">
                    <h2>Categorías</h2>
                    <ul class="category-list">
                        <?php
                        $categorias = [
                            'gpu' => 'Tarjetas Gráficas', 'cpu' => 'Procesadores', 'ram' => 'Memoria RAM',
                            'placa' => 'Placas Madre', 'disco' => 'Almacenamiento', 'otros' => 'Otros'
                        ];
                        echo '<li class="' . (empty($categoria_activa) ? 'active' : '') . '"><a href="catalogo.php">Todas</a></li>';
                        foreach ($categorias as $key => $value) {
                            $isActive = ($categoria_activa === $key) ? 'active' : '';
                            echo "<li class=\"{$isActive}\"><a href=\"catalogo.php?categoria={$key}\">{$value}</a></li>";
                        }
                        ?>
                    </ul>
                </div>
                <div class="price-filter-section">
                    <h2>Rango de Precio</h2>
                    <div class="price-range-slider">
                        <input type="range" min="0" max="2000000" value="<?php echo htmlspecialchars($max_precio); ?>" step="10000" class="slider" id="priceRange">
                        <div class="price-labels">
                            <span>$0</span>
                            <span id="priceValue">$<?php echo number_format($max_precio, 0, ',', '.'); ?></span>
                        </div>
                    </div>
                </div>
            </aside>
            <main class="product-catalogue-main">
                <div class="catalogue-header-info">
                    <h1 class="catalogue-title">Componentes para PC</h1>
                    <div class="product-count"><?php echo count($productos); ?> productos disponibles</div>
                    <div class="sort-by">
                        <label for="sort">Ordenar por:</label>
                        <select id="sort">
                            <option value="relevancia" <?php if ($orden_actual == 'relevancia') echo 'selected'; ?>>Relevancia</option>
                            <option value="precio_asc" <?php if ($orden_actual == 'precio_asc') echo 'selected'; ?>>Precio: Menor a Mayor</option>
                            <option value="precio_desc" <?php if ($orden_actual == 'precio_desc') echo 'selected'; ?>>Precio: Mayor a Menor</option>
                            <option value="nombre_asc" <?php if ($orden_actual == 'nombre_asc') echo 'selected'; ?>>Nombre (A-Z)</option>
                        </select>
                    </div>
                </div>
                <?php if (isset($_SESSION['mensaje_exito'])): ?>
                    <div class="mensaje-exito"><?php echo htmlspecialchars($_SESSION['mensaje_exito']); unset($_SESSION['mensaje_exito']); ?></div>
                <?php endif; ?>
                <div class="productos-grid-container">
                    <?php if (empty($productos)): ?>
                        <p class="no-results">No se encontraron productos que coincidan con tus filtros.</p>
                    <?php else: ?>
                        <?php foreach ($productos as $prod): ?>
                            <div class="product-card-style">
                                <?php $es_favorito = in_array($prod['id_producto'], $favoritos_usuario); ?>
                                <button class="wishlist-btn <?php echo $es_favorito ? 'active' : ''; ?>" data-product-id="<?php echo $prod['id_producto']; ?>" title="Añadir a la lista de deseos">
                                    <i class="<?php echo $es_favorito ? 'fas' : 'far'; ?> fa-heart"></i>
                                </button>
                                
                                <a href="catalogo.php?id=<?php echo $prod['id_producto']; ?>" class="product-link">
                                    <img src="uploads/<?php echo htmlspecialchars($prod['imagen_principal'] ?? 'default.png'); ?>" alt="<?php echo htmlspecialchars($prod['nombre']); ?>">
                                    <div class="product-info-wrapper">
                                        <span class="product-brand"><?php echo htmlspecialchars($prod['marca_nombre'] ?? 'Sin Marca'); ?></span>
                                        <h3 class="product-name"><?php echo htmlspecialchars($prod['nombre']); ?></h3>
                                    </div>
                                </a>
                                <div class="product-pricing">
                                    <p class="product-price-new">$<?php echo number_format($prod['precio'], 0, ',', '.'); ?></p>
                                </div>
                                <?php if ($prod['stock'] > 0): ?>
                                    <form action="carrito.php" method="POST" class="add-to-cart-form">
                                        <input type="hidden" name="id_producto" value="<?php echo $prod['id_producto']; ?>">
                                        <button type="submit" class="add-to-cart-btn"><i class="fas fa-shopping-cart"></i> Agregar</button>
                                    </form>
                                <?php else: ?>
                                    <form class="add-to-cart-form">
                                        <button type="button" class="add-to-cart-btn" disabled style="background-color: #6c757d; border-color: #6c757d; cursor: not-allowed;">
                                            <i class="fas fa-ban"></i> Agotado
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    
    <?php elseif ($vista_actual == 'detalle'): ?>
        <div class="container mt-4">
            <a href="catalogo.php" class="back-link">← Volver al Catálogo</a>
            <nav class="breadcrumb-nav" aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Inicio</a></li>
                    <li class="breadcrumb-item"><a href="catalogo.php?categoria=<?php echo htmlspecialchars($producto['categoria']); ?>"><?php echo htmlspecialchars($nombre_categoria_legible); ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($producto['nombre']); ?></li>
                </ol>
            </nav>

            <div class="product-detail-layout">
                <div class="image-gallery">
                    <div class="main-image"><img id="mainProductImage" src="uploads/<?php echo htmlspecialchars($producto['imagen_principal']); ?>" alt="<?php echo htmlspecialchars($producto['nombre']); ?>"></div>
                    <div class="thumbnail-images">
                        <img src="uploads/<?php echo htmlspecialchars($producto['imagen_principal']); ?>" class="active" onclick="changeImage(this.src)">
                        <?php
                        $sql_galeria = "SELECT nombre_archivo FROM producto_imagenes WHERE id_producto = ?";
                        if ($stmt_galeria = $conn->prepare($sql_galeria)) {
                            $stmt_galeria->bind_param("i", $id_producto);
                            $stmt_galeria->execute();
                            $resultado_galeria = $stmt_galeria->get_result();
                            while($img = $resultado_galeria->fetch_assoc()) {
                                echo '<img src="uploads/' . htmlspecialchars($img['nombre_archivo']) . '" onclick="changeImage(this.src)">';
                            }
                            $stmt_galeria->close();
                        }
                        ?>
                    </div>
                </div>
                <div class="product-main-info">
                    <p class="product-brand"><?php echo htmlspecialchars($producto['marca_nombre'] ?? 'Marca Desconocida'); ?></p>
                    <h1 class="product-title"><?php echo htmlspecialchars($producto['nombre']); ?></h1>
                    
                    <?php $es_favorito = in_array($id_producto, $favoritos_usuario); ?>
                    <button class="wishlist-btn <?php echo $es_favorito ? 'active' : ''; ?>" data-product-id="<?php echo $id_producto; ?>">
                        <i class="<?php echo $es_favorito ? 'fas' : 'far'; ?> fa-heart"></i> 
                        <span class="wishlist-text"><?php echo $es_favorito ? 'En tu lista de deseos' : 'Añadir a la lista de deseos'; ?></span>
                    </button>
                    
                    <p class="product-price">$<?php echo number_format($producto['precio'], 0, ',', '.'); ?></p>
                    <p class="product-stock">Stock disponible: <?php echo $producto['stock']; ?> unidades</p>
                    <?php if ($producto['stock'] > 0): ?>
                        <form action="carrito.php" method="POST">
                            <input type="hidden" name="id_producto" value="<?php echo $id_producto; ?>">
                            <div class="quantity-selector"><label for="quantity">Cantidad:</label><button type="button" id="qty-minus">-</button><input type="number" id="quantity" name="cantidad" value="1" min="1" readonly><button type="button" id="qty-plus">+</button></div>
                            <button type="submit" class="boton-agregar"><i class="fas fa-shopping-cart"></i> Agregar al Carrito</button>
                        </form>
                    <?php else: ?>
                        <div class="quantity-selector" style="opacity: 0.5; margin-bottom: 10px;">
                            <label for="quantity">Cantidad:</label>
                            <button type="button" disabled>-</button><input type="number" value="0" min="0" disabled><button type="button" disabled>+</button>
                        </div>
                        <button type="button" class="boton-agregar" disabled style="background-color: #6c757d; border-color: #6c757d; cursor: not-allowed;">
                            <i class="fas fa-ban"></i> Producto Agotado
                        </button>
                    <?php endif; ?>
                    <div class="product-meta">
                        <span>SKU: #<?php echo str_pad($producto['id_producto'], 6, '0', STR_PAD_LEFT); ?></span>
                        <span>Categoría: <a href="catalogo.php?categoria=<?php echo htmlspecialchars($producto['categoria']); ?>"><?php echo htmlspecialchars($nombre_categoria_legible); ?></a></span>
                    </div>
                </div>
            </div>

            <div class="full-description">
                <h2>Descripción del Producto</h2>
                <p><?php echo nl2br(htmlspecialchars($producto['descripcion'])); ?></p>
            </div>

            <div class="reviews-section">
                <h2>Opiniones del Producto</h2>
                <?php if ($total_reseñas > 0): ?>
                <div class="reviews-summary">
                    <div class="stars-big"><?php for ($i = 1; $i <= 5; $i++) echo '<i class="' . (($i <= $calificacion_promedio) ? 'fas' : 'far') . ' fa-star"></i>'; ?></div>
                    <span><?php echo $calificacion_promedio; ?> de 5</span>
                    <small>(Basado en <?php echo $total_reseñas; ?> opiniones)</small>
                </div>
                <?php endif; ?>
                <div class="review-list">
                    <?php if (empty($reseñas)): ?>
                        <p class="no-results">Este producto aún no tiene opiniones. ¡Sé el primero en dejar una!</p>
                    <?php else: ?>
                        <?php foreach ($reseñas as $reseña): ?>
                        <div class="review-item">
                            <div class="review-header">
                                <div class="stars-small"><?php for ($i=1; $i<=5; $i++) echo '<i class="'.(($i <= $reseña['calificacion']) ? 'fas' : 'far').' fa-star"></i>'; ?></div>
                                <span class="author"><?php echo htmlspecialchars($reseña['nombre_usuario']); ?></span>
                                <span class="date"><?php echo date("d/m/Y", strtotime($reseña['fecha'])); ?></span>
                            </div>
                            <h4><?php echo htmlspecialchars($reseña['titulo']); ?></h4>
                            <p><?php echo nl2br(htmlspecialchars($reseña['comentario'])); ?></p>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php if ($usuario_ha_comprado): ?>
                <div class="review-form-container">
                    <h3>Escribe tu propia reseña</h3>
                    <?php if(isset($_SESSION['mensaje_exito_reseña'])) { echo "<p style='color:green;'>".$_SESSION['mensaje_exito_reseña']."</p>"; unset($_SESSION['mensaje_exito_reseña']); } ?>
                    <form action="catalogo.php?id=<?php echo $id_producto; ?>" method="POST">
                        <input type="hidden" name="accion" value="dejar_reseña">
                        <input type="hidden" name="id_producto" value="<?php echo $id_producto; ?>">
                        <div class="form-group"><label>Tu calificación:</label><div class="star-rating"><input type="radio" id="star5" name="calificacion" value="5" required><label for="star5" title="5 estrellas">★</label><input type="radio" id="star4" name="calificacion" value="4"><label for="star4" title="4 estrellas">★</label><input type="radio" id="star3" name="calificacion" value="3"><label for="star3" title="3 estrellas">★</label><input type="radio" id="star2" name="calificacion" value="2"><label for="star2" title="2 estrellas">★</label><input type="radio" id="star1" name="calificacion" value="1"><label for="star1" title="1 estrella">★</label></div></div>
                        <div class="form-group"><label for="titulo">Título de tu reseña:</label><input type="text" id="titulo" name="titulo" required></div>
                        <div class="form-group"><label for="comentario">Tu reseña:</label><textarea id="comentario" name="comentario" required></textarea></div>
                        <button type="submit" class="btn-submit-review">Enviar Reseña</button>
                    </form>
                </div>
                <?php elseif(isset($_SESSION['id'])): ?>
                    <p style="margin-top: 20px; font-style: italic;">Debes haber comprado este producto para poder dejar una reseña.</p>
                <?php else: ?>
                    <p style="margin-top: 20px;"><a href="login.php">Inicia sesión</a> para dejar una reseña.</p>
                <?php endif; ?>
            </div>

            <?php if (!empty($productos_similares)): ?>
                <div class="similares-container mt-5">
                    <h2>Productos Similares</h2>
                    <div class="similares-grid">
                        <?php foreach ($productos_similares as $similar): ?>
                            <div class="similar-card">
                                <a href="catalogo.php?id=<?php echo $similar['id_producto']; ?>" class="enlace-producto">
                                    <img src="uploads/<?php echo htmlspecialchars($similar['imagen_principal']); ?>" alt="<?php echo htmlspecialchars($similar['nombre']); ?>">
                                    <h4><?php echo htmlspecialchars($similar['nombre']); ?></h4>
                                    <p>$<?php echo number_format($similar['precio'], 0, ',', '.'); ?></p>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <script src="js/catalogo.js"></script>
    <script src="js/wishlist.js"></script>
</body>
</html>
<footer class="footer">
<?php
require 'includes/footer.php';
?>
