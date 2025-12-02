<?php
session_start();
// --- NUEVO: Incluir el actualizador de actividad ---
require_once "check_activity.php";

// --- 1. DEFINES LAS VARIABLES PARA EL HEAD ---
$titulo_pagina = "Gestión de Usuarios";
$css_pagina_especifica = "css/Gusuarios.css"; 
require 'includes/head.php';

// --- NUEVO: Estilos en línea para los estados VIP ---
echo "
<style>
    .vip-status-active { color: #0d6efd; font-weight: bold; }
    .vip-status-expired { color: #6c757d; }
    .vip-status-none { color: #adb5bd; }
</style>
";
// --- FIN NUEVO ---


// 1. VERIFICACIÓN DE SEGURIDAD
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["id"]) || $_SESSION["permisos"] !== 'A') {
    header("location: login.php");
    exit;
}

require '/var/www/config/config.php';

// Definición de variables
$nombre = $email = $permisos = $password = "";
$vip_status = ""; 
$vip_expiry_date = "";
$nombre_err = $email_err = $permisos_err = $password_err = "";
$mensaje_exito = $error_general = "";
$modo_edicion = false;
$id_usuario_editar = 0;
$foto_actual = "";

// --- LÓGICA DE ACCIONES ---

// 2. LÓGICA PARA DESACTIVAR/REACTIVAR
if (isset($_GET["toggle_active"]) && isset($_GET['new_status'])) {
    $id_usuario_toggle = trim($_GET["toggle_active"]);
    if ($id_usuario_toggle != $_SESSION["id"]) {
        $sql = "UPDATE usuario SET activo = ? WHERE id_usuario = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ii", $_GET['new_status'], $id_usuario_toggle);
            if ($stmt->execute()) { header("location: gestionar_usuarios.php"); exit(); }
        }
    }
}

// 3. LÓGICA PARA ELIMINAR
if (isset($_GET["delete"])) {
    $id_usuario_eliminar = trim($_GET["delete"]);
    
    if ($id_usuario_eliminar != $_SESSION["id"]) {
        $sql_check = "SELECT id_pedido FROM pedidos WHERE id_usuario = ? LIMIT 1";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("i", $id_usuario_eliminar);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            $error_general = "No se puede eliminar este usuario porque tiene pedidos asociados. Desactívalo en su lugar.";
        } else {
            $conn->begin_transaction();
            try {
                $sql_backup = "INSERT INTO usuarios_eliminados (id_usuario, nombre, email, password, telefono, direccion, region, permisos, id_chat, foto_perfil, fecha_eliminacion, vip_status, vip_expiry_date)
                               SELECT id_usuario, nombre, email, password, telefono, direccion, region, permisos, id_chat, foto_perfil, CURRENT_TIMESTAMP, vip_status, vip_expiry_date 
                               FROM usuario WHERE id_usuario = ?";
                $stmt_backup = $conn->prepare($sql_backup);
                $stmt_backup->bind_param("i", $id_usuario_eliminar);
                $stmt_backup->execute();
                $stmt_backup->close();

                $sql_delete = "DELETE FROM usuario WHERE id_usuario = ?";
                $stmt_delete = $conn->prepare($sql_delete);
                $stmt_delete->bind_param("i", $id_usuario_eliminar);
                $stmt_delete->execute();
                $stmt_delete->close();
                
                $conn->commit();
                header("location: gestionar_usuarios.php");
                exit();
            } catch (mysqli_sql_exception $e) {
                $conn->rollback();
                $error_general = "Error al eliminar el usuario: " . $e->getMessage();
            }
        }
    }
}


