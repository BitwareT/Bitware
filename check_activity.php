<?php
// check_activity.php

// (Asegúrate de que la sesión esté iniciada ANTES de incluir este archivo)
if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true && isset($_SESSION["id"])) {

    // Incluye la conexión (Asegúrate de que la ruta sea la absoluta correcta)
    if (!isset($conn) || !$conn) {
        require_once "/var/www/config/config.php"; 
    }

    // Usar una conexión temporal si $conn no estaba definida antes
    $temp_conn = false;
    if (!isset($conn) || !$conn) {
         $conn_activity = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
         if ($conn_activity) {
             $conn = $conn_activity; // Usar la conexión temporal
             $temp_conn = true;
         }
    }

    // Ejecutar la actualización solo si tenemos una conexión válida
    if (isset($conn) && $conn) {
        // 1. Actualiza la última actividad (Tu código existente)
        $sql_update_activity = "UPDATE usuario SET last_activity = NOW() WHERE id_usuario = ?";
        if ($stmt_activity = $conn->prepare($sql_update_activity)) {
            $stmt_activity->bind_param("i", $_SESSION["id"]);
            $stmt_activity->execute();
            $stmt_activity->close();
        } else {
            error_log("Error al preparar la actualización de actividad: " . $conn->error);
        }

        // --- !! INICIO: PASO 7 - REVISIÓN VIP EN CADA PÁGINA !! ---
        
        // 2. Si la sesión dice que es VIP, volvemos a verificar
        if (isset($_SESSION['is_vip']) && $_SESSION['is_vip'] === true) {
            $hoy = date('Y-m-d');
            $id_usuario_vip_check = $_SESSION['id'];

            $sql_vip_check = "SELECT vip_status, vip_expiry_date FROM usuario WHERE id_usuario = ?";
            if ($stmt_vip_check = $conn->prepare($sql_vip_check)) {
                $stmt_vip_check->bind_param("i", $id_usuario_vip_check);
                $stmt_vip_check->execute();
                $res_vip_check = $stmt_vip_check->get_result();
                
                if ($row_vip = $res_vip_check->fetch_assoc()) {
                    // Si la fecha de expiración NO es nula Y ya pasó
                    if ($row_vip['vip_status'] == 'Active' && !is_null($row_vip['vip_expiry_date']) && $row_vip['vip_expiry_date'] < $hoy) {
                        
                        // 1. Actualiza la sesión (deja de ser VIP)
                        $_SESSION['is_vip'] = false;
                        
                        // 2. Actualiza la BD (lo marca como Expirado)
                        $sql_expire = "UPDATE usuario SET vip_status = 'Expired' WHERE id_usuario = ?";
                        if ($stmt_expire = $conn->prepare($sql_expire)) {
                            $stmt_expire->bind_param("i", $id_usuario_vip_check);
                            $stmt_expire->execute();
                            $stmt_expire->close();
                        }
                    }
                }
                $stmt_vip_check->close();
            }
        }
        // --- !! FIN: PASO 7 !! ---

    } else {
         error_log("Error: No se pudo establecer conexión a BD en check_activity.php");
    }

    // Cerrar la conexión temporal si se creó aquí
    if ($temp_conn && isset($conn)) {
        $conn->close();
        unset($conn); 
    }
}
?>