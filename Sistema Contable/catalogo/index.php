<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

$tituloPagina = 'Catalogo de Cuentas';

$busqueda = param('q');
$desde = param('desde');
$hasta = param('hasta');
$verSaldos = param('saldos', '1') === '1';

$saldosCatalogo = [];
if ($verSaldos) {
    foreach (saldosPorCuenta($desde, $hasta, null, 8) as $s) {
        $saldosCatalogo[$s['codigo']] = $s;
    }
}

$where = ['activo = 1'];
$params = [];
if ($busqueda !== '') {
    $where[] = '(codigo LIKE ? OR nombre LIKE ?)';
    $params[] = '%' . $busqueda . '%';
    $params[] = '%' . $busqueda . '%';
}

$st = db()->prepare('SELECT * FROM catalogo_cuentas WHERE ' . implode(' AND ', $where) . ' ORDER BY codigo');
$st->execute($params);
$cuentas = $st->fetchAll();

$porTipo = [];
foreach ($cuentas as $c) {
    $porTipo[$c['tipo']][] = $c;
}

require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <div>
        <h4 class="mb-0"><i class="bi bi-diagram-3 me-1"></i> Catalogo de Cuentas</h4>
        <small class="text-muted">La lista completa de cuentas que usa el sistema y que puede recibir movimientos.</small>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="<?= e(url('reportes/indice_cuentas.php')) ?>"><i class="bi bi-list-ol"></i> Ver como indice</a>
        <button class="btn btn-outline-primary" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
    </div>
</div>

<div class="que-es-esto mb-3 no-print">
    <i class="bi bi-info-circle"></i>
    <div>
        Las cuentas van de mayor a menor segun sus digitos:
        <strong>clase</strong> (1 digito, el tipo: Activo, Pasivo...),
        <strong>grupo</strong> (2), <strong>auxiliar</strong> (4), <strong>cuenta</strong> (6) y
        <strong>detalle</strong> (8). Solo las del ultimo nivel admiten movimientos: por eso en el formulario de
        asientos aparecen las de 8 digitos.
    </div>
</div>

<form method="get" class="card mb-3 no-print">
    <div class="card-header"><i class="bi bi-search me-1"></i> Buscar en el catalogo</div>
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small mb-0" for="c_q">Codigo o nombre</label>
                <input type="search" id="c_q" name="q" class="form-control form-control-sm" value="<?= e($busqueda) ?>" placeholder="Ej. caja, 1101, banco">
                <div class="form-text">No distingue mayusculas ni espacios.</div>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-0" for="c_desde">Saldos desde</label>
                <input type="date" id="c_desde" name="desde" class="form-control form-control-sm" value="<?= e($desde) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-0" for="c_hasta">Saldos hasta</label>
                <input type="date" id="c_hasta" name="hasta" class="form-control form-control-sm" value="<?= e($hasta) ?>">
            </div>
            <div class="col-md-2">
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" name="saldos" value="1" id="chkSaldos" <?= $verSaldos ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="chkSaldos">Mostrar los saldos</label>
                </div>
            </div>
            <div class="col-md-2 d-flex gap-2 justify-content-end">
                <button class="btn btn-sm btn-primary" type="submit">Buscar</button>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('catalogo/index.php')) ?>">Quitar filtros</a>
            </div>
        </div>
    </div>
</form>

<?php foreach ($porTipo as $tipo => $lista): ?>
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-diagram-3"></i> <?= e(tipoNombre($tipo)) ?> &middot; <?= e($lista[0]['codigo'][0]) ?></span>
            <span class="badge bg-<?= e(tipoClase($tipo)) ?>"><?= count($lista) ?> cuentas</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm tabla-contables align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:130px">Codigo</th>
                        <th style="width:130px">Nivel</th>
                        <th>Nombre de la cuenta</th>
                        <th style="width:110px">Naturaleza</th>
                        <?php if ($verSaldos): ?>
                            <th class="num" style="width:150px">Saldo deudor</th>
                            <th class="num" style="width:150px">Saldo acreedor</th>
                        <?php endif; ?>
                        <th style="width:150px" class="no-print">Ver</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($lista as $c):
                        $nivel = (int)$c['nivel'];
                        $s = $saldosCatalogo[$c['codigo']] ?? null;
                        $clase = $nivel === 4 ? 'marca-grupo' : ($nivel >= 6 ? 'marca-grupo' : '');
                    ?>
                        <tr class="<?= $clase ?>">
                            <td class="fw-semibold"><?= e($c['codigo']) ?></td>
                            <td><span class="badge bg-light text-muted border"><?= e(nivelNombre($nivel)) ?></span></td>
                            <td>
                                <?= guiones($nivel) ?><?= e($c['nombre']) ?>
                                <?php if ((int)$c['es_hoja'] === 0): ?>
                                    <span class="badge bg-secondary ms-1">Solo agrupa</span>
                                <?php else: ?>
                                    <span class="badge bg-success bg-opacity-25 text-success ms-1">Acepta movimientos</span>
                                <?php endif; ?>
                            </td>
                            <td class="small"><?= e($c['naturaleza'] === 'D' ? 'Deudor' : 'Acreedor') ?></td>
                            <?php if ($verSaldos): ?>
                                <td class="num"><?= $s && (float)$s['deudor'] > 0 ? money($s['deudor']) : '' ?></td>
                                <td class="num"><?= $s && (float)$s['acreedor'] > 0 ? money($s['acreedor']) : '' ?></td>
                            <?php endif; ?>
                            <td class="no-print">
                                <?php if ((int)$c['es_hoja'] === 1): ?>
                                    <a class="btn btn-sm btn-outline-primary" target="_blank"
                                       href="<?= e(url('mayor/index.php?cuenta=' . (int)$c['id'])) ?>"
                                       title="Ver el movimiento de esta cuenta">
                                        <i class="bi bi-journal-text"></i> Mayor
                                    </a>
                                <?php else: ?>
                                    <small class="text-muted">-</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
            </table>
        </div>
    </div>
<?php endforeach; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
