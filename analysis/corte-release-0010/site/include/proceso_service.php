<?php
/** Persistencia de inicio/cierre; cada transición bloquea la fila antes de decidir. */
final class ProcesoService {
    public function __construct(private PDO $db) {}
    private function query(string $sql, array $args=[]): PDOStatement {
        $q=$this->db->prepare($sql);$q->execute($args);return $q;
    }
    private function lock(): string { return $this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''; }
    private function ms($time): int {
        if (!preg_match('/^(\d+):([0-5]\d):([0-5]\d)$/',(string)$time,$m)) return 0;
        return ((int)$m[1]*3600+(int)$m[2]*60+(int)$m[3])*1000;
    }
    private function time(int $ms): string {
        $s=intdiv($ms,1000);return sprintf('%02d:%02d:%02d',intdiv($s,3600),intdiv($s%3600,60),$s%60);
    }
    public function start(int $task,int $detail,int $operator,int $helper,int $pendingId=0): array {
        if ($task<=0 || $detail<=0 || $operator<=0) throw new InvalidArgumentException('Datos de tarea incompletos.');
        $this->db->beginTransaction();
        try {
            $d=$this->query('SELECT * FROM detalles WHERE id=?'.$this->lock(),[$detail])->fetch(PDO::FETCH_ASSOC);
            if (!$d || in_array((int)$d['c_estado'],[2,3],true)) throw new RuntimeException('Tipología suspendida o terminada.');
            if (!$this->query('SELECT id FROM tareas WHERE id=?',[$task])->fetchColumn()) throw new RuntimeException('Tarea inexistente.');
            $p=$this->query('SELECT * FROM procesos WHERE tarea_id=? AND detalle_id=? ORDER BY id DESC LIMIT 1'.$this->lock(),[$task,$detail])->fetch(PDO::FETCH_ASSOC);
            if ($p && (int)$p['estado']===2) throw new RuntimeException('Tarea ya realizada.');
            $other=$this->query('SELECT id FROM procesos WHERE detalle_id=? AND estado=1 AND tarea_id<>? LIMIT 1',[$detail,$task])->fetchColumn();
            if ($other) throw new RuntimeException('La tipología ya tiene otra tarea activa.');
            $pending=null;
            if ($pendingId) {
                $pending=$this->query('SELECT * FROM pendientes WHERE id=? AND detalle_id=? AND valided=0'.$this->lock(),[$pendingId,$detail])->fetch(PDO::FETCH_ASSOC);
                if (!$pending || !$p || (int)$pending['proceso_id']!==(int)$p['id']) throw new RuntimeException('El pendiente ya cambió. Recargue la tipología.');
            }
            $now=procesoFechaLocal();$resumed=false;$elapsed=0;
            if ($p) {
                if ((int)$p['estado']===1 && (int)$p['operador_id']!==$operator && !$pending) throw new RuntimeException('Otro operario tiene esta tarea activa.');
                $reset=in_array((int)$p['estado'],[3,4],true);
                $resumed=!$reset;$elapsed=$reset?0:max($this->ms($p['tiempo_real']),(int)($pending['tiempo_tarea_ms']??0));
                $this->query('UPDATE procesos SET estado=1,fin=NULL,operador_id=?,operador_aux=?,inicio=?,tiempo_real=? WHERE id=?',[$operator,$helper,$reset?$now:$p['inicio'],$this->time($elapsed),$p['id']]);
                $id=(int)$p['id'];
            } else {
                $this->query('INSERT INTO procesos (tarea_id,detalle_id,estado,operador_id,operador_aux,inicio,created,tiempo_real) VALUES (?,?,1,?,?,?,?,?)',[$task,$detail,$operator,$helper,$now,$now,'00:00:00']);
                $id=(int)$this->db->lastInsertId();
            }
            if ($pending) $this->query('UPDATE pendientes SET valided=1,updated=? WHERE id=?',[$now,$pendingId]);
            $this->db->commit();return ['id'=>$id,'retomada'=>$resumed,'tiempo_ms'=>$elapsed];
        } catch(Throwable $e) {if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    public function savePending(array $data,bool $closeDay=false): array {
        $session=(int)($data['sesionId']??0);$operator=(int)($data['operadorId']??0);
        $detail=(int)($data['detalleId']??0);$process=(int)($data['procesoId']??0);
        foreach(['tiempoOrdenMs','tiempoTareaMs','tiempoTareaTotalMs','tiempoOperadorMs'] as $field) {
            $v=filter_var($data[$field]??0,FILTER_VALIDATE_INT);
            if($v===false || $v<0)throw new InvalidArgumentException('Tiempo inválido.');
            $data[$field]=$v;
        }
        $this->db->beginTransaction();
        try {
            $sessionRow=$this->query('SELECT * FROM sesiones WHERE id=? AND operador_id=?'.$this->lock(),[$session,$operator])->fetch(PDO::FETCH_ASSOC);
            if(!$sessionRow)throw new RuntimeException('Sesión inexistente.');
            if($sessionRow['estado']!=='activa') {
                if($closeDay){$this->db->commit();return ['status'=>'ok'];}
                throw new RuntimeException('La jornada ya está cerrada.');
            }
            $d=$this->query('SELECT * FROM detalles WHERE id=?'.$this->lock(),[$detail])->fetch(PDO::FETCH_ASSOC);
            if(!$d || (int)$d['orden_id']!==(int)($data['ordenId']??0) || (int)$d['c_estado']===3)throw new RuntimeException('Tipología inválida.');
            $existing=$this->query('SELECT id FROM pendientes WHERE detalle_id=? AND sesion_id=? AND proceso_id=? AND valided=0 ORDER BY id DESC LIMIT 1',[$detail,$session,$process])->fetchColumn();
            if($existing && !$closeDay){$this->db->commit();return ['status'=>'ok','pendienteId'=>(int)$existing];}
            $now=procesoFechaLocal();
            if($process){
                $p=$this->query('SELECT * FROM procesos WHERE id=?'.$this->lock(),[$process])->fetch(PDO::FETCH_ASSOC);
                if(!$p || (int)$p['detalle_id']!==$detail || (int)$p['operador_id']!==$operator || !in_array((int)$p['estado'],[0,1],true))throw new RuntimeException('La tarea cambió; no se guardó el pendiente.');
                $this->query('UPDATE procesos SET estado=0,tiempo_real=?,fin=NULL WHERE id=?',[$this->time($data['tiempoTareaMs']),$process]);
                $task=(int)$p['tarea_id'];
            } else { $task=0;$data['tiempoTareaMs']=0; }
            $this->query('INSERT INTO pendientes (orden_id,detalle_id,proceso_id,sesion_id,tarea_actual_id,tiempo_orden_ms,tiempo_tarea_ms,tiempo_tarea_total_ms,tiempo_operador_ms,menu,valided) VALUES (?,?,?,?,?,?,?,?,?,?,0)',[(int)$data['ordenId'],$detail,$process,$session,$task,$data['tiempoOrdenMs'],$data['tiempoTareaMs'],$data['tiempoTareaTotalMs'],$data['tiempoOperadorMs'],$data['menu']??1]);
            $id=(int)$this->db->lastInsertId();
            $type=$closeDay?'cierre_jornada':'suspension';
            $this->query('INSERT INTO eventos (proceso_id,tipo,descripcion,tiempo_real,operador_id,created) VALUES (?,?,?,?,?,?)',[$process?:null,$type,$type,'00:00:00',$operator,$now]);
            if($closeDay)$this->query('UPDATE sesiones SET estado=?,fecha_fin=? WHERE id=?',['cerrada_con_actividad',$now,$session]);
            else $this->query('UPDATE detalles SET c_estado=2 WHERE id=?',[$detail]);
            $this->db->commit();return ['status'=>'ok','pendienteId'=>$id];
        } catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    public function close(int $id,$duration,int $state,string $circuit): array {
        $ms=filter_var($duration,FILTER_VALIDATE_INT);
        if ($ms===false || $ms<0 || !in_array($state,[0,1,2],true)) throw new InvalidArgumentException('Tiempo o estado inválido.');
        $this->db->beginTransaction();
        try {
            $p=$this->query('SELECT * FROM procesos WHERE id=?'.$this->lock(),[$id])->fetch(PDO::FETCH_ASSOC);
            if (!$p) throw new RuntimeException('Proceso inexistente.');
            if (in_array((int)$p['estado'],[2,4],true)) {
                $this->db->commit();return ['status'=>'ok','id'=>$id,'descartada'=>(int)$p['estado']===4,'tiempo_ms'=>$this->ms($p['tiempo_real'])];
            }
            $discard=$circuit==='armado' && $state===2 && $ms<=60000;
            $now=procesoFechaLocal();
            if ($discard) {
                $description='Descartada por duracion de hasta 60 segundos; tarea='.$p['tarea_id'].'; detalle='.$p['detalle_id'].'; ayudante='.$p['operador_aux'].'; duracion_ms='.$ms;
                $this->query('INSERT INTO eventos (proceso_id,tipo,descripcion,tiempo_real,operador_id,created) VALUES (?,?,?,?,?,?)',[$id,'lectura_descartada',$description,$this->time($ms),$p['operador_id'],$now]);
            }
            $final=$discard?4:$state;
            $this->query('UPDATE procesos SET estado=?,tiempo_real=?,fin=? WHERE id=?',[$final,$this->time($ms),in_array($final,[2,4],true)?$now:null,$id]);
            $this->db->commit();return ['status'=>'ok','id'=>$id,'descartada'=>$discard,'tiempo_ms'=>$ms];
        } catch(Throwable $e) {if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
}
