<?php
session_start();
require '/var/www/config/config.php';

$titulo_pagina = 'Gestión de Solicitudes';
$css_pagina_especifica = "css/GSolicitudes.css"; 
require 'includes/head.php'; // head básico

// --- Seguridad ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["permisos"] !== 'A') {
    header("location: login.php");
    exit;
}

$mensaje_exito = "";
$mensaje_error = "";

// --- Actualizar estado ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id_solicitud'])) {
    $id_solicitud = $_POST['id_solicitud'];
    $nuevo_estado = $_POST['estado'];

    $sql_update = "UPDATE solicitudes_servicio SET estado = ? WHERE id = ?";
    if ($stmt = $conn->prepare($sql_update)) {
        $stmt->bind_param("si", $nuevo_estado, $id_solicitud);
        if ($stmt->execute()) {
            $mensaje_exito = "✅ Estado actualizado correctamente (#$id_solicitud)";
        } else {
            $mensaje_error = "❌ Error al actualizar el estado.";
        }
        $stmt->close();
    }
}

// --- Filtro ---
$filtro_estado = $_GET['filtro_estado'] ?? 'todos';
$sql_where = ($filtro_estado != 'todos') ? " WHERE estado = '".$conn->real_escape_string($filtro_estado)."'" : "";

$sql = "SELECT id, nombre_cliente, email_cliente, tipo_servicio, descripcion_solicitud, presupuesto_estimado, estado, fecha_solicitud
        FROM solicitudes_servicio
        $sql_where
        ORDER BY fecha_solicitud DESC";
$solicitudes = $conn->query($sql);
?>

<div class="container">
    <div class="header-bar">
        <h1>Gestión de Solicitudes</h1>
        <a href="dashboard.php" class="back-link">← Volver</a>
    </div>

    <div class="filter-nav">
        <a href="?filtro_estado=todos" class="<?= ($filtro_estado=='todos')?'active':''; ?>">Todas</a>
        <a href="?filtro_estado=Pendiente" class="<?= ($filtro_estado=='Pendiente')?'active':''; ?>">Pendientes</a>
        <a href="?filtro_estado=En Proceso" class="<?= ($filtro_estado=='En Proceso')?'active':''; ?>">En Proceso</a>
        <a href="?filtro_estado=Completada" class="<?= ($filtro_estado=='Completada')?'active':''; ?>">Completadas</a>
    </div>

    <?php if ($mensaje_exito): ?>
        <div class="alert-success"><?= $mensaje_exito; ?></div>
    <?php elseif ($mensaje_error): ?>
        <div class="alert-danger"><?= $mensaje_error; ?></div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Cliente</th>
                <th>Email</th>
                <th>Tipo Servicio</th>
                <th>Presupuesto (€)</th>
                <th>Fecha</th>
                <th>Estado</th>
                <th>Descripción</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($solicitudes && $solicitudes->num_rows > 0): ?>
                <?php while ($row = $solicitudes->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['id']; ?></td>
                        <td><?= htmlspecialchars($row['nombre_cliente']); ?></td>
                        <td><?= htmlspecialchars($row['email_cliente']); ?></td>
                        <td><?= htmlspecialchars($row['tipo_servicio']); ?></td>
                        <td><?= number_format($row['presupuesto_estimado'], 2, ',', '.'); ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($row['fecha_solicitud'])); ?></td>
                        <td>
                            <form method="POST" class="update-form">
                                <input type="hidden" name="id_solicitud" value="<?= $row['id']; ?>">
                                <select name="estado">
                                    <option value="Pendiente" <?= ($row['estado']=='Pendiente')?'selected':''; ?>>Pendiente</option>
                                    <option value="En Proceso" <?= ($row['estado']=='En Proceso')?'selected':''; ?>>En Proceso</option>
                                    <option value="Completada" <?= ($row['estado']=='Completada')?'selected':''; ?>>Completada</option>
                                </select>
                                <button type="submit" class="btn-update">Actualizar</button>
                            </form>
                        </td>
                        <td>
                            <button class="btn-details" data-descripcion="<?= htmlspecialchars($row['descripcion_solicitud']); ?>">
                                Ver
                            </button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="8" style="text-align:center;color:#888;">No hay solicitudes registradas.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

    <!-- MODAL FUERA DEL CONTENEDOR -->
    <div id="custom-modal-overlay" class="custom-modal-overlay" style="display:none;">
    <div class="custom-modal-box">
        <div class="custom-modal-header">
        <h2>Descripción de la Solicitud</h2>
        <button class="custom-modal-close">&times;</button>
        </div>
        <div class="custom-modal-body" id="modal-description"></div>
    </div>
    </div>


    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const overlay = document.getElementById("custom-modal-overlay");
            const modalBody = document.getElementById("modal-description");
            const closeBtn = document.querySelector(".custom-modal-close");

            closeBtn.addEventListener("click", () => overlay.style.display = "none");

            document.querySelectorAll(".btn-details").forEach(btn => {
                btn.addEventListener("click", () => {
                    modalBody.textContent = btn.getAttribute("data-descripcion") || "Sin descripción disponible.";
                    overlay.style.display = "flex";
                });
            });
        });

    </script>
</body>
</html>