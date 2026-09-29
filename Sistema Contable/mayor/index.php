<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

$tituloPagina = 'Libro Mayor';

$desde = param('desde');
$hasta = param('hasta');
$cuentaId = (int)param('cuenta', '0');

$cuentasHoja = db()->query(
    'SELECT id, codigo, nombre, naturaleza, nivel FROM catalogo_cuentas
     WHERE es_hoja = 1 AND activo = 1 ORDER BY codigo'
)->fetchAll();

$resumen = [];
$hojasResumen = [];
$detalle = [];
$cuentaSel = null;

$saldos = saldosPorCuenta($desde, $hasta, null, 8);

foreach ($saldos as $s) {
    if ((int)$s['es_hoja'] !== 1) {
        continue;
    }
    if ((float)$s['debe'] == 0.0 && (float)$s['haber'] == 0.0) {
        continue;
    }
    $resumen[] = $s;
    $hojasResumen[$s['codigo']] = true;
}

// Igual que en el balance de comprobacion: se inserta la cuenta de mayor y el
// subrubro encima de sus hojas, con subtotal, para que se lea
// "EFECTIVO Y EQUIVALENTES DE EFECTIVO > CAJA > CAJA PRINCIPAL".
$arbolResumen = [];
foreach ($saldos as $s) {
    if ((int)$s['es_hoja'] === 1) {
        if (isset($hojasResumen[$s['codigo']])) {
            $s['es_fila_grupo'] = 0;
            $arbolResumen[] = $s;
        }
        continue;
    }
    if (!in_array((int)$s['nivel'], [4, 6], true)) {
        continue;
    }
    foreach (array_keys($hojasResumen) as $codigoHoja) {
        if (str_starts_with((string)$codigoHoja, $s['codigo'])) {
            $s['es_fila_grupo'] = 1;
            $arbolResumen[] = $s;
            break;
        }
    }
}

$totalDebeResumen = array_sum(array_map(static fn($s) => (float)$s['debe'], $resumen));
$totalHaberResumen = array_sum(array_map(static fn($s) => (float)$s['haber'], $resumen));

if ($cuentaId > 0) {
    $st = db()->prepare('SELECT * FROM catalogo_cuentas WHERE id = ?');
    $st->execute([$cuentaId]);
    $cuentaSel = $st->fetch() ?: null;

    if ($cuentaSel) {
        $condiciones = ['p.cuenta_codigo LIKE ?', "a.estado = 'REGISTRADO'"];
        $params = [$cuentaSel['codigo'] . '%'];
        if ($desde) {
            $condiciones[] = 'a.fecha >= ?';
            $params[] = $desde;
        }
        if ($hasta) {
            $condiciones[] = 'a.fecha <= ?';
            $params[] = $hasta;
        }
        $sql = 'SELECT a.id AS asiento_id, a.numero, a.fecha, a.concepto, a.documento, p.debe, p.haber
                FROM asiento_partidas p
                INNER JOIN asientos a ON a.id = p.asiento_id
                WHERE ' . implode(' AND ', $condiciones) . '
                ORDER BY a.fecha, a.numero, p.linea';
        $st = db()->prepare($sql);
        $st->execute($params);
        $detalle = $st->fetchAll();
    }
}

if (param('exportar') === 'csv') {
    $filas = [];
    foreach ($resumen as $s) {
        $filas[] = [$s['codigo'], $s['nombre'], $s['debe'], $s['haber'], $s['deudor'], $s['acreedor']];
    }
    exportarCsv('libro_mayor.csv', ['Codigo', 'Cuenta', 'Total debito', 'Total credito', 'Saldo deudor', 'Saldo acreedor'], $filas);
}

$totalDebeDetalle = 0.0;
$totalHaberDetalle = 0.0;
$saldoAcumulado = 0.0;
$naturaleza = $cuentaSel['naturaleza'] ?? 'D';
foreach ($detalle as $i => $d) {
    $totalDebeDetalle += (float)$d['debe'];
    $totalHaberDetalle += (float)$d['haber'];
    $saldoAcumulado = round($saldoAcumulado + (float)$d['debe'] - (float)$d['haber'], 2);
    $detalle[$i]['saldo'] = $saldoAcumulado;
}
$netoFinal = round($totalDebeDetalle - $totalHaberDetalle, 2);

