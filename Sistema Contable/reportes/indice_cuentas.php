<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

$tituloPagina = 'Indice de Cuentas';

$busqueda = param('q');
$tipoFiltro = param('tipo');

$where = ['activo = 1'];
$params = [];
if ($busqueda !== '') {
    $where[] = '(codigo LIKE ? OR nombre LIKE ?)';
    $params[] = '%' . $busqueda . '%';
    $params[] = '%' . $busqueda . '%';
}
if (in_array($tipoFiltro, ['ACTIVO', 'PASIVO', 'PATRIMONIO', 'RESULTADO_DEUDOR', 'RESULTADO_ACREEDOR', 'ORDEN'], true)) {
    $where[] = 'tipo = ?';
    $params[] = $tipoFiltro;
}

$st = db()->prepare('SELECT * FROM catalogo_cuentas WHERE ' . implode(' AND ', $where) . ' ORDER BY codigo');
$st->execute($params);
$cuentas = $st->fetchAll();

if (param('exportar') === 'csv') {
    $datos = [];
    foreach ($cuentas as $c) {
        $datos[] = [$c['codigo'], $c['nombre'], $c['nivel'], nivelNombre((int)$c['nivel']), $c['tipo'], $c['naturaleza'] === 'D' ? 'Deudor' : 'Acreedor'];
    }
    exportarCsv('indice_cuentas.csv', ['Codigo', 'Nombre', 'Nivel', 'Tipo de nivel', 'Clasificacion', 'Naturaleza'], $datos);
}

$total = (int)db()->query('SELECT COUNT(*) FROM catalogo_cuentas WHERE activo = 1')->fetchColumn();

require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <div>
        <h4 class="mb-0"><i class="bi bi-list-ol me-1"></i> Indice de Cuentas</h4>
        <small class="text-muted">El catalogo completo (<?= $total ?> cuentas) en orden, como lo pide la practica.</small>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="<?= e(url('reportes/indice_cuentas.php?exportar=csv&q=' . urlencode($busqueda) . '&tipo=' . urlencode($tipoFiltro))) ?>">
            <i class="bi bi-filetype-csv"></i> Exportar CSV
        </a>
        <button class="btn btn-outline-primary" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
    </div>
</div>

<div class="que-es-esto mb-3 no-print">
    <i class="bi bi-info-circle"></i>
    <div>
        A diferencia del catalogo, aqui <strong>no hay saldos ni importes</strong>: solo la lista de cuentas con su
        codigo, su nombre y su clasificacion. La clasificacion sale del primer digito del codigo:
        <strong>1</strong> Activo, <strong>2</strong> Pasivo, <strong>3</strong> Capital,
        <strong>4</strong> Costos y gastos, <strong>5</strong> Ingresos, <strong>6/7</strong> Cuentas de orden.
    </div>
</div>

<form method="get" class="card mb-3 no-print">
    <div class="card-header"><i class="bi bi-search me-1"></i> Buscar en el indice</div>
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small mb-0" for="ic_q">Codigo o nombre</label>
                <input type="search" id="ic_q" name="q" class="form-control form-control-sm" value="<?= e($busqueda) ?>"
                       placeholder="Ej. 4102, publicidad, depreciacion">
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-0" for="ic_tipo">Solo una clasificacion</label>
                <select name="tipo" id="ic_tipo" class="form-select form-select-sm">
                    <option value="">Todas las clasificaciones</option>
                    <?php foreach (['ACTIVO', 'PASIVO', 'PATRIMONIO', 'RESULTADO_DEUDOR', 'RESULTADO_ACREEDOR', 'ORDEN'] as $t): ?>
                        <option value="<?= $t ?>" <?= $tipoFiltro === $t ? 'selected' : '' ?>><?= e(tipoNombre($t)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2 justify-content-end">
                <button class="btn btn-sm btn-primary" type="submit">Buscar</button>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('reportes/indice_cuentas.php')) ?>">Quitar filtros</a>
            </div>
        </div>
    </div>
</form>

<div class="reporte card">
    <div class="encabezado-reporte text-center">
        <div class="fw-bold"><?= e(empresa()['nombre']) ?></div>
        <div><?= e(empresa()['carrera']) ?></div>
        <h1 class="mt-2 mb-1">Indice de Cuentas</h1>
        <div class="small">Catalogo de cuentas &middot; <?= count($cuentas) ?> resultado(s)</div>
    </div>

    <div class="table-responsive">
        <table class="table table-sm table-bordered tabla-contables align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:120px">Codigo</th>
                    <th>Nombre de la cuenta</th>
                    <th style="width:110px">Nivel</th>
                    <th style="width:150px">Clasificacion</th>
                    <th style="width:110px">Naturaleza</th>
                    <th style="width:120px">Movimiento</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$cuentas): ?>
                <tr><td colspan="6">
                    <div class="vacio-amable">
                        <i class="bi bi-search"></i>
                        <h5>Ninguna cuenta coincide</h5>
                        <p class="mb-2">Pruebe con otra palabra o quite los filtros.</p>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('reportes/indice_cuentas.php')) ?>">Quitar filtros</a>
                    </div>
                </td></tr>
            <?php endif; ?>
            <?php
            $tipoPrevio = null;
            foreach ($cuentas as $c):
                if ($tipoPrevio !== $c['tipo']):
                    $tipoPrevio = $c['tipo'];
            ?>
                <tr class="marca-clase">
                    <td colspan="6"><?= e(tipoNombre($c['tipo'])) ?> &middot; <?= e($c['codigo']) ?></td>
                </tr>
            <?php endif; ?>
                <tr>
                    <td class="fw-semibold"><?= e($c['codigo']) ?></td>
                    <td><?= guiones((int)$c['nivel']) ?><?= e($c['nombre']) ?></td>
                    <td><span class="badge bg-secondary"><?= e(nivelNombre((int)$c['nivel'])) ?></span></td>
                    <td><span class="badge bg-<?= e(tipoClase($c['tipo'])) ?>"><?= e(str_replace('_', ' ', $c['tipo'])) ?></span></td>
                    <td><?= e($c['naturaleza'] === 'D' ? 'Deudor' : 'Acreedor') ?></td>
                    <td>
                        <?php if ((int)$c['es_hoja'] === 1): ?>
                            <span class="badge bg-success">Detalle</span>
                        <?php else: ?>
                            <span class="badge bg-light text-muted">Agrupacion</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
