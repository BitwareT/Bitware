<?php
session_start();
// --- NUEVO: Incluir el actualizador de actividad ---
require_once "check_activity.php";
// --- FIN NUEVO ---

// 1. VERIFICACIÓN DE SEGURIDAD (SOLO VENDEDORES 'V')
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["permisos"] !== 'V') {
    
    // Si no es Vendedor, quizás es Admin.
    if (isset($_SESSION["permisos"]) && $_SESSION["permisos"] === 'A') {
         header("location: gestionar_productos.php");
         exit;
    }
    
    // Si no es ni Vendedor ni Admin, al login
    header("location: login.php");
    exit;
}

// === LÍNEA CRÍTICA FALTANTE (AÑADIDA AQUÍ) ===
require '/var/www/config/config.php';
// =============================================

$id_vendedor_actual = $_SESSION['id']; // ID del Vendedor logueado

// --- OBTENER LISTA DE VENDEDORES (Para el formulario) ---
// (Esta consulta ya no es necesaria para el Vendedor, pero la dejamos para la lista de marcas)
// La consulta de vendedores se eliminó porque el vendedor no puede asignar productos.

// Definición de variables
$nombre = $descripcion = $precio = $stock = $id_marca = $categoria = $imagen = "";
$nombre_err = $descripcion_err = $precio_err = $stock_err = $id_marca_err = $categoria_err = $imagen_err = $error_general = "";
$mensaje_exito = "";
$modo_edicion = false;
$id_producto_editar = 0;
$imagenes_galeria_existentes = [];

// ===================================================================
// LÓGICA DE ACCIONES (DESDE GET PARAMS) - CON SEGURIDAD
// ===================================================================

// --- LÓGICA PARA DESACTIVAR/REACTIVAR ---
if (isset($_GET["toggle_active"]) && !empty(trim($_GET["toggle_active"]))) {
    $id_producto = trim($_GET["toggle_active"]);
    $nuevo_estado = (int)($_GET['new_status'] ?? 0);
    // VENDEDOR: Solo puede desactivar SUS productos
    $sql = "UPDATE producto SET activo = ? WHERE id_producto = ? AND id_vendedor = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("iii", $nuevo_estado, $id_producto, $id_vendedor_actual);
        if ($stmt->execute()) {
            header("location: mis_productos.php");
            exit();
        }
        $stmt->close();
    }
}

// --- LÓGICA PARA BORRADO FÍSICO CON RESPALDO ---
if (isset($_GET["delete"]) && !empty(trim($_GET["delete"]))) {
    $id_producto_borrar = trim($_GET["delete"]);

    // VENDEDOR: Verificar que el producto le pertenece ANTES de borrar
    $sql_check_owner = "SELECT id_producto FROM producto WHERE id_producto = ? AND id_vendedor = ?";
    $stmt_check = $conn->prepare($sql_check_owner);
    $stmt_check->bind_param("ii", $id_producto_borrar, $id_vendedor_actual);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows == 0) {
        $_SESSION['error_general'] = "No tienes permiso para borrar este producto.";
        header("location: mis_productos.php");
        exit();
    }
    $stmt_check->close();

    $conn->begin_transaction();
    try {
        // Copiar el producto a la tabla de respaldo
        $sql_backup = "INSERT INTO productos_eliminados (id_producto, nombre, descripcion, precio, stock, id_marca, categoria, imagen, activo, id_vendedor, fecha_eliminacion)
                       SELECT id_producto, nombre, descripcion, precio, stock, id_marca, categoria, imagen_principal, activo, id_vendedor, CURRENT_TIMESTAMP
                       FROM producto WHERE id_producto = ?";
        $stmt_backup = $conn->prepare($sql_backup);
        $stmt_backup->bind_param("i", $id_producto_borrar);
        $stmt_backup->execute();
        $stmt_backup->close();

        // Eliminar las imágenes de la galería asociadas
        $sql_delete_gallery = "DELETE FROM producto_imagenes WHERE id_producto = ?";
        $stmt_delete_gallery = $conn->prepare($sql_delete_gallery);
        $stmt_delete_gallery->bind_param("i", $id_producto_borrar);
        $stmt_delete_gallery->execute();
        $stmt_delete_gallery->close();

        // Eliminar el producto de la tabla principal
        $sql_delete = "DELETE FROM producto WHERE id_producto = ?";
        $stmt_delete = $conn->prepare($sql_delete);
        $stmt_delete->bind_param("i", $id_producto_borrar);
        $stmt_delete->execute();
        $stmt_delete->close();

        $conn->commit();
        header("location: mis_productos.php");
        exit();
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        if ($e->getCode() == 1451) {
            $_SESSION['error_general'] = "No se puede eliminar este producto porque está asociado a pedidos existentes. Considere desactivarlo en su lugar.";
        } else {
            $_SESSION['error_general'] = "Error al eliminar el producto: " . $e->getMessage();
        }
        header("location: mis_productos.php");
        exit();
    }
}

