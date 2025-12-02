<?php
session_start();
require_once "check_activity.php";

// 1. VERIFICACIÓN DE SEGURIDAD (SOLO VENDEDORES 'V')
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["permisos"] !== 'V') {
    header("location: login.php");
    exit;
}

require '/var/www/config/config.php';
$id_vendedor_actual = $_SESSION['id'];

// --- 2. CONSULTAS SQL PARA LOS GRÁFICOS ---

// --- GRÁFICO 1: VENTAS TOTALES (ÚLTIMOS 30 DÍAS) ---
$labels_ventas = [];
$data_ventas = [];
$total_ingresos_vendedor_periodo = 0; // <-- AÑADE ESTA LÍNEA

$sql_ventas_dia = "SELECT 
                        DATE(p.fecha_pedido) as dia, 
                        SUM(pp.cantidad * pp.precio_unitario) as total_dia
                   FROM producto pr
                   JOIN pedidos_productos pp ON pr.id_producto = pp.id_producto
                   JOIN pedidos p ON pp.id_pedido = p.id_pedido
                   WHERE pr.id_vendedor = ? 
                     AND p.estado IN ('Pagado', 'Enviado', 'Entregado') 
                     AND p.fecha_pedido >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                   GROUP BY DATE(p.fecha_pedido)
                   ORDER BY dia ASC";

if ($stmt_ventas = $conn->prepare($sql_ventas_dia)) {
    $stmt_ventas->bind_param("i", $id_vendedor_actual);
    $stmt_ventas->execute();
    $resultado_ventas = $stmt_ventas->get_result();
    while ($fila = $resultado_ventas->fetch_assoc()) {
        $labels_ventas[] = $fila['dia'];
        $data_ventas[] = $fila['total_dia'];
        $total_ingresos_vendedor_periodo += $fila['total_dia'];
    }
    $stmt_ventas->close();
}

// --- GRÁFICO 2: TOP 5 PRODUCTOS (POR UNIDADES) ---
$labels_top_productos = [];
$data_top_productos = [];

$sql_top_productos = "SELECT 
                            pr.nombre, 
                            SUM(pp.cantidad) as total_vendido
                      FROM producto pr
                      JOIN pedidos_productos pp ON pr.id_producto = pp.id_producto
                      JOIN pedidos p ON pp.id_pedido = p.id_pedido
                      WHERE pr.id_vendedor = ? 
                        AND p.estado IN ('Pagado', 'Enviado', 'Entregado')
                      GROUP BY pr.id_producto, pr.nombre
                      ORDER BY total_vendido DESC
                      LIMIT 5";

if ($stmt_top = $conn->prepare($sql_top_productos)) {
    $stmt_top->bind_param("i", $id_vendedor_actual);
    $stmt_top->execute();
    $resultado_top = $stmt_top->get_result();
    while ($fila = $resultado_top->fetch_assoc()) {
        $labels_top_productos[] = $fila['nombre'];
        $data_top_productos[] = $fila['total_vendido'];
    }
    $stmt_top->close();
}

// --- NUEVO: OBTENER LISTA DE PRODUCTOS (Para el <select> de predicción) ---
$productos_para_predecir = [];
// Consulta SEGURA: solo trae productos de ESTE vendedor
$sql_productos = "SELECT id_producto, nombre FROM producto WHERE activo = 1 AND id_vendedor = ? ORDER BY nombre ASC";
if ($stmt_prods = $conn->prepare($sql_productos)) {
    $stmt_prods->bind_param("i", $id_vendedor_actual);
    $stmt_prods->execute();
    $productos_para_predecir = $stmt_prods->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_prods->close();
}

