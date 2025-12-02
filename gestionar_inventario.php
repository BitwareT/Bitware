<?php
session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["permisos"] !== 'A') {
    header("location: login.php");
    exit;
}

require '/var/www/config/config.php';

$mensaje_exito="";
$search_term="";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['stock'])){
    $sql_update = "UPDATE producto SET stock = ? WHERE id_producto = ?";

    if ($stmt = $conn->prepare($sql_update)){
        foreach ($_POST['stock'] as $id_producto => $cantidad){

            $stock_actualizado = max(0, intval($cantidad));
            $stmt->bind_param("ii", $stock_actualizado, $id_producto);
            $stmt->execute();
        }
        $mensaje_exito = "¡El inventario Fue actualizado exitosamente";
        $stmt->execute();
    }
    
}

$sql_select = "SELECT p.id_producto, p.nombre, p.stock, m.nombre AS marca_nombre
               FROM producto p
               LEFT JOIN marcas m ON p.id_marca = m.id_marca";

// Si hay un término de búsqueda, añadirlo a la consulta
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_term = trim($_GET['search']);
    $sql_select .= " WHERE p.nombre LIKE ?";
}
$sql_select .= " ORDER BY p.stock ASC, p.nombre ASC";

$stmt_select = $conn->prepare($sql_select);

// Si hay búsqueda, vincular el parámetro
if (!empty($search_term)) {
    $like_term = "%" . $search_term . "%";
    $stmt_select->bind_param("s", $like_term);
}

$stmt_select->execute();
$result = $stmt_select->get_result();

?>
<?php
// --- 1. DEFINES LAS VARIABLES PARA EL HEAD ---
$titulo_pagina = ($vista_actual == 'detalle') ? htmlspecialchars($producto['nombre']) : 'Gestionar Inventario - Admin';
$css_pagina_especifica = "css/GInventario.css"; 
$body_atributos = 'data-stock="' . htmlspecialchars($producto['stock'] ?? '99') . '"';

// --- 2. LLAMAS AL HEAD ---
require 'includes/head.php'; // o head.php, el que estés usando
?>
<body>
    <div class="container"> 
        <a href="dashboard.php">&larr; Volver al Panel de Administrador</a>
        <h1>Gestión de Inventario y Stock</h1>

                <?php if ($mensaje_exito) echo "<div class='alert-success'>$mensaje_exito</div>"; ?>

        <div class="toolbar">
            <form action="gestionar_inventario.php" method="get" class="search-form">
                <input type="text" name="search" placeholder="Buscar producto por nombre..." value="<?php echo htmlspecialchars($search_term); ?>">
                <button type="submit">Buscar</button>
            </form>
        </div>

        <form action="gestionar_inventario.php" method="post">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Producto</th>
                        <th>Marca</th>
                        <th>Stock Actual</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result && $result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            $row_class = '';
                            $status_text = 'OK';
                            if ($row['stock'] <= 0) {
                                $row_class = 'out-of-stock';
                                $status_text = 'Sin Stock';
                            } elseif ($row['stock'] < 10) { // Límite para "Bajo Stock"
                                $row_class = 'low-stock';
                                $status_text = 'Bajo Stock';
                            }

                            echo "<tr class='" . $row_class . "'>";
                            echo "<td>" . $row['id_producto'] . "</td>";
                            echo "<td>" . htmlspecialchars($row['nombre']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['marca_nombre']) . "</td>";
                            echo "<td><input type='number' name='stock[" . $row['id_producto'] . "]' value='" . $row['stock'] . "' min='0' class='stock-input'></td>";
                            echo "<td class='status'>" . $status_text . "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5'>No se encontraron productos.</td></tr>";
                    }
                    $stmt_select->close();
                    ?>
                </tbody>
            </table>
            <button type="submit" class="btn-update">Actualizar Inventario</button>
        </form>
    </div>
</body>
</html>
<?php
$conn->close();
?>
<footer class="footer">
<?php
require 'includes/footer.php';
?>