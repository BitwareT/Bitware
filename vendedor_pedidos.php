<?php
session_start();
// --- Incluir el actualizador de actividad ---
require_once "check_activity.php";

// 1. VERIFICACIÓN DE SEGURIDAD (SOLO VENDEDORES 'V')
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["permisos"] !== 'V') {
    header("location: login.php");
    exit;
}

require '/var/www/config/config.php';
$id_vendedor_actual = $_SESSION['id']; // ID del Vendedor logueado

// --- 2. DEFINES LAS VARIABLES PARA EL HEAD ---
$titulo_pagina = 'Gestionar Mis Pedidos';
$css_pagina_especifica = "css/GPedidos.css";
require 'includes/head.php';

$mensaje_exito = "";
$mensaje_error = "";

// --- 3. LÓGICA PARA ACTUALIZAR EL ESTADO (Igual que el Admin) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id_pedido_actualizar'])) {
    $id_pedido = $_POST['id_pedido_actualizar'];
    $nuevo_estado = $_POST['estado'];

    // (Aquí podrías añadir una segunda validación para asegurarte
    // que el pedido que intenta cambiar SÍ contiene un item tuyo, por seguridad)

    $sql_update = "UPDATE pedidos SET estado = ? WHERE id_pedido = ?";
    if ($stmt = $conn->prepare($sql_update)) {
        $stmt->bind_param("si", $nuevo_estado, $id_pedido);
        if ($stmt->execute()) {
            $mensaje_exito = "¡Estado del pedido #" . $id_pedido . " actualizado correctamente!";
        } else {
            $mensaje_error = "Error al actualizar el pedido.";
        }
        $stmt->close();
    }
}

// --- 4. LÓGICA DE PAGINACIÓN Y FILTRADO (Adaptada para Vendedor) ---
$filtro_estado = $_GET['filtro_estado'] ?? 'todos'; 
define('PEDIDOS_POR_PAGINA', 15);
$pagina_actual = (int)($_GET['pagina'] ?? 1);
if ($pagina_actual < 1) $pagina_actual = 1;
$offset = ($pagina_actual - 1) * PEDIDOS_POR_PAGINA;

// --- INICIO: FILTRO DE VENDEDOR ---
// Esta es la parte clave.
// 1. Cláusula WHERE base: Solo pedidos que contengan productos de este vendedor
$sql_where = " WHERE pr.id_vendedor = ? ";
$params_where = [$id_vendedor_actual];
$types_where = "i";

// 2. Añadir el filtro de estado (si existe)
if ($filtro_estado != 'todos') {
    $sql_where .= " AND p.estado = ? ";
    $params_where[] = $filtro_estado;
    $types_where .= "s";
}
// --- FIN: FILTRO DE VENDEDOR ---


// 5. OBTENER EL NÚMERO TOTAL DE PEDIDOS (para la paginación)
// (Usamos los mismos JOINs y el mismo WHERE)
$sql_total = "SELECT COUNT(DISTINCT p.id_pedido) as total 
              FROM pedidos p
              JOIN usuario u ON p.id_usuario = u.id_usuario
              JOIN pedidos_productos pp ON p.id_pedido = pp.id_pedido
              JOIN producto pr ON pp.id_producto = pr.id_producto
              {$sql_where}"; // Aplicamos el filtro (Vendedor + Estado)

$stmt_total = $conn->prepare($sql_total);
$stmt_total->bind_param($types_where, ...$params_where);
$stmt_total->execute();
$resultado_total = $stmt_total->get_result();
$total_pedidos = $resultado_total->fetch_assoc()['total'] ?? 0;
$total_paginas = ceil($total_pedidos / PEDIDOS_POR_PAGINA);
$stmt_total->close();
?>

