<?php
session_start();

header('Content-Type: application/json');

// 1. Obtener datos del JS
$raw_data_js = file_get_contents('php://input');
$data_from_js = json_decode($raw_data_js, true);

// 2. Inyectar datos de sesión (Seguridad)
$data_from_js['userId'] = $_SESSION['id'] ?? null;
$data_from_js['permisos'] = $_SESSION['permisos'] ?? 'U';
$data_from_js['nombre_usuario'] = $_SESSION['nombre'] ?? 'Invitado';
$data_from_js['email_usuario'] = $_SESSION['email'] ?? '';

$json_to_python = json_encode($data_from_js);

// 3. Enviar a Python (AHORA VIA HTTPS)
$ch = curl_init();

// CAMBIO IMPORTANTE: Usamos https://127.0.0.1:5000
curl_setopt($ch, CURLOPT_URL, "https://127.0.0.1:5000/chat");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $json_to_python);

// Configuración SSL para conexión interna (Localhost)
// Como nos conectamos a 127.0.0.1 pero el certificado es de bitware.site,
// desactivamos la verificación estricta SOLO para esta conexión interna.
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($json_to_python)
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);

$api_response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch); // Capturar error si falla
curl_close($ch);

// 4. Responder al JS
if ($http_code == 200 && $api_response) {
    echo $api_response;
} else {
    // Si falla, mostramos el error real en el log para que puedas depurar
    error_log("Error conectando PHP -> Python: HTTP $http_code - Error: $curl_error");
    echo json_encode([
        'respuesta' => 'En este momento nuestros sistemas están en mantenimiento. Por favor intenta más tarde.',
        'productos' => []
    ]);
}
exit;
?>