<?php
session_start();
// Apuntamos un nivel hacia arriba para encontrar el config.php
require '/var/www/config/config.php';

$id_pedido = $_GET['id_pedido'] ?? 'N/A';
$monto = $_GET['monto'] ?? '0';

// OBTENER TODOS LOS PRODUCTOS DEL PEDIDO PARA EL MODAL
$productos_pedido = [];
if ($id_pedido !== 'N/A') {
    $sql = "SELECT p.nombre, p.imagen_principal, pp.cantidad, pp.precio_unitario
            FROM pedidos_productos pp
            JOIN producto p ON pp.id_producto = p.id_producto
            WHERE pp.id_pedido = ?";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $id_pedido);
        $stmt->execute();
        $result = $stmt->get_result();
        $productos_pedido = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webpay - Pagar</title>
    <link rel="icon" href="../images/favicon.ico" type="image/ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: white; padding: 25px; border-radius: 8px; max-width: 500px; width: 90%; box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px; }
        .modal-close { background: none; border: none; font-size: 1.5em; cursor: pointer; }
        .product-item { display: flex; gap: 15px; align-items: center; margin-bottom: 10px; }
        .product-item img { width: 50px; height: 50px; object-fit: cover; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="page-accent-top"></div>
    <header class="main-header"></header>

    <main class="payment-wrapper">
        <section class="payment-panel">
            <div class="left-col">
                <div class="paying-to"><p class="small-muted">Estás pagando en:</p><div class="merchant-badge"><div class="merchant-square">B</div><div class="merchant-name">Bitware</div></div></div>
                <div class="amount-block"><p class="small-muted">Monto a pagar:</p><p class="amount-value">$<?php echo number_format($monto, 0, ',', '.'); ?></p></div>
                <a href="#" id="show-details-link" class="details-link"><i class="fas fa-plus-circle"></i> Ver detalle de compra</a>
                <div class="info-box"><i class="fas fa-info-circle"></i><p>Ahora Webpay detecta automáticamente si tu tarjeta es débito, crédito o prepago.</p></div>
                <p class="payment-method-title">Selecciona tu medio de pago:</p>
                <div class="payment-options">
                    <button class="option-card selected" type="button"><i class="fas fa-credit-card"></i><div class="option-text"><strong>Tarjetas</strong><small>Crédito, Débito, Prepago</small></div></button>
                </div>
                <a href="http://bitware.site/carrito.php" class="cancel-link">Anular compra y volver</a>
            </div>
            <div class="right-col">
                <p class="form-title">Ingresa los datos de tu tarjeta:</p>
                <div class="card-mockup">
                    <div class="card-mockup-top"><div class="chip"></div><div class="bank-icon"><i class="fas fa-university"></i></div></div>
                    <div class="card-mockup-number" id="mock-card-number">XXXX XXXX XXXX XXXX</div>
                    <div class="card-mockup-footer"><div class="expiry" id="mock-expiry">XX/XX</div><div class="card-icon" id="mock-card-icon"><i class="far fa-credit-card"></i></div></div>
                </div>
                <form action="procesar_pago.php" method="post" class="card-form" autocomplete="off">
                    <input type="hidden" name="id_pedido" value="<?php echo htmlspecialchars($id_pedido); ?>">
                    <div class="form-row"><label for="card_number">Número de tarjeta</label><input type="text" id="card_number" name="card_number" placeholder="Prueba con 4... para éxito, 5... para fallo" required inputmode="numeric" maxlength="19"></div>
                    <div class="form-row" style="display: flex; gap: 10px;">
                        <div style="flex: 1;"><label for="expiry_date">Fecha Expiración</label><input type="text" id="expiry_date" name="expiry_date" placeholder="MM/YY" required maxlength="5"></div>
                        <div style="flex: 1;"><label for="cvv">CVV</label><input type="text" id="cvv" name="cvv" placeholder="123" required maxlength="4" inputmode="numeric"></div>
                    </div>
                    <button type="submit" class="submit-btn">Continuar</button>
                </form>
                <div class="accepted-cards"><div class="card-badge">VISA</div><div class="card-badge">MC</div><div class="card-badge">AMEX</div></div>
            </div>
        </section>
    </main>

    <footer class="page-footer"><div class="footer-inner"><span class="footer-dot">●</span><span class="footer-host">Bitware Store</span></div></footer>

    <div class="modal-overlay" id="details-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Detalle del Pedido #<?php echo htmlspecialchars($id_pedido); ?></h2>
                <button class="modal-close" id="close-details-modal">&times;</button>
            </div>
            <div class="modal-body">
                <?php if (!empty($productos_pedido)): ?>
                    <?php foreach ($productos_pedido as $producto): ?>
                    <div class="product-item">
                        <img src="../uploads/<?php echo htmlspecialchars($producto['imagen_principal']); ?>" alt="">
                        <div><strong><?php echo htmlspecialchars($producto['nombre']); ?></strong> (x<?php echo $producto['cantidad']; ?>)</div>
                        <div style="margin-left: auto; font-weight: bold;">$<?php echo number_format($producto['precio_unitario'] * $producto['cantidad'], 0, ',', '.'); ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No se pudo cargar el detalle de los productos.</p>
                <?php endif; ?>
                <hr>
                <div style="text-align: right; font-weight: bold; font-size: 1.2em;">Total: $<?php echo number_format($monto, 0, ',', '.'); ?></div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const showLink = document.getElementById('show-details-link');
            const closeBtn = document.getElementById('close-details-modal');
            const modal = document.getElementById('details-modal');
            showLink.addEventListener('click', (e) => { e.preventDefault(); modal.style.display = 'flex'; });
            closeBtn.addEventListener('click', () => { modal.style.display = 'none'; });
            modal.addEventListener('click', (e) => { if (e.target === modal) modal.style.display = 'none'; });

        // Lógica de la Tarjeta Interactiva
        const cardNumberInput = document.getElementById('card_number');
        const expiryInput = document.getElementById('expiry_date');
        const mockNumber = document.getElementById('mock-card-number');
        const mockExpiry = document.getElementById('mock-expiry');
        const mockIcon = document.getElementById('mock-card-icon');

        cardNumberInput.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '').substring(0, 16);
            value = value.replace(/(\d{4})/g, '$1 ').trim();
            this.value = value;
            mockNumber.textContent = value || 'XXXX XXXX XXXX XXXX';
            const firstDigit = value.charAt(0);
            if (firstDigit === '4') mockIcon.innerHTML = '<i class="fab fa-cc-visa" style="font-size: 2em;"></i>';
            else if (firstDigit === '5') mockIcon.innerHTML = '<i class="fab fa-cc-mastercard" style="font-size: 2em;"></i>';
            else mockIcon.innerHTML = '<i class="far fa-credit-card"></i>';
        });

        expiryInput.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '').substring(0, 4);
            if (value.length > 2) value = value.slice(0, 2) + '/' + value.slice(2);
            this.value = value;
            mockExpiry.textContent = value || 'XX/XX';
        });
    });
    </script>
</body>
</html>