// ===================================================================
// --- LÓGICA PARA ELIMINAR UNA IMAGEN DE LA GALERÍA (CORREGIDO) ---
// ===================================================================
if (isset($_GET['delete_img']) && is_numeric($_GET['delete_img']) && isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    $id_imagen_a_eliminar = $_GET['delete_img'];
    $id_producto_redirect = $_GET['edit_id'];

    // --- INICIO: CHEQUEO DE VENDEDOR ---
    $sql_check_owner = "SELECT pi.nombre_archivo 
                        FROM producto_imagenes pi
                        JOIN producto p ON pi.id_producto = p.id_producto
                        WHERE pi.id_imagen = ? 
                        AND p.id_vendedor = ? 
                        AND p.id_producto = ?";
                        
    if ($stmt_check = $conn->prepare($sql_check_owner)) {
        $stmt_check->bind_param("iii", $id_imagen_a_eliminar, $id_vendedor_actual, $id_producto_redirect);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($row_check = $result_check->fetch_assoc()) {
            // --- El Vendedor SÍ es el dueño ---
            
            // 1. Borrar archivo del servidor
            $ruta_archivo = 'uploads/' . $row_check['nombre_archivo'];
            if (!empty($row_check['nombre_archivo']) && file_exists($ruta_archivo)) { 
                unlink($ruta_archivo); 
            }

            // 2. Borrar registro de la BD
            $sql_delete = "DELETE FROM producto_imagenes WHERE id_imagen = ?";
            if ($stmt_delete = $conn->prepare($sql_delete)) {
                $stmt_delete->bind_param("i", $id_imagen_a_eliminar);
                $stmt_delete->execute();
                $stmt_delete->close();
            }
            
        } else {
            $_SESSION['error_general'] = "Intento de acción no autorizada.";
        }
        $stmt_check->close();
        
    } else {
         $_SESSION['error_general'] = "Error al verificar permisos de la imagen.";
    }
    
    header("Location: mis_productos.php?edit=" . $id_producto_redirect);
    exit;
    // --- FIN: CHEQUEO DE VENDEDOR ---
}
// ===================================================================
// ===================================================================


