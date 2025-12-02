<?php
session_start();
require '/var/www/config/config.php';

// Preparamos una respuesta en formato JSON
header('Content-Type: application/json');

// 1. Verificación de seguridad: El usuario debe estar logueado
if (!isset($_SESSION['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Debes iniciar sesión para añadir a favoritos.']);
    exit;
}

// 2. Obtener los datos enviados por JavaScript
$data = json_decode(file_get_contents('php://input'), true);
$id_producto = $data['id_producto'] ?? 0;
$id_usuario = $_SESSION['id'];

if ($id_producto == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Producto no válido.']);
    exit;
}

// 3. Lógica para añadir o quitar de favoritos
try {
    // Primero, verificamos si el producto ya está en la lista de favoritos del usuario
    $sql_check = "SELECT id_favorito FROM favoritos WHERE id_usuario = ? AND id_producto = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("ii", $id_usuario, $id_producto);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows > 0) {
        // Si ya existe, lo eliminamos (acción de "quitar de favoritos")
        $sql_delete = "DELETE FROM favoritos WHERE id_usuario = ? AND id_producto = ?";
        $stmt_delete = $conn->prepare($sql_delete);
        $stmt_delete->bind_param("ii", $id_usuario, $id_producto);
        $stmt_delete->execute();
        $stmt_delete->close();
        
        echo json_encode(['status' => 'success', 'action' => 'removed']);

    } else {
        // Si no existe, lo insertamos (acción de "añadir a favoritos")
        $sql_insert = "INSERT INTO favoritos (id_usuario, id_producto) VALUES (?, ?)";
        $stmt_insert = $conn->prepare($sql_insert);
        $stmt_insert->bind_param("ii", $id_usuario, $id_producto);
        $stmt_insert->execute();
        $stmt_insert->close();

        echo json_encode(['status' => 'success', 'action' => 'added']);
    }
    
    $stmt_check->close();
    $conn->close();

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Hubo un error en el servidor.']);
}
?>