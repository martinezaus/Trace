SET FOREIGN_KEY_CHECKS=0;
CREATE TABLE `codigos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(6) NOT NULL,
  `tarea` varchar(50) NOT NULL,
  `barcode` mediumblob NOT NULL,
  `barcode2d` mediumblob COMMENT 'Para codigos de 2D (QR, Imagen)',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1006 DEFAULT CHARSET=utf8mb3;
CREATE TABLE `control` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `proceso` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `ultimo_mes` int unsigned DEFAULT NULL,
  `ultimo_anio` int unsigned DEFAULT NULL,
  `ultima_ejecucion` timestamp NULL DEFAULT NULL,
  `estado` enum('ok','error','en_proceso') COLLATE utf8mb4_general_ci DEFAULT 'ok',
  `detalle` text COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_proceso` (`proceso`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
CREATE TABLE `detallehistorial` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tipo` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'reversion_tipologia',
  `orden_id` int NOT NULL,
  `detalle_id` int NOT NULL,
  `operador_id` int DEFAULT NULL,
  `revertido_por` int DEFAULT NULL,
  `tiempo_real_anterior` int NOT NULL DEFAULT '0',
  `eficiencia_anterior` decimal(6,2) DEFAULT NULL,
  `motivo` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_historial_detalle` (`detalle_id`),
  KEY `idx_historial_orden` (`orden_id`),
  KEY `idx_historial_tipo` (`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `detalles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `orden_id` int unsigned NOT NULL,
  `operador_id` int unsigned NOT NULL DEFAULT '0',
  `codigo` char(13) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `barcode` mediumblob,
  `barcode2d` mediumblob COMMENT 'Para codigos de 2D (QR, Imagen)',
  `descripcion` text COLLATE utf8mb4_unicode_520_ci,
  `tipologia` char(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `etapas` int NOT NULL DEFAULT '0' COMMENT 'Tareas que debe pasar la tipologia',
  `tiempo_estimado` time DEFAULT NULL COMMENT 'Tiempo estimado para completar la tarea',
  `tiempo_real` time DEFAULT NULL COMMENT 'Tiempo real que tomó la tarea',
  `eficiencia` decimal(5,2) DEFAULT '0.00' COMMENT 'Indicce de Eficiencia. Por ejemplo: +35% = tardaste 35% mas, -20% = terminaste 20% antes',
  `c_estado` bigint NOT NULL DEFAULT '0' COMMENT 'Estado: 0: pendiente, 1:pausada, 2:suspendida, 3:terminada',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `details` text COLLATE utf8mb4_unicode_520_ci COMMENT 'Detalle, observacion que tiene la abertura antes de salir del taller.',
  `ayudante` bit(1) NOT NULL DEFAULT b'0' COMMENT 'Ayudante -> 0: NO; 1:SI',
  `codigos_vidrio` mediumtext COLLATE utf8mb4_unicode_520_ci,
  PRIMARY KEY (`id`),
  KEY `index_foreignkey_detalles_orden` (`orden_id`),
  KEY `detalles_operador_fk` (`operador_id`),
  CONSTRAINT `detalles_operador_fk` FOREIGN KEY (`operador_id`) REFERENCES `operadores` (`id`),
  CONSTRAINT `detalles_ordenes_FK` FOREIGN KEY (`orden_id`) REFERENCES `ordenes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=130 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
CREATE TABLE `documentos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `orden_id` int unsigned NOT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `archivo_final` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `descripcion` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `tipo` bigint DEFAULT NULL COMMENT '1 - op; 2 - pla; 3 - pla vidrio',
  `create` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `update` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `documentos_unique` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `documentositems` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `documento_id` int NOT NULL,
  `perfil` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `descripcion` text COLLATE utf8mb4_general_ci,
  `barra` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tilde` tinyint(1) NOT NULL DEFAULT '0',
  `pagina` int NOT NULL DEFAULT '1',
  `pos_x` decimal(10,3) DEFAULT NULL,
  `pos_y` decimal(10,3) DEFAULT NULL,
  `observacion` text COLLATE utf8mb4_general_ci,
  `cantidad` int DEFAULT NULL,
  `base` int DEFAULT NULL,
  `altura` int DEFAULT NULL,
  `create` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update` timestamp NULL DEFAULT NULL,
  `usuario` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=272 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `eventos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `proceso_id` int unsigned DEFAULT NULL,
  `tipo` enum('pausa','suspension','reanudar','cierre_jornada','lectura_descartada') COLLATE utf8mb4_general_ci DEFAULT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tiempo_real` time DEFAULT NULL COMMENT 'Tiempo real dedicado a la tarea',
  `operador_id` int unsigned DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `eventos_proceso_FK` (`proceso_id`),
  KEY `eventos_operador_FK` (`operador_id`),
  CONSTRAINT `eventos_operador_FK` FOREIGN KEY (`operador_id`) REFERENCES `operadores` (`id`),
  CONSTRAINT `eventos_proceso_FK` FOREIGN KEY (`proceso_id`) REFERENCES `procesos` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
CREATE TABLE `operadores` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `token` varchar(10) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Identificador/Clave para ingresar',
  `estado` bit(1) NOT NULL DEFAULT b'0' COMMENT '0: Activo, 1: Inactivo',
  `barcode` mediumblob NOT NULL,
  `session` bit(1) DEFAULT b'0' COMMENT 'Dato donde el usuario no pueda tener más de una sesión activa al mismo tiempo',
  `corte` bit(1) DEFAULT b'1',
  `foto` mediumblob,
  `create` datetime DEFAULT CURRENT_TIMESTAMP,
  `session_token` varchar(128) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Identificador de la sesión PHP activa',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `ordenes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `codigo` int unsigned DEFAULT NULL,
  `barcode` mediumblob COMMENT 'Codigo de Barra',
  `barcode2d` mediumblob COMMENT 'Codigo de 2D (QR, Imagen)',
  `f_alta` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `cliente` varchar(191) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `archivo` varchar(191) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `c_estado` int unsigned DEFAULT '0' COMMENT '0: CARGADA; 1:PROCESANDO; 2:TERMINADA; 3:INSTALACION/COLOCACION',
  `tiempo_orden` time DEFAULT NULL COMMENT 'Tiempo total de la orden (Tiempo obtenido de la suma te todos los items/tipologias Arch. Plano)',
  `tiempo_armado` time NOT NULL DEFAULT '00:00:00' COMMENT 'Tiempo de armado (Tiempo Terminacion de procesos + Tiempo de Eventos)',
  `tiempo_vidrio` time NOT NULL DEFAULT '00:00:00' COMMENT 'Tiempo de procesos de vidrio.',
  `tiempo_corte` time NOT NULL DEFAULT '00:00:00' COMMENT 'Tiempo de procesos de corte perfil + vidrio.',
  `observaciones` text COLLATE utf8mb4_unicode_520_ci COMMENT 'Observaciones sobre alguna de las aberturas que componen la orden',
  `duracion` varchar(191) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `timestamp_inicio` datetime DEFAULT NULL,
  `productividad` decimal(10,2) DEFAULT '0.00' COMMENT 'Porcentaje de Productividad de la Orden.',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
CREATE TABLE `pausas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Codigo',
  `descripcion` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `barcode` mediumblob NOT NULL,
  `barcode2d` mediumblob COMMENT 'Para codigos de 2D (QR, Imagen)',
  `flag` bit(1) NOT NULL DEFAULT b'0' COMMENT 'Bandera. Me dice si suma tiempo de pausa a la Orden o no suma tiempo a la orden. 0: No suma, 1: Suma',
  `codigo_id` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `pausas_codigos_FK` (`codigo_id`),
  CONSTRAINT `pausas_codigos_FK` FOREIGN KEY (`codigo_id`) REFERENCES `codigos` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
CREATE TABLE `sesiones` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `operador_id` int unsigned NOT NULL,
  `fecha_inicio` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_fin` datetime DEFAULT NULL,
  `estado` enum('activa','cerrada_con_actividad','cerrada_sin_actividad') COLLATE utf8mb4_general_ci DEFAULT 'activa',
  `validate` bit(1) NOT NULL DEFAULT b'0' COMMENT '0: Valida, 1: No valida',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sesiones_operadores_FK` (`operador_id`)
) ENGINE=InnoDB AUTO_INCREMENT=96 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `suspensiones` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Codigo',
  `descripcion` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `barcode` mediumblob NOT NULL,
  `barcode2d` mediumblob COMMENT 'Para codigos de 2D (QR, Imagen)',
  `flag` bit(1) NOT NULL DEFAULT b'0' COMMENT 'Bandera. Me dice si suma tiempo de pausa a la Orden o no suma tiempo a la orden. 0: No suma, 1: Suma',
  `codigo_id` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `suspensiones_codigos_FK` (`codigo_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `systemnegocio` (
  `id` int NOT NULL AUTO_INCREMENT,
  `logo` longblob COMMENT 'Logo de la empresa',
  `coeficientev` decimal(5,2) NOT NULL DEFAULT '17.50' COMMENT 'Coeficiente para calculo de tiempo corte, armado VIDRIO',
  `escala` decimal(5,2) NOT NULL DEFAULT '1.50' COMMENT 'Tamaño del tilde en los PDF y en la leyendas',
  `tamanio` int unsigned NOT NULL DEFAULT '7' COMMENT 'Tamaño de la letra en el PDF. Observacion.',
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `color` enum('oscuro','claro') COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Color del Sistema. Claro/Oscuro',
  `tiempo_cortep` time DEFAULT NULL COMMENT 'Tiempo estimado de corte aluminio',
  `tiempo_cortev` time DEFAULT NULL COMMENT 'Tiempo estimado de corte vidrio',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
CREATE TABLE `systemsmtp` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `host` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `port` int DEFAULT '587',
  `email` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `to` text COLLATE utf8mb4_general_ci NOT NULL COMMENT 'destinatarios, separados por como.',
  `server` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `encryption` enum('none','ssl','tls','starttls') COLLATE utf8mb4_general_ci DEFAULT 'tls',
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
CREATE TABLE `tareas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `barcode` mediumblob NOT NULL,
  `barcode2d` mediumblob COMMENT 'Para codigos de 2d (QR, Imagen)',
  `tipo` int unsigned NOT NULL COMMENT '0: Metal, 1: Vidrio, 2: Cortes, 3: Mosquiteros; 9:Todos',
  `enable` bit(1) NOT NULL DEFAULT b'0' COMMENT 'Opcion que habilita o no la tarea',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=100 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
INSERT INTO systemreleases(release_id,version,package_sha256,status,attempts,started_at) VALUES('0010','1.0.10','0000000000000000000000000000000000000000000000000000000000000000','applied',1,'2026-10-14T00:00:00Z');
INSERT INTO `operadores` (`id`,`nombre`,`token`,`barcode`) VALUES (1,'QA OPERARIO','QA','QA');
INSERT INTO `ordenes` (`id`,`codigo`,`cliente`,`c_estado`,`tiempo_orden`) VALUES (1,11001,'QA - sin datos reales',2,'01:00:00');
INSERT INTO `tareas` (`id`,`codigo`,`descripcion`,`barcode`,`tipo`) VALUES (1,'QA001','QA TAREA','QA',0);
INSERT INTO `detalles` (`id`,`orden_id`,`operador_id`,`codigo`,`tipologia`,`tiempo_estimado`,`tiempo_real`,`c_estado`) VALUES (1,1,1,'0000000000001','QA','01:00:00','02:00:00',3);
INSERT INTO `procesos` (`id`,`detalle_id`,`tarea_id`,`operador_id`,`estado`,`tiempo_real`,`inicio`,`fin`) VALUES (1,1,1,1,2,'01:50:00','2026-10-13 08:00:00','2026-10-13 09:50:00');
INSERT INTO `eventos` (`id`,`proceso_id`,`tipo`,`descripcion`,`tiempo_real`,`operador_id`) VALUES (1,1,'pausa','QA PAUSA','00:15:00',1);
INSERT INTO `detallehistorial` (`id`,`orden_id`,`detalle_id`,`tipo`,`tiempo_real_anterior`) VALUES (1,1,1,'reversion_tipologia',600);
SET FOREIGN_KEY_CHECKS=1;