// LÓGICA PARA PROCESAR EL FORMULARIO (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // (Validaciones de campos - sin cambios)
    if (empty(trim($_POST["nombre"]))) { $nombre_err = "El nombre es obligatorio."; } else { $nombre = trim($_POST["nombre"]); }
    if (empty(trim($_POST["descripcion"]))) { $descripcion_err = "La descripción es obligatoria."; } else { $descripcion = trim($_POST["descripcion"]); }
    if (!isset($_POST["precio"]) || !is_numeric($_POST["precio"]) || $_POST["precio"] < 0) { $precio_err = "El precio debe ser un número positivo."; } else { $precio = trim($_POST["precio"]); }
    if (!isset($_POST["stock"]) || !is_numeric($_POST["stock"])) { $stock_err = "El stock debe ser un número entero."; } else { $stock = trim($_POST["stock"]); }
    if (empty($_POST["id_marca"])) { $id_marca_err = "Debe seleccionar una marca."; } else { $id_marca = $_POST["id_marca"]; }
    if (empty($_POST["categoria"])) { $categoria_err = "Debe seleccionar una categoría."; } else { $categoria = $_POST["categoria"]; }
    
    // (Manejo de imagen principal - sin cambios)
    $nombre_imagen_principal = $_POST['imagen_actual'] ?? '';
    if (isset($_FILES["imagen_principal"]) && $_FILES["imagen_principal"]["error"] == 0) {
        $directorio_subidas = "uploads/";
        if (!is_dir($directorio_subidas)) { mkdir($directorio_subidas, 0775, true); }
        $nombre_archivo_nuevo = uniqid() . '_' . basename($_FILES["imagen_principal"]["name"]);
        $archivo_destino = $directorio_subidas . $nombre_archivo_nuevo;
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $file_extension = strtolower(pathinfo($archivo_destino, PATHINFO_EXTENSION));
        if (!in_array($file_extension, $allowed_types)) {
            $imagen_err = "Formato de imagen no permitido.";
        } else {
             if (move_uploaded_file($_FILES["imagen_principal"]["tmp_name"], $archivo_destino)) {
                $nombre_imagen_principal = $nombre_archivo_nuevo;
            } else { $imagen_err = "Hubo un error al subir la imagen principal."; }
        }
    } elseif (empty($_POST["id_producto"]) && empty($nombre_imagen_principal)) {
        $imagen_err = "La imagen principal es obligatoria para un nuevo producto.";
    }


    if (empty($nombre_err) && empty($descripcion_err) && empty($precio_err) && empty($stock_err) && empty($id_marca_err) && empty($categoria_err) && empty($imagen_err)) {
        
        $id_producto_actual = 0;

        if (isset($_POST["id_producto"]) && !empty($_POST["id_producto"])) { 
            // --- MODO EDICIÓN (VENDEDOR) ---
            $id_producto_actual = $_POST["id_producto"];
            
            // !! INICIO DE LA CORRECCIÓN !!
            // Esta es la consulta SQL COMPLETA (igual a la de gestionar_productos.php)
            $sql = "UPDATE producto SET nombre = ?, descripcion = ?, precio = ?, stock = ?, id_marca = ?, categoria = ?, imagen_principal = ? 
                    WHERE id_producto = ? AND id_vendedor = ?";
            
            if ($stmt = $conn->prepare($sql)) {
                // Y este es el bind_param COMPLETO, con 9 variables (ssdiissii)
                $stmt->bind_param("ssdiissii", $nombre, $descripcion, $precio, $stock, $id_marca, $categoria, $nombre_imagen_principal, $id_producto_actual, $id_vendedor_actual);
                if (!$stmt->execute()) { 
                    $error_general = "Error al actualizar el producto: " . $stmt->error; 
                }
                $stmt->close();
            } else { 
                $error_general = "Error al preparar la actualización: " . $conn->error; 
            }
            
        } else { 
            // --- MODO CREACIÓN (VENDEDOR) ---
            $sql = "INSERT INTO producto (nombre, descripcion, precio, stock, id_marca, categoria, imagen_principal, id_vendedor) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("ssdisisi", $nombre, $descripcion, $precio, $stock, $id_marca, $categoria, $nombre_imagen_principal, $id_vendedor_actual);
                 if ($stmt->execute()) {
                    $id_producto_actual = $conn->insert_id;
                 } else { $error_general = "Error al crear el producto: " . $stmt->error; }
                $stmt->close();
            } else { $error_general = "Error al preparar la inserción: " . $conn->error; }
        }

        // Manejo de galería
        if ($id_producto_actual > 0 && isset($_FILES["galeria_imagenes"]) && empty($error_general)) {
            $orden_actual = 0;
            $sql_orden = "SELECT MAX(orden) as max_orden FROM producto_imagenes WHERE id_producto = ?";
            if ($stmt_orden = $conn->prepare($sql_orden)) {
                $stmt_orden->bind_param("i", $id_producto_actual);
                $stmt_orden->execute();
                $result_orden = $stmt_orden->get_result();
                if ($row_orden = $result_orden->fetch_assoc()) { $orden_actual = $row_orden['max_orden'] ?? 0; }
                $stmt_orden->close();
            }
            $sql_insert_img = "INSERT INTO producto_imagenes (id_producto, nombre_archivo, orden) VALUES (?, ?, ?)";
            $directorio_subidas = "uploads/";

            foreach ($_FILES['galeria_imagenes']['tmp_name'] as $key => $tmp_name) {
                if (!empty($tmp_name) && $_FILES['galeria_imagenes']['error'][$key] == 0) {
                    $nombre_archivo_galeria = uniqid() . '_' . basename($_FILES["galeria_imagenes"]["name"][$key]);
                    $archivo_destino_galeria = $directorio_subidas . $nombre_archivo_galeria;
                    $allowed_types_gal = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    $file_extension_gal = strtolower(pathinfo($archivo_destino_galeria, PATHINFO_EXTENSION));
                    if (in_array($file_extension_gal, $allowed_types_gal) && move_uploaded_file($tmp_name, $archivo_destino_galeria)) {
                        $orden_actual++;
                        if ($stmt_img = $conn->prepare($sql_insert_img)) {
                            $stmt_img->bind_param("isi", $id_producto_actual, $nombre_archivo_galeria, $orden_actual);
                            $stmt_img->execute();
                            $stmt_img->close();
                        } else {$error_general .= " Error al preparar inserción de imagen galería. "; }
                    } else { $error_general .= " Error al subir imagen de galería o tipo inválido. ";}
                }
            }
        }
        
        if(empty($error_general)) {
             $mensaje_exito = "¡Producto guardado correctamente!";
             if (!isset($_POST["id_producto"]) || empty($_POST["id_producto"])) {
                  $nombre = $descripcion = $precio = $stock = $id_marca = $categoria = $imagen = "";
                  $id_producto_editar = 0;
                  $modo_edicion = false;
                  $imagenes_galeria_existentes = [];
             }
        }

    } // Fin validación errores
} // Fin POST


