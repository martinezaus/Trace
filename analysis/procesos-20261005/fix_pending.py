from pathlib import Path
p=Path('src/include/proceso_service.php');s=p.read_text(encoding='utf-8');s=s.replace('int $helper): array','int $helper,int $pendingId=0): array',1)
s=s.replace("            $now=procesoFechaLocal();$resumed=false;$elapsed=0;",'''            $pending=null;
            if ($pendingId) {
                $pending=$this->query('SELECT * FROM pendientes WHERE id=? AND detalle_id=? AND valided=0'.$this->lock(),[$pendingId,$detail])->fetch(PDO::FETCH_ASSOC);
                if (!$pending || !$p || (int)$pending['proceso_id']!==(int)$p['id']) throw new RuntimeException('El pendiente ya cambió. Recargue la tipología.');
            }
            $now=procesoFechaLocal();$resumed=false;$elapsed=0;''')
s=s.replace("(int)$p['operador_id']!==$operator)","(int)$p['operador_id']!==$operator && !$pending)",1)
s=s.replace("$resumed=!$reset;$elapsed=$reset?0:$this->ms($p['tiempo_real']);", "$resumed=!$reset;$elapsed=$reset?0:max($this->ms($p['tiempo_real']),(int)($pending['tiempo_tarea_ms']??0));")
s=s.replace("            $this->db->commit();return ['id'", "            if ($pending) $this->query('UPDATE pendientes SET valided=1,updated=? WHERE id=?',[$now,$pendingId]);\n            $this->db->commit();return ['id'",1)
method='''    public function savePending(array $data,bool $closeDay=false): array {
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
'''
s=s.replace('    public function close(',method+'    public function close(',1);p.write_text(s,encoding='utf-8')
p=Path('src/api/procesos.php');s=p.read_text(encoding='utf-8');s=s.replace("(int)($_POST['operadorAux']??0)))", "(int)($_POST['operadorAux']??0),(int)($_POST['pendienteId']??0)))")
s=s.replace("        }elseif($_POST['accion']==='contarCompletadas')",'''        }elseif($_POST['accion']==='suspender'){
            try {
                $data=json_decode($_POST['datos']??'',true,512,JSON_THROW_ON_ERROR);
                $service=new ProcesoService(R::getDatabaseAdapter()->getDatabase()->getPDO());
                echo json_encode($service->savePending($data));
            }catch(Throwable $e){error_log('Suspender proceso: '.$e->getMessage());echo json_encode(['status'=>'error']);}
            exit;
        }elseif($_POST['accion']==='contarCompletadas')''');p.write_text(s,encoding='utf-8')
p=Path('src/api/sesiones.php');s=p.read_text(encoding='utf-8-sig');s=s.replace("    require_once '../include/functions.php';", "    require_once '../include/functions.php';\n    require_once '../include/procesos_fecha.php';\n    require_once '../include/proceso_service.php';")
a=s.index('            try {',s.index("$accion === 'cerrarConPendiente'"));b=s.index("        }else if ($accion === 'update')",a)
s=s[:a]+'''            try {
                $datosCierre['sesionId']=$sesionId;
                $service=new ProcesoService(R::getDatabaseAdapter()->getDatabase()->getPDO());
                echo json_encode([$service->savePending($datosCierre,true)]);
            } catch(Throwable $e) {
                registrarLog('ERROR','SESION','Cierre transaccional fallido: '.$e->getMessage());
                echo json_encode([['status'=>'error','valor'=>'No se pudo guardar el cierre.']]);
            }
'''+s[b:];p.write_text(s,encoding='utf-8')
p=Path('src/plugins/js/jquery.procesosuspenderV1.0.3.js');p.write_text('''function suspendeTipologiaActual() { return transicionArmado(suspenderTipologiaArmado); }
async function suspenderTipologiaArmado() {
    if (!estado.tareaEnCurso || !estado.procesoId || estado.pausaActiva) {
        mostrarMensaje('Debe tener una tarea activa sin pausa para suspender.','#BD1A00',12000,'mensaje');
        return;
    }
    const datos={ordenId:estado.ordenId,detalleId:estado.detalleActualId,procesoId:estado.procesoId,
        sesionId:estado.sesionId,operadorId:estado.operadorId,tareaActualId:estado.tareaActualId,
        tiempoOrdenMs:estado.inicioOrdenTs ? Date.now()-estado.inicioOrdenTs : (estado.tiempoOrdenMs||0),
        tiempoTareaMs:tiempoTareaActualMs(),tiempoTareaTotalMs:estado.tiempoTareaTotalMs||0,
        tiempoOperadorMs:estado.tiempoOperario||0,menu:getMenuActual()};
    try {
        const r=await $.post(URL_API.proceso,{accion:'suspender',datos:JSON.stringify(datos)},null,'json');
        if (r?.status !== 'ok' || !Number(r.pendienteId)) throw new Error('Suspensión no confirmada');
        limpiarTareaArmado();
        Object.assign(estado,{detalleActualId:0,tareaActualNro:0,tiempoTareaTotalMs:0,operadorIdAux:0});
        $('#topologia,#label_tpo_ideal,#label_operario_aux').text('');
        mostrarMensaje('Tipología suspendida. El tiempo y la tarea quedaron guardados.','#32A123',15000,'mensaje');
    } catch(e) {
        mostrarMensaje('No se pudo suspender. La tarea sigue activa; vuelva a intentarlo.','#BD1A00',15000,'mensaje');
        logger('ERROR','SUSPENDER',e.message || e);
    }
}
''',encoding='utf-8')
# Resume atomically claims the process and consumes pending, before starting the local timer.
p=Path('src/plugins/js/jquery.procesoV1.0.3.js');s=p.read_text(encoding='utf-8');s=s.replace('estado.tareaActualId = Number(tareaPendiente[0].id);', '''const retomada = await $.post(URL_API.proceso,{accion:'add',tarea:Number(tareaPendiente[0].id),
                    detalle:detalle.id,operador:estado.operadorId,operadorAux:estado.operadorIdAux,
                    pendienteId:estado.pendienteId},null,'json');
                if (!Number(retomada?.id)) throw new Error('No se pudo recuperar la tarea pendiente.');
                estado.procesoId = Number(retomada.id);
                estado.tiempoTareaMs = Number(retomada.tiempo_ms) || 0;
                estado.tareaActualId = Number(tareaPendiente[0].id);''',1)
s=s.replace('estado.inicioTareaTs = ahora;\n\t\t\t\tiniciarTimer(ahora,', 'estado.inicioTareaTs = Date.now();\n\t\t\t\tiniciarTimer(estado.inicioTareaTs,',1)
s=s.replace('const respStatus = await $.post(URL_API.pendiente, {accion: "status", id: estado.pendienteId});', 'const respStatus = estado.tareaEnCurso ? [{status:"ok"}] : await $.post(URL_API.pendiente, {accion: "status", id: estado.pendienteId});',1)
s=s.replace('async function transicionArmado(accion) {','async function transicionArmado(accion) {',1)
s=s.replace('try { return await accion(); }\n    finally', '''try { return await accion(); }
    catch(e) { logger('ERROR','PROCESO',e.message || e); mostrarMensaje('No se pudo completar la operación. Vuelva a intentarlo.','#BD1A00',12000,'mensaje'); }
    finally''',1)
p.write_text(s,encoding='utf-8')
