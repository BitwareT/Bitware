<?php
session_start();
// --- NUEVO: Incluir el actualizador de actividad ---
require_once "check_activity.php"; //
// --- FIN NUEVO ---
// 1. VERIFICACIÓN DE SEGURIDAD
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { //
    header("location: login.php");
    exit;
}

require '/var/www/config/config.php'; //

$id_usuario_actual = $_SESSION["id"]; //

$pedidos = [];
$sql = "SELECT 
            p.id_pedido, p.fecha_pedido, p.total, p.estado,
            MIN(pr.nombre) AS nombre_producto, 
            MIN(pr.imagen_principal) AS imagen_producto
        FROM pedidos p
        LEFT JOIN pedidos_productos pp ON p.id_pedido = pp.id_pedido
        LEFT JOIN producto pr ON pp.id_producto = pr.id_producto
        WHERE p.id_usuario = ?
        GROUP BY p.id_pedido, p.fecha_pedido, p.total, p.estado 
        ORDER BY p.id_pedido DESC"; //

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $id_usuario_actual);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $pedidos = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        echo "Error al ejecutar la consulta.";
    }
    $stmt->close();
} else {
    echo "Error al preparar la consulta.";
}
$conn->close();
?>
<?php
// --- 1. DEFINES LAS VARIABLES PARA EL HEAD ---
$titulo_pagina = ($vista_actual == 'detalle') ? htmlspecialchars($producto['nombre']) : 'Mis Pedidos - Bitware';
$css_pagina_especifica = "css/mis_pedidos.css"; 
$body_atributos = 'data-stock="' . htmlspecialchars($producto['stock'] ?? '99') . '"';

