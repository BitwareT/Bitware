<?php
session_start();
require '/var/www/config/config.php';

// Seguridad: Solo para administradores
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["permisos"] !== 'A') {
    header("location: login.php");
    exit;
}

// --- 1. DEFINES LAS VARIABLES PARA EL HEAD ---
// (Estas líneas son las que faltaban)
$titulo_pagina = ($vista_actual == 'detalle') ? htmlspecialchars($producto['nombre']) : 'Gestión de Cupones';
$css_pagina_especifica = "css/Gproductos.css"; // <-- ¡ESTA ES LA LÍNEA CLAVE QUE FALTA!
$body_atributos = 'data-stock="' . htmlspecialchars($producto['stock'] ?? '99') . '"';
require 'includes/head.php';

// Lógica para crear un nuevo cupón
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['crear_cupon'])) {
    $codigo = strtoupper(trim($_POST['codigo']));
    $tipo = $_POST['tipo_descuento'];
    $valor = $_POST['valor'];
    $expiracion = !empty($_POST['fecha_expiracion']) ? $_POST['fecha_expiracion'] : NULL;

    $sql = "INSERT INTO cupones (codigo, tipo_descuento, valor, fecha_expiracion) VALUES (?, ?, ?, ?)";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ssds", $codigo, $tipo, $valor, $expiracion);
        $stmt->execute();
        $stmt->close();
        header("location: gestionar_cupones.php");
        exit;
    }
}

// Lógica para eliminar un cupón
if (isset($_GET['eliminar'])) {
    $id_cupon = $_GET['eliminar'];
    $sql = "DELETE FROM cupones WHERE id_cupon = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $id_cupon);
        $stmt->execute();
        $stmt->close();
        header("location: gestionar_cupones.php");
        exit;
    }
}

// Obtener todos los cupones para mostrarlos
$cupones = $conn->query("SELECT * FROM cupones ORDER BY id_cupon DESC");
?>

<!DOCTYPE html>
<html lang="es">
<body>
    <div class="container">
        <a href="dashboard.php">&larr; Volver al Panel de Administrador</a>
        <h1>Gestión de Cupones de Descuento</h1>

        <div class="form-container">
            <h2>Crear Nuevo Cupón</h2>
            <form action="gestionar_cupones.php" method="post">
                <div class="form-group">
                    <label>Código (Ej: BIENVENIDO10)</label>
                    <input type="text" name="codigo" required>
                </div>
                <div class="form-group">
                    <label>Tipo de Descuento</label>
                    <select name="tipo_descuento">
                        <option value="porcentaje">Porcentaje (%)</option>
                        <option value="fijo">Monto Fijo ($)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Valor (Ej: 10 para 10% o 5000 para $5.000)</label>
                    <input type="number" name="valor" step="0.01" min="10" required>
                </div>
                <div class="form-group">
                    <label>Fecha de Expiración (Opcional)</label>
                    <input type="date" name="fecha_expiracion">
                </div>
                <button type="submit" name="crear_cupon" class="btn btn-primary">Crear Cupón</button>
            </form>
        </div>

        <h2>Lista de Cupones</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Código</th>
                    <th>Tipo</th>
                    <th>Valor</th>
                    <th>Expira</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($cupones && $cupones->num_rows > 0): ?>
                    <?php while ($cupon = $cupones->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $cupon['id_cupon']; ?></td>
                            <td><?php echo htmlspecialchars($cupon['codigo']); ?></td>
                            <td><?php echo ucfirst($cupon['tipo_descuento']); ?></td>
                            <td><?php echo ($cupon['tipo_descuento'] == 'porcentaje') ? $cupon['valor'] . '%' : '$' . number_format($cupon['valor']); ?></td>
                            <td><?php echo $cupon['fecha_expiracion'] ? date('d/m/Y', strtotime($cupon['fecha_expiracion'])) : 'No expira'; ?></td>
                            <td>
                                <a href="gestionar_cupones.php?eliminar=<?php echo $cupon['id_cupon']; ?>" onclick="return confirm('¿Estás seguro?');" class="delete">Eliminar</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6">No hay cupones creados.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
