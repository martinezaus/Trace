<?php
declare(strict_types=1);

function revisionMarcarDocumento(PDO $db, int $documentoId, int $operadorId): array
{
    if ($documentoId < 1 || $operadorId < 1) throw new InvalidArgumentException('Documento u operador inválido.');
    $ownTransaction = !$db->inTransaction();
    if ($ownTransaction) $db->beginTransaction();
    try {
        $lock = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $q = $db->prepare('SELECT CAST(revisado AS UNSIGNED) AS revisado FROM documentos WHERE id=?' . $lock);
        $q->execute([$documentoId]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new OutOfBoundsException('No se encontró el documento.');
        if ((int)$row['revisado'] === 1) throw new DomainException('El documento ya fue revisado.');
        $q = $db->prepare('UPDATE documentos SET revisado=1,fecha_hora_revisado=CURRENT_TIMESTAMP,operador_rev=? WHERE id=? AND revisado=0');
        $q->execute([$operadorId, $documentoId]);
        if ($q->rowCount() !== 1) throw new DomainException('El documento ya fue revisado.');
        $q = $db->prepare('SELECT fecha_hora_revisado,operador_rev FROM documentos WHERE id=?');
        $q->execute([$documentoId]);
        $result = $q->fetch(PDO::FETCH_ASSOC);
        if ($ownTransaction) $db->commit();
        return $result;
    } catch (Throwable $e) {
        if ($ownTransaction && $db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

function revisionRevertirDocumento(PDO $db, int $documentoId): bool
{
    if ($documentoId < 1) throw new InvalidArgumentException('Documento inválido.');
    $ownTransaction = !$db->inTransaction();
    if ($ownTransaction) $db->beginTransaction();
    try {
        $lock = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $q = $db->prepare('SELECT CAST(revisado AS UNSIGNED) AS revisado FROM documentos WHERE id=?' . $lock);
        $q->execute([$documentoId]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new OutOfBoundsException('No se encontró el documento.');
        if ((int)$row['revisado'] !== 1) {
            if ($ownTransaction) $db->commit();
            return false;
        }
        $q = $db->prepare('UPDATE documentos SET revisado=0,fecha_hora_revisado=NULL,operador_rev=NULL WHERE id=? AND revisado=1');
        $q->execute([$documentoId]);
        if ($q->rowCount() !== 1) throw new RuntimeException('No se pudo revertir la revisión.');
        if ($ownTransaction) $db->commit();
        return true;
    } catch (Throwable $e) {
        if ($ownTransaction && $db->inTransaction()) $db->rollBack();
        throw $e;
    }
}
