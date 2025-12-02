<?php
session_start();
require '/var/www/config/config.php';
require_once "check_activity.php";

// 1. VERIFICACIÓN DE SEGURIDAD (SOLO ADMIN 'A')
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["permisos"] !== 'A') {
    header("location: login.php");
    exit;
}


// --- 1. DEFINES LAS VARIABLES PARA EL HEAD ---
$titulo_pagina = 'Ver Reportes Administrador';
// --- ¡CORREGIDO! ---
$css_pagina_especifica = "css/GReportes.css?v=1.0"; 
$body_atributos = ''; // No se necesita
require 'includes/head.php';

// --- 2. CONSULTAS SQL PARA LOS GRÁFICOS DEL ADMIN ---

// --- GRÁFICO 1: INGRESOS TOTALES (Plataforma completa) ÚLTIMOS 30 DÍAS ---
$labels_ventas = [];
$data_ventas = [];
$total_ingresos_periodo = 0;

$sql_ventas_dia = "SELECT 
                        DATE(fecha_pedido) as dia, 
                        SUM(total) as total_dia
                   FROM pedidos
                   WHERE estado IN ('Pagado', 'Enviado', 'Entregado') 
                     AND fecha_pedido >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                   GROUP BY DATE(fecha_pedido)
                   ORDER BY dia ASC";

if ($stmt_ventas = $conn->prepare($sql_ventas_dia)) {
    $stmt_ventas->execute();
    $resultado_ventas = $stmt_ventas->get_result();
    while ($fila = $resultado_ventas->fetch_assoc()) {
        $labels_ventas[] = $fila['dia'];
        $data_ventas[] = $fila['total_dia'];
        $total_ingresos_periodo += $fila['total_dia'];
    }
    $stmt_ventas->close();
}

// --- GRÁFICO 2: TOP 5 VENDEDORES (Por Ingresos) ---
$labels_top_vendedores = [];
$data_top_vendedores = [];

$sql_top_vendedores = "SELECT 
                             COALESCE(v.nombre, 'Admin (Bitware)') as vendedor_nombre,
                             SUM(pp.cantidad * pp.precio_unitario) as total_revenue
                         FROM pedidos_productos pp
                         JOIN producto pr ON pp.id_producto = pr.id_producto
                         JOIN pedidos p ON pp.id_pedido = p.id_pedido
                         LEFT JOIN usuario v ON pr.id_vendedor = v.id_usuario 
                         WHERE p.estado IN ('Pagado', 'Enviado', 'Entregado')
                         GROUP BY vendedor_nombre
                         ORDER BY total_revenue DESC
                         LIMIT 5";

if ($stmt_vend = $conn->prepare($sql_top_vendedores)) {
    $stmt_vend->execute();
    $resultado_top_vend = $stmt_vend->get_result();
    while ($fila = $resultado_top_vend->fetch_assoc()) {
        $labels_top_vendedores[] = $fila['vendedor_nombre'];
        $data_top_vendedores[] = $fila['total_revenue'];
    }
    $stmt_vend->close();
}

// --- GRÁFICO 3: TOP 5 PRODUCTOS MÁS VENDIDOS (Por Ingresos) ---
$labels_top_productos = [];
$data_top_productos = [];

$sql_top_productos = "SELECT 
                            pr.nombre, 
                            SUM(pp.cantidad * pp.precio_unitario) as total_revenue
                      FROM pedidos_productos pp
                      JOIN producto pr ON pp.id_producto = pr.id_producto
                      JOIN pedidos p ON pp.id_pedido = p.id_pedido
                      WHERE p.estado IN ('Pagado', 'Enviado', 'Entregado')
                      GROUP BY pr.id_producto, pr.nombre
                      ORDER BY total_revenue DESC
                      LIMIT 5";

if ($stmt_top = $conn->prepare($sql_top_productos)) {
    $stmt_top->execute();
    $resultado_top = $stmt_top->get_result();
    while ($fila = $resultado_top->fetch_assoc()) {
        $labels_top_productos[] = $fila['nombre'];
        $data_top_productos[] = $fila['total_revenue'];
    }
    $stmt_top->close();
}

// --- GRÁFICO 4: NUEVOS USUARIOS ÚLTIMOS 30 DÍAS ---
$labels_new_users = [];
$data_new_users = [];
$total_nuevos_usuarios = 0;

