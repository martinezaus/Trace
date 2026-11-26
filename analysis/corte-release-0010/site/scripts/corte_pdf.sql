CREATE TABLE IF NOT EXISTS cortes (
 documento_id INT NOT NULL PRIMARY KEY,
 detalle_id INT UNSIGNED NOT NULL,
 proceso_id INT UNSIGNED NOT NULL UNIQUE,
 estado VARCHAR(16) NOT NULL DEFAULT 'pendiente',
 operador_id INT UNSIGNED NOT NULL,
 sesion_id INT UNSIGNED NOT NULL,
 elapsed_ms BIGINT NOT NULL DEFAULT 0,
 active_since BIGINT NOT NULL DEFAULT 0,
 heartbeat_ms BIGINT NOT NULL DEFAULT 0,
 pause_since BIGINT NOT NULL DEFAULT 0,
 pause_suma INT NOT NULL DEFAULT 0,
 evento_id INT UNSIGNED NULL,
 token VARCHAR(48) NOT NULL DEFAULT '',
 INDEX corte_operador_estado (operador_id,estado)
) ENGINE=InnoDB;
