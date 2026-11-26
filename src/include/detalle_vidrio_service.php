<?php
final class DetalleVidrioService {
    private const TASK_CODE = 'COLOCACION-VIDRIO-OBRA';
    private const TASK_DESCRIPTION = 'Colocación en obra';
    private const EVENT_DESCRIPTION = 'colocacion en obra';

    public function __construct(private PDO $db) {}

    private function query(string $sql, array $args = []): PDOStatement {
        $statement = $this->db->prepare($sql);
        $statement->execute($args);
        return $statement;
    }

    public function registrarColocacion(int $detalleId, int $operadorSesion): array {
        if ($detalleId < 1) throw new InvalidArgumentException('Tipología inválida.');

        $this->db->beginTransaction();
        try {
            $lock = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $detalle = $this->query(
                'SELECT * FROM detalles WHERE id=?' . $lock,
                [$detalleId]
            )->fetch(PDO::FETCH_ASSOC);
            if (!$detalle) throw new InvalidArgumentException('La tipología no existe.');
            if (strpos((string)$detalle['tipologia'], 'V-') !== 0) {
                throw new InvalidArgumentException('Esta acción sólo está disponible para vidrios.');
            }

            $tareaId = $this->query('SELECT id FROM tareas WHERE codigo=? ORDER BY id LIMIT 1', [self::TASK_CODE])->fetchColumn();

            if ((int)$detalle['c_estado'] === 3) {
                if ($tareaId) {
                    $procesoId = $this->query(
                        'SELECT p.id FROM procesos p JOIN eventos e ON e.proceso_id=p.id
                         WHERE p.detalle_id=? AND p.tarea_id=? AND p.estado=2
                           AND e.tipo=? AND e.descripcion=?
                         ORDER BY p.id DESC LIMIT 1',
                        [$detalleId, $tareaId, 'lectura_descartada', self::EVENT_DESCRIPTION]
                    )->fetchColumn();
                    if ($procesoId) {
                        $this->db->commit();
                        return ['status' => 'ok', 'id' => $detalleId, 'procesoId' => (int)$procesoId, 'repetido' => true];
                    }
                }
                throw new InvalidArgumentException('La tipología ya está terminada. Actualice el listado.');
            }

            if ($this->query('SELECT id FROM procesos WHERE detalle_id=? AND estado=1 LIMIT 1', [$detalleId])->fetchColumn()) {
                throw new InvalidArgumentException('No se puede registrar mientras haya un proceso de armado activo.');
            }
            if ($this->query('SELECT id FROM pendientes WHERE detalle_id=? AND valided=0 LIMIT 1', [$detalleId])->fetchColumn()) {
                throw new InvalidArgumentException('La tipología tiene un proceso pendiente. Reanúdelo o ciérrelo antes de registrar la colocación.');
            }

            if (!$tareaId) {
                $this->query(
                    'INSERT INTO tareas (codigo,descripcion,barcode,barcode2d,tipo,enable) VALUES (?,?,?,?,?,?)',
                    [self::TASK_CODE, self::TASK_DESCRIPTION, '', null, 1, 0]
                );
                $tareaId = (int)$this->db->lastInsertId();
            } else {
                $tareaId = (int)$tareaId;
            }

            $operadorId = $operadorSesion > 0
                && $this->query('SELECT id FROM operadores WHERE id=?', [$operadorSesion])->fetchColumn()
                    ? $operadorSesion
                    : (int)$detalle['operador_id'];
            if ($operadorId < 1 || !$this->query('SELECT id FROM operadores WHERE id=?', [$operadorId])->fetchColumn()) {
                throw new InvalidArgumentException('No hay un operador válido para registrar la colocación.');
            }

            $ahora = procesoFechaLocal();
            $this->query(
                "UPDATE detalles SET c_estado=3,tiempo_real='00:00:00',tiempo_estimado='00:00:00',eficiencia=0 WHERE id=?",
                [$detalleId]
            );
            $this->query(
                'INSERT INTO procesos (detalle_id,tarea_id,estado,operador_id,inicio,fin,created,tiempo_real)
                 VALUES (?,?,2,?,?,?,?,?)',
                [$detalleId, $tareaId, $operadorId, $ahora, $ahora, $ahora, '00:00:00']
            );
            $procesoId = (int)$this->db->lastInsertId();
            $this->query(
                'INSERT INTO eventos (proceso_id,tipo,descripcion,tiempo_real,operador_id,created)
                 VALUES (?,?,?,?,?,?)',
                [$procesoId, 'lectura_descartada', self::EVENT_DESCRIPTION, '00:00:00', $operadorId, $ahora]
            );

            $this->db->commit();
            return ['status' => 'ok', 'id' => $detalleId, 'procesoId' => $procesoId, 'repetido' => false];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
}