// 4. LÓGICA PARA PROCESAR FORMULARIO (CREAR Y ACTUALIZAR)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_usuario_editar = $_POST["id_usuario"] ?? 0;

    if (empty(trim($_POST["nombre"]))) { $nombre_err = "El nombre es obligatorio."; } else { $nombre = trim($_POST["nombre"]); }
    if (empty(trim($_POST["email"]))) { $email_err = "El email es obligatorio."; } else { $email = strtolower(trim($_POST["email"])); }
    if (empty($_POST["permisos"])) { $permisos_err = "Debe seleccionar un permiso."; } else { $permisos = $_POST["permisos"]; }

    $vip_status_post = $_POST['vip_status'];
    $vip_expiry_post = !empty($_POST['vip_expiry_date']) ? $_POST['vip_expiry_date'] : NULL;
    if ($vip_status_post != 'Active') {
        $vip_expiry_post = NULL;
    }

    if (empty($id_usuario_editar) && (empty(trim($_POST["password"])) || strlen(trim($_POST["password"])) < 6)) { $password_err = "La contraseña es obligatoria (mínimo 6 caracteres)."; }
    if (!empty(trim($_POST["password"])) && strlen(trim($_POST["password"])) < 6) { $password_err = "La nueva contraseña debe tener al menos 6 caracteres."; }
    
    if (empty($nombre_err) && empty($email_err) && empty($permisos_err) && empty($password_err)) {
        
        if (!empty($id_usuario_editar)) { // ACTUALIZAR
            $password = trim($_POST["password"]);
            $sql = !empty($password) 
                ? "UPDATE usuario SET nombre = ?, email = ?, permisos = ?, password = ?, vip_status = ?, vip_expiry_date = ? WHERE id_usuario = ?" 
                : "UPDATE usuario SET nombre = ?, email = ?, permisos = ?, vip_status = ?, vip_expiry_date = ? WHERE id_usuario = ?";
            
            if ($stmt = $conn->prepare($sql)) {
                if (!empty($password)) { 
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt->bind_param("ssssssi", $nombre, $email, $permisos, $hashed_password, $vip_status_post, $vip_expiry_post, $id_usuario_editar); 
                } else { 
                    $stmt->bind_param("sssssi", $nombre, $email, $permisos, $vip_status_post, $vip_expiry_post, $id_usuario_editar); 
                }
                if ($stmt->execute()) {
                    header("location: gestionar_usuarios.php");
                    exit();
                }
            }
        } else { // CREAR
            $password = trim($_POST["password"]);
            $sql = "INSERT INTO usuario (nombre, email, password, permisos, vip_status, vip_expiry_date) VALUES (?, ?, ?, ?, ?, ?)";
            if ($stmt = $conn->prepare($sql)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt->bind_param("ssssss", $nombre, $email, $hashed_password, $permisos, $vip_status_post, $vip_expiry_post);
                if ($stmt->execute()) {
                    header("location: gestionar_usuarios.php");
                    exit();
                }
            }
        }
    }
}