// LÓGICA PARA CARGAR DATOS EN EDICIÓN (GET)
if (isset($_GET["edit"]) && !empty(trim($_GET["edit"]))) {
    $id_producto_editar = trim($_GET["edit"]);
    
    $sql = "SELECT nombre, descripcion, precio, stock, id_marca, categoria, imagen_principal FROM producto WHERE id_producto = ? AND id_vendedor = ?";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ii", $id_producto_editar, $id_vendedor_actual);
        if ($stmt->execute()) {
            $result_edit = $stmt->get_result(); 
            if ($result_edit->num_rows == 1) {
                $row_edit = $result_edit->fetch_assoc();
                $nombre = $row_edit['nombre']; $descripcion = $row_edit['descripcion']; $precio = $row_edit['precio'];
                $stock = $row_edit['stock']; $id_marca = $row_edit['id_marca']; $categoria = $row_edit['categoria'];
                $imagen = $row_edit['imagen_principal']; 
                $modo_edicion = true;

                // Cargar galería existente
                $sql_galeria = "SELECT id_imagen, nombre_archivo FROM producto_imagenes WHERE id_producto = ? ORDER BY orden ASC";
                if ($stmt_galeria = $conn->prepare($sql_galeria)) {
                    $stmt_galeria->bind_param("i", $id_producto_editar);
                    $stmt_galeria->execute();
                    $result_galeria = $stmt_galeria->get_result();
                    $imagenes_galeria_existentes = $result_galeria->fetch_all(MYSQLI_ASSOC);
                    $stmt_galeria->close();
                }
            } else { $error_general = "Producto no encontrado o no te pertenece."; }
        } else { $error_general = "Error al buscar producto para editar."; }
        $stmt->close();
    } else { $error_general = "Error al preparar la búsqueda para edición."; }
}