// --- NUEVO: LÓGICA PARA LLAMAR A LA API DE PREDICCIÓN ---
$prediccion_labels = json_encode([]);
$prediccion_data = json_encode([]);
$prediccion_producto_nombre = "";
$prediccion_error = "";
$id_producto_predecir = 0; // Inicializar

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id_producto_predecir'])) {
    $id_producto_predecir = $_POST['id_producto_predecir'];

    // 1. Validar que el producto seleccionado pertenece al vendedor
    $producto_valido = false;
    foreach($productos_para_predecir as $p) {
        if ($p['id_producto'] == $id_producto_predecir) {
            $prediccion_producto_nombre = $p['nombre'];
            $producto_valido = true;
            break;
        }
    }

    if ($producto_valido) {
        // 2. Llamar a la API de Python (app.py)
        $ch = curl_init();
        
        // 1. HTTPS
        curl_setopt($ch, CURLOPT_URL, "https://127.0.0.1:5000/predict_demand"); 
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['id_producto' => $id_producto_predecir]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        // 2. TIMEOUT
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); 

        // 3. BYPASS SSL (Crucial para localhost)
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $api_response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); // Definir variable http_code
        
        if (curl_errno($ch)) {
             $prediccion_error = "Error de cURL: " . curl_error($ch);
        }
        curl_close($ch);
        
        // ... resto de tu c�digo ...        
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
            $prediccion_error = "No se pudo conectar con la API de predicción (HTTP {$http_code}).";
            error_log("Error de cURL en reportes_vendedor.php: " . curl_error($ch));
        }
    } else {
        $prediccion_error = "ID de producto no válido o no te pertenece.";
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reportes de Ventas - Vendedor</title>
    <link rel="stylesheet" href="css/GReportes.css?v=1.0"> 
    <link rel="icon" href="images/favicon.ico" type="image/ico">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="css/chatbot.css">
    <link rel="stylesheet" href="css/responsive.css">
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-link">&larr; Volver a mi Panel</a>
        <h1>Mis Reportes de Ventas</h1>
        <p>Reportes generados solo de tus productos en pedidos pagados, enviados o entregados.</p>

        <div class="kpi-grid">
            <div class="kpi-card ingresos">
                <div class="icon">
                    <i class="bi bi-cash-stack"></i> 
                </div>
                <div class="info">
                    <h3>Ingresos (Últ. 30 días)</h3>
                    <p>$<?php echo number_format($total_ingresos_vendedor_periodo, 0, ',', '.'); ?></p>
                </div>
            </div>
        </div>
        
        <div class="charts-grid">

            <div class="chart-container">
                <h2>Ventas de los Últimos 30 Días</h2>
                <canvas id="ventasChart"></canvas> 
            </div>

            <div class="chart-container">
                <h2>Top 5 Productos (Unidades Vendidas)</h2>
                <canvas id="topProductosChart"></canvas>
            </div>

            <div class="chart-container" style="grid-column: 1 / -1;"> 
                <h2>Predicción de Demanda (Próximos 30 días)</h2>
                
                <form action="reportes_vendedor.php" method="POST" style="display:flex; gap:10px; margin-bottom:20px;">
                    <select name="id_producto_predecir" required style="flex-grow:1; padding:5px;">
                        <option value="">-- Selecciona uno de TUS productos --</option>
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
                    <p>Selecciona uno de tus productos para ver la demanda futura.</p>
                <?php endif; ?>
            </div>
        </div> </div> <script>
        // --- Convertir datos de PHP a JavaScript ---
        const labelsVentas = <?php echo json_encode($labels_ventas); ?>;
        const dataVentas = <?php echo json_encode($data_ventas); ?>;
        const labelsTopProductos = <?php echo json_encode($labels_top_productos); ?>;
        const dataTopProductos = <?php echo json_encode($data_top_productos); ?>;

        // --- Configuración Gráfico 1: Ventas por Día (Línea) ---
        const ctxVentas = document.getElementById('ventasChart').getContext('2d');
        if (ctxVentas && dataVentas.length > 0) {
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

        // --- Configuración Gráfico 2: Top Productos (Barras Horizontales) ---
        const ctxTopProductos = document.getElementById('topProductosChart').getContext('2d');
        if (ctxTopProductos && dataTopProductos.length > 0) {
            new Chart(ctxTopProductos, {
                type: 'bar',
                data: {
                    labels: labelsTopProductos,
                    datasets: [{
                        label: 'Unidades Vendidas',
                        data: dataTopProductos,
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.5)',
                            'rgba(54, 162, 235, 0.5)',
                            'rgba(255, 206, 86, 0.5)',
                            'rgba(75, 192, 192, 0.5)',
                            'rgba(153, 102, 255, 0.5)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    indexAxis: 'y', // Barras horizontales
                    scales: { x: { beginAtZero: true } },
                    plugins: { legend: { display: false } }
                }
            });
        }

        // --- INICIO: SCRIPT PARA GRÁFICO 3 (PREDICCIÓN) ---
        <?php if (!empty($prediccion_producto_nombre) && empty($prediccion_error)): ?>
            const ctxPrediccion = document.getElementById('prediccionChart').getContext('2d');
            if(ctxPrediccion) {
                new Chart(ctxPrediccion, {
                    type: 'line',
                    data: {
                        labels: <?php echo $prediccion_labels; ?>, // Datos de la API
                        datasets: [{
                            label: 'Unidades pronosticadas',
                            data: <?php echo $prediccion_data; ?>, // Datos de la API
                            borderColor: 'rgba(220, 53, 69, 1)',
                            backgroundColor: 'rgba(220, 53, 69, 0.1)',
                            fill: true,
                            tension: 0.1,
                            borderDash: [5, 5] // Línea punteada
                        }]
                    },
                    options: {
                        scales: {
                            y: { beginAtZero: true, ticks: { stepSize: 1 } } // Muestra 1, 2, 3...
                        }
                    }
                });
            }
        <?php endif; ?>
        // --- FIN: SCRIPT GRÁFICO 3 ---
    </script>

    <?php
    require 'includes/footer.php';
    ?>
</body>
</html>