<?php
/** Sólo elimina órdenes cargadas. Los archivos se apartan y se restauran si falla la BD. */
final class OrdenDeleteDenied extends RuntimeException {}
final class OrdenDeleteService {
    private string $root;
    public function __construct(private PDO $db,string $root) {
        $real=realpath($root);
        if($real===false || !is_dir($real))throw new RuntimeException('Directorio de archivos no disponible.');
        $this->root=$real;
    }
    private function query(string $sql,array $args=[]): PDOStatement {
        $q=$this->db->prepare($sql);$q->execute($args);return $q;
    }
    private function lock(): string {return $this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';}
    private function exists(string $table): bool {
        if($this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite')return (bool)$this->query("SELECT name FROM sqlite_master WHERE type='table' AND name=?",[$table])->fetchColumn();
        return (bool)$this->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->fetchColumn();
    }
    private function candidate(string $name,bool $modified,int $id,array &$files): void {
        if($name==='')return;
        if(str_contains($name,"\0") || strpbrk($name,'/\\:')!==false || in_array($name,['.','..'],true))throw new OrdenDeleteDenied('La orden contiene una ruta de archivo inválida. No se eliminó.');
        if($modified){
            $shared=$this->query('SELECT id FROM documentos WHERE orden_id<>? AND LOWER(archivo_final)=LOWER(?) LIMIT 1',[$id,$name])->fetchColumn();
        }else{
            $shared=$this->query('SELECT id FROM documentos WHERE orden_id<>? AND LOWER(nombre)=LOWER(?) LIMIT 1',[$id,$name])->fetchColumn()
                || $this->query('SELECT id FROM ordenes WHERE id<>? AND LOWER(archivo)=LOWER(?) LIMIT 1',[$id,$name])->fetchColumn();
        }
        if($shared)return; // Otro registro sigue usando el mismo archivo físico.
        $path=$this->root.DIRECTORY_SEPARATOR.($modified?'modificados'.DIRECTORY_SEPARATOR:'').$name;
        if(is_link($path))throw new OrdenDeleteDenied('La orden contiene un enlace de archivo. Requiere revisión.');
        if(!file_exists($path))return; // Un archivo ya ausente no impide eliminar una orden cargada.
        $real=realpath($path);
        $prefix=$this->root.DIRECTORY_SEPARATOR;
        if($real===false || strncmp($real,$prefix,strlen($prefix))!==0 || !is_file($real))throw new OrdenDeleteDenied('La ruta del archivo no es válida. No se eliminó la orden.');
        $files[$real]=$real;
    }
    public function delete(int $id): array {
        if($id<=0)throw new OrdenDeleteDenied('Identificador de orden inválido.');
        $moved=[];$stage=null;
        $this->db->beginTransaction();
        try {
            $order=$this->query('SELECT * FROM ordenes WHERE id=?'.$this->lock(),[$id])->fetch(PDO::FETCH_ASSOC);
            if(!$order)throw new OrdenDeleteDenied('La orden no existe o ya fue eliminada.');
            if((string)$order['c_estado']!=='0')throw new OrdenDeleteDenied('Sólo se pueden eliminar órdenes en estado Cargado. La orden cambió de estado.');
            // Bloquear también los hijos antes de inspeccionar archivos y borrar.
            $this->query('SELECT id FROM detalles WHERE orden_id=?'.$this->lock(),[$id])->fetchAll();
            $docs=$this->query('SELECT id,nombre,archivo_final FROM documentos WHERE orden_id=?'.$this->lock(),[$id])->fetchAll(PDO::FETCH_ASSOC);
            $activity=$this->query('SELECT p.id FROM procesos p JOIN detalles d ON d.id=p.detalle_id WHERE d.orden_id=? LIMIT 1',[$id])->fetchColumn();
            if($activity || $this->query('SELECT id FROM pendientes WHERE orden_id=? LIMIT 1',[$id])->fetchColumn())throw new OrdenDeleteDenied('La orden tiene actividad registrada; no se puede eliminar aunque figure Cargado.');
            if($this->exists('cortepdf') && $this->query('SELECT c.documento_id FROM cortepdf c JOIN documentos d ON d.id=c.documento_id WHERE d.orden_id=? LIMIT 1',[$id])->fetchColumn())throw new OrdenDeleteDenied('La orden tiene tareas de corte registradas; no se puede eliminar.');
            if($this->exists('detallehistorial') && $this->query('SELECT id FROM detallehistorial WHERE orden_id=? LIMIT 1',[$id])->fetchColumn())throw new OrdenDeleteDenied('La orden tiene historial de producción; no se puede eliminar.');
            $files=[];$this->candidate((string)($order['archivo']??''),false,$id,$files);
            foreach($docs as $doc){$this->candidate((string)$doc['nombre'],false,$id,$files);$this->candidate((string)($doc['archivo_final']??''),true,$id,$files);}
            if($files){
                $stage=$this->root.DIRECTORY_SEPARATOR.'.trace-delete-'.bin2hex(random_bytes(12));
                if(!@mkdir($stage,0700))throw new RuntimeException('No se pudo preparar el borrado de archivos.');
                $plan=[];foreach(array_values($files)as $i=>$file)$plan[]=['original'=>$file,'temporal'=>$stage.DIRECTORY_SEPARATOR.$i];
                if(@file_put_contents($stage.DIRECTORY_SEPARATOR.'recuperacion.json',json_encode(['orden'=>$id,'archivos'=>$plan],JSON_THROW_ON_ERROR))===false)throw new RuntimeException('No se pudo registrar la recuperación de archivos.');
                foreach($plan as $item){if(!@rename($item['original'],$item['temporal']))throw new RuntimeException('No se pudo apartar un archivo; no se eliminó la orden.');$moved[]=$item;}
            }
            $this->query('DELETE FROM documentositems WHERE documento_id IN (SELECT id FROM documentos WHERE orden_id=?)',[$id]);
            $this->query('DELETE FROM documentos WHERE orden_id=?',[$id]);
            $this->query('DELETE FROM lotes WHERE orden_id=?',[$id]);
            $this->query('DELETE FROM detalles WHERE orden_id=?',[$id]);
            if($this->query('DELETE FROM ordenes WHERE id=? AND c_estado=0',[$id])->rowCount()!==1)throw new RuntimeException('La orden cambió durante la eliminación.');
            $this->db->commit();
        }catch(Throwable $e){
            $rollbackError=null;
            try {if($this->db->inTransaction())$this->db->rollBack();}catch(Throwable $failure){$rollbackError=$failure;}
            $restored=true;
            foreach(array_reverse($moved)as $item){
                // No sobrescribir un archivo nuevo aparecido durante el fallo.
                if(file_exists($item['original']) || is_link($item['original']) || !@rename($item['temporal'],$item['original']))$restored=false;
            }
            if($stage && $restored){@unlink($stage.DIRECTORY_SEPARATOR.'recuperacion.json');@rmdir($stage);}
            if(!$restored)throw new RuntimeException('No se eliminó la orden. Revisar recuperación de archivos en '.$stage,0,$e);
            if($rollbackError)throw new RuntimeException('No se pudo confirmar el rollback de la base. Revisar antes de reintentar.',0,$rollbackError);
            throw $e;
        }
        $clean=true;
        foreach($moved as $item)if(!@unlink($item['temporal']))$clean=false;
        if($stage && $clean){$clean=@unlink($stage.DIRECTORY_SEPARATOR.'recuperacion.json') && @rmdir($stage);}
        $response=['resultado'=>'ok','mensaje'=>'La orden se eliminó correctamente.'];
        if(!$clean){error_log('Eliminación de orden '.$id.': limpieza pendiente en '.$stage);$response['advertencia']='La orden fue eliminada. Quedaron archivos temporales de eliminación para revisar con soporte.';}
        return $response;
    }
}