// (Mostrar errores de sesión - sin cambios)
if (isset($_SESSION['error_general'])) {
    $error_general = $_SESSION['error_general'];
    unset($_SESSION['error_general']);
}
?>
<?php
// --- 1. DEFINES LAS VARIABLES PARA EL HEAD ---
$titulo_pagina = "Mis Productos - Vendedor<";

// Sigue funcionando, pero ahora como un array de un solo ítem
$estilos_especificos = [
    "css/Gproductos.css",
    "css/GProducto.css",
];

// --- 2. LLAMAS AL HEAD ---
require 'includes/head2.php';

?>
<body>
    <div class="container">
        <a href="dashboard.php">&larr; Volver a mi Panel</a>
        <h1>Mis Productos</h1>

        <div class="form-container">
            <h2><?php echo $modo_edicion ? "Editar Producto (ID: " . $id_producto_editar . ")" : "Agregar Nuevo Producto"; ?></h2>
            <?php if (!empty($mensaje_exito)) echo "<div class='alert-success'>$mensaje_exito</div>"; ?>
            <?php if (!empty($error_general)) echo "<div class='alert-danger'>$error_general</div>"; ?>

            <form action="mis_productos.php<?php echo $modo_edicion ? '?edit='.$id_producto_editar : ''; ?>" method="post" enctype="multipart/form-data">
                <?php if ($modo_edicion): ?>
                    <input type="hidden" name="id_producto" value="<?php echo $id_producto_editar; ?>">
                <?php endif; ?>
                <input type="hidden" name="imagen_actual" value="<?php echo htmlspecialchars($imagen ?? ''); ?>">

                <div class="form-group">
                    <label>Nombre</label>
                    <input type="text" name="nombre" value="<?php echo htmlspecialchars($nombre);?>">
                    <span class="text-danger"><?php echo $nombre_err;?></span>
                </div>
                <div class="form-group">
                    <label>Descripción</label>
                    <textarea name="descripcion"><?php echo htmlspecialchars($descripcion);?></textarea>
                    <span class="text-danger"><?php echo $descripcion_err;?></span>
                </div>
                <div class="form-group">
                    <label>Precio</label>
                    <input type="number" step="0.01" name="precio" value="<?php echo htmlspecialchars($precio);?>">
                    <span class="text-danger"><?php echo $precio_err;?></span>
                </div>
                <div class="form-group">
                    <label>Stock</label>
                    <input type="number" name="stock" value="<?php echo htmlspecialchars($stock);?>">
                    <span class="text-danger"><?php echo $stock_err;?></span>
                </div>

                <div class="form-group">
                    <label>Marca</label>
                    <select name="id_marca">
                        <option value="">-- Seleccionar Marca --</option>
                        <?php
                            $sql_marcas = "SELECT id_marca, nombre FROM marcas ORDER BY nombre";
                            $result_marcas = $conn->query($sql_marcas);
                            if ($result_marcas && $result_marcas->num_rows > 0) {
                                while ($row_marca = $result_marcas->fetch_assoc()) {
                                    $selected = (($id_marca ?? '') == $row_marca['id_marca']) ? 'selected' : '';
                                    echo "<option value='" . $row_marca['id_marca'] . "' $selected>" . htmlspecialchars($row_marca['nombre']) . "</option>";
                                }
                            }
                        ?>
                    </select>
                    <span class="text-danger"><?php echo $id_marca_err;?></span>
                </div>

                <div class="form-group">
                    <label>Categoría</label>
                    <select name="categoria">
                        <option value="">-- Seleccionar Categoría --</option>
                        <option value="cpu" <?php if (($categoria ?? '') == 'cpu') echo 'selected'; ?>>CPU / Procesador</option>
                        <option value="gpu" <?php if (($categoria ?? '') == 'gpu') echo 'selected'; ?>>GPU / Tarjeta Gráfica</option>
                        <option value="ram" <?php if (($categoria ?? '') == 'ram') echo 'selected'; ?>>Memoria RAM</option>
                        <option value="placa" <?php if (($categoria ?? '') == 'placa') echo 'selected'; ?>>Placa Madre</option>
                        <option value="disco" <?php if (($categoria ?? '') == 'disco') echo 'selected'; ?>>Almacenamiento</option>
                        <option value="otros" <?php if (($categoria ?? '') == 'otros') echo 'selected'; ?>>Otros</option>
                    </select>
                     <span class="text-danger"><?php echo $categoria_err;?></span>
                </div>

                <div class="form-group">
                    <label>Imagen Principal <?php echo $modo_edicion ? '(Opcional: Subir para reemplazar)' : '(Obligatoria)'; ?></label>
                    <?php if ($modo_edicion && !empty($imagen)): ?>
                        <div style="margin-bottom: 10px;"><img src="uploads/<?php echo htmlspecialchars($imagen); ?>" alt="Imagen actual" style="max-width: 100px; max-height: 100px; border-radius: 5px;"></div>
                    <?php endif; ?>
                    <input type="file" name="imagen_principal" accept="image/jpeg, image/png, image/gif, image/webp">
                    <span class="text-danger"><?php echo $imagen_err;?></span>
                </div>

                <div class="form-group">
                    <label>Imágenes de la Galería (Opcional)</label>
                    <?php if ($modo_edicion && !empty($imagenes_galeria_existentes)): ?>
                        <div class="galeria-existente">
                            <?php foreach ($imagenes_galeria_existentes as $img_galeria): ?>
                                <div class="galeria-item">
                                    <img src="uploads/<?php echo htmlspecialchars($img_galeria['nombre_archivo']); ?>" alt="Imagen de galería">
                                    <a href="mis_productos.php?delete_img=<?php echo $img_galeria['id_imagen']; ?>&edit_id=<?php echo $id_producto_editar; ?>" class="delete-img-btn" 
                                       data-bs-toggle="modal" data-bs-target="#confirmModal"
                                       data-action-url="mis_productos.php?delete_img=<?php echo $img_galeria['id_imagen']; ?>&edit_id=<?php echo $id_producto_editar; ?>"
                                       data-warning-text="¿Seguro que quieres eliminar esta imagen de la galería? Esta acción es permanente."
                                       data-button-class="btn-danger"
                                       onclick="event.preventDefault(); /* Previene el clic normal */">&times;</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="galeria_imagenes[]" multiple accept="image/jpeg, image/png, image/gif, image/webp">
                    <small>Puedes añadir más imágenes. Las nuevas se sumarán a las existentes.</small>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn <?php echo $modo_edicion ? 'btn-success' : 'btn-primary'; ?>"><?php echo $modo_edicion ? "Actualizar Producto" : "Agregar Producto"; ?></button>
                    <?php if ($modo_edicion): ?>
                         <a href="mis_productos.php" style="margin-left: 10px; text-decoration: none;">Cancelar Edición</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php
        // --- LÓGICA PARA FILTROS (VENDEDOR) ---
        $filtro_categoria = $_GET['filtro_cat'] ?? '';
        $filtro_marca = $_GET['filtro_marca'] ?? '';

        // Obtener todas las marcas para los botones
        $marcas_para_filtros = [];
        $sql_marcas_filtro = "SELECT id_marca, nombre FROM marcas ORDER BY nombre ASC";
        $result_marcas_filtro = $conn->query($sql_marcas_filtro);
        if ($result_marcas_filtro) {
            $marcas_para_filtros = $result_marcas_filtro->fetch_all(MYSQLI_ASSOC);
        }

        // Definir categorías
        $categorias_para_filtros = [
            'cpu' => 'CPU', 'gpu' => 'GPU', 'ram' => 'RAM',
            'placa' => 'Placa Madre', 'disco' => 'Almacenamiento', 'otros' => 'Otros'
        ];

        // --- CONSULTA SQL MODIFICADA (VENDEDOR) ---
        $sql_select = "SELECT p.id_producto, p.nombre, p.stock, p.imagen_principal, p.activo, m.nombre as nombre_marca
                       FROM producto p
                       LEFT JOIN marcas m ON p.id_marca = m.id_marca";
        
        $condiciones_where = ["p.id_vendedor = ?"]; // ¡Filtro base!
        $params_filtro = [$id_vendedor_actual];
        $types_filtro = "i";

        if (!empty($filtro_categoria)) {
            $condiciones_where[] = "p.categoria = ?";
            $params_filtro[] = $filtro_categoria;
            $types_filtro .= "s";
        }
        if (!empty($filtro_marca) && is_numeric($filtro_marca)) {
            $condiciones_where[] = "p.id_marca = ?";
            $params_filtro[] = $filtro_marca;
            $types_filtro .= "i";
        }

        $sql_select .= " WHERE " . implode(" AND ", $condiciones_where);
        $sql_select .= " ORDER BY p.id_producto DESC";

        $stmt_lista = $conn->prepare($sql_select);
        if ($stmt_lista) {
            $stmt_lista->bind_param($types_filtro, ...$params_filtro);
            $stmt_lista->execute();
            $result = $stmt_lista->get_result();
        } else {
            echo "<div class='alert-danger'>Error al preparar la consulta de la lista: " . $conn->error . "</div>";
            $result = false;
        }
        ?>

        <h2>Lista de Mis Productos</h2>

        <div class="filter-controls">
            <strong>Filtrar por:</strong><br>
            <a href="mis_productos.php" class="filter-btn <?php if(empty($filtro_categoria) && empty($filtro_marca)) echo 'active'; ?>">Todos</a> |
            <strong>Categoría:</strong>
            <?php foreach ($categorias_para_filtros as $key => $nombre_cat): ?>
                <a href="?filtro_cat=<?php echo $key; ?><?php if($filtro_marca) echo '&filtro_marca='.$filtro_marca; ?>" class="filter-btn <?php if($filtro_categoria == $key) echo 'active'; ?>"><?php echo $nombre_cat; ?></a>
            <?php endforeach; ?> |
            <strong>Marca:</strong>
            <?php foreach ($marcas_para_filtros as $marca): ?>
                <a href="?filtro_marca=<?php echo $marca['id_marca']; ?><?php if($filtro_categoria) echo '&filtro_cat='.$filtro_categoria; ?>" class="filter-btn <?php if($filtro_marca == $marca['id_marca']) echo 'active'; ?>"><?php echo htmlspecialchars($marca['nombre']); ?></a>
            <?php endforeach; ?>
        </div>
        
        <table>
            <thead><tr><th>ID</th><th>Imagen</th><th>Nombre</th><th>Stock</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
                <?php
                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $row_class = $row['activo'] ? '' : 'inactive-row';
                        echo "<tr class='" . $row_class . "'>";
                        echo "<td>" . $row['id_producto'] . "</td>";
                        echo "<td>";
                        if (!empty($row['imagen_principal']) && file_exists("uploads/" . $row['imagen_principal'])) {
                            echo "<img src='uploads/" . htmlspecialchars($row['imagen_principal']) . "' alt='" . htmlspecialchars($row['nombre']) . "' style='width: 50px; height: 50px; object-fit: cover; border-radius: 4px;'>";
                        } else {
                             echo "<img src='uploads/default.png' alt='Sin imagen' style='width: 50px; height: 50px; object-fit: cover; border-radius: 4px;'>";
                        }
                        echo "</td>";
                        echo "<td>" . htmlspecialchars($row['nombre']) . "</td>";
                        echo "<td>" . $row['stock'] . "</td>";
                        echo "<td>" . ($row['activo'] ? '<span style="color: green;">Activo</span>' : '<span style="color: red;">Inactivo</span>') . "</td>";
                        echo "<td class='actions'>";
                        
                        // --- INICIO: ENLACES MODIFICADOS PARA USAR MODAL ---
                        echo "<a href='mis_productos.php?edit=" . $row['id_producto'] . "' class='edit'>Editar</a> ";
                        
                        if ($row['activo']) {
                             echo "<a href='#' class='deactivate' 
                                    data-bs-toggle='modal' 
                                    data-bs-target='#confirmModal' 
                                    data-action-url='mis_productos.php?toggle_active=" . $row['id_producto'] . "&new_status=0' 
                                    data-warning-text='¿Seguro que quieres DESACTIVAR este producto? El producto no será visible en el catálogo.' 
                                    data-button-class='btn-warning'>Desactivar</a> ";
                        } else {
                             echo "<a href='#' class='reactivate' 
                                    data-bs-toggle='modal' 
                                    data-bs-target='#confirmModal' 
                                    data-action-url='mis_productos.php?toggle_active=" . $row['id_producto'] . "&new_status=1' 
                                    data-warning-text='¿Seguro que quieres REACTIVAR este producto?' 
                                    data-button-class='btn-success'>Reactivar</a> ";
                        }
                        if (!$row['activo']) {
                            echo "<a href='#' class='delete-permanent' 
                                    data-bs-toggle='modal' 
                                    data-bs-target='#confirmModal' 
                                    data-action-url='mis_productos.php?delete=" . $row['id_producto'] . "' 
                                    data-warning-text='¡ADVERTENCIA! Esta acción es PERMANENTE y creará un respaldo. ¿Estás seguro de que quieres eliminar este producto?' 
                                    data-button-class='btn-danger'>Borrar</a>";
                        }
                        // --- FIN: ENLACES MODIFICADOS ---
                        
                        echo "</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='6'>No tienes productos registrados" . (!empty($filtro_categoria) || !empty($filtro_marca) ? " con los filtros seleccionados." : ".") . "</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
    
    <?php if (isset($stmt_lista) && $stmt_lista) $stmt_lista->close(); // Cerrar el statement de la lista ?>

    <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="confirmModalLabel">Confirmar Acción</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" id="confirmModalBody">
            </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <a href="#" id="confirmModalButton" class="btn btn-danger">Confirmar</a>
          </div>
        </div>
      </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var confirmModal = document.getElementById('confirmModal');
        
        if(confirmModal) { // Asegurarse de que el modal existe
            confirmModal.addEventListener('show.bs.modal', function (event) {
                
                // 1. Identifica el botón que disparó el modal
                var button = event.relatedTarget; 

                // 2. Extrae la información de los atributos 'data-*' del botón
                var actionUrl = button.getAttribute('data-action-url');
                var warningText = button.getAttribute('data-warning-text');
                var buttonClass = button.getAttribute('data-button-class') || 'btn-danger';

                // 3. Obtiene los elementos del modal que vamos a cambiar
                var modalBody = document.getElementById('confirmModalBody');
                var confirmButton = document.getElementById('confirmModalButton');
                var modalTitle = document.getElementById('confirmModalLabel');

                // 4. Actualiza el contenido del modal
                modalBody.textContent = warningText;
                modalTitle.textContent = (buttonClass === 'btn-danger') ? '¿Estás seguro?' : 'Confirmar Acción';
                
                // 5. Actualiza el botón de "Confirmar"
                confirmButton.setAttribute('href', actionUrl);
                confirmButton.className = 'btn ' + buttonClass; // Cambia el color
            });
        }
    });
    </script>
    </body>
</html>
<footer class="footer">
<?php
require 'includes/footer.php';
?>
<?php
$conn->close();
?>