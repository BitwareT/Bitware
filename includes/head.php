<?php
// --- Valores por defecto ---
$titulo_pagina = $titulo_pagina ?? 'Bitware';
$css_pagina_especifica = $css_pagina_especifica ?? null; // Acepta la variable de 1 solo CSS
$body_atributos = $body_atributos ?? ''; 
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php echo htmlspecialchars($titulo_pagina); ?></title>

    <link rel="icon" href="images/favicon.ico" type="image/ico">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/chatbot.css">

    <?php if ($css_pagina_especifica): ?>
        <link rel="stylesheet" href="<?php echo htmlspecialchars($css_pagina_especifica); ?>">
    <?php endif; ?>

    <link rel="stylesheet" href="css/responsive.css">

</head>
<body <?php echo $body_atributos; ?>>