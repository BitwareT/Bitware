<?php
session_start();
require '/var/www/config/config.php';

// --- 1. VARIABLES DEL HEAD (CORREGIDAS) ---
// (Ya no usamos las variables que causaban el error)
$titulo_pagina = 'Iniciar Sesión | Bitware';
$css_pagina_especifica = "css/login.css"; 
// No se necesita $body_atributos aquí
require 'includes/head.php'; // O 'head.php', el que uses para 1 CSS

// --- 2. LÓGICA DE USUARIOS EN TIEMPO REAL (NUEVO) ---
$total_usuarios_activos = 0;
// Contamos todos los usuarios que están marcados como 'activos'
$sql_count = "SELECT COUNT(*) as total FROM usuario WHERE activo = 1";
if ($result_count = $conn->query($sql_count)) {
    $data_count = $result_count->fetch_assoc();
    $total_usuarios_activos = $data_count['total'] ?? 0;
}
// --- FIN DE LA LÓGICA NUEVA ---

$email = $password = "";
$email_err = $password_err = $login_err = "";

// Redirigir si ya está logueado
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("location: dashboard.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (empty(trim($_POST["email"]))) {
        $email_err = "Por favor, ingrese su correo electrónico.";
    } else {
        $email = strtolower(trim($_POST["email"]));
    }

    if (empty(trim($_POST["password"]))) {
        $password_err = "Por favor, ingrese su contraseña.";
    } else {
        $password = trim($_POST["password"]);
    }

    if (empty($email_err) && empty($password_err)) {
        
        // --- !! CORRECCIÓN 1: 'activo' (con v) !! ---
        $sql = "SELECT id_usuario, nombre, password, permisos, foto_perfil, verificado, activo, vip_status, vip_expiry_date FROM usuario WHERE email = ?";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $email);
            if ($stmt->execute()) {
                $stmt->store_result();
                if ($stmt->num_rows == 1) {
                    
                    // --- !! CORRECCIÓN 2: 'activo' (con v) !! ---
                    $stmt->bind_result($id, $nombre, $hashed_password, $permisos, $foto_perfil, $verificado, $activo, $vip_status, $vip_expiry);

                    if ($stmt->fetch()) {
                        if (password_verify($password, $hashed_password)) {

                            // --- !! CORRECCIÓN 3: Lógica limpiada y anidada correctamente !! ---
                            
                            if ($verificado == 1) {

                                // Comprobación de Activo
                                if ($activo == 1) { 
                                    // ÉXITO: La cuenta está verificada Y activa.
                                    $_SESSION["loggedin"] = true;
                                    $_SESSION["id"] = $id;
                                    $_SESSION["nombre"] = $nombre;
                                    $_SESSION["permisos"] = $permisos;
                                    $_SESSION["foto_perfil"] = $foto_perfil;
                                    $_SESSION['is_vip'] = false; // Por defecto no es VIP

                                    if ($vip_status == 'Active' && (is_null($vip_expiry) || $vip_expiry >= date('Y-m-d'))) {
                                        $_SESSION['is_vip'] = true;
                                    }

                                    // LÓGICA PARA CARGAR EL CARRITO GUARDADO
                                    $sql_load_cart = "SELECT id_producto, cantidad FROM carritos_guardados WHERE id_usuario = ?";
                                    if ($stmt_cart = $conn->prepare($sql_load_cart)) {
                                        $stmt_cart->bind_param("i", $id);
                                        $stmt_cart->execute();
                                        $result_cart = $stmt_cart->get_result();

                                        if ($result_cart->num_rows > 0) {
                                            if (!isset($_SESSION['carrito'])) {
                                                $_SESSION['carrito'] = [];
                                            }
                                            while ($item = $result_cart->fetch_assoc()) {
                                                if (isset($_SESSION['carrito'][$item['id_producto']])) {
                                                    $_SESSION['carrito'][$item['id_producto']] += $item['cantidad'];
                                                } else {
                                                    $_SESSION['carrito'][$item['id_producto']] = $item['cantidad'];
                                                }
                                            }

                                            $sql_delete_cart = "DELETE FROM carritos_guardados WHERE id_usuario = ?";
                                            if($stmt_delete = $conn->prepare($sql_delete_cart)){
                                                $stmt_delete->bind_param("i", $id);
                                                $stmt_delete->execute();
                                                $stmt_delete->close();
                                            }
                                        }
                                        $stmt_cart->close();
                                    }

                                    if (isset($_SESSION['accion_pendiente']) && $_SESSION['accion_pendiente'] == 'agregar_al_carrito' && isset($_SESSION['producto_pendiente'])) {
                                        // ... (lógica de agregar producto pendiente) ...
                                    }

                                    // Redirección normal al dashboard
                                    header("location: dashboard.php");
                                    exit;
                                    
                                } else {
                                    // ERROR: La cuenta está desactivada.
                                    $login_err = "Tu cuenta ha sido desactivada. Por favor, contacta con soporte@bitware.site para ver el problema.";
                                }
                                
                            } else {
                                // ERROR: La cuenta no está verificada.
                                $login_err = "Tu cuenta aún no ha sido verificada. Revisa tu correo electrónico (incluyendo spam) y haz clic en el enlace.";
                            }
                        } else {
                            $login_err = "Correo o contraseña incorrectos.";
                        }
                    }
                } else {
                    $login_err = "Correo o contraseña incorrectos.";
                }
            } else {
                $login_err = "Oops! Algo salió mal al intentar verificar tus datos. Inténtalo más tarde.";
            }
            $stmt->close();
        } else {
             $login_err = "Oops! Algo salió mal en la conexión. Inténtalo más tarde.";
        }
    }
    // (No cerramos la conexión aquí porque la usamos en el HTML)
}
?>