require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <div>
        <h4 class="mb-0"><i class="bi bi-list-ul me-1"></i> Libro Mayor</h4>
        <small class="text-muted">El detalle de cada cuenta: que se le sumo, que se le resto y cuanto saldo le queda.</small>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="<?= e(url('mayor/index.php?exportar=csv&desde=' . urlencode($desde) . '&hasta=' . urlencode($hasta))) ?>">
            <i class="bi bi-filetype-csv"></i> Exportar CSV
        </a>
        <button class="btn btn-outline-primary" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
    </div>
</div>

<div class="que-es-esto mb-3 no-print">
    <i class="bi bi-info-circle"></i>
    <div>
        Elija una <strong>cuenta de detalle</strong> (por ejemplo <em>11010101 Caja Principal</em>) para ver todos sus
        movimientos uno por uno, o deje <strong>Resumen de todas las cuentas</strong> para ver el saldo acumulado de
        cada cuenta del catalogo. Los saldos se calculan solos con cada asiento.
    </div>
</div>

<div class="leyenda-columnas no-print">
    <span><b>Debito:</b> el dinero que entra a la cuenta.</span>
    <span><b>Credito:</b> el dinero que sale de la cuenta.</span>
    <span><b>Saldo deudor:</b> lo que la cuenta tiene a favor.</span>
    <span><b>Saldo acreedor:</b> lo que la cuenta debe.</span>
</div>