$sql_new_users = "SELECT 
                        DATE(fecha_registro) as dia, 
                        COUNT(*) as total_dia
                  FROM usuario
                  WHERE fecha_registro >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                  GROUP BY DATE(fecha_registro)
                  ORDER BY dia ASC";

if ($stmt_users = $conn->prepare($sql_new_users)) {
    $stmt_users->execute();
    $resultado_users = $stmt_users->get_result();
    while ($fila = $resultado_users->fetch_assoc()) {
        $labels_new_users[] = $fila['dia'];
        $data_new_users[] = $fila['total_dia'];
        $total_nuevos_usuarios += $fila['total_dia'];
    }
    $stmt_users->close();
}


// --- INICIO: NUEVA CONSULTA (GRÁFICO 5: ESTADO VIP) ---
$labels_vip = [];
$data_vip = [];
$total_vips_activos = 0; // Para el nuevo KPI

$sql_vip_status = "SELECT 
                        CASE 
                            WHEN vip_status = 'Active' THEN 'VIP Activo'
                            ELSE 'No VIP' 
                        END as status_vip,
                        COUNT(*) as total
                   FROM usuario
                   GROUP BY status_vip";

if ($stmt_vip = $conn->prepare($sql_vip_status)) {
    $stmt_vip->execute();
    $resultado_vip = $stmt_vip->get_result();
    while ($fila = $resultado_vip->fetch_assoc()) {
        $labels_vip[] = $fila['status_vip'];
        $data_vip[] = $fila['total'];
        
        if ($fila['status_vip'] == 'VIP Activo') {
            $total_vips_activos = $fila['total'];
        }
    }
    $stmt_vip->close();
}
// --- FIN: NUEVA CONSULTA ---


// --- OBTENER LISTA DE PRODUCTOS (Para el <select> de predicción) ---
$productos_para_predecir = [];
$sql_productos = "SELECT id_producto, nombre FROM producto WHERE activo = 1 ORDER BY nombre ASC";
if ($result_prods = $conn->query($sql_productos)) {
    $productos_para_predecir = $result_prods->fetch_all(MYSQLI_ASSOC);
}

// --- LÓGICA PARA LLAMAR A LA API DE PREDICCIÓN ---
$prediccion_labels = json_encode([]);
$prediccion_data = json_encode([]);
$prediccion_producto_nombre = "";
$prediccion_error = "";
$id_producto_predecir = 0; // Inicializar

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id_producto_predecir'])) {
    $id_producto_predecir = $_POST['id_producto_predecir'];

    // Buscar el nombre del producto
    foreach($productos_para_predecir as $p) {
        if ($p['id_producto'] == $id_producto_predecir) {
            $prediccion_producto_nombre = $p['nombre'];
            break;
        }
    }

