-- ============================================================
-- Migración OPCIONAL de seguridad para systemusage
-- ------------------------------------------------------------
-- El esquema original guarda password/pwmaster en varchar(32),
-- compatible únicamente con hashes MD5 (32 hex). El sistema
-- funciona igual sin este script (modo de compatibilidad MD5),
-- pero se recomienda aplicarlo para pasar a bcrypt.
--
-- Después de ejecutar este script y cambiar cada clave una vez
-- desde el panel (sección "Sistema y licencia"), el código detecta
-- automáticamente el nuevo ancho de columna y empieza a guardar
-- con password_hash() (bcrypt) en lugar de MD5.
-- ============================================================

ALTER TABLE `systemusage`
  MODIFY `password` VARCHAR(255) NOT NULL COMMENT 'Clave USUARIO ADMIN (hash bcrypt)',
  MODIFY `pwmaster` VARCHAR(255) NOT NULL COMMENT 'Clave maestra (hash bcrypt)';

-- IMPORTANTE: las claves existentes en formato MD5 seguirán
-- funcionando para el LOGIN (verify_password admite ambos formatos),
-- pero no se re-hashean solas: debe guardarlas de nuevo una vez
-- desde el formulario de "Sistema y licencia" para que queden en bcrypt.
