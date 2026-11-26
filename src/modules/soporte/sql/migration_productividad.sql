-- Configuración económica versionada. No modifica tiempos ni reglas de producción.
CREATE TABLE IF NOT EXISTS systemproductividad (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    vigente_desde DATE NOT NULL,
    moneda CHAR(3) NOT NULL,
    costos_activos TINYINT NOT NULL DEFAULT 0,
    tarifa_centavos BIGINT UNSIGNED DEFAULT NULL,
    adicionales_activos TINYINT NOT NULL DEFAULT 0,
    base_adicional VARCHAR(20) NOT NULL DEFAULT 'ahorro',
    escala_json TEXT NOT NULL,
    creado_por VARCHAR(20) NOT NULL,
    created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX productividad_vigencia (vigente_desde,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