// 5. LÓGICA PARA CARGAR DATOS EN EL FORMULARIO DE EDICIÓN
if (isset($_GET["edit"])) {
    $id_usuario_editar = trim($_GET["edit"]);

    if ($id_usuario_editar == $_SESSION["id"]) {
        header("location: editar_perfil.php");
        exit;
    }

    $sql = "SELECT nombre, email, permisos, foto_perfil, vip_status, vip_expiry_date FROM usuario WHERE id_usuario = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $id_usuario_editar);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($result->num_rows == 1) {
                $row = $result->fetch_assoc();
                $nombre = $row['nombre']; 
                $email = $row['email']; 
                $permisos = $row['permisos']; 
                $foto_actual = $row['foto_perfil'];
                $vip_status = $row['vip_status'];
                $vip_expiry_date = $row['vip_expiry_date'];
                $modo_edicion = true;
            }
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<body>
<div class="container">
    <a href="dashboard.php">&larr; Volver al Panel de Administrador</a>
    <h1>Gestión de Usuarios</h1>
    
    <?php if ($error_general) echo "<div class='alert-danger'>$error_general</div>"; ?>
    
    <div class="form-container">
        <h2><?php echo $modo_edicion ? "Editar Usuario (ID: " . $id_usuario_editar . ")" : "Agregar Nuevo Usuario"; ?></h2>
        <?php if ($mensaje_exito) echo "<div class='alert-success'>$mensaje_exito</div>"; ?>
        <?php if (!empty($error_general)) echo "<div class='alert-danger'>$error_general</div>"; ?>

        <form action="gestionar_usuarios.php<?php echo $modo_edicion ? '?edit='.$id_usuario_editar : ''; ?>" method="post">
            <?php if ($modo_edicion): ?>
                <input type="hidden" name="id_usuario" value="<?php echo $id_usuario_editar; ?>">
            <?php endif; ?>

            <?php
            if ($modo_edicion):
                $foto_perfil_url = 'uploads/default.png'; 
                if (!empty($foto_actual) && file_exists('uploads/' . $foto_actual)) {
                    $foto_perfil_url = 'uploads/' . htmlspecialchars($foto_actual);
                }
            ?>
                <div class="form-group" style="text-align: center; margin-bottom: 20px;">
                    <img src="<?php echo $foto_perfil_url; ?>" alt="Foto de Perfil" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid #eee;">
                </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label>Nombre</label>
                <input type="text" name="nombre" value="<?php echo htmlspecialchars($nombre); ?>">
                <span class="text-danger"><?php echo $nombre_err; ?></span>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>">
                <span class="text-danger"><?php echo $email_err; ?></span>
            </div>
            
            <div class="form-group">
                <label>Permisos</label>
                <select name="permisos">
                    <option value="">-- Seleccionar --</option>
                    <option value="A" <?php if($permisos == 'A') echo 'selected'; ?>>Administrador</option>
                    <option value="V" <?php if($permisos == 'V') echo 'selected'; ?>>Vendedor</option>
                    <option value="U" <?php if($permisos == 'U') echo 'selected'; ?>>Usuario</option>
                    </select>
                <span class="text-danger"><?php echo $permisos_err; ?></span>
            </div>
            <div class="form-group">
                <label>Contraseña</label>
                <input type="password" name="password" placeholder="<?php echo $modo_edicion ? 'Dejar en blanco para no cambiar' : ''; ?>">
                <span class="text-danger"><?php echo $password_err; ?></span>
                <?php if ($modo_edicion): ?>
                    <small style="color: #6c757d; display: block; margin-top: 5px;">Deja este campo en blanco si no deseas cambiar la contraseña actual.</small>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label>Estado VIP</label>
                <select name="vip_status">
                    <option value="None" <?php if(empty($vip_status) || $vip_status == 'None') echo 'selected'; ?>>No VIP</option>
                    <option value="Active" <?php if($vip_status == 'Active') echo 'selected'; ?>>Activo</option>
                    <option value="Expired" <?php if($vip_status == 'Expired') echo 'selected'; ?>>Expirado</option>
                </select>
            </div>
            <div class="form-group">
                <label>Fecha Expiración VIP</label>
                <input type="date" name="vip_expiry_date" value="<?php echo htmlspecialchars($vip_expiry_date); ?>">
                <small style="color: #6c757d; display: block; margin-top: 5px;">(Solo se guardará si el estado es "Activo")</small>
            </div>

            <div class="form-group">
                <button type="submit" class="btn <?php echo $modo_edicion ? 'btn-success' : 'btn-primary'; ?>">
                    <?php echo $modo_edicion ? "Actualizar Usuario" : "Crear Usuario"; ?>
                </button>
                <?php if ($modo_edicion): ?>
                    <a href="gestionar_usuarios.php" class="btn btn-cancel">Cancelar Edición</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <h2>Lista de Usuarios</h2>

    <div class="filter-controls">
        <?php 
            $filtro_permiso = $_GET['filtro_permiso'] ?? 'todos'; 
            $filtro_vip = $_GET['filtro_vip'] ?? 'todos'; 
        ?>
        <strong>Permisos:</strong>
        <a href="?filtro_permiso=todos&filtro_vip=<?php echo $filtro_vip; ?>" class="filter-btn <?php if($filtro_permiso == 'todos') echo 'active'; ?>">Todos</a>
        <a href="?filtro_permiso=A&filtro_vip=<?php echo $filtro_vip; ?>" class="filter-btn <?php if($filtro_permiso == 'A') echo 'active'; ?>">Admins</a>
        <a href="?filtro_permiso=V&filtro_vip=<?php echo $filtro_vip; ?>" class="filter-btn <?php if($filtro_permiso == 'V') echo 'active'; ?>">Vendedores</a>
        <a href="?filtro_permiso=U&filtro_vip=<?php echo $filtro_vip; ?>" class="filter-btn <?php if($filtro_permiso == 'U') echo 'active'; ?>">Usuarios</a>
        <span style="margin-left: 15px;"><strong>VIP:</strong></span>
        <a href="?filtro_vip=todos&filtro_permiso=<?php echo $filtro_permiso; ?>" class="filter-btn <?php if($filtro_vip == 'todos') echo 'active'; ?>">Todos</a>
        <a href="?filtro_vip=Active&filtro_permiso=<?php echo $filtro_permiso; ?>" class="filter-btn <?php if($filtro_vip == 'Active') echo 'active'; ?>">VIP Activos</a>
        <a href="?filtro_vip=Expired&filtro_permiso=<?php echo $filtro_permiso; ?>" class="filter-btn <?php if($filtro_vip == 'Expired') echo 'active'; ?>">VIP Expirados</a>
        <a href="?filtro_vip=None&filtro_permiso=<?php echo $filtro_permiso; ?>" class="filter-btn <?php if($filtro_vip == 'None') echo 'active'; ?>">No VIP</a>
    </div>

    <table>
        <thead><tr><th>ID</th><th>Nombre</th><th>Email</th><th>Permisos</th><th>Estado VIP</th><th>Estado</th><th>Online</th><th>Acciones</th></tr></thead>
        <tbody>
            <?php
            $sql_select = "SELECT id_usuario, nombre, email, permisos, activo, last_activity, vip_status, vip_expiry_date FROM usuario";
            
            $condiciones_where = [];
            $params = [];
            $types = "";

            if ($filtro_permiso != 'todos') {
                $condiciones_where[] = "permisos = ?";
                $params[] = $filtro_permiso;
                $types .= "s";
            }
            if ($filtro_vip != 'todos') {
                if ($filtro_vip == 'None') {
                    $condiciones_where[] = "(vip_status IS NULL OR vip_status = 'None')";
                } else {
                    $condiciones_where[] = "vip_status = ?";
                    $params[] = $filtro_vip;
                    $types .= "s";
                }
            }

            if (!empty($condiciones_where)) {
                $sql_select .= " WHERE " . implode(" AND ", $condiciones_where);
            }
            
            $sql_select .= " ORDER BY id_usuario DESC";
            $stmt = $conn->prepare($sql_select);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $row_class = $row['activo'] ? '' : 'inactive-row';
                    echo "<tr class='{$row_class}'>";
                    echo "<td>{$row['id_usuario']}</td>";
                    echo "<td>" . htmlspecialchars($row['nombre']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                    
                    // --- INICIO: COLUMNA PERMISOS CON BADGES ---
                    echo "<td>";
                    if ($row['permisos'] == 'A') {
                        echo '<span class="role-badge admin-badge" title="Administrador"><i class="bi bi-shield-lock-fill"></i> Admin</span>';
                    } elseif ($row['permisos'] == 'V') {
                        echo '<span class="role-badge vendor-badge" title="Vendedor"><i class="bi bi-shop"></i> Vendedor</span>';
                    } else {
                        echo '<span class="role-badge user-badge" title="Usuario"><i class="bi bi-person-fill"></i> Usuario</span>';
                    }
                    echo "</td>";
                    // --- FIN: COLUMNA PERMISOS CON BADGES ---
                    
                    // --- INICIO: COLUMNA ESTADO VIP CON BADGES (refactorizado) ---
                    echo "<td>";
                    if ($row['vip_status'] == 'Active') {
                        $expiry_text = $row['vip_expiry_date'] ? ' (Vence: ' . date('d/m/Y', strtotime($row['vip_expiry_date'])) . ')' : '';
                        echo '<span class="vip-badge-table vip-active" title="VIP Activo' . $expiry_text . '"><i class="bi bi-patch-check-fill"></i> VIP Activo</span>';
                    } elseif ($row['vip_status'] == 'Expired') {
                        echo '<span class="vip-badge-table vip-expired">Expirado</span>';
                    } else {
                        echo '<span class="vip-badge-table vip-none">No VIP</span>';
                    }
                    echo "</td>";
                    // --- FIN: COLUMNA ESTADO VIP ---
                    
                    echo "<td>" . ($row['activo'] ? '<span style="color: green;">Activo</span>' : '<span style="color: red;">Inactivo</span>') . "</td>";
                    
                    echo "<td>";
                    if ($row['last_activity']) {
                        $last_seen = strtotime($row['last_activity']);
                        $five_minutes_ago = time() - (5 * 60); 

                        if ($last_seen > $five_minutes_ago) {
                            echo '<span style="color: green; font-weight: bold;">● En línea</span>';
                        } else {
                            $time_diff = time() - $last_seen;
                            if ($time_diff < 3600) { echo '<span style="color: grey;">○ Visto hace ' . floor($time_diff / 60) . ' min</span>'; } 
                            elseif ($time_diff < 86400) { echo '<span style="color: grey;">○ Visto hace ' . floor($time_diff / 3600) . ' h</span>'; } 
                            else { echo '<span style="color: grey;">○ Visto ' . date('d/m/Y', $last_seen) . '</span>'; }
                        }
                    } else {
                        echo '<span style="color: grey;">○ Nunca</span>';
                    }
                    echo "</td>";

                    echo "<td class='actions'>";
                    echo "<a href='gestionar_usuarios.php?edit={$row['id_usuario']}' class='edit'>Editar</a>";
                    if ($row['id_usuario'] != $_SESSION['id']) {
                        if ($row['activo']) {
                            echo "<a href='gestionar_usuarios.php?toggle_active={$row['id_usuario']}&new_status=0' class='deactivate' onclick='return confirm(\"¿Desactivar?\");'>Desactivar</a>";
                        } else {
                            echo "<a href='gestionar_usuarios.php?toggle_active={$row['id_usuario']}&new_status=1' class='reactivate' onclick='return confirm(\"¿Reactivar?\");'>Reactivar</a>";
                        }
                        echo "<a href='gestionar_usuarios.php?delete={$row['id_usuario']}' class='delete' onclick='return confirm(\"¡ADVERTENCIA! El usuario será respaldado y eliminado permanentemente. ¿Continuar?\");'>Borrar</a>";
                    }
                    echo "</td></tr>";
                }
            } else {
                echo "<tr><td colspan='8'>No se encontraron usuarios con este filtro.</td></tr>";
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
