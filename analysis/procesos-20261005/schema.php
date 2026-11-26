<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../../src/include/config.php';
$db=new PDO('mysql:host='.hostBD.';dbname='.nombreBD,usuarioBD,claveBD);
foreach(['procesos','detalles','pendientes','eventos','sesiones','tareas'] as $t){echo "$t\n";foreach($db->query('SHOW COLUMNS FROM '.$t)as $c)echo $c['Field'].' '.$c['Type'].' '.$c['Null'].' default='.($c['Default']??'NULL')."\n";}
