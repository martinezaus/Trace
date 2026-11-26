<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../include/reportes_service.php';
$db=reportDatabase();
$db->exec(file_get_contents(__DIR__.'/../modules/soporte/sql/migration_productividad.sql'));
echo "OK: tabla de configuración de productividad disponible; sin cambios en datos de producción.\n";
