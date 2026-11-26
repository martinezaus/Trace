from pathlib import Path
p=Path('src/include/proceso_service.php');p.write_text(p.read_text(encoding='utf-8-sig'),encoding='utf-8')
p=Path('src/api/procesos.php');s=p.read_text(encoding='utf-8-sig');s=s.replace("require_once __DIR__ . '/../include/procesos_fecha.php';","require_once __DIR__ . '/../include/procesos_fecha.php';\n    require_once __DIR__ . '/../include/proceso_service.php';")
s=s.replace("'tarea_codigo' => $tarea_codigo]", "'tarea_codigo' => $tarea_codigo, 'estado' => (int)$proceso->estado]")
a=s.index("            $proceso_existente",s.index("$_POST['accion']==='add'"));b=s.index("        }elseif($_POST['accion']==='contarCompletadas')",a)
s=s[:a]+'''            try {
                $service=new ProcesoService(R::getDatabaseAdapter()->getDatabase()->getPDO());
                echo json_encode($service->start((int)($_POST['tarea']??0),(int)($_POST['detalle']??0),(int)($_POST['operador']??0),(int)($_POST['operadorAux']??0)));
            } catch(Throwable $e) {
                error_log('Inicio de proceso: '.$e->getMessage());
                echo json_encode(['id'=>0,'retomada'=>false,'error'=>'No se pudo iniciar la tarea.']);
            }
            exit;
'''+s[b:]
a=s.index("\n",s.index("}elseif($_POST['accion']==='update')"));s=s[:a]+'''
            try {
                $service=new ProcesoService(R::getDatabaseAdapter()->getDatabase()->getPDO());
                $response[]=$service->close((int)($_POST['id']??0),$_POST['tiempo']??null,(int)($_POST['estado']??-1),(string)($_POST['circuito']??''));
            } catch(Throwable $e) {
                error_log('Cierre de proceso: '.$e->getMessage());
                $response[]=['status'=>'error','id'=>0];
            }
            echo json_encode($response);
            exit;
        }
    }
''';p.write_text(s,encoding='utf-8')
