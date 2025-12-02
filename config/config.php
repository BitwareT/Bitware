<?php
// -- CONFIGURACION DE LA BASE DE DATOS --
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'bitware_user');
define('DB_PASSWORD', 'Rocky25..');
define('DB_NAME', 'bitware');

// --- INICIO: CONFIGURACION SMTP DE CORREO CENTRALIZADA ---
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_USERNAME', 'contacto@bitware.site');
define('SMTP_PASSWORD', 'Rocky26..');
define('SMTP_PORT', 465);
// --- FIN: CONFIGURACION SMTP DE CORREO CENTRALIZADA ---

// -- CONEXION A LA BASE DE DATOS --
$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verificar la conexion
if ($conn === false) {
    die("ERROR: No se pudo conectar a la base de datos. " . mysqli_connect_error());
}

// Configurar charset a UTF-8 (recomendado)
mysqli_set_charset($conn, "utf8mb4");

?>