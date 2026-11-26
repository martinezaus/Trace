-- MySQL dump 10.13  Distrib 8.0.46, for Linux (x86_64)
--
-- Host: localhost    Database: trace_release0011_test
-- ------------------------------------------------------
-- Server version	8.0.46

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `codigos`
--

DROP TABLE IF EXISTS `codigos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `codigos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(6) NOT NULL,
  `tarea` varchar(50) NOT NULL,
  `barcode` mediumblob NOT NULL,
  `barcode2d` mediumblob COMMENT 'Para codigos de 2D (QR, Imagen)',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1006 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `codigos`
--

LOCK TABLES `codigos` WRITE;
/*!40000 ALTER TABLE `codigos` DISABLE KEYS */;
/*!40000 ALTER TABLE `codigos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `control`
--

DROP TABLE IF EXISTS `control`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `control` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `proceso` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `ultimo_mes` int unsigned DEFAULT NULL,
  `ultimo_anio` int unsigned DEFAULT NULL,
  `ultima_ejecucion` timestamp NULL DEFAULT NULL,
  `estado` enum('ok','error','en_proceso') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'ok',
  `detalle` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_proceso` (`proceso`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `control`
--

LOCK TABLES `control` WRITE;
/*!40000 ALTER TABLE `control` DISABLE KEYS */;
/*!40000 ALTER TABLE `control` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cortes`
--

DROP TABLE IF EXISTS `cortes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cortes` (
  `documento_id` int NOT NULL,
  `detalle_id` int unsigned NOT NULL,
  `proceso_id` int unsigned NOT NULL,
  `estado` varchar(16) NOT NULL DEFAULT 'pendiente',
  `operador_id` int unsigned NOT NULL,
  `sesion_id` int unsigned NOT NULL,
  `elapsed_ms` bigint NOT NULL DEFAULT '0',
  `active_since` bigint NOT NULL DEFAULT '0',
  `heartbeat_ms` bigint NOT NULL DEFAULT '0',
  `pause_since` bigint NOT NULL DEFAULT '0',
  `pause_suma` int NOT NULL DEFAULT '0',
  `evento_id` int unsigned DEFAULT NULL,
  `token` varchar(48) NOT NULL DEFAULT '',
  PRIMARY KEY (`documento_id`),
  UNIQUE KEY `proceso_id` (`proceso_id`),
  KEY `corte_operador_estado` (`operador_id`,`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cortes`
--

LOCK TABLES `cortes` WRITE;
/*!40000 ALTER TABLE `cortes` DISABLE KEYS */;
/*!40000 ALTER TABLE `cortes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `detallehistorial`
--

DROP TABLE IF EXISTS `detallehistorial`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detallehistorial` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tipo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'reversion_tipologia',
  `orden_id` int NOT NULL,
  `detalle_id` int NOT NULL,
  `operador_id` int DEFAULT NULL,
  `revertido_por` int DEFAULT NULL,
  `tiempo_real_anterior` int NOT NULL DEFAULT '0',
  `eficiencia_anterior` decimal(6,2) DEFAULT NULL,
  `motivo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_historial_detalle` (`detalle_id`),
  KEY `idx_historial_orden` (`orden_id`),
  KEY `idx_historial_tipo` (`tipo`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `detallehistorial`
--

LOCK TABLES `detallehistorial` WRITE;
/*!40000 ALTER TABLE `detallehistorial` DISABLE KEYS */;
INSERT INTO `detallehistorial` VALUES (1,'reversion_tipologia',1,1,NULL,NULL,600,NULL,NULL,'2026-10-14 14:30:37');
/*!40000 ALTER TABLE `detallehistorial` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `detalles`
--

DROP TABLE IF EXISTS `detalles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detalles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `orden_id` int unsigned NOT NULL,
  `operador_id` int unsigned NOT NULL DEFAULT '0',
  `codigo` char(13) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `barcode` mediumblob,
  `barcode2d` mediumblob COMMENT 'Para codigos de 2D (QR, Imagen)',
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `tipologia` char(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `etapas` int NOT NULL DEFAULT '0' COMMENT 'Tareas que debe pasar la tipologia',
  `tiempo_estimado` time DEFAULT NULL COMMENT 'Tiempo estimado para completar la tarea',
  `tiempo_real` time DEFAULT NULL COMMENT 'Tiempo real que tomÃ³ la tarea',
  `eficiencia` decimal(5,2) DEFAULT '0.00' COMMENT 'Indicce de Eficiencia. Por ejemplo: +35% = tardaste 35% mas, -20% = terminaste 20% antes',
  `c_estado` bigint NOT NULL DEFAULT '0' COMMENT 'Estado: 0: pendiente, 1:pausada, 2:suspendida, 3:terminada',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci COMMENT 'Detalle, observacion que tiene la abertura antes de salir del taller.',
  `ayudante` bit(1) NOT NULL DEFAULT b'0' COMMENT 'Ayudante -> 0: NO; 1:SI',
  `codigos_vidrio` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  PRIMARY KEY (`id`),
  KEY `index_foreignkey_detalles_orden` (`orden_id`),
  KEY `detalles_operador_fk` (`operador_id`),
  CONSTRAINT `detalles_operador_fk` FOREIGN KEY (`operador_id`) REFERENCES `operadores` (`id`),
  CONSTRAINT `detalles_ordenes_FK` FOREIGN KEY (`orden_id`) REFERENCES `ordenes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=130 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `detalles`
--

LOCK TABLES `detalles` WRITE;
/*!40000 ALTER TABLE `detalles` DISABLE KEYS */;
INSERT INTO `detalles` VALUES (1,1,1,'0000000000001',NULL,NULL,NULL,'QA',0,'01:00:00','02:00:00',0.00,3,'2026-10-14 14:30:37','2026-10-14 14:30:37',NULL,0x00,NULL);
/*!40000 ALTER TABLE `detalles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documentos`
--

DROP TABLE IF EXISTS `documentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `documentos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `orden_id` int unsigned NOT NULL,
  `nombre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `archivo_final` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `descripcion` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tipo` bigint DEFAULT NULL COMMENT '1 - op; 2 - pla; 3 - pla vidrio',
  `create` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `update` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `documentos_unique` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documentos`
--

LOCK TABLES `documentos` WRITE;
/*!40000 ALTER TABLE `documentos` DISABLE KEYS */;
/*!40000 ALTER TABLE `documentos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documentositems`
--

DROP TABLE IF EXISTS `documentositems`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `documentositems` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `documento_id` int NOT NULL,
  `perfil` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `barra` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tilde` tinyint(1) NOT NULL DEFAULT '0',
  `pagina` int NOT NULL DEFAULT '1',
  `pos_x` decimal(10,3) DEFAULT NULL,
  `pos_y` decimal(10,3) DEFAULT NULL,
  `observacion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `cantidad` int DEFAULT NULL,
  `base` int DEFAULT NULL,
  `altura` int DEFAULT NULL,
  `create` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update` timestamp NULL DEFAULT NULL,
  `usuario` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=272 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documentositems`
--

LOCK TABLES `documentositems` WRITE;
/*!40000 ALTER TABLE `documentositems` DISABLE KEYS */;
/*!40000 ALTER TABLE `documentositems` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eventos`
--

DROP TABLE IF EXISTS `eventos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `eventos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `proceso_id` int unsigned DEFAULT NULL,
  `tipo` enum('pausa','suspension','reanudar','cierre_jornada','lectura_descartada') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `descripcion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tiempo_real` time DEFAULT NULL COMMENT 'Tiempo real dedicado a la tarea',
  `operador_id` int unsigned DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `eventos_proceso_FK` (`proceso_id`),
  KEY `eventos_operador_FK` (`operador_id`),
  CONSTRAINT `eventos_operador_FK` FOREIGN KEY (`operador_id`) REFERENCES `operadores` (`id`),
  CONSTRAINT `eventos_proceso_FK` FOREIGN KEY (`proceso_id`) REFERENCES `procesos` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eventos`
--

LOCK TABLES `eventos` WRITE;
/*!40000 ALTER TABLE `eventos` DISABLE KEYS */;
INSERT INTO `eventos` VALUES (1,1,'pausa','QA PAUSA','00:15:00',1,'2026-10-14 14:30:37');
/*!40000 ALTER TABLE `eventos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lotes`
--

DROP TABLE IF EXISTS `lotes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lotes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `orden_id` int unsigned NOT NULL,
  `tipologias` int unsigned NOT NULL COMMENT 'Nos muestra la cantidad de aberturas',
  `vidrios` int unsigned DEFAULT NULL COMMENT 'Cantidad de Vidrios.',
  `items` int unsigned NOT NULL DEFAULT '0',
  `piezas` int unsigned NOT NULL DEFAULT '0',
  `piezas_perfil` int unsigned NOT NULL DEFAULT '0',
  `piezas_vidrio` int unsigned NOT NULL DEFAULT '0',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `index_foreignkey_lotes_orden` (`orden_id`),
  CONSTRAINT `lotes_ordenes_FK` FOREIGN KEY (`orden_id`) REFERENCES `ordenes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lotes`
--

LOCK TABLES `lotes` WRITE;
/*!40000 ALTER TABLE `lotes` DISABLE KEYS */;
/*!40000 ALTER TABLE `lotes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `operadores`
--

DROP TABLE IF EXISTS `operadores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `operadores` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `token` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Identificador/Clave para ingresar',
  `estado` bit(1) NOT NULL DEFAULT b'0' COMMENT '0: Activo, 1: Inactivo',
  `barcode` mediumblob NOT NULL,
  `session` bit(1) DEFAULT b'0' COMMENT 'Dato donde el usuario no pueda tener mÃ¡s de una sesiÃ³n activa al mismo tiempo',
  `corte` bit(1) DEFAULT b'1',
  `foto` mediumblob,
  `create` datetime DEFAULT CURRENT_TIMESTAMP,
  `session_token` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Identificador de la sesiÃ³n PHP activa',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `operadores`
--

LOCK TABLES `operadores` WRITE;
/*!40000 ALTER TABLE `operadores` DISABLE KEYS */;
INSERT INTO `operadores` VALUES (1,'QA OPERARIO','QA',0x00,0x5141,0x00,0x01,NULL,'2026-10-14 14:30:37',NULL);
/*!40000 ALTER TABLE `operadores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ordenes`
--

DROP TABLE IF EXISTS `ordenes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ordenes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `codigo` int unsigned DEFAULT NULL,
  `barcode` mediumblob COMMENT 'Codigo de Barra',
  `barcode2d` mediumblob COMMENT 'Codigo de 2D (QR, Imagen)',
  `f_alta` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `cliente` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `archivo` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `c_estado` int unsigned DEFAULT '0' COMMENT '0: CARGADA; 1:PROCESANDO; 2:TERMINADA; 3:INSTALACION/COLOCACION',
  `tiempo_orden` time DEFAULT NULL COMMENT 'Tiempo total de la orden (Tiempo obtenido de la suma te todos los items/tipologias Arch. Plano)',
  `tiempo_armado` time NOT NULL DEFAULT '00:00:00' COMMENT 'Tiempo de armado (Tiempo Terminacion de procesos + Tiempo de Eventos)',
  `tiempo_vidrio` time NOT NULL DEFAULT '00:00:00' COMMENT 'Tiempo de procesos de vidrio.',
  `tiempo_corte` time NOT NULL DEFAULT '00:00:00' COMMENT 'Tiempo de procesos de corte perfil + vidrio.',
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci COMMENT 'Observaciones sobre alguna de las aberturas que componen la orden',
  `duracion` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `timestamp_inicio` datetime DEFAULT NULL,
  `productividad` decimal(10,2) DEFAULT '0.00' COMMENT 'Porcentaje de Productividad de la Orden.',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ordenes`
--

LOCK TABLES `ordenes` WRITE;
/*!40000 ALTER TABLE `ordenes` DISABLE KEYS */;
INSERT INTO `ordenes` VALUES (1,11001,NULL,NULL,'2026-10-14 14:30:37','QA - sin datos reales',NULL,2,'01:00:00','00:00:00','00:00:00','00:00:00',NULL,NULL,NULL,0.00);
/*!40000 ALTER TABLE `ordenes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pausas`
--

DROP TABLE IF EXISTS `pausas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pausas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Codigo',
  `descripcion` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `barcode` mediumblob NOT NULL,
  `barcode2d` mediumblob COMMENT 'Para codigos de 2D (QR, Imagen)',
  `flag` bit(1) NOT NULL DEFAULT b'0' COMMENT 'Bandera. Me dice si suma tiempo de pausa a la Orden o no suma tiempo a la orden. 0: No suma, 1: Suma',
  `codigo_id` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `pausas_codigos_FK` (`codigo_id`),
  CONSTRAINT `pausas_codigos_FK` FOREIGN KEY (`codigo_id`) REFERENCES `codigos` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pausas`
--

LOCK TABLES `pausas` WRITE;
/*!40000 ALTER TABLE `pausas` DISABLE KEYS */;
/*!40000 ALTER TABLE `pausas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pendientes`
--

DROP TABLE IF EXISTS `pendientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pendientes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `orden_id` int unsigned NOT NULL,
  `detalle_id` int unsigned NOT NULL,
  `proceso_id` int unsigned DEFAULT NULL,
  `sesion_id` int unsigned NOT NULL,
  `tarea_actual_id` int DEFAULT NULL,
  `tiempo_orden_ms` int unsigned DEFAULT NULL,
  `tiempo_tarea_ms` int unsigned DEFAULT NULL,
  `tiempo_tarea_total_ms` int unsigned DEFAULT NULL,
  `tiempo_operador_ms` int unsigned DEFAULT NULL,
  `tarea_actual_nro` int NOT NULL DEFAULT '0',
  `menu` int unsigned DEFAULT NULL,
  `valided` bit(1) DEFAULT b'0' COMMENT '0: Valido, 1: No Valido',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pendientes_ordenes_FK` (`orden_id`),
  CONSTRAINT `pendientes_ordenes_FK` FOREIGN KEY (`orden_id`) REFERENCES `ordenes` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pendientes`
--

LOCK TABLES `pendientes` WRITE;
/*!40000 ALTER TABLE `pendientes` DISABLE KEYS */;
/*!40000 ALTER TABLE `pendientes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `procesos`
--

DROP TABLE IF EXISTS `procesos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `procesos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `detalle_id` int unsigned NOT NULL,
  `tarea_id` int unsigned NOT NULL,
  `estado` int unsigned NOT NULL DEFAULT '0' COMMENT '0: pendiente;1: proceso;2: finalizada',
  `inicio` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fin` datetime DEFAULT NULL,
  `tiempo_real` time DEFAULT NULL,
  `operador_id` int unsigned NOT NULL,
  `operador_aux` int unsigned DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `etapas_detalles_FK` (`detalle_id`),
  KEY `etapas_tareas_FK` (`tarea_id`),
  KEY `procesos_operadores_FK` (`operador_id`),
  KEY `procesos_operadores_FK_1` (`operador_aux`),
  CONSTRAINT `etapas_detalles_FK` FOREIGN KEY (`detalle_id`) REFERENCES `detalles` (`id`),
  CONSTRAINT `etapas_tareas_FK` FOREIGN KEY (`tarea_id`) REFERENCES `tareas` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `procesos`
--

LOCK TABLES `procesos` WRITE;
/*!40000 ALTER TABLE `procesos` DISABLE KEYS */;
INSERT INTO `procesos` VALUES (1,1,1,2,'2026-10-13 08:00:00','2026-10-13 09:50:00','01:50:00',1,NULL,'2026-10-14 14:30:37');
/*!40000 ALTER TABLE `procesos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `productividad`
--

DROP TABLE IF EXISTS `productividad`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `productividad` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `operador_id` int unsigned NOT NULL,
  `mes` int unsigned NOT NULL,
  `anio` int unsigned NOT NULL,
  `cantidad` int NOT NULL DEFAULT '0',
  `productividad` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_operador_mes_anio` (`operador_id`,`mes`,`anio`),
  CONSTRAINT `productividad_operadores_FK` FOREIGN KEY (`operador_id`) REFERENCES `operadores` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `productividad`
--

LOCK TABLES `productividad` WRITE;
/*!40000 ALTER TABLE `productividad` DISABLE KEYS */;
/*!40000 ALTER TABLE `productividad` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sesiones`
--

DROP TABLE IF EXISTS `sesiones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sesiones` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `operador_id` int unsigned NOT NULL,
  `fecha_inicio` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_fin` datetime DEFAULT NULL,
  `estado` enum('activa','cerrada_con_actividad','cerrada_sin_actividad') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'activa',
  `validate` bit(1) NOT NULL DEFAULT b'0' COMMENT '0: Valida, 1: No valida',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sesiones_operadores_FK` (`operador_id`)
) ENGINE=InnoDB AUTO_INCREMENT=96 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sesiones`
--

LOCK TABLES `sesiones` WRITE;
/*!40000 ALTER TABLE `sesiones` DISABLE KEYS */;
/*!40000 ALTER TABLE `sesiones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `suspensiones`
--

DROP TABLE IF EXISTS `suspensiones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `suspensiones` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Codigo',
  `descripcion` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `barcode` mediumblob NOT NULL,
  `barcode2d` mediumblob COMMENT 'Para codigos de 2D (QR, Imagen)',
  `flag` bit(1) NOT NULL DEFAULT b'0' COMMENT 'Bandera. Me dice si suma tiempo de pausa a la Orden o no suma tiempo a la orden. 0: No suma, 1: Suma',
  `codigo_id` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `suspensiones_codigos_FK` (`codigo_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `suspensiones`
--

LOCK TABLES `suspensiones` WRITE;
/*!40000 ALTER TABLE `suspensiones` DISABLE KEYS */;
/*!40000 ALTER TABLE `suspensiones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `systemnegocio`
--

DROP TABLE IF EXISTS `systemnegocio`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `systemnegocio` (
  `id` int NOT NULL AUTO_INCREMENT,
  `logo` longblob COMMENT 'Logo de la empresa',
  `coeficientev` decimal(5,2) NOT NULL DEFAULT '17.50' COMMENT 'Coeficiente para calculo de tiempo corte, armado VIDRIO',
  `escala` decimal(5,2) NOT NULL DEFAULT '1.50' COMMENT 'TamaÃ±o del tilde en los PDF y en la leyendas',
  `tamanio` int unsigned NOT NULL DEFAULT '7' COMMENT 'TamaÃ±o de la letra en el PDF. Observacion.',
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `color` enum('oscuro','claro') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Color del Sistema. Claro/Oscuro',
  `tiempo_cortep` time DEFAULT NULL COMMENT 'Tiempo estimado de corte aluminio',
  `tiempo_cortev` time DEFAULT NULL COMMENT 'Tiempo estimado de corte vidrio',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `systemnegocio`
--

LOCK TABLES `systemnegocio` WRITE;
/*!40000 ALTER TABLE `systemnegocio` DISABLE KEYS */;
/*!40000 ALTER TABLE `systemnegocio` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `systemreleases`
--

DROP TABLE IF EXISTS `systemreleases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `systemreleases` (
  `release_id` varchar(32) NOT NULL,
  `version` varchar(32) NOT NULL,
  `package_sha256` varchar(64) NOT NULL,
  `status` varchar(16) NOT NULL,
  `attempts` int NOT NULL,
  `started_at` varchar(32) NOT NULL,
  `finished_at` varchar(32) DEFAULT NULL,
  `backup_path` text,
  `last_error` text,
  PRIMARY KEY (`release_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `systemreleases`
--

LOCK TABLES `systemreleases` WRITE;
/*!40000 ALTER TABLE `systemreleases` DISABLE KEYS */;
INSERT INTO `systemreleases` VALUES ('0010','1.0.10','0000000000000000000000000000000000000000000000000000000000000000','applied',1,'2026-10-14T00:00:00Z',NULL,NULL,NULL);
/*!40000 ALTER TABLE `systemreleases` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `systemsmtp`
--

DROP TABLE IF EXISTS `systemsmtp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `systemsmtp` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `host` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `port` int DEFAULT '587',
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `to` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'destinatarios, separados por como.',
  `server` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `encryption` enum('none','ssl','tls','starttls') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'tls',
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `systemsmtp`
--

LOCK TABLES `systemsmtp` WRITE;
/*!40000 ALTER TABLE `systemsmtp` DISABLE KEYS */;
/*!40000 ALTER TABLE `systemsmtp` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `systemusage`
--

DROP TABLE IF EXISTS `systemusage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `systemusage` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(10) NOT NULL,
  `password` varchar(255) NOT NULL COMMENT 'Hash bcrypt. Clave USUARIO ADMIN.',
  `enabled` bit(1) NOT NULL DEFAULT b'0' COMMENT 'Habilitado: 0: No habilitado, 1: Habilitado',
  `licencia` date DEFAULT NULL COMMENT 'Fecha de vencimiento de la licencia',
  `nombre` varchar(100) NOT NULL,
  `version` varchar(7) NOT NULL COMMENT 'Version del programa, cambia por actualizacion o modificacion de algun modulo',
  `pwmaster` varchar(255) NOT NULL COMMENT 'Hash bcrypt. Clave maestra (superuser).',
  `barcode_master` mediumblob NOT NULL,
  `barcode2d` mediumblob COMMENT 'Codigo 2d (QR, Imagen)',
  `create` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `soporte` bigint NOT NULL DEFAULT '1' COMMENT 'Modulo Soporte. (1) = Habilitado',
  `ia` bigint NOT NULL DEFAULT '0' COMMENT 'Modulo IA: (0)= Inhabilitado',
  `personal` bigint NOT NULL DEFAULT '0' COMMENT 'Modulo de Personal. (0): Inhabilitado',
  `instalacion` bigint NOT NULL DEFAULT '0' COMMENT 'Modulo de Instalacion y Logistica. (0):Inhabilitado',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `systemusage`
--

LOCK TABLES `systemusage` WRITE;
/*!40000 ALTER TABLE `systemusage` DISABLE KEYS */;
/*!40000 ALTER TABLE `systemusage` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tareas`
--

DROP TABLE IF EXISTS `tareas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tareas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `barcode` mediumblob NOT NULL,
  `barcode2d` mediumblob COMMENT 'Para codigos de 2d (QR, Imagen)',
  `tipo` int unsigned NOT NULL COMMENT '0: Metal, 1: Vidrio, 2: Cortes, 3: Mosquiteros; 9:Todos',
  `enable` bit(1) NOT NULL DEFAULT b'0' COMMENT 'Opcion que habilita o no la tarea',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=100 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tareas`
--

LOCK TABLES `tareas` WRITE;
/*!40000 ALTER TABLE `tareas` DISABLE KEYS */;
INSERT INTO `tareas` VALUES (1,'QA001','QA TAREA',0x5141,NULL,0,0x00);
/*!40000 ALTER TABLE `tareas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `traceprocessdateaudit`
--

DROP TABLE IF EXISTS `traceprocessdateaudit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `traceprocessdateaudit` (
  `release_id` varchar(16) NOT NULL,
  `proceso_id` bigint NOT NULL,
  `inicio_original` varchar(32) DEFAULT NULL,
  `inicio_nuevo` varchar(32) DEFAULT NULL,
  `fin_original` varchar(32) DEFAULT NULL,
  `created_original` varchar(32) DEFAULT NULL,
  `estado_original` int NOT NULL,
  `status` varchar(20) NOT NULL,
  `motivo` text NOT NULL,
  `registrado` varchar(32) NOT NULL,
  PRIMARY KEY (`release_id`,`proceso_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `traceprocessdateaudit`
--

LOCK TABLES `traceprocessdateaudit` WRITE;
/*!40000 ALTER TABLE `traceprocessdateaudit` DISABLE KEYS */;
/*!40000 ALTER TABLE `traceprocessdateaudit` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-14 14:35:32
