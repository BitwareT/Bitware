<?php
session_start();
require '/var/www/config/config.php';

// 1. VERIFICAR SI HAY UN USUARIO Y UN CARRITO QUE GUARDAR
if (isset($_SESSION['id']) && !empty($_SESSION['carrito'])) {
    $id_usuario = $_SESSION['id'];

    // 2. LIMPIAR CUALQUIER CARRITO ANTIGUO GUARDADO PARA ESTE USUARIO
    $sql_delete = "DELETE FROM carritos_guardados WHERE id_usuario = ?";
    if ($stmt_delete = $conn->prepare($sql_delete)) {
        $stmt_delete->bind_param("i", $id_usuario);
        $stmt_delete->execute();
        $stmt_delete->close();
    }

    // 3. GUARDAR CADA PRODUCTO DEL CARRITO ACTUAL EN LA BASE DE DATOS
    $sql_insert = "INSERT INTO carritos_guardados (id_usuario, id_producto, cantidad) VALUES (?, ?, ?)";
    if ($stmt_insert = $conn->prepare($sql_insert)) {
        foreach ($_SESSION['carrito'] as $id_producto => $cantidad) {
            $stmt_insert->bind_param("iii", $id_usuario, $id_producto, $cantidad);
            $stmt_insert->execute();
        }
        $stmt_insert->close();
    }
}

// 4. DESTRUIR LA SESIÓN (COMPORTAMIENTO ORIGINAL)
$_SESSION = array();
session_destroy();

// Redirigir a la página de inicio
header("location: index.php");
exit;
?>