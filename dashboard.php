<?php
session_start();
// --- NUEVO: Incluir el actualizador de actividad ---
require_once "check_activity.php";
// --- FIN NUEVO ---
// Si no hay sesión iniciada, redirigir al login
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// Se verifica que la variable de permisos exista para evitar errores.
if (!isset($_SESSION["permisos"])) {
    header("location: logout.php");
    exit;
}

// Determinar la ruta de la foto de perfil
$foto_perfil_url = 'uploads/default.png'; // Imagen por defecto
if (isset($_SESSION["foto_perfil"]) && !empty($_SESSION["foto_perfil"]) && file_exists('uploads/' . $_SESSION["foto_perfil"])) {
    $foto_perfil_url = 'uploads/' . $_SESSION["foto_perfil"];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Bitware</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="images/favicon.ico" type="image/ico"> <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/chatbot.css">
</head>
<body>
    <div class="dashboard-container">
        
<div class="dashboard-container">
        
        <header class="dashboard-header">
            <img src="<?php echo htmlspecialchars($foto_perfil_url); ?>" alt="Foto de Perfil" class="profile-pic">
            <div class="welcome-message">
                
                <h1>
                    Hola, <b><?php echo htmlspecialchars($_SESSION["nombre"]); ?></b>
                
                    <?php // Badge de Rol (Admin o Vendedor) ?>
                    <?php if ($_SESSION["permisos"] === 'A'): ?>
                        <span class="role-badge admin-badge" title="Administrador">
                            <i class="bi bi-shield-lock-fill"></i> Admin
                        </span>
                    <?php elseif ($_SESSION["permisos"] === 'V'): ?>
                        <span class="role-badge vendor-badge" title="Vendedor">
                            <i class="bi bi-shop"></i> Vendedor
                        </span>
                    <?php endif; ?>

                    <?php // Badge de VIP (se muestra independientemente del rol) ?>
                    <?php if (isset($_SESSION['is_vip']) && $_SESSION['is_vip'] === true): ?>
                        <span class="role-badge vip-badge" title="Usuario VIP">
                            <i class="bi bi-patch-check-fill"></i> VIP
                        </span>
                    <?php endif; ?>
                </h1>
                <p>Bienvenido a tu panel de control. Desde aquí puedes gestionar tu cuenta y actividad.</p>
            </div>
        </header>

        <main class="panel-container">
            <?php if ($_SESSION["permisos"] === 'A'): // Panel de Administrador ?> 
                
                <h2>Panel de Administrador</h2>
                <div class="dashboard-grid">
                    <a href="editar_perfil.php" class="dashboard-card">
                        <div class="card-icon"><i class="bi bi-person-gear"></i></div>
                        <span class="card-title">Editar Mi Perfil</span>
                    </a>
                    <a href="gestionar_usuarios.php" class="dashboard-card">
                        <div class="card-icon"><i class="bi bi-people"></i></div>
                        <span class="card-title">Gestionar Usuarios</span>
                    </a>
                    <a href="gestionar_pedidos.php" class="dashboard-card">
                        <div class="card-icon"><i class="bi bi-receipt-cutoff"></i></div>
                        <span class="card-title">Gestionar Pedidos</span>
                    </a>
                    <a href="gestionar_inventario.php" class="dashboard-card">
                        <div class="card-icon"><i class="bi bi-box-seam"></i></div>
                        <span class="card-title">Gestionar Inventario</span>
                    </a>
                     <a href="gestionar_solicitudes.php" class="dashboard-card">
                        <div class="card-icon"><i class="bi bi-calendar2-check"></i></div>
                        <span class="card-title">Gestionar Solicitudes</span>
                    </a>
                    <a href="gestionar_tickets.php" class="dashboard-card"> 
                        <div class="card-icon"><i class="bi bi-ticket-perforated"></i></div>
                        <span class="card-title">Gestionar Tickets</span> 
                    </a>
                    <a href="ver_reportes.php" class="dashboard-card">
                        <div class="card-icon"><i class="bi bi-bar-chart-line"></i></div>
                        <span class="card-title">Ver Reportes</span>
                    </a>
                    <a href="catalogo.php" class="dashboard-card">
                        <div class="card-icon"><i class="bi bi-shop"></i></div>
                        <span class="card-title">Ver Catálogo</span>
                    </a>
                    <a href="gestionar_cupones.php" class="dashboard-card">
                        <div class="card-icon"><i class="bi bi-ticket-detailed"></i></div>
                        <span class="card-title">Gestionar Cupones</span>
                    </a>
                    <a href="gestionar_productos.php" class="dashboard-card">
                        <div class="card-icon"><i class="bi bi-boxes"></i></div>
                        <span class="card-title">Gestionar Productos</span>
                    </a>
                    <a href="reporte_wishlist.php" class="dashboard-card">
                        <div class="card-icon"><i class="bi bi-heart"></i></div>
                        <span class="card-title">Reporte de Deseados</span>
                    </a>
                    <a href="mis_pedidos.php" class="dashboard-card">
                        <div class="card-icon"><i class="bi bi-receipt"></i></div>
                        <span class="card-title">Mis Pedidos</span>
                    </a>
                    <a href="carrito.php" class="dashboard-card">
                        <div class="card-icon"><i class="bi bi-cart"></i></div>
                        <span class="card-title">Carrito</span>
                    </a>
                    <a href="index.php" class="dashboard-card">
                        <div class="card-icon"><i class="bi bi-house-door"></i></div>
                        <span class="card-title">Ir a la Tienda</span>
                    </a>
                </div>

            <?php elseif ($_SESSION["permisos"] === 'V'): // Panel de Vendedor ?>
                
                <h2>Panel de Vendedor</h2>
                <p>Bienvenido a tu panel de vendedor. Aquí puedes gestionar tus productos y ver tus ventas.</p>
                <div class="dashboard-grid">
                    
                    <a href="editar_perfil.php" class="dashboard-card">
                        <div class="card-icon"><i class="bi bi-person-gear"></i></div>
                        <span class="card-title">Editar Mi Perfil</span>
                    </a>
                    
                    <a href="mis_productos.php" class="dashboard-card">
                        <div class="card-icon"><i class="bi bi-boxes"></i></div>
                        <span class="card-title">Mis Productos</span>
                    </a>
                    
                    <a href="mis_ventas.php" class="dashboard-card">
                        <div class="card-icon"><i class="bi bi-receipt-cutoff"></i></div>
                        <span class="card-title">Mis Ventas</span>
                    </a>

                    <a href="vendedor_pedidos.php" class="dashboard-card">
                        <div class="card-icon"><i class="bi bi-box-seam"></i></div>
                        <span class="card-title">Gestionar Mis Pedidos</span>
                    </a>

                    <a href="reportes_vendedor.php" class="dashboard-card">
                        <div class="card-icon"><i class="bi bi-bar-chart-line"></i></div>
                        <span class="card-title">Mis Reportes</span>
                    </a>

                    <a href="mis_tickets.php" class="dashboard-card">
                        <div class="card-icon"><i class="bi bi-ticket-perforated"></i></div>
                        <span class="card-title">Mis Tickets</span> 
                    </a>

                    <a href="catalogo.php" class="dashboard-card">
                        <div class="card-icon"><i class="bi bi-shop"></i></div>
                        <span class="card-title">Ver Catálogo</span>
                    </a>

                    <a href="index.php" class="dashboard-card">
                        <div class="card-icon"><i class="bi bi-house-door"></i></div>
                        <span class="card-title">Ir a la Tienda</span>
                    </a>
                </div> 
            <?php else: // Panel de Cliente (Usuario 'U') ?> 
                
                <h2>Panel de Cliente</h2>
                <div class="dashboard-grid">
                    
                    <?php if (!isset($_SESSION['is_vip']) || $_SESSION['is_vip'] !== true): ?>
                    <a href="hacerse_vip.php" class="dashboard-card" style="background-color: #e7f1ff; border-color: #0d6efd;">
                        <div class="card-icon"><i class="bi bi-patch-check-fill"></i></div>
                        <span class="card-title">¡Conviértete en VIP!</span>
                    </a>
                    <?php endif; ?>
                    <a href="editar_perfil.php" class="dashboard-card">
                        <div class="card-icon"><i class="bi bi-person-gear"></i></div>
                        <span class="card-title">Editar Mi Perfil</span>
                    </a>
                    <a href="catalogo.php" class="dashboard-card">
                        <div class="card-icon"><i class="bi bi-shop"></i></div>
                        <span class="card-title">Ver Catálogo</span>
                    </a>
                    <a href="mis_pedidos.php" class="dashboard-card">
                        <div class="card-icon"><i class="bi bi-receipt"></i></div>
                        <span class="card-title">Mis Pedidos</span>
                    </a>
                    <a href="carrito.php" class="dashboard-card">
                        <div class="card-icon"><i class="bi bi-cart"></i></div>
                        <span class="card-title">Carrito</span>
                    </a>
                    <a href="mi_lista_deseados.php" class="dashboard-card">
                        <div class="card-icon"><i class="bi bi-heart"></i></div>
                        <span class="card-title">Mi Lista de Deseados</span>
                    </a>
                    <a href="mis_solicitudes.php" class="dashboard-card">
                        <div class="card-icon"><i class="bi bi-calendar2-check"></i></div>
                        <span class="card-title">Mis Solicitudes</span>
                    </a>
                    <a href="mis_tickets.php" class="dashboard-card"> 
                        <div class="card-icon"><i class="bi bi-ticket-perforated"></i></div>
                        <span class="card-title">Mis Tickets</span> 
                    </a>
                    <a href="index.php" class="dashboard-card">
                        <div class="card-icon"><i class="bi bi-house-door"></i></div>
                        <span class="card-title">Ir a la Tienda</span>
                    </a>
                </div>
            <?php endif; ?>
        </main>

        <div class="logout-section">
            <a href="logout.php" class="btn-logout">
                <i class="bi bi-box-arrow-right"></i>
                <span>Cerrar Sesión</span>
            </a>
        </div>
    </div> 
</body>
</html>
<footer class="footer">
<?php
// --- AÑADE ESTA LÍNEA AL FINAL DE TU ARCHIVO ---
require 'includes/footer.php';
?>