<form method="get" class="card mb-3 no-print">
    <div class="card-header"><i class="bi bi-search me-1"></i> Que quiero ver</div>
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label small mb-0" for="m_cuenta">Cuenta</label>
                <select name="cuenta" id="m_cuenta" class="form-select form-select-sm">
                    <option value="0">Resumen de todas las cuentas</option>
                    <?php foreach ($cuentasHoja as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $cuentaId === (int)$c['id'] ? 'selected' : '' ?>>
                            <?= e($c['codigo']) ?> &middot; <?= e($c['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Solo aparecen las cuentas donde se pueden anotar movimientos.</div>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-0" for="m_desde">Desde la fecha</label>
                <input type="date" id="m_desde" name="desde" class="form-control form-control-sm" value="<?= e($desde) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-0" for="m_hasta">Hasta la fecha</label>
                <input type="date" id="m_hasta" name="hasta" class="form-control form-control-sm" value="<?= e($hasta) ?>">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-sm btn-primary" type="submit">Ver la cuenta</button>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('mayor/index.php')) ?>">Quitar filtros</a>
            </div>
        </div>
    </div>
</form>

<?php if ($cuentaSel): ?>
    <div class="reporte card mb-4">
        <div class="encabezado-reporte d-flex justify-content-between align-items-end">
            <div>
                <h1 class="mb-1">Libro Mayor</h1>
                <div><strong><?= e($cuentaSel['codigo']) ?> &middot; <?= e($cuentaSel['nombre']) ?></strong></div>
                <div class="small text-muted">
                    Naturaleza: <?= $naturaleza === 'D' ? 'Deudor' : 'Acreedor' ?>
                    &middot; <?= $desde ? 'Desde ' . e(fechaCorta($desde)) : 'Sin limite de fecha inicial' ?>
                    &middot; <?= $hasta ? 'Hasta ' . e(fechaCorta($hasta)) : 'Sin limite de fecha final' ?>
                </div>
            </div>
            <div class="text-end small"><?= e(empresa()['nombre']) ?></div>
        </div>

        <div class="table-responsive">
            <table class="table tabla-contables table-sm table-bordered align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:100px">Fecha</th>
                        <th style="width:70px">Asiento</th>
                        <th>Concepto</th>
                        <th class="num" style="width:130px">Debito</th>
                        <th class="num" style="width:130px">Credito</th>
                        <th class="num" style="width:140px">Saldo deudor</th>
                        <th class="num" style="width:140px">Saldo acreedor</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$detalle): ?>
                    <tr><td colspan="7">
                        <div class="vacio-amable">
                            <i class="bi bi-inbox"></i>
                            <h5>Esta cuenta no tiene movimientos</h5>
                            <p class="mb-2">Pruebe con otras fechas o elija otra cuenta del catalogo.</p>
                        </div>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($detalle as $d): ?>
                    <tr>
                        <td><?= e(fechaCorta($d['fecha'])) ?></td>
                        <td><a class="text-decoration-none" target="_blank" href="<?= e(url('diario/ver.php?id=' . (int)$d['asiento_id'])) ?>"><?= (int)$d['numero'] ?></a></td>
                        <td><?= e($d['concepto']) ?><?= $d['documento'] ? ' <span class="text-muted">(' . e($d['documento']) . ')</span>' : '' ?></td>
                        <td class="num"><?= (float)$d['debe'] > 0 ? money($d['debe']) : '' ?></td>
                        <td class="num"><?= (float)$d['haber'] > 0 ? money($d['haber']) : '' ?></td>
                        <td class="num"><?= $d['saldo'] > 0 ? money($d['saldo']) : '' ?></td>
                        <td class="num"><?= $d['saldo'] < 0 ? money(abs($d['saldo'])) : '' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="marca-total">
                    <tr>
                        <td colspan="3" class="text-end">Totales</td>
                        <td class="num"><?= money($totalDebeDetalle) ?></td>
                        <td class="num"><?= money($totalHaberDetalle) ?></td>
                        <td class="num"><?= $netoFinal > 0 ? money($netoFinal) : '' ?></td>
                        <td class="num"><?= $netoFinal < 0 ? money(abs($netoFinal)) : '' ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
<?php endif; ?>

<div class="reporte card">
    <div class="encabezado-reporte d-flex justify-content-between align-items-end">
        <div>
            <h1 class="mb-1"><?= $cuentaSel ? 'Resumen de saldos (mayorizacion)' : 'Libro Mayor &middot; Resumen de saldos' ?></h1>
            <div class="small text-muted">Clasificacion automatica por digito del codigo de cuenta &middot; 1 Activo &middot; 2 Pasivo &middot; 3 Capital &middot; 4 Gastos &middot; 5 Ingresos</div>
        </div>
        <div class="text-end small"><?= e(empresa()['nombre']) ?></div>
    </div>

    <div class="table-responsive">
        <table class="table tabla-contables table-sm table-bordered align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:110px">Codigo</th>
                    <th>Cuenta</th>
                    <th style="width:110px">Clasificacion</th>
                    <th class="num" style="width:130px">Total debito</th>
                    <th class="num" style="width:130px">Total credito</th>
                    <th class="num" style="width:130px">Saldo deudor</th>
                    <th class="num" style="width:130px">Saldo acreedor</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$arbolResumen): ?>
                <tr><td colspan="7">
                    <div class="vacio-amable">
                        <i class="bi bi-journal-plus"></i>
                        <h5>Todavia no hay nada que mostrar</h5>
                        <p class="mb-2">El resumen se llena solo en cuanto registre el primer asiento.</p>
                        <a class="btn btn-sm btn-primary" href="<?= e(url('diario/nuevo.php')) ?>">Registrar un asiento</a>
                    </div>
                </td></tr>
            <?php endif; ?>
            <?php
            $tipoActual = '';
            foreach ($arbolResumen as $s):
                if ($tipoActual !== $s['tipo']):
                    $tipoActual = $s['tipo'];
            ?>
                <tr class="marca-clase">
                    <td colspan="7"><?= e($s['codigo']) ?> &middot; <?= e(tipoNombre($s['tipo'])) ?></td>
                </tr>
            <?php endif; ?>
                <tr class="<?= $s['es_fila_grupo'] ? 'marca-grupo' : '' ?>">
                    <td class="fw-semibold"><?= e($s['codigo']) ?></td>
                    <td>
                        <?= guiones((int)$s['nivel']) ?><?= e($s['nombre']) ?>
                    </td>
                    <td><span class="badge bg-<?= e(tipoClase($s['tipo'])) ?>"><?= e(str_replace('_', ' ', $s['tipo'])) ?></span></td>
                    <td class="num"><?= money($s['debe']) ?></td>
                    <td class="num"><?= money($s['haber']) ?></td>
                    <td class="num"><?= $s['deudor'] > 0 ? money($s['deudor']) : '' ?></td>
                    <td class="num"><?= $s['acreedor'] > 0 ? money($s['acreedor']) : '' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot class="marca-total">
                <tr>
                    <td colspan="3" class="text-end">Totales generales</td>
                    <td class="num"><?= money($totalDebeResumen) ?></td>
                    <td class="num"><?= money($totalHaberResumen) ?></td>
                    <td colspan="2" class="num <?= abs($totalDebeResumen - $totalHaberResumen) < 0.009 ? 'cuadra' : 'no-cuadra' ?>">
                        <?= abs($totalDebeResumen - $totalHaberResumen) < 0.009 ? 'Cuadra' : 'DESCUADRADO ' . money($totalDebeResumen - $totalHaberResumen) ?>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
