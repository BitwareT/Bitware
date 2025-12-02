-- phpMyAdmin SQL Dump
-- version 5.2.1deb3
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 02-12-2025 a las 01:14:51
-- Versión del servidor: 8.0.44-0ubuntu0.24.04.1
-- Versión de PHP: 8.3.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `bitware`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `carritos_guardados`
--

CREATE TABLE `carritos_guardados` (
  `id_carrito` int NOT NULL,
  `id_usuario` int NOT NULL,
  `id_producto` int NOT NULL,
  `cantidad` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `chatbot`
--

CREATE TABLE `chatbot` (
  `id_chat` int NOT NULL,
  `id_usuario` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `chatbot_respuestas`
--

CREATE TABLE `chatbot_respuestas` (
  `id` int NOT NULL,
  `pregunta_clave` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `respuestas` text COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `chatbot_respuestas`
--

INSERT INTO `chatbot_respuestas` (`id`, `pregunta_clave`, `respuestas`) VALUES
(1, 'métodos de pago', 'Aceptamos tarjetas de crédito, débito y transferencias bancarias.;Puedes pagar con Webpay o PayPal.'),
(2, 'envío', 'Los envíos en Santiago tardan de 2 a 3 días hábiles.;Para regiones, el tiempo de envío es de 3 a 5 días hábiles.'),
(3, 'garantía', 'Sí, todos nuestros productos tienen una garantía de 1 año.;Claro, la garantía cubre fallos de fábrica por 12 meses.');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `contacto_mensajes`
--

CREATE TABLE `contacto_mensajes` (
  `id` int NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `asunto` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `mensaje` text COLLATE utf8mb4_general_ci NOT NULL,
  `respuesta` text COLLATE utf8mb4_general_ci,
  `fecha_respuesta` datetime DEFAULT NULL,
  `fecha_envio` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `leido` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0=No Leído, 1=Leído, 2=Respondido'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `contacto_mensajes`
--

INSERT INTO `contacto_mensajes` (`id`, `nombre`, `email`, `asunto`, `mensaje`, `respuesta`, `fecha_respuesta`, `fecha_envio`, `leido`) VALUES
(5, 'AdoLuche', 'adoluche@gmail.com', 'Ayuda', 'a', 'hola', '2025-10-29 19:50:31', '2025-10-29 19:46:31', 2);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cupones`
--

CREATE TABLE `cupones` (
  `id_cupon` int NOT NULL,
  `codigo` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `tipo_descuento` enum('porcentaje','fijo') COLLATE utf8mb4_general_ci NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `fecha_expiracion` date DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cupones`
--

INSERT INTO `cupones` (`id_cupon`, `codigo`, `tipo_descuento`, `valor`, `fecha_expiracion`, `activo`) VALUES
(6, 'BIENVENIDO10', 'porcentaje', 10.00, '2025-12-01', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `dashboard`
--

CREATE TABLE `dashboard` (
  `id_dashboard` bigint NOT NULL,
  `nombre` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `descripcion` text COLLATE utf8mb4_general_ci,
  `contenido` text COLLATE utf8mb4_general_ci,
  `fecha_creacion` date DEFAULT NULL,
  `id_usuario` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `devoluciones`
--

CREATE TABLE `devoluciones` (
  `id_devolucion` int NOT NULL,
  `id_pedido` int NOT NULL,
  `id_usuario` int NOT NULL,
  `motivo` text COLLATE utf8mb4_general_ci NOT NULL,
  `estado` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Pendiente',
  `fecha_solicitud` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `devoluciones`
--

INSERT INTO `devoluciones` (`id_devolucion`, `id_pedido`, `id_usuario`, `motivo`, `estado`, `fecha_solicitud`) VALUES
(3, 103, 69, 'Producto dañado o defectuoso', 'Solicitado', '2025-11-04 01:32:10'),
(4, 103, 69, 'Producto dañado o defectuoso', 'Solicitado', '2025-11-04 01:55:26'),
(5, 112, 69, 'Producto dañado o defectuoso', 'Solicitado', '2025-11-04 01:59:02'),
(6, 115, 90, 'Producto dañado o defectuoso', 'Solicitado', '2025-11-04 12:11:08'),
(7, 173, 69, 'Recibí un producto incorrecto', 'Solicitado', '2025-11-15 00:22:14'),
(8, 177, 69, 'Ya no lo quiero / No me gustó', 'Solicitado', '2025-11-15 00:24:59'),
(9, 179, 114, 'Ya no lo quiero / No me gustó', 'Solicitado', '2025-11-16 02:10:29'),
(10, 174, 111, 'Ya no lo quiero / No me gustó', 'Solicitado', '2025-11-16 16:38:14'),
(11, 181, 69, 'Producto dañado o defectuoso', 'Solicitado', '2025-11-18 12:46:48');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `favoritos`
--

CREATE TABLE `favoritos` (
  `id_favorito` int NOT NULL,
  `id_usuario` int NOT NULL,
  `id_producto` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `favoritos`
--

INSERT INTO `favoritos` (`id_favorito`, `id_usuario`, `id_producto`) VALUES
(3, 69, 1),
(1, 69, 14),
(4, 69, 16),
(7, 90, 1),
(6, 95, 14),
(5, 95, 20),
(10, 111, 14),
(8, 111, 18),
(9, 111, 20),
(11, 114, 15),
(13, 114, 20),
(12, 114, 21);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `marcas`
--

CREATE TABLE `marcas` (
  `id_marca` int NOT NULL,
  `nombre` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `descripcion` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `marcas`
--

INSERT INTO `marcas` (`id_marca`, `nombre`, `descripcion`) VALUES
(1, 'NVIDIA', 'NVIDIA Corporation es una empresa estadounidense de tecnología, con sede en Santa Clara, California, conocida por diseñar y fabricar unidades de procesamiento gráfico (GPU). Su ascenso se vio impulsado por el auge de la inteligencia artificial (IA)'),
(2, 'AMD', 'Advanced Micro Devices, Inc. (AMD) es una empresa estadounidense de tecnología, con sede en Santa Clara, California, conocida por diseñar y desarrollar procesadores para computadoras (CPU), unidades de procesamiento gráfico (GPU) y soluciones para servidores, consolas de videojuegos y centros de datos'),
(3, 'INTEL', 'Intel Corporation es una empresa estadounidense líder en la fabricación de semiconductores y tecnología, con sede en Santa Clara, California. Conocida principalmente por inventar la arquitectura de microprocesadores x86, que ha sido el estándar para la mayoría de los ordenadores personales durante décadas, Intel diseña y fabrica procesadores, chipsets, unidades de procesamiento gráfico (GPU) y otros componentes tecnológicos.'),
(4, 'MSI', 'MSI (Micro-Star International Co., Ltd.) es una corporación multinacional taiwanesa, fundada en 1986, líder en el diseño y la fabricación de hardware de alta gama para los mercados de videojuegos, creación de contenido, negocios y soluciones de IA e IoT. A lo largo de su historia, MSI se ha posicionado como una de las marcas de confianza en la industria, especialmente entre los gamers'),
(5, 'GENERICO', 'Marcas Genericas'),
(6, 'OTROS', 'Marcas no categorizadas como genérico ');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mensajes`
--

CREATE TABLE `mensajes` (
  `id_mensaje` int NOT NULL,
  `mensaje` text COLLATE utf8mb4_general_ci,
  `id_chat` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `metodo_pago`
--

CREATE TABLE `metodo_pago` (
  `id_met_pag` int NOT NULL,
  `descripcion` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `metodo_pago`
--

INSERT INTO `metodo_pago` (`id_met_pag`, `descripcion`) VALUES
(1, 'Tarjeta de Crédito');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `moneda`
--

CREATE TABLE `moneda` (
  `id_moneda` int NOT NULL,
  `tipo_moneda` varchar(25) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `descripcion` varchar(25) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `moneda`
--

INSERT INTO `moneda` (`id_moneda`, `tipo_moneda`, `descripcion`) VALUES
(1, 'CLP', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notificaciones_stock`
--

CREATE TABLE `notificaciones_stock` (
  `id_notificacion` int NOT NULL,
  `id_usuario` int NOT NULL,
  `id_producto` int NOT NULL,
  `email_usuario` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `notificado` tinyint(1) NOT NULL DEFAULT '0',
  `fecha_solicitud` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pagos`
--

CREATE TABLE `pagos` (
  `id_pago` bigint NOT NULL,
  `monto` decimal(10,2) DEFAULT NULL,
  `estado` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `fecha_pago` date DEFAULT NULL,
  `id_met_pag` int DEFAULT NULL,
  `id_moneda` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedidos`
--

CREATE TABLE `pedidos` (
  `id_pedido` int NOT NULL,
  `fecha_pedido` date DEFAULT NULL,
  `total` decimal(10,2) NOT NULL,
  `estado` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Pendiente',
  `id_usuario` int DEFAULT NULL,
  `id_pago` bigint DEFAULT NULL,
  `id_cupon` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `pedidos`
--

INSERT INTO `pedidos` (`id_pedido`, `fecha_pedido`, `total`, `estado`, `id_usuario`, `id_pago`, `id_cupon`) VALUES
(86, '2025-10-22', 4990.00, 'Pendiente', 90, NULL, NULL),
(87, '2025-10-22', 4990.00, 'Pagado', 90, NULL, NULL),
(88, '2025-10-22', 4990.00, 'Pagado', 69, NULL, NULL),
(89, '2025-10-22', 4990.00, 'Pendiente', 69, NULL, NULL),
(90, '2025-10-22', 4990.00, 'Pendiente', 69, NULL, NULL),
(91, '2025-10-22', 4990.00, 'Pendiente', 69, NULL, NULL),
(92, '2025-10-22', 4990.00, 'Pendiente', 69, NULL, NULL),
(93, '2025-10-22', 1999990.00, 'Pendiente', 69, NULL, NULL),
(94, '2025-10-22', 4990.00, 'Pendiente', 69, NULL, NULL),
(95, '2025-10-22', 4990.00, 'Pendiente', 69, NULL, NULL),
(96, '2025-10-22', 4990.00, 'Pendiente', 69, NULL, NULL),
(97, '2025-10-27', 5190.00, 'Pendiente', 69, NULL, NULL),
(98, '2025-10-27', 5190.00, 'Pendiente', 69, NULL, NULL),
(99, '2025-10-27', 1999990.00, 'Pendiente', 69, NULL, NULL),
(100, '2025-10-27', 5190.00, 'Pendiente', 69, NULL, NULL),
(101, '2025-10-27', 5190.00, 'Pendiente', 69, NULL, NULL),
(102, '2025-10-27', 24989.90, 'Pagado', 69, NULL, NULL),
(103, '2025-10-27', 24989.90, 'En Devolución', 69, NULL, NULL),
(104, '2025-10-29', 1999990.00, 'Pagado', 93, NULL, NULL),
(105, '2025-10-29', 990000.00, 'Pagado', 69, NULL, NULL),
(106, '2025-10-31', 1999990.00, 'Pagado', 69, NULL, NULL),
(107, '2025-11-02', 900000.00, 'Pendiente', 95, NULL, NULL),
(108, '2025-11-02', 1999990.00, 'Pendiente', 69, NULL, NULL),
(109, '2025-11-02', 570000.00, 'Fallido', 69, NULL, NULL),
(110, '2025-11-03', 900000.00, 'Pendiente', 93, NULL, NULL),
(111, '2025-11-03', 360000.00, 'Pendiente', 93, NULL, NULL),
(112, '2025-11-03', 1999990.00, 'En Devolución', 69, NULL, NULL),
(113, '2025-11-03', 1999990.00, 'Pagado', 102, NULL, NULL),
(114, '2025-11-03', 1999990.00, 'Enviado', 102, NULL, NULL),
(115, '2025-11-04', 29989.90, 'En Devolución', 90, NULL, NULL),
(116, '2025-11-04', 29989.90, 'Pagado', 69, NULL, NULL),
(117, '2025-11-04', 29989.90, 'Pagado', 95, NULL, NULL),
(118, '2025-11-04', 5190.00, 'Pagado', 95, NULL, NULL),
(119, '2025-09-07', 360000.00, 'Entregado', 93, NULL, NULL),
(120, '2025-09-10', 360000.00, 'Entregado', 93, NULL, NULL),
(121, '2025-09-15', 360000.00, 'Entregado', 93, NULL, NULL),
(122, '2025-09-18', 720000.00, 'Entregado', 93, NULL, NULL),
(123, '2025-09-20', 360000.00, 'Entregado', 93, NULL, NULL),
(124, '2025-09-24', 360000.00, 'Entregado', 93, NULL, NULL),
(125, '2025-09-27', 720000.00, 'Entregado', 93, NULL, NULL),
(126, '2025-09-30', 360000.00, 'Entregado', 93, NULL, NULL),
(127, '2025-10-04', 720000.00, 'Entregado', 93, NULL, NULL),
(128, '2025-10-07', 360000.00, 'Entregado', 93, NULL, NULL),
(129, '2025-10-10', 1080000.00, 'Entregado', 93, NULL, NULL),
(130, '2025-10-15', 720000.00, 'Entregado', 93, NULL, NULL),
(131, '2025-10-20', 1080000.00, 'Entregado', 93, NULL, NULL),
(132, '2025-10-25', 720000.00, 'Entregado', 93, NULL, NULL),
(133, '2025-10-30', 1440000.00, 'Entregado', 93, NULL, NULL),
(134, '2025-11-01', 1080000.00, 'Entregado', 93, NULL, NULL),
(135, '2025-11-02', 1800000.00, 'Entregado', 93, NULL, NULL),
(136, '2025-11-03', 1080000.00, 'Pagado', 93, NULL, NULL),
(137, '2025-09-06', 20000.00, 'Entregado', 102, NULL, NULL),
(138, '2025-09-09', 40000.00, 'Entregado', 102, NULL, NULL),
(139, '2025-09-11', 20000.00, 'Entregado', 102, NULL, NULL),
(140, '2025-09-14', 20000.00, 'Entregado', 102, NULL, NULL),
(141, '2025-09-17', 40000.00, 'Entregado', 102, NULL, NULL),
(142, '2025-09-21', 20000.00, 'Entregado', 102, NULL, NULL),
(143, '2025-09-25', 20000.00, 'Entregado', 102, NULL, NULL),
(144, '2025-09-28', 40000.00, 'Entregado', 102, NULL, NULL),
(145, '2025-10-02', 20000.00, 'Entregado', 102, NULL, NULL),
(146, '2025-10-06', 20000.00, 'Entregado', 102, NULL, NULL),
(147, '2025-10-09', 40000.00, 'Entregado', 102, NULL, NULL),
(148, '2025-10-13', 20000.00, 'Entregado', 102, NULL, NULL),
(149, '2025-10-17', 20000.00, 'Entregado', 102, NULL, NULL),
(150, '2025-10-21', 40000.00, 'Entregado', 102, NULL, NULL),
(151, '2025-10-28', 20000.00, 'Entregado', 102, NULL, NULL),
(152, '2025-11-01', 40000.00, 'Entregado', 102, NULL, NULL),
(153, '2025-11-05', 360000.00, 'Pagado', 69, NULL, NULL),
(154, '2025-11-05', 360000.00, 'Pendiente', 69, NULL, NULL),
(155, '2025-11-05', 8590.00, 'Pagado', 69, NULL, NULL),
(156, '2025-11-05', 15490.00, 'Cancelado', 107, NULL, NULL),
(157, '2025-11-05', 13990.00, 'Pagado', 90, NULL, NULL),
(158, '2025-11-05', 23590.00, 'Cancelado', 109, NULL, NULL),
(159, '2025-11-05', 1869990.00, 'Cancelado', 109, NULL, NULL),
(160, '2025-11-05', 8950000.00, 'Cancelado', 109, NULL, NULL),
(161, '2025-11-05', 13500000.00, 'Cancelado', 109, NULL, NULL),
(162, '2025-11-05', 14800000.00, 'Cancelado', 109, NULL, NULL),
(163, '2025-11-05', 292600.00, 'Cancelado', 109, NULL, NULL),
(164, '2025-11-07', 14990.00, 'Pendiente', 111, NULL, NULL),
(165, '2025-11-07', 10000.00, 'pagado', 111, NULL, NULL),
(166, '2025-11-07', 10000.00, 'pagado', 102, NULL, NULL),
(167, '2025-11-07', 10000.00, 'pagado', 102, NULL, NULL),
(168, '2025-11-07', 10000.00, 'pagado', 102, NULL, NULL),
(169, '2025-11-07', 10000.00, 'Fallido', 102, NULL, NULL),
(170, '2025-11-07', 10000.00, 'Pagado', 102, NULL, NULL),
(171, '2025-11-07', 289000.00, 'Pagado', 102, NULL, NULL),
(172, '2025-11-07', 21241.41, 'Pagado', 69, NULL, NULL),
(173, '2025-11-10', 289000.00, 'En Devolución', 69, NULL, NULL),
(174, '2025-11-10', 340000.00, 'En Devolución', 111, NULL, NULL),
(175, '2025-11-11', 10000.00, 'Pagado', 90, NULL, NULL),
(177, '2025-11-15', 195500.00, 'En Devolución', 69, NULL, NULL),
(178, '2025-11-16', 306000.00, 'Fallido', 114, NULL, 6),
(179, '2025-11-16', 340000.00, 'En Devolución', 114, NULL, NULL),
(180, '2025-11-17', 1699991.50, 'Pagado', 69, NULL, NULL),
(181, '2025-11-18', 773491.50, 'En Devolución', 69, NULL, NULL),
(182, '2025-11-18', 10000.00, 'Pagado', 111, NULL, NULL),
(183, '2025-11-24', 357000.00, 'Pagado', 73, NULL, NULL),
(184, '2025-11-28', 440000.00, 'Pagado', 111, NULL, NULL),
(185, '2025-11-29', 24990.00, 'Pagado', 111, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedidos_productos`
--

CREATE TABLE `pedidos_productos` (
  `id_detalle` int NOT NULL,
  `id_pedido` int NOT NULL,
  `id_producto` int NOT NULL,
  `cantidad` int NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `pedidos_productos`
--

INSERT INTO `pedidos_productos` (`id_detalle`, `id_pedido`, `id_producto`, `cantidad`, `precio_unitario`) VALUES
(30, 86, 14, 1, 1999990.00),
(31, 87, 14, 1, 1999990.00),
(32, 88, 14, 1, 1999990.00),
(33, 89, 14, 1, 1999990.00),
(34, 90, 14, 1, 1999990.00),
(35, 91, 14, 1, 1999990.00),
(36, 92, 14, 1, 1999990.00),
(37, 93, 14, 1, 1999990.00),
(38, 94, 14, 1, 1999990.00),
(39, 95, 14, 1, 1999990.00),
(40, 96, 14, 1, 1999990.00),
(41, 97, 14, 1, 1999990.00),
(42, 98, 14, 1, 1999990.00),
(43, 99, 14, 1, 1999990.00),
(44, 100, 14, 1, 1999990.00),
(45, 101, 14, 1, 1999990.00),
(46, 102, 14, 1, 1999990.00),
(47, 103, 14, 1, 1999990.00),
(48, 104, 14, 1, 1999990.00),
(49, 105, 21, 1, 420000.00),
(50, 105, 18, 1, 570000.00),
(51, 106, 14, 1, 1999990.00),
(52, 107, 20, 1, 900000.00),
(53, 108, 14, 1, 1999990.00),
(54, 109, 18, 1, 570000.00),
(55, 110, 20, 1, 900000.00),
(56, 111, 1, 1, 360000.00),
(57, 112, 14, 1, 1999990.00),
(58, 113, 14, 1, 1999990.00),
(59, 114, 14, 1, 1999990.00),
(60, 115, 14, 1, 1999990.00),
(61, 116, 14, 1, 1999990.00),
(62, 117, 14, 1, 1999990.00),
(63, 118, 23, 1, 20000.00),
(64, 119, 1, 1, 360000.00),
(65, 120, 1, 1, 360000.00),
(66, 121, 1, 1, 360000.00),
(67, 122, 1, 2, 360000.00),
(68, 123, 1, 1, 360000.00),
(69, 124, 1, 1, 360000.00),
(70, 125, 1, 2, 360000.00),
(71, 126, 1, 1, 360000.00),
(72, 127, 1, 2, 360000.00),
(73, 128, 1, 1, 360000.00),
(74, 129, 1, 3, 360000.00),
(75, 130, 1, 2, 360000.00),
(76, 131, 1, 3, 360000.00),
(77, 132, 1, 2, 360000.00),
(78, 133, 1, 4, 360000.00),
(79, 134, 1, 3, 360000.00),
(80, 135, 1, 5, 360000.00),
(81, 136, 1, 3, 360000.00),
(82, 137, 23, 1, 20000.00),
(83, 138, 23, 2, 20000.00),
(84, 139, 23, 1, 20000.00),
(85, 140, 23, 1, 20000.00),
(86, 141, 23, 2, 20000.00),
(87, 142, 23, 1, 20000.00),
(88, 143, 23, 1, 20000.00),
(89, 144, 23, 2, 20000.00),
(90, 145, 23, 1, 20000.00),
(91, 146, 23, 1, 20000.00),
(92, 147, 23, 2, 20000.00),
(93, 148, 23, 1, 20000.00),
(94, 149, 23, 1, 20000.00),
(95, 150, 23, 2, 20000.00),
(96, 151, 23, 1, 20000.00),
(97, 152, 23, 2, 20000.00),
(98, 153, 1, 1, 360000.00),
(99, 154, 1, 1, 360000.00),
(100, 155, 1, 1, 360000.00),
(102, 157, 20, 1, 900000.00),
(103, 158, 22, 1, 300000.00),
(104, 158, 17, 1, 340000.00),
(105, 158, 1, 1, 360000.00),
(106, 158, 21, 1, 420000.00),
(107, 158, 15, 1, 440000.00),
(108, 159, 22, 1, 300000.00),
(109, 159, 17, 1, 340000.00),
(110, 159, 1, 1, 360000.00),
(111, 159, 21, 1, 420000.00),
(112, 159, 15, 1, 440000.00),
(113, 160, 18, 5, 570000.00),
(114, 160, 16, 4, 230000.00),
(115, 160, 1, 3, 360000.00),
(116, 160, 17, 2, 340000.00),
(117, 160, 21, 5, 420000.00),
(118, 160, 15, 3, 440000.00),
(119, 161, 1, 12, 360000.00),
(120, 161, 17, 8, 340000.00),
(121, 161, 21, 7, 420000.00),
(122, 161, 15, 8, 440000.00),
(123, 162, 1, 20, 360000.00),
(124, 162, 17, 10, 340000.00),
(125, 162, 21, 10, 420000.00),
(126, 163, 1, 35, 360000.00),
(127, 163, 17, 28, 340000.00),
(128, 163, 21, 17, 420000.00),
(129, 164, 999, 1, 10000.00),
(130, 165, 999, 1, 10000.00),
(131, 166, 999, 1, 10000.00),
(132, 167, 999, 1, 10000.00),
(133, 168, 999, 1, 10000.00),
(134, 169, 999, 1, 10000.00),
(135, 170, 999, 1, 10000.00),
(136, 171, 17, 1, 340000.00),
(137, 172, 14, 1, 1999990.00),
(138, 173, 17, 1, 340000.00),
(139, 174, 17, 1, 340000.00),
(140, 175, 999, 1, 10000.00),
(142, 177, 16, 1, 230000.00),
(143, 178, 17, 1, 340000.00),
(144, 179, 17, 1, 340000.00),
(145, 180, 14, 1, 1999990.00),
(146, 181, 20, 1, 900000.00),
(147, 182, 999, 1, 10000.00),
(148, 183, 21, 1, 420000.00),
(149, 184, 15, 1, 440000.00),
(150, 185, 23, 1, 20000.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `producto`
--

CREATE TABLE `producto` (
  `id_producto` int NOT NULL,
  `nombre` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `descripcion` text COLLATE utf8mb4_general_ci,
  `precio` decimal(10,2) DEFAULT NULL,
  `stock` int DEFAULT NULL,
  `id_marca` int DEFAULT NULL,
  `id_vendedor` int DEFAULT NULL,
  `categoria` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `imagen_principal` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `producto`
--

INSERT INTO `producto` (`id_producto`, `nombre`, `descripcion`, `precio`, `stock`, `id_marca`, `id_vendedor`, `categoria`, `imagen_principal`, `activo`) VALUES
(1, 'RTX 3060 8GB', 'RTX 3060 8GB', 360000.00, 10, 1, NULL, 'gpu', '68e2f152c8a85_rtx4090.jpg', 1),
(14, 'AdoLuche', 'AdoLuche', 1999990.00, 8, 6, NULL, 'otros', '68e5aa800cd7b_AdoLuche.jpg', 1),
(15, 'Intel Core i7-13700K', 'Procesador de 13ª generación con 16 núcleos (8 P-cores + 8 E-cores) y 24 hilos. Ideal para gaming de alto rendimiento y creación de contenido exigente. Frecuencia turbo máxima de 5.4 GHz. Requiere socket LGA1700.', 440000.00, 24, 3, NULL, 'cpu', '69014e27627bd_Intel-Core-i7-13700K-BX8071513700K-PLAY-FACTORY.png', 1),
(16, 'AMD Ryzen 5 7600X', 'Procesador de 6 núcleos y 12 hilos basado en la arquitectura Zen 4. Excelente rendimiento en juegos y multitarea. Frecuencia boost de hasta 5.3 GHz. Compatible con socket AM5 y memoria DDR5.', 230000.00, 0, 2, NULL, 'cpu', '69014e6a8e4f8_6e0a6b8b-9cee-40b4-ab02-79e853c76d50-510x383.jpg', 1),
(17, 'Intel Core i5-13600K', 'Fantástico procesador de gama media-alta con 14 núcleos (6 P-cores + 8 E-cores) y 20 hilos. Gran balance entre precio y rendimiento para gaming y productividad. Socket LGA1700.', 340000.00, 48, 3, NULL, 'cpu', '69014ea580b23_1303-intel-core-i5-13600kf-35-ghz-box.jpg', 1),
(18, 'AMD Ryzen 7 7800X3D', 'Considerado uno de los mejores procesadores para gaming gracias a su tecnología 3D V-Cache. 8 núcleos, 16 hilos y un rendimiento excepcional en juegos. Socket AM5.', 570000.00, 10, 2, NULL, 'cpu', '69014ed452d80_38320_20240603_173901.png', 1),
(19, 'NVIDIA GeForce RTX 4070 Ti SUPER 16GB GDDR6X', 'Tarjeta gráfica de gama alta ideal para jugar en 1440p y 4K con Ray Tracing y DLSS 3. Ofrece un rendimiento excepcional y eficiencia energética mejorada.', 570000.00, 25, 1, NULL, '0', '69014f1f2201e_1873169_picture_1706588671.jpg', 1),
(20, 'AMD Radeon RX 7800 XT 16GB', 'Excelente opción para gaming en 1440p con altos FPS. Gran rendimiento raster y VRAM generosa para texturas de alta resolución. Competencia directa de la RTX 4070.', 900000.00, 24, 2, NULL, 'gpu', '69014f5d59d89_1819750_picture_1695706289.png', 1),
(21, 'Gigabyte GeForce RTX 4060 EAGLE OC 8G', 'Tarjeta gráfica de gama media perfecta para jugar en 1080p con ajustes altos/ultra. Compatible con DLSS 3 para un mayor rendimiento. Eficiente y accesible.', 420000.00, 24, 1, NULL, 'gpu', '69014f96ac75f_1777560_picture_1688535231.webp', 1),
(22, 'Sapphire PULSE AMD Radeon RX 7600 8GB', 'Tarjeta de entrada ideal para gaming en 1080p. Buen rendimiento por su precio, compatible con FSR para mejorar los FPS.', 300000.00, 25, 2, NULL, 'gpu', '69014fd79322b_1767787_picture_1685549207.jpg', 1),
(23, 'AdoLuche', 'ado', 20000.00, 149, 6, 95, 'otros', '690a3e5bb4af0_AdoLuche3.jpg', 1),
(999, 'Membresía VIP Anual Bitware', 'Acceso a beneficios exclusivos, descuentos del 15% y soporte prioritario durante 12 meses.', 10000.00, 998, NULL, NULL, NULL, 'images/vip_membership.jpg', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos_eliminados`
--

CREATE TABLE `productos_eliminados` (
  `id_producto` int NOT NULL,
  `nombre` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `descripcion` text COLLATE utf8mb4_general_ci,
  `precio` decimal(10,2) DEFAULT NULL,
  `stock` int DEFAULT NULL,
  `id_marca` int DEFAULT NULL,
  `categoria` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `imagen` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `id_vendedor` int DEFAULT NULL,
  `fecha_eliminacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `productos_eliminados`
--

INSERT INTO `productos_eliminados` (`id_producto`, `nombre`, `descripcion`, `precio`, `stock`, `id_marca`, `categoria`, `imagen`, `activo`, `id_vendedor`, `fecha_eliminacion`) VALUES
(12, 'AdoLuche', 'Ado', 1.00, 1, 6, '0', '68e5a4ac98d43_Ado.jpg', 1, NULL, '2025-10-07 23:39:29'),
(11, 'AdoLuche', 'Ado', 1.00, 1, 6, '0', '68e5a34b33fb5_Ado.jpg', 1, NULL, '2025-10-07 23:53:55'),
(13, 'AdoLuche', 'Ado', 1.00, 1, 6, '0', '68e5a80caf190_Ado.jpg', 1, NULL, '2025-10-07 23:54:06'),
(7, 'Producto de Prueba', 'Esta es una descripción de prueba.', 99990.00, 46, 1, 'cpu', '69001b2f1f068_Ado.jpg', 1, NULL, '2025-10-28 01:28:44'),
(1001, 'Prueba', 'Prueba', 1.00, 1, 2, '0', '6917a1e38ffb5_WhatsApp Image 2025-09-29 at 20.16.42.jpeg', 0, 95, '2025-11-14 21:45:18'),
(1002, 'Prueba', 'Prueba', 1.00, 1, 2, '0', '6917a26aa3921_AdoLuche3.jpg', 0, 89, '2025-11-14 21:45:32'),
(1000, 'Prueba', 'Prueba', 1.00, 1, 2, '0', '6917a14b0750b_WhatsApp Image 2025-09-29 at 20.16.42.jpeg', 0, NULL, '2025-11-14 21:45:58');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `producto_imagenes`
--

CREATE TABLE `producto_imagenes` (
  `id_imagen` int NOT NULL,
  `id_producto` int NOT NULL,
  `nombre_archivo` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `orden` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `producto_imagenes`
--

INSERT INTO `producto_imagenes` (`id_imagen`, `id_producto`, `nombre_archivo`, `orden`) VALUES
(3, 14, '68f0569a86b3f_AdoLuche2.jpg', 1),
(4, 14, '68f056a12fadd_AdoLuche3.jpg', 2),
(5, 15, '69014e2765f28_Intel-Core-i7-13700K-BX8071513700K-PLAY-FACTORY-600x600.png', 1),
(6, 16, '69014e6a9048d_6e0a6b8b-9cee-40b4-ab02-79e853c76d50-510x383.jpg', 1),
(7, 17, '69014ea58281b_1303-intel-core-i5-13600kf-35-ghz-box.jpg', 1),
(8, 18, '69014ed4548f8_38320_20240603_173901.png', 1),
(9, 19, '69014f1f23d99_1873169_picture_1706588671.jpg', 1),
(10, 20, '69014f5d5ba61_1819750_picture_1695706289.png', 1),
(11, 21, '69014f96aee8f_1777560_picture_1688535231.webp', 1),
(12, 22, '69014fd795782_1767787_picture_1685549207.jpg', 1),
(13, 23, '690a3e5bb6175_AdoLuche2.jpg', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reseñas`
--

CREATE TABLE `reseñas` (
  `id_reseña` int NOT NULL,
  `id_producto` int NOT NULL,
  `id_usuario` int NOT NULL,
  `calificacion` int NOT NULL,
  `titulo` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `comentario` text COLLATE utf8mb4_general_ci NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `aprobado` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `reseñas`
--

INSERT INTO `reseñas` (`id_reseña`, `id_producto`, `id_usuario`, `calificacion`, `titulo`, `comentario`, `fecha`, `aprobado`) VALUES
(2, 20, 90, 5, 'Lo mejor', 'Excelente producto y con muy buenos descuentos, totalmente recomendado', '2025-11-05 18:29:57', 1),
(3, 23, 95, 5, 'ad', 'ad', '2025-11-18 12:47:44', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `respuestas_chat`
--

CREATE TABLE `respuestas_chat` (
  `id_respuesta` int NOT NULL,
  `respuesta` text COLLATE utf8mb4_general_ci,
  `id_chat` int DEFAULT NULL,
  `id_mensaje_original` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitudes_servicio`
--

CREATE TABLE `solicitudes_servicio` (
  `id` int NOT NULL,
  `id_usuario` int NOT NULL,
  `nombre_cliente` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `email_cliente` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `tipo_servicio` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion_solicitud` text COLLATE utf8mb4_general_ci NOT NULL,
  `presupuesto_estimado` decimal(10,2) DEFAULT NULL,
  `estado` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Pendiente',
  `fecha_solicitud` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `soporte_mensajes`
--

CREATE TABLE `soporte_mensajes` (
  `id_mensaje` int NOT NULL,
  `id_ticket` int NOT NULL,
  `id_remitente` int NOT NULL,
  `es_admin` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0=Usuario, 1=Admin',
  `mensaje` text COLLATE utf8mb4_general_ci NOT NULL,
  `fecha_envio` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `soporte_mensajes`
--

INSERT INTO `soporte_mensajes` (`id_mensaje`, `id_ticket`, `id_remitente`, `es_admin`, `mensaje`, `fecha_envio`) VALUES
(31, 12, 114, 0, 'me llego toda rota', '2025-11-16 02:10:29'),
(32, 12, 69, 1, 'Tu mama esta rota', '2025-11-16 02:11:41'),
(33, 12, 69, 1, 'Donde estai Micki', '2025-11-16 02:12:56'),
(34, 13, 111, 0, 'a', '2025-11-16 16:38:14'),
(35, 14, 69, 0, 'daño', '2025-11-18 12:46:48');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `soporte_tickets`
--

CREATE TABLE `soporte_tickets` (
  `id_ticket` int NOT NULL,
  `id_usuario` int NOT NULL,
  `asunto` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `estado` enum('Abierto','Respondido por Admin','Respondido por Cliente','Cerrado') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Abierto',
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ultima_actualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `soporte_tickets`
--

INSERT INTO `soporte_tickets` (`id_ticket`, `id_usuario`, `asunto`, `estado`, `fecha_creacion`, `ultima_actualizacion`) VALUES
(12, 114, 'Solicitud de Devolución para Pedido #000179', 'Cerrado', '2025-11-16 02:10:29', '2025-11-16 02:13:04'),
(13, 111, 'Solicitud de Devolución para Pedido #000174', 'Abierto', '2025-11-16 16:38:14', '2025-11-16 16:38:14'),
(14, 69, 'Solicitud de Devolución para Pedido #000181', 'Abierto', '2025-11-18 12:46:48', '2025-11-18 12:46:48');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ticket_adjuntos`
--

CREATE TABLE `ticket_adjuntos` (
  `id_adjunto` int NOT NULL,
  `id_ticket` int NOT NULL,
  `nombre_archivo` varchar(255) NOT NULL,
  `ruta_archivo` varchar(255) NOT NULL,
  `fecha_subida` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `ticket_adjuntos`
--

INSERT INTO `ticket_adjuntos` (`id_adjunto`, `id_ticket`, `nombre_archivo`, `ruta_archivo`, `fecha_subida`) VALUES
(4, 12, 'Tio Rene.jpg', 'uploads/tickets/dev-69193295961424.75457149_TioRene.jpg', '2025-11-16 02:10:29');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario`
--

CREATE TABLE `usuario` (
  `id_usuario` int NOT NULL,
  `nombre` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `codigo_verificacion` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `verificado` tinyint(1) NOT NULL DEFAULT '0',
  `reset_token` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL,
  `rut` varchar(12) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `telefono` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `direccion` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `region` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `permisos` char(1) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `vip_status` enum('None','Active','Expired') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'None',
  `vip_expiry_date` date DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `id_chat` int DEFAULT NULL,
  `foto_perfil` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `last_activity` timestamp NULL DEFAULT NULL,
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuario`
--

INSERT INTO `usuario` (`id_usuario`, `nombre`, `email`, `password`, `codigo_verificacion`, `verificado`, `reset_token`, `reset_token_expiry`, `rut`, `telefono`, `direccion`, `region`, `permisos`, `vip_status`, `vip_expiry_date`, `activo`, `id_chat`, `foto_perfil`, `last_activity`, `fecha_registro`) VALUES
(1, 'Andres Miranda Aguilar', 'andres.m.a@gmail.com', '$2y$10$BTOkYIds80o68JQtDtSCeesfsEs1tZq7UTxBOHvF2UUScIXMHQz.C', NULL, 1, NULL, NULL, '21542967-9', '+56959731240', 'a', 'Metropolitana', 'A', 'Active', '2026-09-26', 1, NULL, '68e83faccc7c8_Andres Miranda Aguilar.png', '2025-11-07 18:32:49', '2025-11-04 18:09:13'),
(69, 'Tobben', 'tobbent@gmail.com', '$2y$10$GuoxzSsSNAJ9LGni5L66s.dcQKhc5vDZTAn/6Iscnm92D/8JRKvdS', NULL, 1, NULL, NULL, '21542967-9', '911111111', 'Puente alto', 'Metropolitana', 'A', 'Active', '2026-12-06', 1, NULL, '68e1b3d5917c0_Ado.jpg', '2025-11-30 00:50:23', '2025-11-04 18:09:13'),
(73, 'Felipe', 'felipe@gmail.com', '$2y$10$.inSgctF03PLlR.3Jf5JdevWx40ZF46.y0plFwvgxyq9OoCNKKX3u', NULL, 1, NULL, NULL, '21001155-2', '987558693', 'altolaguirre', 'Valparaíso', 'A', 'Active', '2025-11-30', 1, NULL, NULL, '2025-11-29 22:53:44', '2025-11-04 18:09:13'),
(89, 'Dilan', 'dilan@gmail.com', '$2y$10$l.2KsPN4LWN0u6yk3YDqQekfDCadY04kOxH3bGCS2mQXJ7Q5mw39u', NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, 'V', 'None', NULL, 1, NULL, NULL, '2025-11-15 19:48:44', '2025-11-04 18:09:13'),
(90, 'Darcko', 'darcko@gmail.com', '$2y$10$zIelqpD4sPBTFoKKm6Yr8OoonjNOJpW6gsAEM04raYTDbp58S4wJ.', NULL, 1, NULL, NULL, '21542967-9', '516546546', 'Puente alto', 'Metropolitana', 'U', 'Active', '2026-11-11', 1, NULL, '6909edaa09818_229845.jpg', '2025-11-18 12:56:02', '2025-11-04 18:09:13'),
(93, 'Cesar Arce', 'cesararce@gmail.com', '$2y$10$7j5b6y2KdsxsjJO6FJus7e1xLCkZTiEPRmaG2iWqJNemHpuwz.Hpa', 'df0cf282004679fcb2242051718be1aa', 1, NULL, NULL, '11111111-1', '111111111', 'Rancagua', 'Rancagua', 'U', 'None', NULL, 0, NULL, '690030f9eeccb_Cesar.jpg', '2025-11-06 20:00:40', '2025-11-04 18:09:13'),
(95, 'AdoLuche', 'adoluche@gmail.com', '$2y$10$f0h7oZI.nmrYIquLCZ/JbeT/o7UaYAYMbJMpCZvaCmXE7DWdmnO9W', NULL, 1, NULL, NULL, '21542967-9', '932490076', 'asdasd', 'asdasd', 'V', 'None', NULL, 1, NULL, '69027d24dfbc2_AdoLuche3.jpg', '2025-11-30 01:09:48', '2025-11-04 18:09:13'),
(102, 'DavidC', 'david.cabezas.armando@gmail.com', '$2y$10$GCKOcW0MFLBtode9cqM8wuI5HGXRUbHdPUhOpkShcEwvEb2oiIagK', NULL, 1, NULL, NULL, '21542967-9', '911111111', 'puente alto', 'Rancagua', 'U', 'Active', '2026-11-07', 1, NULL, '690bb2ce69240_Gemini_Generated_Image_jlf7r6jlf7r6jlf7.png', '2025-11-07 18:31:37', '2025-11-04 18:09:13'),
(104, 'Nelvin Andrade', 'nelvinandrade@gmail.com', '$2y$10$052YZnJzgLInW7cBjLek4uFvuJOIAFlhG6FA7bg133JeID4BvIzSe', 'b901aa483cbc59aad540a5b84bcc80a0', 1, NULL, NULL, NULL, NULL, NULL, NULL, 'U', 'None', NULL, 1, NULL, NULL, NULL, '2025-11-04 18:09:13'),
(107, 'Darcko Cabezas', 'arnaycf1@gmail.com', '$2y$10$TX/FkbAGDYdfsnr3Gs7z2uB/tMxlrReg.LBojSs8M7bm2lTWZfcuq', NULL, 1, NULL, NULL, '21510194-0', '974448816', 'Los Enebros 3255', 'Metropolitana', 'U', 'None', NULL, 1, NULL, '690b51bb293c4_images.jpg', '2025-11-05 13:58:20', '2025-11-05 13:28:23'),
(108, 'Kevin', 'ulloakevin402@gmail.com', '$2y$10$9TR/.nbaEz4hn2ysRYSTcOmOabiR4HwMsiEYeyr1W.a220eYq8hYq', NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, 'U', 'None', NULL, 1, NULL, NULL, '2025-11-05 16:17:02', '2025-11-05 16:15:05'),
(109, 'Andresito', 'andres.aguilar02@inacapmail.cl', '$2y$10$oqf.1vVUCZwZ2IZLEkkp0OeIPKRIIP4EDRfry6uSrwymEEjQSafG6', NULL, 1, NULL, NULL, '11111111-1', '232312312', 'Calle Abogado 446, Puente alto', 'Metropolitana', 'U', 'Active', '2025-12-24', 1, NULL, '690b96a23722d_images.jpg', '2025-11-05 18:33:40', '2025-11-05 18:18:50'),
(111, 'user', 'user@gmail.com', '$2y$10$0twQrP0flgS2ZfNX0OIwkefWspYJxfD.5uSRP0..v26ar759bnlwG', NULL, 1, NULL, NULL, '12312312-9', '123123123', '12312312', '123123', 'U', 'Expired', NULL, 1, NULL, '691a0395c009e_Gemini_Generated_Image_jlf7r6jlf7r6jlf7.png', '2025-11-29 22:48:59', '2025-11-07 16:30:30'),
(114, 'josephpepe', 'joseph.parra03@inacapmail.cl', '$2y$10$7cNHcChp0muBmz65E4QXueIGtCjkqGUejjtVgG7eD/W8NAIQJaGlS', NULL, 1, NULL, NULL, '15055145-9', '912345654', 'av.german satula 211', 'Metropolitana', 'U', 'None', NULL, 1, NULL, NULL, '2025-11-16 02:13:49', '2025-11-16 02:00:15');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios_eliminados`
--

CREATE TABLE `usuarios_eliminados` (
  `id_usuario` int NOT NULL,
  `nombre` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `telefono` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `direccion` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `region` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `permisos` char(1) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `id_chat` int DEFAULT NULL,
  `foto_perfil` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `fecha_eliminacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `vip_status` varchar(10) COLLATE utf8mb4_general_ci DEFAULT 'None',
  `vip_expiry_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios_eliminados`
--

INSERT INTO `usuarios_eliminados` (`id_usuario`, `nombre`, `email`, `password`, `telefono`, `direccion`, `region`, `permisos`, `id_chat`, `foto_perfil`, `fecha_eliminacion`, `vip_status`, `vip_expiry_date`) VALUES
(3, 'Felipe', 'f@gmail.com', '$2y$10$sSpM/Eu/YYLHYhJJUDhcj.GFdfsB7a4g9G0tx//4Lq11dFkz4eAey', NULL, NULL, NULL, 'U', NULL, NULL, '2025-10-08 00:09:41', 'None', NULL),
(82, 'Sofia Castillo', 'sofia.c@email.com', '$2y$10$K.v9gH6.qJ/hG8.rJ3fL/./p.o./N6.xR2.y.gJ8.b.k.e.o.k.s', NULL, NULL, NULL, 'U', NULL, NULL, '2025-10-16 16:42:24', 'None', NULL),
(91, 'david', 'david.cabezas.armando@gmail.com', '$2y$10$7.rVEjCRtqP1wNaZ67GHye40B9yF2sllT9g0GQjKp4wZ4K/Vkfn6S', NULL, NULL, NULL, 'U', NULL, NULL, '2025-10-27 03:22:17', 'None', NULL),
(2, 'd', 'd@gmail.com', '$2y$10$tu2Lrv5RFPvNM8DpVRFf8.Q.88KbX2YEZYazAcYcxK4/dEodfjFTS', '932490076', 'Puente alto', 'Metropolitana', 'U', NULL, NULL, '2025-10-28 01:29:37', 'None', NULL),
(92, 'nelvin', 'nelvin@gmail.com', '$2y$10$pMLNmLAGy8/4KqViAOq15ODLAD5.ZpMSkrxLf0vuTQu/Sk1Zkj5l6', NULL, NULL, NULL, 'U', NULL, NULL, '2025-11-03 20:22:23', 'None', NULL),
(94, 'David Zuñiga', 'dzuniga@gmail.com', '$2y$10$fAeWR1W2kKbqQ11tn.mnHuAX8o5lUY.cgiu7C9B1AA4Qxx3XrtLui', NULL, NULL, NULL, 'U', NULL, NULL, '2025-11-03 20:22:40', 'None', NULL),
(97, 'Lisandro Ponse', 'lisandro@gmail.com', '$2y$10$6uI4uk8DBrN/ktee3GpPluQnSUyw1xDBJqhe.CtFHobbRtyDfP/6i', '', '', '', 'U', NULL, NULL, '2025-11-03 20:22:48', 'None', NULL),
(105, 'Cesar Arce', 'funeraria@gmail.com', '$2y$10$D9SyeBAFLBrOPBLTMrIi.eedWaP2A6WFehtMELJm8FeqIPcBzg/SW', NULL, NULL, NULL, 'U', NULL, NULL, '2025-11-04 17:24:52', 'None', NULL),
(106, 'Cesar Arce', 'misninas@gmail.com', '$2y$10$BMBpuEkxoh8BCHCnvhzXh.xfOl5DvYAvr2eL.X4/Crqh924b6Z5xG', NULL, NULL, NULL, 'U', NULL, NULL, '2025-11-04 17:25:02', 'None', NULL),
(103, 'Cesar Arce', 'cesar@gmail.com', '$2y$10$ftQR7CyrsmUgrPkox4VX6uK/gvLs6kLp8UXCfGO.Kmqq.e4U5HuEu', NULL, NULL, NULL, 'U', NULL, NULL, '2025-11-04 17:25:07', 'None', NULL),
(96, 'nico', 'nico@gmail.com', '$2y$10$sWfmF8OX4zI4EbTqOP7Csu.ELnHoJmnd/rJlAKbQYUeTjitN.AYKS', NULL, NULL, NULL, 'U', NULL, NULL, '2025-11-04 17:25:23', 'None', NULL),
(110, 'ski eres un ga y tu', 'dilanweco@gmail.com', '$2y$10$GHg2W69hdpoGTpYQr8HMiuo6GjGpBvSxdEMyZwTzFJGw5bG3MWWOO', NULL, NULL, NULL, 'U', NULL, NULL, '2025-11-05 19:26:15', 'None', NULL),
(112, 'Prueba Register', 'zerpenter123lol@gmail.com', '$2y$10$VLhX44nmBBO2maToSVUQx.7r1DL7WkuoFqF8P4fQqWMYW0CygJh76', NULL, NULL, NULL, 'U', NULL, NULL, '2025-11-11 01:36:24', 'None', NULL),
(113, 'alexd', 'dekame9264@etramay.com', '$2y$10$kll1pV2W6EzVZlKTqj405ea2fPRFJVyJqAhMpZ01huJMngXOjli5m', '345345345', '34534534', '34534534', 'U', NULL, NULL, '2025-11-11 20:04:46', 'None', NULL);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `carritos_guardados`
--
ALTER TABLE `carritos_guardados`
  ADD PRIMARY KEY (`id_carrito`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `chatbot`
--
ALTER TABLE `chatbot`
  ADD PRIMARY KEY (`id_chat`),
  ADD KEY `Chatbot_Usuario_FK` (`id_usuario`);

--
-- Indices de la tabla `chatbot_respuestas`
--
ALTER TABLE `chatbot_respuestas`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `contacto_mensajes`
--
ALTER TABLE `contacto_mensajes`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `cupones`
--
ALTER TABLE `cupones`
  ADD PRIMARY KEY (`id_cupon`),
  ADD UNIQUE KEY `codigo` (`codigo`);

--
-- Indices de la tabla `dashboard`
--
ALTER TABLE `dashboard`
  ADD PRIMARY KEY (`id_dashboard`),
  ADD KEY `Dashboard_Usuario_FK` (`id_usuario`);

--
-- Indices de la tabla `devoluciones`
--
ALTER TABLE `devoluciones`
  ADD PRIMARY KEY (`id_devolucion`),
  ADD KEY `id_pedido` (`id_pedido`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `favoritos`
--
ALTER TABLE `favoritos`
  ADD PRIMARY KEY (`id_favorito`),
  ADD UNIQUE KEY `usuario_producto_unico` (`id_usuario`,`id_producto`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `id_producto` (`id_producto`);

--
-- Indices de la tabla `marcas`
--
ALTER TABLE `marcas`
  ADD PRIMARY KEY (`id_marca`);

--
-- Indices de la tabla `mensajes`
--
ALTER TABLE `mensajes`
  ADD PRIMARY KEY (`id_mensaje`),
  ADD KEY `Mensajes_Chatbot_FK` (`id_chat`);

--
-- Indices de la tabla `metodo_pago`
--
ALTER TABLE `metodo_pago`
  ADD PRIMARY KEY (`id_met_pag`);

--
-- Indices de la tabla `moneda`
--
ALTER TABLE `moneda`
  ADD PRIMARY KEY (`id_moneda`);

--
-- Indices de la tabla `notificaciones_stock`
--
ALTER TABLE `notificaciones_stock`
  ADD PRIMARY KEY (`id_notificacion`),
  ADD KEY `id_producto` (`id_producto`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD PRIMARY KEY (`id_pago`),
  ADD KEY `Pagos_Metodo_FK` (`id_met_pag`),
  ADD KEY `Pagos_Moneda_FK` (`id_moneda`);

--
-- Indices de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  ADD PRIMARY KEY (`id_pedido`),
  ADD KEY `Pedidos_Usuario_FK` (`id_usuario`),
  ADD KEY `Pedidos_Pagos_FK` (`id_pago`),
  ADD KEY `fk_pedido_cupon` (`id_cupon`);

--
-- Indices de la tabla `pedidos_productos`
--
ALTER TABLE `pedidos_productos`
  ADD PRIMARY KEY (`id_detalle`),
  ADD KEY `id_pedido` (`id_pedido`),
  ADD KEY `id_producto` (`id_producto`);

--
-- Indices de la tabla `producto`
--
ALTER TABLE `producto`
  ADD PRIMARY KEY (`id_producto`),
  ADD KEY `Producto_Marca_FK` (`id_marca`),
  ADD KEY `idx_id_vendedor` (`id_vendedor`);

--
-- Indices de la tabla `productos_eliminados`
--
ALTER TABLE `productos_eliminados`
  ADD KEY `Producto_Marca_FK` (`id_marca`),
  ADD KEY `id_producto` (`id_producto`);

--
-- Indices de la tabla `producto_imagenes`
--
ALTER TABLE `producto_imagenes`
  ADD PRIMARY KEY (`id_imagen`),
  ADD KEY `id_producto` (`id_producto`);

--
-- Indices de la tabla `reseñas`
--
ALTER TABLE `reseñas`
  ADD PRIMARY KEY (`id_reseña`),
  ADD KEY `id_producto` (`id_producto`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `respuestas_chat`
--
ALTER TABLE `respuestas_chat`
  ADD PRIMARY KEY (`id_respuesta`),
  ADD KEY `Respuestas_Chatbot_FK` (`id_chat`),
  ADD KEY `Respuestas_Mensajes_FK` (`id_mensaje_original`);

--
-- Indices de la tabla `solicitudes_servicio`
--
ALTER TABLE `solicitudes_servicio`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `soporte_mensajes`
--
ALTER TABLE `soporte_mensajes`
  ADD PRIMARY KEY (`id_mensaje`),
  ADD KEY `id_ticket` (`id_ticket`);

--
-- Indices de la tabla `soporte_tickets`
--
ALTER TABLE `soporte_tickets`
  ADD PRIMARY KEY (`id_ticket`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `ticket_adjuntos`
--
ALTER TABLE `ticket_adjuntos`
  ADD PRIMARY KEY (`id_adjunto`),
  ADD KEY `id_ticket` (`id_ticket`);

--
-- Indices de la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`id_usuario`),
  ADD KEY `Usuario_Chatbot_FK` (`id_chat`);

--
-- Indices de la tabla `usuarios_eliminados`
--
ALTER TABLE `usuarios_eliminados`
  ADD KEY `Usuario_Chatbot_FK` (`id_chat`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `carritos_guardados`
--
ALTER TABLE `carritos_guardados`
  MODIFY `id_carrito` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `chatbot`
--
ALTER TABLE `chatbot`
  MODIFY `id_chat` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `chatbot_respuestas`
--
ALTER TABLE `chatbot_respuestas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `contacto_mensajes`
--
ALTER TABLE `contacto_mensajes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `cupones`
--
ALTER TABLE `cupones`
  MODIFY `id_cupon` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `dashboard`
--
ALTER TABLE `dashboard`
  MODIFY `id_dashboard` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `devoluciones`
--
ALTER TABLE `devoluciones`
  MODIFY `id_devolucion` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `favoritos`
--
ALTER TABLE `favoritos`
  MODIFY `id_favorito` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `marcas`
--
ALTER TABLE `marcas`
  MODIFY `id_marca` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `mensajes`
--
ALTER TABLE `mensajes`
  MODIFY `id_mensaje` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `metodo_pago`
--
ALTER TABLE `metodo_pago`
  MODIFY `id_met_pag` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `moneda`
--
ALTER TABLE `moneda`
  MODIFY `id_moneda` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `notificaciones_stock`
--
ALTER TABLE `notificaciones_stock`
  MODIFY `id_notificacion` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pagos`
--
ALTER TABLE `pagos`
  MODIFY `id_pago` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  MODIFY `id_pedido` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=186;

--
-- AUTO_INCREMENT de la tabla `pedidos_productos`
--
ALTER TABLE `pedidos_productos`
  MODIFY `id_detalle` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=151;

--
-- AUTO_INCREMENT de la tabla `producto`
--
ALTER TABLE `producto`
  MODIFY `id_producto` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1003;

--
-- AUTO_INCREMENT de la tabla `producto_imagenes`
--
ALTER TABLE `producto_imagenes`
  MODIFY `id_imagen` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de la tabla `reseñas`
--
ALTER TABLE `reseñas`
  MODIFY `id_reseña` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `respuestas_chat`
--
ALTER TABLE `respuestas_chat`
  MODIFY `id_respuesta` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `solicitudes_servicio`
--
ALTER TABLE `solicitudes_servicio`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `soporte_mensajes`
--
ALTER TABLE `soporte_mensajes`
  MODIFY `id_mensaje` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT de la tabla `soporte_tickets`
--
ALTER TABLE `soporte_tickets`
  MODIFY `id_ticket` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de la tabla `ticket_adjuntos`
--
ALTER TABLE `ticket_adjuntos`
  MODIFY `id_adjunto` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `usuario`
--
ALTER TABLE `usuario`
  MODIFY `id_usuario` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=115;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `carritos_guardados`
--
ALTER TABLE `carritos_guardados`
  ADD CONSTRAINT `fk_carrito_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `chatbot`
--
ALTER TABLE `chatbot`
  ADD CONSTRAINT `Chatbot_Usuario_FK` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `dashboard`
--
ALTER TABLE `dashboard`
  ADD CONSTRAINT `Dashboard_Usuario_FK` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `favoritos`
--
ALTER TABLE `favoritos`
  ADD CONSTRAINT `fk_favorito_producto` FOREIGN KEY (`id_producto`) REFERENCES `producto` (`id_producto`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_favorito_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `mensajes`
--
ALTER TABLE `mensajes`
  ADD CONSTRAINT `Mensajes_Chatbot_FK` FOREIGN KEY (`id_chat`) REFERENCES `chatbot` (`id_chat`);

--
-- Filtros para la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD CONSTRAINT `Pagos_Metodo_FK` FOREIGN KEY (`id_met_pag`) REFERENCES `metodo_pago` (`id_met_pag`),
  ADD CONSTRAINT `Pagos_Moneda_FK` FOREIGN KEY (`id_moneda`) REFERENCES `moneda` (`id_moneda`);

--
-- Filtros para la tabla `pedidos`
--
ALTER TABLE `pedidos`
  ADD CONSTRAINT `fk_pedido_cupon` FOREIGN KEY (`id_cupon`) REFERENCES `cupones` (`id_cupon`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `Pedidos_Pagos_FK` FOREIGN KEY (`id_pago`) REFERENCES `pagos` (`id_pago`),
  ADD CONSTRAINT `Pedidos_Usuario_FK` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `pedidos_productos`
--
ALTER TABLE `pedidos_productos`
  ADD CONSTRAINT `fk_detalle_pedido` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos` (`id_pedido`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_detalle_producto` FOREIGN KEY (`id_producto`) REFERENCES `producto` (`id_producto`) ON DELETE CASCADE;

--
-- Filtros para la tabla `producto`
--
ALTER TABLE `producto`
  ADD CONSTRAINT `fk_producto_vendedor` FOREIGN KEY (`id_vendedor`) REFERENCES `usuario` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `Producto_Marca_FK` FOREIGN KEY (`id_marca`) REFERENCES `marcas` (`id_marca`);

--
-- Filtros para la tabla `producto_imagenes`
--
ALTER TABLE `producto_imagenes`
  ADD CONSTRAINT `fk_producto_imagenes` FOREIGN KEY (`id_producto`) REFERENCES `producto` (`id_producto`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `reseñas`
--
ALTER TABLE `reseñas`
  ADD CONSTRAINT `fk_reseña_producto` FOREIGN KEY (`id_producto`) REFERENCES `producto` (`id_producto`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reseña_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `respuestas_chat`
--
ALTER TABLE `respuestas_chat`
  ADD CONSTRAINT `Respuestas_Chatbot_FK` FOREIGN KEY (`id_chat`) REFERENCES `chatbot` (`id_chat`),
  ADD CONSTRAINT `Respuestas_Mensajes_FK` FOREIGN KEY (`id_mensaje_original`) REFERENCES `mensajes` (`id_mensaje`);

--
-- Filtros para la tabla `solicitudes_servicio`
--
ALTER TABLE `solicitudes_servicio`
  ADD CONSTRAINT `solicitudes_servicio_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `soporte_mensajes`
--
ALTER TABLE `soporte_mensajes`
  ADD CONSTRAINT `fk_mensaje_ticket` FOREIGN KEY (`id_ticket`) REFERENCES `soporte_tickets` (`id_ticket`) ON DELETE CASCADE;

--
-- Filtros para la tabla `soporte_tickets`
--
ALTER TABLE `soporte_tickets`
  ADD CONSTRAINT `fk_ticket_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `ticket_adjuntos`
--
ALTER TABLE `ticket_adjuntos`
  ADD CONSTRAINT `ticket_adjuntos_ibfk_1` FOREIGN KEY (`id_ticket`) REFERENCES `soporte_tickets` (`id_ticket`) ON DELETE CASCADE;

--
-- Filtros para la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD CONSTRAINT `Usuario_Chatbot_FK` FOREIGN KEY (`id_chat`) REFERENCES `chatbot` (`id_chat`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