// --- 2. LLAMAS AL HEAD ---
require 'includes/head.php'; // o head.php, el que estés usando
?>
<body>

    <header class="main-header"> <div class="header-content">
            <a href="index.php" class="logo">Bitware</a>
            <a href="dashboard.php" class="account-link">
                <i class="fas fa-user-circle"></i> Mi Cuenta
            </a>
        </div>
    </header>

    <main class="container">
        <div class="orders-header"> <h1>Mis Pedidos</h1>
            <p>Aquí puedes ver tu historial de compras.</p>
        </div>

        <?php if (empty($pedidos)): ?>
            <div class="order-card empty-state"> <h2>No has realizado ningún pedido todavía.</h2>
                <p>¡Explora nuestro catálogo y encuentra los mejores componentes!</p>
                <a href="catalogo.php" class="btn-primary">Ir al Catálogo</a>
            </div>
        <?php else: ?>
            <div class="orders-list">
                <?php foreach ($pedidos as $pedido): ?>
                    <?php
                        // ============================================
                        // ==== LÓGICA DE ESTADO (Original) ====
                        // ============================================
                        $estado = strtolower($pedido['estado'] ?? 'pendiente'); //
                        $status_class = '';
                        $status_icon = '';
                        $status_text = '';

                        switch ($estado) { //
                            case 'pagado': //
                                $status_class = 'status-pagado'; //
                                $status_icon = '<i class="fas fa-money-check-alt"></i>'; //
                                $status_text = 'Pagado'; //
                                break;
                            case 'enviado': //
                                $status_class = 'status-en-camino'; //
                                $status_icon = '<i class="fas fa-truck"></i>'; //
                                $status_text = 'En camino'; //
                                break;
                            case 'entregado': //
                            case 'completado': //
                                $status_class = 'status-entregado'; //
                                $status_icon = '<i class="fas fa-check-circle"></i>'; //
                                $status_text = 'Entregado'; //
                                break;
                            case 'pendiente': //
                            case 'fallido': //
                                $status_class = 'status-pendiente'; //
                                $status_icon = '<i class="fas fa-clock"></i>'; //
                                $status_text = 'Pendiente'; //
                                break;
                            case 'cancelado': //
                                $status_class = 'status-cancelado'; //
                                $status_icon = '<i class="fas fa-times-circle"></i>'; //
                                $status_text = 'Cancelado'; //
                                break;
                            case 'en devolución': // (Estado corregido, antes 'devolución')
                                $status_class = 'status-devolucion'; //
                                $status_icon = '<i class="fas fa-undo-alt"></i>'; //
                                $status_text = 'En Devolución'; //
                                break;
                            default: //
                                $status_class = 'status-pendiente'; //
                                $status_icon = '<i class="fas fa-question-circle"></i>'; //
                                $status_text = htmlspecialchars(ucfirst($pedido['estado'])); //
                        }
                    ?>
                    <div class="order-card">
                        <img src="uploads/<?php echo htmlspecialchars($pedido['imagen_producto'] ?? 'default.png'); ?>" alt="Producto" class="order-image"> <div class="order-info">
                            <div class="order-id-status">
                                <span class="order-id">Pedido #<?php echo str_pad($pedido['id_pedido'], 6, '0', STR_PAD_LEFT); ?></span>
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <?php echo $status_icon; ?> <?php echo $status_text; ?>
                                </span>
                            </div>
                            <h2 class="product-name"><?php echo htmlspecialchars($pedido['nombre_producto']); ?> (y posiblemente otros)</h2> <p class="order-date"><?php echo date("d M, Y", strtotime($pedido['fecha_pedido'])); ?></p> <p class="order-price">$<?php echo number_format($pedido['total'], 0, ',', '.'); ?></p> </div>
                        <div class="order-actions">
                            <a href="ver_detalle_pedido_usuario.php?id=<?php echo $pedido['id_pedido']; ?>" class="details-link">Ver detalles</a>
                            
                            <?php if ($estado == 'entregado'): ?>
                                <button class="btn-devolucion" data-id="<?php echo $pedido['id_pedido']; ?>">
                                    <i class="fas fa-undo-alt"></i> Solicitar Devolución
                                </button>
                            <?php endif; ?>
                        </div>
                        </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <div class="modal-overlay" id="devolucion-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Solicitar Devolución</h2>
                <button class="modal-close" id="modal-close-btn">&times;</button>
            </div>
            <div class="modal-body">
                <form id="devolucion-form" enctype="multipart/form-data">
                    <p>Estás solicitando una devolución para el Pedido <strong id="modal-pedido-id">#XXXX</strong>.</p>
                    
                    <input type="hidden" id="modal_id_pedido" name="id_pedido">
                    
                    <div class="form-group">
                        <label for="motivo">Motivo de la devolución:</label>
                        <select id="motivo" name="motivo" required>
                            <option value="">-- Selecciona un motivo --</option>
                            <option value="Producto dañado o defectuoso">Producto dañado o defectuoso</option>
                            <option value="Recibí un producto incorrecto">Recibí un producto incorrecto</option>
                            <option value="Ya no lo quiero / No me gustó">Ya no lo quiero / No me gustó</option>
                            <option value="Otro">Otro (explicar abajo)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="mensaje">Por favor, danos más detalles (requerido):</label>
                        <textarea id="mensaje" name="mensaje" rows="4" placeholder="Ej: El procesador llegó con los pines doblados." required></textarea>
                    </div>

                    <div class="form-group">
                        <label for="evidencia">Adjuntar evidencia (Fotos):</label>
                        <input type="file" id="evidencia" name="evidencia[]" multiple accept="image/*" style="width: 100%;">
                        <small>(Puedes seleccionar varias imágenes. Límite 5MB por archivo)</small>
                    </div>
                    <button type="submit" class="btn-submit-devolucion" id="form-submit-btn">Enviar Solicitud</button>
                </form>
                <div id="modal-respuesta"></div>
            </div>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('devolucion-modal');
        const closeModalBtn = document.getElementById('modal-close-btn');
        const devolucionForm = document.getElementById('devolucion-form');
        const modalPedidoIdSpan = document.getElementById('modal-pedido-id');
        const modalPedidoIdInput = document.getElementById('modal_id_pedido');
        const modalRespuesta = document.getElementById('modal-respuesta');
        const submitBtn = document.getElementById('form-submit-btn');

        // 1. Abrir el modal
        document.querySelectorAll('.btn-devolucion').forEach(button => {
            button.addEventListener('click', function() {
                const pedidoId = this.getAttribute('data-id');
                modalPedidoIdSpan.textContent = '#' + pedidoId.padStart(6, '0');
                modalPedidoIdInput.value = pedidoId;
                devolucionForm.reset();
                modalRespuesta.innerHTML = '';
                submitBtn.disabled = false;
                devolucionForm.style.display = 'block';
                modal.style.display = 'flex';
            });
        });

        // 2. Cerrar el modal
        closeModalBtn.addEventListener('click', () => modal.style.display = 'none');
        modal.addEventListener('click', (e) => {
            if (e.target === modal) modal.style.display = 'none';
        });

        // 3. Enviar el formulario (AJAX)
        devolucionForm.addEventListener('submit', function(e) {
            e.preventDefault();
            modalRespuesta.innerHTML = '<p>Procesando...</p>';
            submitBtn.disabled = true;
            const formData = new FormData(this);
            
            fetch('solicitar_devolucion.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    modalRespuesta.innerHTML = `<p style="color: green;">${data.message}</p>`;
                    devolucionForm.style.display = 'none';
                    // Recargar la página después de 2 seg
                    setTimeout(() => location.reload(), 2000);
                } else {
                    modalRespuesta.innerHTML = `<p style="color: red;">Error: ${data.message}</p>`;
                    submitBtn.disabled = false;
                }
            })
            .catch(error => {
                modalRespuesta.innerHTML = '<p style="color: red;">Hubo un error de conexión.</p>';
                submitBtn.disabled = false;
                console.error('Error:', error);
            });
        });
    });
    </script>
    </body>
</html>
<footer class="footer">
<?php
require 'includes/footer.php';
?>