// --- USAR cURL PARA LLAMAR A TU API DE PYTHON ---
    $ch = curl_init();

    // 1. IMPORTANTE: Usar protocolo HTTPS expl�cito
    curl_setopt($ch, CURLOPT_URL, "https://127.0.0.1:5000/predict_demand"); 
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['id_producto' => $id_producto_predecir]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    // 2. IMPORTANTE: Ignorar verificaci�n de certificados SSL
    // (Necesario porque el certificado es de 'bitware.site' pero conectamos a '127.0.0.1')
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    // 3. IMPORTANTE: Aumentar tiempo de espera (Python tarda en entrenar)
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); 

    $api_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); // Obtener c�digo HTTP
    
    if (curl_errno($ch)) {
        // Capturar error si cURL falla antes de recibir respuesta
        $prediccion_error = "Error de cURL: " . curl_error($ch);
    }
    
    curl_close($ch);
    $api_response = curl_exec($ch);    
    if ($http_code == 200 && $api_response) {
        $data = json_decode($api_response, true);
        if (isset($data['success']) && $data['success'] == true) {
            $prediccion_labels = json_encode($data['forecast_labels']);
            $prediccion_data = json_encode($data['forecast_data']);
        } else {
            $prediccion_error = $data['error'] ?? "Error desconocido en la API.";
        }
    } elseif ($http_code == 404) {
         $error_data = json_decode($api_response, true);
         $prediccion_error = $error_data['error'] ?? "Datos insuficientes para predecir.";
    } else {
        $prediccion_error = "No se pudo conectar con la API de predicción (HTTP {$http_code}). Asegúrate de que app.py esté corriendo.";
        error_log("Error de cURL en ver_reportes.php: " . curl_error($ch)); // Para tus logs
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<body>
    <div class="container">
        <a href="dashboard.php" class="back-link">&larr; Volver al Panel de Administrador</a>
        <h1>Reportes Generales de la Plataforma</h1>

        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-title">Ingresos (Últ. 30 días)</div>
                <div class="kpi-value">$<?php echo number_format($total_ingresos_periodo, 0, ',', '.'); ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-title">Nuevos Usuarios (Últ. 30 días)</div>
                <div class="kpi-value"><?php echo $total_nuevos_usuarios; ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-title">Usuarios VIP Activos</div>
                <div class="kpi-value"><?php echo $total_vips_activos; ?></div>
            </div>
        </div>

        <div class="charts-grid">
            
            <div class="chart-container">
                <h2>Ingresos Totales (Últimos 30 Días)</h2>
                <canvas id="ventasChart"></canvas>
            </div>

            <div class="chart-container">
                <h2>Top 5 Vendedores (por Ingresos)</h2>
                <canvas id="topVendedoresChart"></canvas>
            </div>
            
            <div class="chart-container">
                <h2>Top 5 Productos (por Ingresos)</h2>
                <canvas id="topProductosChart"></canvas>
            </div>

            <div class="chart-container">
                <h2>Nuevos Usuarios (Últimos 30 Días)</h2>
                <canvas id="newUsersChart"></canvas>
            </div>

            <div class="chart-container">
                <h2>Distribución de Usuarios (VIP)</h2>
                <canvas id="vipStatusChart"></canvas>
            </div>

            <div class="chart-container" style="grid-column: 1 / -1;"> <h2>Predicción de Demanda (Próximos 30 días)</h2>
                
                <form action="ver_reportes.php" method="POST" style="display:flex; gap:10px; margin-bottom:20px;">
                    <select name="id_producto_predecir" required style="flex-grow:1; padding:5px;">
                        <option value="">-- Selecciona un producto para predecir --</option>
                        <?php foreach($productos_para_predecir as $producto): ?>
                            <option value="<?php echo $producto['id_producto']; ?>" <?php if($id_producto_predecir == $producto['id_producto']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($producto['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" style="padding: 5px 15px;">Predecir</button>
                </form>

                <?php if (!empty($prediccion_producto_nombre) && empty($prediccion_error)): ?>
                    <p>Mostrando predicción para: <strong><?php echo htmlspecialchars($prediccion_producto_nombre); ?></strong></p>
                    <canvas id="prediccionChart"></canvas>
                <?php elseif (!empty($prediccion_error)): ?>
                    <p style="color: red;">Error al predecir '<?php echo htmlspecialchars($prediccion_producto_nombre); ?>': <?php echo htmlspecialchars($prediccion_error); ?></p>
                <?php else: ?>
                    <p>Selecciona un producto y presiona "Predecir" para ver la demanda futura.</p>
                <?php endif; ?>
            </div>
            </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        // --- Convertir datos de PHP a JavaScript ---
        const labelsVentas = <?php echo json_encode($labels_ventas); ?>;
        const dataVentas = <?php echo json_encode($data_ventas); ?>;
        const labelsTopVend = <?php echo json_encode($labels_top_vendedores); ?>;
        const dataTopVend = <?php echo json_encode($data_top_vendedores); ?>;
        const labelsTopProd = <?php echo json_encode($labels_top_productos); ?>;
        const dataTopProd = <?php echo json_encode($data_top_productos); ?>;
        const labelsNewUsers = <?php echo json_encode($labels_new_users); ?>;
        const dataNewUsers = <?php echo json_encode($data_new_users); ?>;
        const labelsVip = <?php echo json_encode($labels_vip); ?>;
        const dataVip = <?php echo json_encode($data_vip); ?>;

        // --- Configuración Gráfico 1: Ventas por Día (Línea) ---
        const ctxVentas = document.getElementById('ventasChart').getContext('2d');
        if (ctxVentas) {
            new Chart(ctxVentas, {
                type: 'line',
                data: {
                    labels: labelsVentas,
                    datasets: [{
                        label: 'Ingresos por día (CLP)',
                        data: dataVentas,
                        borderColor: 'rgba(13, 110, 253, 1)',
                        backgroundColor: 'rgba(13, 110, 253, 0.1)',
                        fill: true,
                        tension: 0.1
                    }]
                }
            });
        }

        // --- Configuración Gráfico 2: Top Vendedores (Dona) ---
        const ctxTopVend = document.getElementById('topVendedoresChart').getContext('2d');
        if (ctxTopVend && dataTopVend.length > 0) {
            new Chart(ctxTopVend, {
                type: 'doughnut',
                data: {
                    labels: labelsTopVend,
                    datasets: [{
                        label: 'Ingresos por Vendedor',
                        data: dataTopVend,
                        backgroundColor: [
                            'rgba(13, 110, 253, 0.7)',
                            'rgba(25, 135, 84, 0.7)',
                            'rgba(255, 193, 7, 0.7)',
                            'rgba(220, 53, 69, 0.7)',
                            'rgba(108, 117, 125, 0.7)'
                        ]
                    }]
                }
            });
        }

        // --- Configuración Gráfico 3: Top Productos (Barras Horizontales) ---
        const ctxTopProductos = document.getElementById('topProductosChart').getContext('2d');
        if (ctxTopProductos && dataTopProd.length > 0) {
            new Chart(ctxTopProductos, {
                type: 'bar',
                data: {
                    labels: labelsTopProd,
                    datasets: [{
                        label: 'Ingresos por Producto (CLP)',
                        data: dataTopProd,
                        backgroundColor: 'rgba(75, 192, 192, 0.5)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    }]
                },
                options: { indexAxis: 'y', plugins: { legend: { display: false } } }
            });
        }

        // --- Configuración Gráfico 4: Nuevos Usuarios (Barras Verticales) ---
        const ctxNewUsers = document.getElementById('newUsersChart').getContext('2d');
        if (ctxNewUsers && dataNewUsers.length > 0) {
            new Chart(ctxNewUsers, {
                type: 'bar',
                data: {
                    labels: labelsNewUsers,
                    datasets: [{
                        label: 'Nuevos Usuarios por Día',
                        data: dataNewUsers,
                        backgroundColor: 'rgba(255, 99, 132, 0.5)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: { y: { ticks: { stepSize: 1 } } },
                    plugins: { legend: { display: false } }
                }
            });
        }
        
        // --- Configuración Gráfico 5: ESTADO VIP ---
        const ctxVipStatus = document.getElementById('vipStatusChart').getContext('2d');
        if (ctxVipStatus && dataVip.length > 0) {
            new Chart(ctxVipStatus, {
                type: 'pie', // Gráfico de Torta
                data: {
                    labels: labelsVip,
                    datasets: [{
                        label: 'Total Usuarios',
                        data: dataVip,
                        backgroundColor: [
                            'rgba(13, 110, 253, 0.7)', // Azul para 'VIP Activo'
                            'rgba(108, 117, 125, 0.7)' // Gris para 'No VIP'
                        ],
                        borderColor: [
                            'rgba(13, 110, 253, 1)',
                            'rgba(108, 117, 125, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    }
                }
            });
        }
        
        // --- SCRIPT PARA GRÁFICO DE PREDICCIÓN ---
        <?php if (!empty($prediccion_producto_nombre) && empty($prediccion_error)): ?>
            const ctxPrediccion = document.getElementById('prediccionChart').getContext('2d');
            if(ctxPrediccion) {
                new Chart(ctxPrediccion, {
                    type: 'line',
                    data: {
                        labels: <?php echo $prediccion_labels; ?>,
                        datasets: [{
                            label: 'Unidades pronosticadas',
                            data: <?php echo $prediccion_data; ?>,
                            borderColor: 'rgba(220, 53, 69, 1)',
                            backgroundColor: 'rgba(220, 53, 69, 0.1)',
                            fill: true,
                            tension: 0.1,
                            borderDash: [5, 5]
                        }]
                    },
                    options: {
                        scales: {
                            y: { beginAtZero: true, ticks: { stepSize: 1 } }
                        }
                    }
                });
            }
        <?php endif; ?>
    </script>
</body>
</html>
<footer class="footer">
<?php
require 'includes/footer.php';
?>
