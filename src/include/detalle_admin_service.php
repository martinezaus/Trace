<?php
final class DetalleAdminService {
    public function __construct(private PDO $db){}
    private function query(string $sql,array $args=[]):PDOStatement{$q=$this->db->prepare($sql);$q->execute($args);return $q;}
    public function revertir(int $id,?int $usuario,string $motivo):array{
        if($id<1)throw new InvalidArgumentException('Tipología inválida.');
        $this->db->beginTransaction();
        try{
            $lock=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
            $ordenId=$this->query('SELECT orden_id FROM detalles WHERE id=?',[$id])->fetchColumn();
            if(!$ordenId)throw new InvalidArgumentException('La tipología no existe.');
            $orden=$this->query('SELECT c_estado FROM ordenes WHERE id=?'.$lock,[$ordenId])->fetch(PDO::FETCH_ASSOC);
            $d=$this->query('SELECT * FROM detalles WHERE id=?'.$lock,[$id])->fetch(PDO::FETCH_ASSOC);
            if(!$d || !$orden || (int)$d['c_estado']!==3)throw new InvalidArgumentException('Sólo se puede revertir una tipología terminada. Actualice el listado.');
            if((int)$orden['c_estado']===3)throw new InvalidArgumentException('La orden está instalada. Revise su estado antes de revertir.');
            $time=explode(':',(string)$d['tiempo_real']);$seconds=count($time)===3?(int)$time[0]*3600+(int)$time[1]*60+(int)$time[2]:0;
            // Guardar sólo el ciclo actual: los anteriores permanecen en el historial.
            $actual=$this->query('SELECT SUM(TIME_TO_SEC(tiempo_real)) FROM procesos WHERE detalle_id=? AND estado=2',[$id])->fetchColumn();
            if($actual!==null && $actual!==false) $seconds=(int)$actual;
            $this->query('INSERT INTO detallehistorial (tipo,orden_id,detalle_id,operador_id,revertido_por,tiempo_real_anterior,eficiencia_anterior,motivo) VALUES (?,?,?,?,?,?,?,?)',['reversion_tipologia_v2',$ordenId,$id,$d['operador_id']?:null,$usuario,$seconds,$d['eficiencia'],$motivo]);
            $this->query('UPDATE procesos SET estado=3 WHERE detalle_id=? AND estado=2',[$id]);
            $this->query("UPDATE detalles SET c_estado=0,tiempo_real='00:00:00',eficiencia=0 WHERE id=?",[$id]);
            $this->query('UPDATE ordenes SET c_estado=1 WHERE id=? AND c_estado=2',[$ordenId]);
            $this->db->commit();return ['status'=>'ok','id'=>$id,'ordenId'=>(int)$ordenId];
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
}
