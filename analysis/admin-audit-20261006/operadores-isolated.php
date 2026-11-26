<?php
require '/var/www/html/vendor/autoload.php';require '/var/www/html/include/config.php';require '/var/www/html/api/rb-mysql.php';
R::setup('mysql:host='.hostBD.';dbname='.nombreBD,usuarioBD,claveBD);R::freeze(true);$db=R::getPDO();
$ddl=$db->query('SHOW CREATE TABLE operadores')->fetch(PDO::FETCH_NUM)[1];$ddl=str_replace('CREATE TABLE','CREATE TEMPORARY TABLE',$ddl);$ddl=preg_replace('/^\s+CONSTRAINT[^\n]*\n/m','',$ddl);$ddl=preg_replace('/,\n\)/',"\n)",$ddl);$db->exec($ddl);
$db->exec("INSERT INTO operadores(id,nombre,token,estado,session,corte,barcode) VALUES(1,'PRUEBA AISLADA','O9999',0,1,0,'TEST')");
$scenario=getenv('AUDIT_SCENARIO');$_SERVER['REQUEST_METHOD']='POST';$_POST=['accion'=>'add','nombre'=>'NUEVO AISLADO','sesion'=>'1','corte'=>'0','id'=>'1'];
switch($scenario){case 'duplicate':$_POST['nombre']='PRUEBA AISLADA';break;case 'empty':$_POST['nombre']='';break;case 'edit':$_POST['accion']='edit';break;case 'edit_missing':$_POST['accion']='edit';$_POST['id']='999';break;case 'disable':$_POST['accion']='del';break;case 'disable_missing':$_POST['accion']='del';$_POST['id']='999';break;case 'reset':$_POST['accion']='reset';break;case 'reset_missing':$_POST['accion']='reset';$_POST['id']='999';break;}
register_shutdown_function(function()use($db,$scenario){echo "\n".json_encode(['scenario'=>$scenario,'rows'=>$db->query('SELECT id,nombre,estado,session,corte FROM operadores')->fetchAll(PDO::FETCH_ASSOC)])."\n";});
$code=file_get_contents('/var/www/html/api/operadores.php');$setup="R::setup('mysql:host='.hostBD.';dbname='.nombreBD, usuarioBD, claveBD);";
if(substr_count($code,$setup)!==1)throw new Exception('Setup inesperado');$code=str_replace($setup,'/* misma conexion, tabla temporal aislada */',$code);chdir('/var/www/html/api');eval('?>'.$code);
