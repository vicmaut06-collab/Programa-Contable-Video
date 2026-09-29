<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

$id = (int)param('id', '0');
$asiento = obtenerAsiento($id);

if (!$asiento) {
    flash('error', 'El asiento solicitado no existe.');
    redirect('diario/asientos.php');
}

$partidas = obtenerPartidas($id);
$tituloPagina = 'Asiento N. ' . $asiento['numero'];

$debe = array_sum(array_map(static fn($p) => (float)$p['debe'], $partidas));
$haber = array_sum(array_map(static fn($p) => (float)$p['haber'], $partidas));

require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <div>
        <h4 class="mb-0"><i class="bi bi-journal-check me-1"></i> Asiento N. <?= (int)$asiento['numero'] ?></h4>
        <small class="text-muted"><?= e(fechaLarga($asiento['fecha'])) ?><?= $asiento['documento'] ? ' &middot; ' . e($asiento['documento']) : '' ?></small>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="<?= e(url('diario/asientos.php')) ?>"><i class="bi bi-arrow-left"></i> Volver al Libro Diario</a>
        <a class="btn btn-primary" href="<?= e(url('diario/editar.php?id=' . $id)) ?>"><i class="bi bi-pencil"></i> Editar este asiento</a>
    </div>
</div>

<div class="card">
    <div class="card-header">Que paso segun el concepto</div>
    <div class="card-body">
        <p class="mb-0 fs-6"><?= e($asiento['concepto']) ?></p>
    </div>
    <div class="card-header">Como se registro</div>
    <div class="table-responsive">
        <table class="table tabla-contables table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:80px">Parte</th>
                    <th style="width:120px">Codigo</th>
                    <th>Cuenta</th>
                    <th class="num" style="width:150px">Debito</th>
                    <th class="num" style="width:150px">Credito</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($partidas as $i => $p): ?>
                <tr>
                    <td><?= (int)$p['linea'] ?></td>
                    <td class="fw-semibold"><?= e($p['cuenta_codigo']) ?></td>
                    <td>
                        <?= e($p['cuenta_nombre']) ?>
                        <a class="ms-2 small text-decoration-none" target="_blank"
                           href="<?= e(url('mayor/index.php?cuenta=' . (int)$p['cuenta_id'] . '&desde=' . urlencode($asiento['fecha']) . '&hasta=' . urlencode($asiento['fecha']))) ?>"
                           title="Ver el movimiento de esta cuenta en el Libro Mayor">
                            <i class="bi bi-box-arrow-up-right"></i> Ver en el Libro Mayor
                        </a>
                    </td>
                    <td class="num"><?= $p['debe'] > 0 ? money($p['debe']) : '' ?></td>
                    <td class="num"><?= $p['haber'] > 0 ? money($p['haber']) : '' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot class="marca-total">
                <tr>
                    <td colspan="3" class="text-end">Suma del asiento</td>
                    <td class="num"><?= money($debe) ?></td>
                    <td class="num"><?= money($haber) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>
            <i class="bi bi-<?= abs($debe - $haber) < 0.009 ? 'check-circle text-success' : 'exclamation-triangle text-danger' ?>"></i>
            Partida doble:
            <strong class="<?= abs($debe - $haber) < 0.009 ? 'cuadra' : 'no-cuadra' ?>">
                <?= abs($debe - $haber) < 0.009 ? 'Correcta' : 'DESCUADRADA' ?>
            </strong>
            <?php if (abs($debe - $haber) < 0.009): ?>
                <span class="text-muted">(<?= count($partidas) ?> partidas, <?= money($debe) ?> en cada lado)</span>
            <?php endif; ?>
        </span>
        <span class="text-muted">Guardado el <?= e(date('d/m/Y H:i', strtotime($asiento['created_at']))) ?></span>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
