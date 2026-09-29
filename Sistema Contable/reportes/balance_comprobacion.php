<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

$tituloPagina = 'Balance de Comprobacion';

$desde = param('desde');
$hasta = param('hasta');
$mostrarSinMovimiento = param('todos') === '1';

$saldos = saldosPorCuenta($desde, $hasta, null, 8);

$filas = [];
foreach ($saldos as $s) {
    if (!$mostrarSinMovimiento && (float)$s['debe'] == 0.0 && (float)$s['haber'] == 0.0) {
        continue;
    }
    if ((int)$s['es_hoja'] !== 1) {
        continue;
    }
    $filas[] = $s;
}

$totalDebe = array_sum(array_map(static fn($s) => (float)$s['debe'], $filas));
$totalHaber = array_sum(array_map(static fn($s) => (float)$s['haber'], $filas));
$totalDeudor = array_sum(array_map(static fn($s) => (float)$s['deudor'], $filas));
$totalAcreedor = array_sum(array_map(static fn($s) => (float)$s['acreedor'], $filas));
$cuadra = abs($totalDebe - $totalHaber) < 0.009;

if (param('exportar') === 'csv') {
    $datos = [];
    foreach ($filas as $s) {
        $datos[] = [$s['codigo'], $s['nombre'], $s['nivel'], $s['debe'], $s['haber'], $s['deudor'], $s['acreedor']];
    }
    exportarCsv('balance_comprobacion.csv', ['Codigo', 'Cuenta', 'Nivel', 'Debito', 'Credito', 'Saldo deudor', 'Saldo acreedor'], $datos);
}

$agrupado = [];
foreach ($filas as $s) {
    $tipoActual = $agrupado[$s['tipo']] ?? null;
    $agrupado[$s['tipo']] = true;
}

require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <div>
        <h4 class="mb-0"><i class="bi bi-clipboard-check me-1"></i> Balance de Comprobacion</h4>
        <small class="text-muted">El reporte que dice si los libros estan bien. Si aqui no cuadra, hay un error en algun asiento.</small>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="<?= e(url('reportes/balance_comprobacion.php?exportar=csv&desde=' . urlencode($desde) . '&hasta=' . urlencode($hasta) . '&todos=' . ($mostrarSinMovimiento ? '1' : '0'))) ?>">
            <i class="bi bi-filetype-csv"></i> Exportar CSV
        </a>
        <button class="btn btn-outline-primary" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
    </div>
</div>

<div class="que-es-esto mb-3 no-print">
    <i class="bi bi-info-circle"></i>
    <div>
        Este reporte existe para <strong>auditar</strong>. Toma todas las cuentas con movimiento y compara dos sumas:
        el total de los <strong>Debitos</strong> y el total de los <strong>Creditos</strong>. Si son iguales, la
        <strong>partida doble</strong> se respetó en todas las cuentas. Reviselo cada vez que termine de registrar
        movimientos.
    </div>
</div>

<div class="leyenda-columnas no-print">
    <span><b>Debito:</b> suma de todo lo que entró a la cuenta.</span>
    <span><b>Credito:</b> suma de todo lo que salió de la cuenta.</span>
    <span><b>Saldo deudor:</b> lo que le queda a la cuenta.</span>
    <span><b>Saldo acreedor:</b> lo que la cuenta debe.</span>
</div>

<form method="get" class="card mb-3 no-print">
    <div class="card-header"><i class="bi bi-sliders me-1"></i> Periodo y opciones</div>
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label small mb-0" for="bc_desde">Desde la fecha</label>
                <input type="date" id="bc_desde" name="desde" class="form-control form-control-sm" value="<?= e($desde) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-0" for="bc_hasta">Hasta la fecha</label>
                <input type="date" id="bc_hasta" name="hasta" class="form-control form-control-sm" value="<?= e($hasta) ?>">
            </div>
            <div class="col-md-3">
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" name="todos" value="1" id="chkTodos" <?= $mostrarSinMovimiento ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="chkTodos">Incluir cuentas sin movimiento</label>
                </div>
            </div>
            <div class="col-md-5 d-flex gap-2 justify-content-end">
                <button class="btn btn-sm btn-primary" type="submit">Comprobar</button>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('reportes/balance_comprobacion.php')) ?>">Quitar filtros</a>
            </div>
        </div>
    </div>
</form>

<div class="reporte card">
    <div class="encabezado-reporte text-center">
        <div class="fw-bold"><?= e(empresa()['nombre']) ?></div>
        <div><?= e(empresa()['carrera']) ?></div>
        <h1 class="mt-2 mb-1">Balance de Comprobacion</h1>
        <div class="small">
            <?= $desde ? 'Del ' . e(fechaCorta($desde)) : 'Desde el inicio' ?> al <?= e(fechaCorta($hasta ?: hoy())) ?>
            &middot; Saldos deudores y acreedores &middot; Expresado en dolares
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-sm table-bordered tabla-contables align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:110px">Codigo</th>
                    <th>Cuenta</th>
                    <th class="num" style="width:140px">Debito</th>
                    <th class="num" style="width:140px">Credito</th>
                    <th class="num" style="width:140px">Saldo deudor</th>
                    <th class="num" style="width:140px">Saldo acreedor</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$filas): ?>
                <tr><td colspan="6">
                    <div class="vacio-amable">
                        <i class="bi bi-inbox"></i>
                        <h5>Nada que comprobar todavia</h5>
                        <p class="mb-2">No hay cuentas con movimiento en este periodo. Registre un asiento y el reporte se llenara solo.</p>
                        <a class="btn btn-sm btn-primary" href="<?= e(url('diario/nuevo.php')) ?>">Registrar un asiento</a>
                    </div>
                </td></tr>
            <?php endif; ?>
            <?php
            $tipoPrevio = null;
            foreach ($filas as $s):
                if ($tipoPrevio !== $s['tipo']):
                    $tipoPrevio = $s['tipo'];
            ?>
                <tr class="marca-clase">
                    <td colspan="6"><?= e(tipoNombre($s['tipo'])) ?> (codigo <?= e($s['codigo'][0]) ?>)</td>
                </tr>
            <?php endif; ?>
                <tr>
                    <td class="fw-semibold"><?= e($s['codigo']) ?></td>
                    <td><?= guiones((int)$s['nivel']) ?><?= e($s['nombre']) ?></td>
                    <td class="num"><?= (float)$s['debe'] > 0 ? money($s['debe']) : '' ?></td>
                    <td class="num"><?= (float)$s['haber'] > 0 ? money($s['haber']) : '' ?></td>
                    <td class="num"><?= (float)$s['deudor'] > 0 ? money($s['deudor']) : '' ?></td>
                    <td class="num"><?= (float)$s['acreedor'] > 0 ? money($s['acreedor']) : '' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="marca-total">
                    <td colspan="2" class="text-end">Totales</td>
                    <td class="num"><?= money($totalDebe) ?></td>
                    <td class="num"><?= money($totalHaber) ?></td>
                    <td class="num"><?= money($totalDeudor) ?></td>
                    <td class="num"><?= money($totalAcreedor) ?></td>
                </tr>
                <tr class="marca-total-final">
                    <td colspan="2"><?= $cuadra ? 'La suma de los debitos es igual a la suma de los creditos: PARTIDA DOBLE CORRECTA' : 'DESCUADRADO: diferencia de ' . money($totalDebe - $totalHaber) ?></td>
                    <td colspan="4" class="text-end"><?= $cuadra ? 'Diferencia ' . money(0) : money($totalDebe - $totalHaber) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
