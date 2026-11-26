<?php
/** Una tarea por PDF. Tiempos y transiciones se calculan en el servidor. */
final class CortePdfService {
    private PDO $db;
    private $clock;
    public function __construct(PDO $db, ?callable $clock = null) {
        $this->db = $db;
        $this->clock = $clock ?? static fn() => (int)round(microtime(true) * 1000);
    }
    private function query(string $sql, array $args = []): PDOStatement {
        $q = $this->db->prepare($sql); $q->execute($args); return $q;
    }
    private function lock(): string { return $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : ''; }
    private function time(int $ms): string { $s=intdiv(max(0,$ms),1000); return sprintf('%02d:%02d:%02d',intdiv($s,3600),intdiv($s%3600,60),$s%60); }
    private function activeTime(array $t, int $now): int {
        // Si desaparece el navegador, no se acumula toda la noche como trabajo.
        $end = min($now, (int)$t['heartbeat_ms'] + 30000);
        return (int)$t['elapsed_ms'] + max(0, $end - (int)$t['active_since']);
    }
    private function save(array $t): void {
        $fields=['detalle_id','proceso_id','estado','operador_id','sesion_id','elapsed_ms','active_since','heartbeat_ms','pause_since','pause_suma','evento_id','token'];
        $args=[];foreach($fields as $f)$args[]=$t[$f];$args[]=$t['documento_id'];
        $this->query('UPDATE cortepdf SET '.implode(',',array_map(static fn($f)=>$f.'=?',$fields)).' WHERE documento_id=?',$args);
    }
    private function sync(array $t): void {
        $seconds=$this->time((int)$t['elapsed_ms']);
        $finished=$t['estado']==='finalizada';
        $this->query('UPDATE procesos SET estado=?, tiempo_real=?, fin=? WHERE id=?',[$finished?2:($t['estado']==='pendiente'?0:1),$seconds,$finished?date('Y-m-d H:i:s'):null,$t['proceso_id']]);
        $this->query('UPDATE detalles SET tiempo_real=?, c_estado=?, operador_id=? WHERE id=?',[$seconds,$finished?3:($t['estado']==='pausa'?1:0),$t['operador_id'],$t['detalle_id']]);
    }
    public function completeMarked(int $doc): ?array {
        $counts=$this->query('SELECT COUNT(*) total, SUM(CASE WHEN COALESCE(tilde,0)<>1 THEN 1 ELSE 0 END) pendientes FROM documentositems WHERE documento_id=?',[$doc])->fetch(PDO::FETCH_ASSOC);
        if(!(int)$counts['total'] || (int)$counts['pendientes'])return null;
        $t=$this->query('SELECT * FROM cortepdf WHERE documento_id=?'.$this->lock(),[$doc])->fetch(PDO::FETCH_ASSOC);
        if($t && $t['estado']!=='finalizada'){
            if($t['estado']==='activa')$t['elapsed_ms']=$this->activeTime($t,($this->clock)());
            $t['estado']='finalizada';$this->save($t);$this->sync($t);
        }
        return $t ?: null;
    }
    private function complete(int $doc): bool {
        return (bool)$this->query('SELECT COUNT(*)>0 AND SUM(CASE WHEN COALESCE(tilde,0)<>1 THEN 1 ELSE 0 END)=0 FROM documentositems WHERE documento_id=?',[$doc])->fetchColumn();
    }
    public function list(int $operador): array {
        return $this->query("SELECT c.documento_id,c.estado,c.elapsed_ms,d.orden_id,d.nombre,o.codigo FROM cortepdf c JOIN documentos d ON d.id=c.documento_id JOIN ordenes o ON o.id=d.orden_id WHERE c.operador_id=? AND c.estado<>'finalizada' ORDER BY c.documento_id",[$operador])->fetchAll(PDO::FETCH_ASSOC);
    }
    public function documents(int $order): array {
        return $this->query('SELECT d.id,d.nombre,d.descripcion,d.tipo,d.archivo_final,c.estado,c.operador_id,(SELECT COUNT(*)>0 AND SUM(CASE WHEN COALESCE(i.tilde,0)<>1 THEN 1 ELSE 0 END)=0 FROM documentositems i WHERE i.documento_id=d.id) completo FROM documentos d LEFT JOIN cortepdf c ON c.documento_id=d.id WHERE d.orden_id=? ORDER BY d.id',[$order])->fetchAll(PDO::FETCH_ASSOC);
    }
    public function action(string $action, int $op, int $session, int $doc, string $token='', int $pauseId=0, bool $partial=false): array {
        $this->db->beginTransaction();
        try {
            if(!$this->query('SELECT id FROM operadores WHERE id=?'.$this->lock(),[$op])->fetchColumn())throw new RuntimeException('Operador no válido.');
            $s=$this->query('SELECT * FROM sesiones WHERE id=? AND operador_id=?'.$this->lock(),[$session,$op])->fetch(PDO::FETCH_ASSOC);
            if(!$s)throw new RuntimeException('La sesión no pertenece al operador.');
            if($s['estado']!=='activa'){
                if($action==='jornada'){$this->db->commit();return ['ok'=>true,'cerrada'=>true];}
                throw new RuntimeException('La jornada ya está cerrada.');
            }
            $now=($this->clock)();
            if($action==='jornada' && !$doc){
                if($this->query("SELECT documento_id FROM cortepdf WHERE operador_id=? AND estado IN ('activa','pausa')",[$op])->fetchColumn())throw new RuntimeException('Hay una tarea activa. Recargue para recuperarla antes de cerrar.');
                $this->query('UPDATE sesiones SET estado=?,fecha_fin=? WHERE id=?',[$this->query('SELECT COUNT(*) FROM cortepdf WHERE sesion_id=?',[$session])->fetchColumn() ? 'cerrada_con_actividad' : 'cerrada_sin_actividad',date('Y-m-d H:i:s'),$session]);
                $this->db->commit();return ['ok'=>true,'cerrada'=>true];
            }
            $t=$this->query('SELECT * FROM cortepdf WHERE documento_id=?'.$this->lock(),[$doc])->fetch(PDO::FETCH_ASSOC);
            $d=$this->query('SELECT * FROM documentos WHERE id=?'.$this->lock(),[$doc])->fetch(PDO::FETCH_ASSOC);
            if(!$d || !in_array((int)$d['tipo'],[2,3],true))throw new RuntimeException('Seleccione un PDF de corte válido.');
            if($action==='iniciar'){
                if($this->complete($doc)){
                    $completed=$this->completeMarked($doc);$this->db->commit();
                    return ['ok'=>true,'tarea'=>$completed,'soloLectura'=>true];
                }
                $order=$this->query('SELECT c_estado FROM ordenes WHERE id=?',[$d['orden_id']])->fetchColumn();
                if(in_array((int)$order,[2,3],true))throw new RuntimeException('La orden está terminada o instalada.');
                if($t && $t['estado']==='finalizada'){$this->db->commit();return ['ok'=>true,'tarea'=>$t,'soloLectura'=>true];}
                if($this->query("SELECT documento_id FROM cortepdf WHERE operador_id=? AND documento_id<>? AND estado IN ('activa','pausa')",[$op,$doc])->fetchColumn())throw new RuntimeException('Deje pendiente la tarea actual antes de abrir otra.');
                if($t && in_array($t['estado'],['activa','pausa'],true) && (int)$t['operador_id']!==$op)throw new RuntimeException('Otro operario tiene activo este PDF. Debe dejarlo pendiente.');
                $created = !$t;
                $previousState = $t['estado'] ?? 'pendiente';
                $previousOperator = (int)($t['operador_id'] ?? $op);
                if(!$t){
                    $config=$this->query('SELECT tiempo_cortep,tiempo_cortev FROM systemnegocio ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
                    $estimated=$config[(int)$d['tipo']===2?'tiempo_cortep':'tiempo_cortev']??'00:00:00';
                    $this->query('INSERT INTO detalles (orden_id,operador_id,codigo,tipologia,descripcion,etapas,tiempo_estimado,c_estado) VALUES (?,?,?,?,?,?,?,?)',[$d['orden_id'],$op,'C'.str_pad((string)$doc,12,'0',STR_PAD_LEFT),'CORTE',$d['nombre'],1,$estimated,0]);
                    $detail=(int)$this->db->lastInsertId();
                    $this->query('INSERT INTO procesos (detalle_id,tarea_id,estado,operador_id) VALUES (?,?,?,?)',[$detail,(int)$d['tipo']===2?20:21,1,$op]);
                    $process=(int)$this->db->lastInsertId();
                    $this->query("INSERT INTO cortepdf (documento_id,detalle_id,proceso_id,estado,operador_id,sesion_id) VALUES (?,?,?,'pendiente',?,?)",[$doc,$detail,$process,$op,$session]);
                    $t=$this->query('SELECT * FROM cortepdf WHERE documento_id=?',[$doc])->fetch(PDO::FETCH_ASSOC);
                }
                if($t['estado']==='activa')$t['elapsed_ms']=$this->activeTime($t,$now);
                // Una pausa interrumpida se conserva y se cierra explícitamente.
                if($t['estado']!=='pausa'){$t['estado']='activa';$t['active_since']=$now;}
                $t['heartbeat_ms']=$now;$t['operador_id']=$op;$t['sesion_id']=$session;$t['token']=bin2hex(random_bytes(24));
                $this->query('UPDATE procesos SET operador_id=? WHERE id=?',[$op,$t['proceso_id']]);
                if($created || $previousState==='pendiente'){
                    $description=($created?'Inicio':'Retoma').' de PDF #'.$doc;
                    if($previousOperator!==$op)$description.=' (operador anterior #'.$previousOperator.')';
                    $this->query('INSERT INTO eventos (proceso_id,tipo,descripcion,tiempo_real,operador_id) VALUES (?,?,?,?,?)',[$t['proceso_id'],'reanudar',$description,'00:00:00',$op]);
                }
            } else {
                if(!$t || (int)$t['operador_id']!==$op || !hash_equals((string)$t['token'],$token))throw new RuntimeException('Esta tarea cambió de sesión. Recargue para recuperar su estado.');
                if((int)$t['sesion_id']!==$session)throw new RuntimeException('La tarea pertenece a otra jornada.');
                if(in_array($action,['guardar','finalizar'],true) && $t['estado']==='finalizada'){$this->db->commit();return ['ok'=>true,'tarea'=>$t];}
                if($action==='pendiente' && $t['estado']==='pendiente'){$this->db->commit();return ['ok'=>true,'tarea'=>$t];}
                if($action==='reanudar' && $t['estado']==='activa'){$this->db->commit();return ['ok'=>true,'tarea'=>$t];}
                if($action==='pausa' && $t['estado']==='pausa'){$this->db->commit();return ['ok'=>true,'tarea'=>$t];}
                if($t['estado']==='activa'){$t['elapsed_ms']=$this->activeTime($t,$now);$t['active_since']=$now;$t['heartbeat_ms']=$now;}
                if($action==='guardar'){
                    if(!in_array($t['estado'],['activa','pausa'],true))throw new RuntimeException('La tarea ya no está activa.');
                } elseif($action==='pausa'){
                    if($t['estado']!=='activa')throw new RuntimeException('La tarea no está activa.');
                    $p=$this->query('SELECT * FROM pausas WHERE id=?',[$pauseId])->fetch(PDO::FETCH_ASSOC);
                    if(!$p)throw new RuntimeException('Pausa no encontrada.');
                    $this->query('INSERT INTO eventos (proceso_id,tipo,descripcion,tiempo_real,operador_id) VALUES (?,?,?,?,?)',[$t['proceso_id'],'pausa',$p['descripcion'],'00:00:00',$op]);
                    $t['evento_id']=(int)$this->db->lastInsertId();$t['pause_since']=$now;$t['pause_suma']=(int)$p['flag'];$t['estado']='pausa';
                } elseif($action==='reanudar'){
                    if($t['estado']!=='pausa')throw new RuntimeException('No hay una pausa activa.');
                    $duration=max(0,$now-(int)$t['pause_since']);
                    $this->query('UPDATE eventos SET tiempo_real=? WHERE id=?',[$this->time($duration),$t['evento_id']]);
                    if((int)$t['pause_suma']===1)$t['elapsed_ms']+=(int)$duration;
                    $t['estado']='activa';$t['active_since']=$now;$t['heartbeat_ms']=$now;$t['pause_since']=0;$t['evento_id']=null;
                } elseif(in_array($action,['pendiente','jornada','finalizar'],true)){
                    if($t['estado']==='pausa')throw new RuntimeException('Cierre la pausa antes de dejar pendiente o finalizar.');
                    if(!in_array($t['estado'],['activa','pendiente'],true))throw new RuntimeException('La tarea ya está terminada.');
                    if($action==='finalizar'){
                        $remaining=(int)$this->query('SELECT COUNT(*) FROM documentositems WHERE documento_id=? AND COALESCE(tilde,0)<>1',[$doc])->fetchColumn();
                        if($remaining && !$partial)throw new RuntimeException('Hay ítems sin tildar. Puede dejar la tarea pendiente o confirmar el cierre parcial.');
                        $t['estado']='finalizada';
                    }else $t['estado']='pendiente';
                    if($action==='pendiente')$this->query('INSERT INTO eventos (proceso_id,tipo,descripcion,tiempo_real,operador_id) VALUES (?,?,?,?,?)',[$t['proceso_id'],'suspension','PDF pendiente para continuar luego','00:00:00',$op]);
                    if($action==='jornada'){
                        $this->query('UPDATE sesiones SET estado=?,fecha_fin=? WHERE id=?',['cerrada_con_actividad',date('Y-m-d H:i:s'),$session]);
                        $this->query('INSERT INTO eventos (proceso_id,tipo,descripcion,tiempo_real,operador_id) VALUES (?,?,?,?,?)',[$t['proceso_id'],'cierre_jornada','PDF pendiente al cerrar jornada','00:00:00',$op]);
                    }
                }else throw new RuntimeException('Acción no válida.');
            }
            $this->save($t);$this->sync($t);$this->db->commit();
            return ['ok'=>true,'tarea'=>$t,'cerrada'=>$action==='jornada'];
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
}
