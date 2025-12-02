<?php
// ¡ESTA LÍNEA ES LA MÁS IMPORTANTE!
// Debe ser lo primero en el archivo, antes de cualquier HTML o echo. y en todos los archivos
session_start();
require '/var/www/config/config.php';

// --- 1. DEFINES LAS VARIABLES PARA EL HEAD ---
$titulo_pagina = "Bitware";

// Sigue funcionando, pero ahora como un array de un solo ítem
$estilos_especificos = [
    "css/Main.css",
    "css/chatbot.css?v=1.2",
];

// --- 2. LLAMAS AL HEAD ---
require 'includes/head2.php';

?>
  <body>
    <nav class="navbar navbar-expand-lg bg-body-tertiary sticky-top">
        <div class="container-fluid">
            <a href="index.php"><img class="navbar-logo" src="images/Favicon.png" alt="" ></a> 
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown" aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNavDropdown">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="index.php">Home</a>
                    </li>
                    <?php if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
                        echo '<li class="nav-item">
                        <a class="nav-link" href="servicios.php">Servicios</a>';// Esto nomas Aparece Cuando el usuario este logueado
                    } 
                    ?>
                    <li class="nav-item">
                        <a class="nav-link" href="catalogo.php">Catalogo</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contacto.php">Contactos</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php
                            // El texto visible del botón cambia según el estado de sesión
                            if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
                                echo "Mi Cuenta";
                            } else {
                                echo "Iniciar Sesión";
                            }
                            ?>
                        </a>
                        <ul class="dropdown-menu">
                        <?php
                        if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
                            // Opciones para usuario logueado
                            echo '<li><a class="dropdown-item" href="dashboard.php">Mi Panel</a></li>';
                            echo '<li><a class="dropdown-item" href="logout.php">Cerrar Sesión</a></li>';
                        } else {
                            // Opciones para invitado
                            echo '<li><a class="dropdown-item" href="login.php">Iniciar Sesión</a></li>';
                            echo '<li><a class="dropdown-item" href="register.php">Registrarse</a></li>';
                        }
                        ?>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="carrito.php" title="Carrito de Compras">
                            <i class="bi bi-cart-fill"></i>
                            <span class="cart-count badge bg-danger rounded-pill">
                                <?php echo array_sum($_SESSION['carrito'] ?? []); ?>
                            </span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="contenedor-home">
        <div class="contenedor-descripcion">
            <div class="text1">
                <h1 class="titulo">Componentes de <br> Computadora <br> de Calidad
                </h1>
                <p class="contenido1">
                    En BitWare encontrarás los mejores componentes para tu PC. 
                    Desde procesadores hasta tarjetas gráficas, todo lo que necesitas
                    para construir o mejorar tu equipo.
                </p>
                <div class="Contenedor-botones">
                    <a href="catalogo.php"><button type="button" class="btn btn-primary" >Ver Productos</button></a>
                </div>

                <div class="contenedor-tarjetas-descripcion">

                    <div class="tarjeta1">
                        <div class="card-mini">
                            <img src="Recursos\Recursos_main\imagen-escudo-home.png" alt="Escudo">
                                <div class="card-mini-text">
                                    <span class="titulo">Garantía</span>
                                    <span class="subtitulo">2 años</span>
                                </div>
                            </div>
                        </div>

                        <div class="tarjeta2">
                            <div class="card-mini">
                            <img src="Recursos\Recursos_main\imagen-rayo-home.png" alt="Escudo">
                                <div class="card-mini-text">
                                    <span class="titulo">Envio</span>
                                    <span class="subtitulo">24 - 48 Hrs.</span>
                                </div>
                            </div>
                        </div>

                        <div class="tarjeta3">
                            <div class="card-mini">
                            <img src="Recursos\Recursos_main\imagen-audifono-home.png" alt="Escudo">
                                <div class="card-mini-text">
                                    <span class="titulo">Soporte</span>
                                    <span class="subtitulo">24/7</span>
                                </div>
                            </div>
                        </div>
                </div>
            </div>

            <img class="imagen-home" class="img-fluid" src="Recursos\Recursos_main\imagen-home.jpg" alt="img">
        </div>
    </div>


    <div class="seccion-2">
        <div class="contenedor-seccion-2">

            <h1 class="titulo-seccion2">
                Nuestros Servicios
            </h1>
            <p class="contenido-seccion2">
                Más que una tienda, somos tu partner tecnológico. Ofrecemos servicios completos para que tengas la mejor experiencia.
            </p>

        </div>
    </div>

    <br>

<!-- aqui añadi las tarjetas -->

<div class="contenedor-tarjetas-seccion2">
    
    <div class="card-servicio">
        <div class="icono-circular">
            <img src="Recursos\Recursos_main\img-tarjeta1.png" alt="Icono">
        </div>
        <div class="card-body-servicio">
            <h5 class="card-titulo-servicio">Ensamblaje<br>Personalizado</h5>
            <p class="card-text-servicio">
                Construimos tu PC ideal según tus necesidades y presupuesto. Nuestros técnicos especializados se encargan de todo.
            </p>
            <ul class="lista-servicio">
                <li>Asesoría gratuita</li>
                <li>Instalación profesional</li>
                <li>Pruebas de rendimiento</li>
            </ul>
        </div>
    </div>

    <div class="card-servicio">
        <div class="icono-circular">
            <img src="Recursos\Recursos_main\img-tarjeta2.png" alt="Icono">
        </div>
        <div class="card-body-servicio">
            <h5 class="card-titulo-servicio">Envío Rápido</h5>
            <p class="card-text-servicio">
                Entrega en 24-48 horas en todo Chile. Embalaje seguro y seguimiento en tiempo real de tu pedido.
            </p>
            <ul class="lista-servicio">
                <li>Envío gratis >$50.000</li>
                <li>Seguimiento online</li>
                <li>Entrega asegurada</li>
            </ul>
        </div>
    </div>

    <div class="card-servicio">
        <div class="icono-circular">
            <img src="Recursos\Recursos_main\img-tarjeta3.png" alt="Icono">
        </div>
        <div class="card-body-servicio">
            <h5 class="card-titulo-servicio">Garantía Extendida</h5>
            <p class="card-text-servicio">
                Todos nuestros productos incluyen garantía de 2 años. Soporte técnico especializado cuando lo necesites.
            </p>
            <ul class="lista-servicio">
                <li>2 años de garantía</li>
                <li>Soporte técnico</li>
                <li>Reparaciones gratuitas</li>
            </ul>
        </div>
    </div>

    <div class="card-servicio">
        <div class="icono-circular">
            <img src="Recursos\Recursos_main\img-tarjeta4.png" alt="Icono">
        </div>
        <div class="card-body-servicio">
            <h5 class="card-titulo-servicio">Soporte 24/7</h5>
            <p class="card-text-servicio">
                Nuestro equipo de expertos está disponible las 24 horas para resolver tus dudas y problemas técnicos.
            </p>
            <ul class="lista-servicio">
                <li>ChatBot en línea</li>
                <li>Soporte telefónico</li>
                <li>Base de conocimientos</li>
            </ul>
        </div>
    </div>

</div>

<footer class="footer">
<?php
// --- AÑADE ESTA LÍNEA AL FINAL DE TU ARCHIVO ---
require 'includes/footerIndex.php';
?>