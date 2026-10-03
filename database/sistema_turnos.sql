-- phpMyAdmin SQL Dump
-- version 4.9.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 03-10-2026 a las 07:04:00
-- Versión del servidor: 8.0.18
-- Versión de PHP: 7.3.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `sistema_turnos`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `administradores_sistema`
--

DROP TABLE IF EXISTS `administradores_sistema`;
CREATE TABLE IF NOT EXISTS `administradores_sistema` (
  `id_admin` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_admin`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `horarios_trabajo`
--

DROP TABLE IF EXISTS `horarios_trabajo`;
CREATE TABLE IF NOT EXISTS `horarios_trabajo` (
  `id_horario` int(11) NOT NULL AUTO_INCREMENT,
  `id_negocio` int(11) NOT NULL,
  `dia_semana` tinyint(4) NOT NULL COMMENT '1=Lunes, 7=Domingo o 0=Domingo, 6=Sábado',
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  PRIMARY KEY (`id_horario`),
  KEY `idx_horario_negocio` (`id_negocio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `negocios`
--

DROP TABLE IF EXISTS `negocios`;
CREATE TABLE IF NOT EXISTS `negocios` (
  `id_negocio` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_completo` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `dni` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `rubro` varchar(80) COLLATE utf8mb4_general_ci NOT NULL,
  `link_unico` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `estado_cuenta` enum('activa','suspendida') COLLATE utf8mb4_general_ci DEFAULT 'activa',
  `fecha_registro` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_negocio`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `link_unico` (`link_unico`),
  KEY `idx_link_unico` (`link_unico`),
  KEY `idx_estado_cuenta` (`estado_cuenta`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `negocios`
--

INSERT INTO `negocios` (`id_negocio`, `nombre_completo`, `dni`, `email`, `password_hash`, `rubro`, `link_unico`, `estado_cuenta`, `fecha_registro`) VALUES
(1, 'Consultorio Odontológico Centro', '30123456789', 'admin@dentista.com', '$2y$10$wK1xUuV4g83BvA1K1X0E1uJ9Xg1W0K1X0E1uJ9Xg1W0K1X0E1uJ9X', 'Odontología', 'consultorio-centro', 'activa', '2026-10-03 03:46:42'),
(5, 'Charrua123', '37366852', 'admin@admin.com', '$2y$10$.DLE1NV7c1MQMgG9S.JfmeJ8rVAj6FCMaO8DU9CPj3qJan8VwDsb.', 'Odontologia', 'charrua123-236', 'activa', '2026-10-03 03:58:10');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `servicios`
--

DROP TABLE IF EXISTS `servicios`;
CREATE TABLE IF NOT EXISTS `servicios` (
  `id_servicio` int(11) NOT NULL AUTO_INCREMENT,
  `id_negocio` int(11) NOT NULL,
  `nombre_servicio` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `duracion_minutos` int(11) NOT NULL,
  `estado` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id_servicio`),
  KEY `idx_servicio_negocio` (`id_negocio`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `servicios`
--

INSERT INTO `servicios` (`id_servicio`, `id_negocio`, `nombre_servicio`, `duracion_minutos`, `estado`) VALUES
(5, 1, 'Consulta Diagnóstico', 30, 1),
(6, 5, 'Consulta General', 30, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `turnos`
--

DROP TABLE IF EXISTS `turnos`;
CREATE TABLE IF NOT EXISTS `turnos` (
  `id_turno` int(11) NOT NULL AUTO_INCREMENT,
  `id_negocio` int(11) NOT NULL,
  `id_servicio` int(11) NOT NULL,
  `nombre_cliente_final` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `telefono_cliente_final` varchar(30) COLLATE utf8mb4_general_ci NOT NULL,
  `fecha_hora_inicio` datetime NOT NULL,
  `fecha_hora_fin` datetime NOT NULL,
  `estado` enum('pendiente','confirmado','cancelado') COLLATE utf8mb4_general_ci DEFAULT 'pendiente',
  PRIMARY KEY (`id_turno`),
  KEY `id_servicio` (`id_servicio`),
  KEY `idx_turnos_negocio_fecha` (`id_negocio`,`fecha_hora_inicio`),
  KEY `idx_estado_turno` (`estado`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `turnos`
--

INSERT INTO `turnos` (`id_turno`, `id_negocio`, `id_servicio`, `nombre_cliente_final`, `telefono_cliente_final`, `fecha_hora_inicio`, `fecha_hora_fin`, `estado`) VALUES
(1, 5, 6, 'Pepito Lachoza', '1231456789', '2026-10-07 06:30:00', '2026-10-07 07:00:00', 'cancelado'),
(2, 5, 6, 'Lucas Romero', '4567894565', '2026-12-11 10:30:00', '2026-12-11 11:00:00', 'confirmado');

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `horarios_trabajo`
--
ALTER TABLE `horarios_trabajo`
  ADD CONSTRAINT `horarios_trabajo_ibfk_1` FOREIGN KEY (`id_negocio`) REFERENCES `negocios` (`id_negocio`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `servicios`
--
ALTER TABLE `servicios`
  ADD CONSTRAINT `servicios_ibfk_1` FOREIGN KEY (`id_negocio`) REFERENCES `negocios` (`id_negocio`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `turnos`
--
ALTER TABLE `turnos`
  ADD CONSTRAINT `turnos_ibfk_1` FOREIGN KEY (`id_negocio`) REFERENCES `negocios` (`id_negocio`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `turnos_ibfk_2` FOREIGN KEY (`id_servicio`) REFERENCES `servicios` (`id_servicio`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