<div class="form-panel-left">
    <div class="left-content">
        <div class="logo-lightning">
            <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="bi bi-lightning-fill" viewBox="0 0 16 16">
                <path d="M5.52.359A.5.5 0 0 1 6 0h4a.5.5 0 0 1 .47.659L8.636 6H13.5a.5.5 0 0 1 .395.807l-7 9a.5.5 0 0 1-.873-.418L7.361 10H3.5a.5.5 0 0 1-.395-.807l7-9a.5.5 0 0 1 .47-.659z"/>
            </svg>
        </div>
        <h1>Bienvenido a Bitware</h1>
        <p>
            La plataforma tecnológica que impulsa tu negocio
            hacia el futuro digital
        </p>
        <div class="stats-grid">
            <div>
                <span><?php echo number_format($total_usuarios_activos); ?>+</span>
                <p>Usuarios activos</p>
            </div>
            <div>
                <span>99.9%</span>
                <p>Uptime</p>
            </div>
            <div>
                <span>24/7</span>
                <p>Soporte</p>
            </div>
        </div>
    </div>
</div>

<div class="login-container">
    <img src="Recursos\Recursos_login\img-contenedor.png" alt="Logo Bitware">
    <h2>Iniciar Sesión</h2>
    <p class="subtitle">Bienvenido a Bitware, ingresa tus credenciales.</p>

    <?php
    $error_a_mostrar = !empty($login_err) ? $login_err : (isset($_SESSION['mensaje_error']) ? $_SESSION['mensaje_error'] : '');
    if (!empty($error_a_mostrar)) {
        echo '<div class="alert alert-danger" style="color:#b30000; background:#ffe6e6; padding:10px; border-radius:6px; margin-bottom:15px; font-size: 0.9em;">' . htmlspecialchars($error_a_mostrar) . '</div>';
        unset($_SESSION['mensaje_error']);
    }
    ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <input type="email" name="email" placeholder="Correo Electrónico" class="<?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($email); ?>">
        <span class="invalid-feedback"><?php echo $email_err; ?></span>

        <input type="password" name="password" placeholder="Contraseña" class="<?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>">
        <span class="invalid-feedback"><?php echo $password_err; ?></span>

        <div class="remember">
            <label>
                <input type="checkbox" name="remember">
                <span class="fake-checkbox"></span> Recordarme
            </label>
        </div>

        <div class="forgot-password">
            <a href="forgot_password.php">¿Olvidaste tu contraseña?</a>
        </div>

        <button type="submit">Iniciar Sesión</button>

        <div class="register-link">
            ¿No tienes una cuenta? <a href="register.php">Regístrate ahora</a><br>
            <a href="index.php">Regresar al inicio</a>
        </div>
    </form>
</div>
<?php $conn->close(); ?>
<?php $conn->close(); ?>