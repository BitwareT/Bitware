<?php
session_start();
// --- NUEVO: Incluir el actualizador de actividad ---
require_once "check_activity.php";
// --- FIN NUEVO ---

// 1. VERIFICACIÓN DE SEGURIDAD (SOLO VENDEDORES 'V')
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["permisos"] !== 'V') {
    header("location: login.php");
    exit;
}

require '/var/www/config/config.php';
$id_vendedor_actual = $_SESSION['id']; // ID del Vendedor logueado

?>
<?php
// --- 1. DEFINES LAS VARIABLES PARA EL HEAD ---
$titulo_pagina = ($vista_actual == 'detalle') ? htmlspecialchars($producto['nombre']) : 'Mis Ventas - Vendedor';
$css_pagina_especifica = "css/GPedidos.css?v=1.1"; 
// --- 2. LLAMAS AL HEAD ---
require 'includes/head.php'; // o head.php, el que estés usando
?>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-link">&larr; Volver a mi Panel</a>
        <h1>Mis Ventas</h1>
        <p>Aquí se muestran todos los productos que has vendido de pedidos ya pagados o completados.</p>

        <table>
            <thead>
                <tr>
                    <th>ID Pedido</th>
                    <th>Fecha Pedido</th>
                    <th>Estado Pedido</th>
                    <th>Comprador</th>
                    <th>Producto Vendido</th>
                    <th>Cantidad</th>
                    <th>Precio Unit.</th>
                    <th>Subtotal Venta</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // ESTA CONSULTA ES LA CLAVE:
                // Busca solo los *ítems* (pedidos_productos) que pertenecen
                // a productos (producto) cuyo id_vendedor sea el actual.
                
                $sql_ventas = "SELECT 
                                    p.id_pedido, p.fecha_pedido, p.estado,
                                    comprador.nombre AS nombre_cliente, 
                                    prod.nombre AS nombre_producto, 
                                    pp.cantidad, pp.precio_unitario,
                                    (pp.cantidad * pp.precio_unitario) AS subtotal_venta
                                FROM producto prod
                                JOIN pedidos_productos pp ON prod.id_producto = pp.id_producto
                                JOIN pedidos p ON pp.id_pedido = p.id_pedido
                                JOIN usuario comprador ON p.id_usuario = comprador.id_usuario
                                WHERE prod.id_vendedor = ? 
                                  AND p.estado IN ('Pagado', 'Enviado', 'Entregado', 'En Devolución')
                                ORDER BY p.id_pedido DESC";

                $stmt = $conn->prepare($sql_ventas);
                $stmt->bind_param("i", $id_vendedor_actual);
                $stmt->execute();
                $ventas = $stmt->get_result();

                if ($ventas && $ventas->num_rows > 0) {
                    $total_ganancias = 0;
                    while ($venta = $ventas->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>#" . str_pad($venta['id_pedido'], 6, '0', STR_PAD_LEFT) . "</td>";
                        echo "<td>" . date("d/m/Y", strtotime($venta['fecha_pedido'])) . "</td>";
                        echo "<td>" . htmlspecialchars($venta['estado']) . "</td>";
                        echo "<td>" . htmlspecialchars($venta['nombre_cliente']) . "</td>";
                        echo "<td>" . htmlspecialchars($venta['nombre_producto']) . "</td>";
                        echo "<td>" . $venta['cantidad'] . "</td>";
                        echo "<td>$" . number_format($venta['precio_unitario'], 0, ',', '.') . "</td>";
                        echo "<td><strong>$" . number_format($venta['subtotal_venta'], 0, ',', '.') . "</strong></td>";
                        echo "</tr>";
                        $total_ganancias += $venta['subtotal_venta'];
                    }
                    
                    // Fila de Total
                    echo "<tr>
                            <td colspan='7' style='text-align: right; font-weight: bold;'>Total Generado (de ventas mostradas):</td>
                            <td style='font-weight: bold; font-size: 1.1em;'>$" . number_format($total_ganancias, 0, ',', '.') . "</td>
                          </tr>";

                } else {
                    echo "<tr><td colspan='8'>Aún no has registrado ninguna venta de pedidos completados.</td></tr>";
                }
                $stmt->close();
                $conn->close();
                ?>
            </tbody>
        </table>
    </div>
</body>
</html>
<footer class="footer">
<?php
require 'includes/footer.php';
?>