<div class="container">
        <a href="dashboard.php" class="back-link">&larr; Volver a mi Panel</a>
        <h1>Gestionar Mis Pedidos</h1>
        <p>Aquí puedes ver y gestionar el estado de los pedidos que contienen tus productos.</p>

        <?php if ($mensaje_exito) echo "<div class='alert-success'>$mensaje_exito</div>"; ?>
        <?php if ($mensaje_error) echo "<div class='alert-danger'>$mensaje_error</div>"; ?>

        <nav class="filter-nav">
            <a href="vendedor_pedidos.php?filtro_estado=todos" class="<?php echo ($filtro_estado == 'todos') ? 'active' : ''; ?>">Todos</a>
            <a href="vendedor_pedidos.php?filtro_estado=Pendiente" class="<?php echo ($filtro_estado == 'Pendiente') ? 'active' : ''; ?>">Pendientes</a>
            <a href="vendedor_pedidos.php?filtro_estado=Pagado" class="<?php echo ($filtro_estado == 'Pagado') ? 'active' : ''; ?>">Pagados</a>
            <a href="vendedor_pedidos.php?filtro_estado=Enviado" class="<?php echo ($filtro_estado == 'Enviado') ? 'active' : ''; ?>">Enviados</a>
            <a href="vendedor_pedidos.php?filtro_estado=Entregado" class="<?php echo ($filtro_estado == 'Entregado') ? 'active' : ''; ?>">Entregados</a>
            <a href="vendedor_pedidos.php?filtro_estado=En Devolución" class="<?php echo ($filtro_estado == 'En Devolución') ? 'active' : ''; ?>">Devoluciones</a>
            <a href="vendedor_pedidos.php?filtro_estado=Cancelado" class="<?php echo ($filtro_estado == 'Cancelado') ? 'active' : ''; ?>">Cancelados</a>
        </nav>
        
        <table>
            <thead>
                <tr>
                    <th>ID Pedido</th>
                    <th>Cliente</th>
                    <th>Producto (Ref)</th>
                    <th>Dirección de Envío</th>
                    <th>Total Pedido</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // --- CONSULTA SQL MODIFICADA con FILTRO DE VENDEDOR y LIMIT/OFFSET ---
                $sql_pedidos = "SELECT 
                            p.id_pedido, p.fecha_pedido, p.total, p.estado,
                            u.nombre AS nombre_cliente, u.direccion, u.region,
                            MIN(pr.nombre) AS nombre_producto, 
                            MIN(pr.imagen_principal) AS imagen_producto
                        FROM pedidos p
                        JOIN usuario u ON p.id_usuario = u.id_usuario
                        JOIN pedidos_productos pp ON p.id_pedido = pp.id_pedido
                        JOIN producto pr ON pp.id_producto = pr.id_producto
                        {$sql_where} 
                        GROUP BY p.id_pedido, p.fecha_pedido, p.total, p.estado, u.nombre, u.direccion, u.region
                        ORDER BY p.id_pedido DESC
                        LIMIT ? OFFSET ?";
                
                $stmt_pedidos = $conn->prepare($sql_pedidos);
                
                $params_final = $params_where;
                $types_final = $types_where;
                
                $params_final[] = PEDIDOS_POR_PAGINA;
                $types_final .= "i";
                $params_final[] = $offset;
                $types_final .= "i";

                $stmt_pedidos->bind_param($types_final, ...$params_final);
                $stmt_pedidos->execute();
                $result = $stmt_pedidos->get_result();

                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>#" . str_pad($row['id_pedido'], 6, '0', STR_PAD_LEFT) . "</td>";
                        echo "<td>" . htmlspecialchars($row['nombre_cliente']) . "</td>";

                        if ($row['nombre_producto']) {
                            echo '<td><div class="product-info"><img src="uploads/' . htmlspecialchars($row['imagen_producto']) . '" alt="' . htmlspecialchars($row['nombre_producto']) . '"><span>' . htmlspecialchars($row['nombre_producto']) . ' (y otros...)</span></div></td>';
                        } else {
                            echo '<td><em>(Producto no disponible)</em></td>';
                        }
                        
                        $direccion_completa = !empty($row['direccion']) ? htmlspecialchars($row['direccion'] . ', ' . $row['region']) : '<em>No especificada</em>';
                        echo "<td>" . $direccion_completa . "</td>";
                        
                        echo "<td>$" . number_format($row['total'], 0, ',', '.') . "</td>";
                        
                        // --- Formulario de Cambio de Estado (El que querías) ---
                        echo '<td>
                                <form action="vendedor_pedidos.php?filtro_estado='.$filtro_estado.'&pagina='.$pagina_actual.'" method="POST" class="update-form">
                                    <input type="hidden" name="id_pedido_actualizar" value="' . $row['id_pedido'] . '">
                                    <select name="estado">
                                        <option value="Pendiente" ' . ($row['estado'] == 'Pendiente' ? 'selected' : '') . '>Pendiente</option>
                                        <option value="Pagado" ' . ($row['estado'] == 'Pagado' ? 'selected' : '') . '>Pagado</option>
                                        <option value="Enviado" ' . ($row['estado'] == 'Enviado' ? 'selected' : '') . '>Enviado</option>
                                        <option value="Entregado" ' . ($row['estado'] == 'Entregado' ? 'selected' : '') . '>Entregado</option>
                                        <option value="Cancelado" ' . ($row['estado'] == 'Cancelado' ? 'selected' : '') . '>Cancelado</option>
                                        <option value="Fallido" ' . ($row['estado'] == 'Fallido' ? 'selected' : '') . '>Fallido</option>
                                        <option value="En Devolución" ' . ($row['estado'] == 'En Devolución' ? 'selected' : '') . '>En Devolución</option>
                                    </select>
                                    <button type="submit" class="btn-update">OK</button>
                                </form>
                              </td>';
                        echo "<td class='actions'><a href='ver_detalle_vendedor.php?id=" . $row['id_pedido'] . "' class='btn-details'>Ver Detalles</a></td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='7'>No se encontraron pedidos que contengan tus productos con el estado '{$filtro_estado}'.</td></tr>";
                }
                $stmt_pedidos->close();
                $conn->close();
                ?>
            </tbody>
        </table>

        <nav class="pagination-nav">
            <?php if ($total_paginas > 1): ?>
                
                <a href="?filtro_estado=<?php echo $filtro_estado; ?>&pagina=<?php echo $pagina_actual - 1; ?>" 
                   class="<?php echo ($pagina_actual <= 1) ? 'disabled' : ''; ?>">
                   &laquo; Anterior
                </a>

                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                    <a href="?filtro_estado=<?php echo $filtro_estado; ?>&pagina=<?php echo $i; ?>" 
                       class="<?php echo ($pagina_actual == $i) ? 'active' : ''; ?>">
                       <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <a href="?filtro_estado=<?php echo $filtro_estado; ?>&pagina=<?php echo $pagina_actual + 1; ?>" 
                   class="<?php echo ($pagina_actual >= $total_paginas) ? 'disabled' : ''; ?>">
                   Siguiente &raquo;
                </a>
                
            <?php endif; ?>
        </nav>
        </div>
</body>
</html>
<footer class="footer">
<?php
require 'includes/footer.php';
?>