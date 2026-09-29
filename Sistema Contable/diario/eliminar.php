<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('diario/asientos.php');
}

if (!csrfValido()) {
    flash('error', 'La sesion expiro. Intente nuevamente.');
    redirect('diario/asientos.php');
}

$id = (int)($_POST['id'] ?? 0);
$asiento = obtenerAsiento($id);

if (!$asiento) {
    flash('error', 'El asiento no existe.');
    redirect('diario/asientos.php');
}

try {
    $pdo = db();
    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM asientos WHERE id = ?')->execute([$id]);
    recalcularSaldos();
    $pdo->commit();
} catch (Throwable $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    flash('error', 'No se pudo eliminar el asiento: ' . $e->getMessage());
    redirect('diario/asientos.php');
}

flash('exito', 'Asiento N. ' . $asiento['numero'] . ' eliminado. Los saldos fueron recalculados.');
redirect('diario/asientos.php');
