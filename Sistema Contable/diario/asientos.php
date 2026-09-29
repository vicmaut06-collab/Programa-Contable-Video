<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

$tituloPagina = 'Libro Diario';

$desde = param('desde');
$hasta = param('hasta');
$busqueda = param('q');
$cuentaFiltro = (int)param('cuenta', '0');

$where = ["a.estado = 'REGISTRADO'"];
$params = [];

if ($desde !== '') {
    $where[] = 'a.fecha >= ?';
    $params[] = $desde;
}
if ($hasta !== '') {
    $where[] = 'a.fecha <= ?';
    $params[] = $hasta;
}
if ($busqueda !== '') {
    $where[] = '(a.concepto LIKE ? OR a.documento LIKE ? OR CAST(a.numero AS TEXT) = ?)';
    $params[] = '%' . $busqueda . '%';
    $params[] = '%' . $busqueda . '%';
    $params[] = $busqueda;
}
if ($cuentaFiltro > 0) {
    $where[] = 'EXISTS (SELECT 1 FROM asiento_partidas pp WHERE pp.asiento_id = a.id AND pp.cuenta_id = ?)';
    $params[] = $cuentaFiltro;
}

$sql = 'SELECT a.*, (SELECT COUNT(*) FROM asiento_partidas pp WHERE pp.asiento_id = a.id) AS lineas
        FROM asientos a
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY a.fecha, a.numero';

$st = db()->prepare($sql);
$st->execute($params);
$asientos = $st->fetchAll();

$totalDebe = 0.0;
$totalHaber = 0.0;
foreach ($asientos as $a) {
    $totalDebe += (float)$a['total_debe'];
    $totalHaber += (float)$a['total_haber'];
}

$cuentasFiltro = db()->query(
    'SELECT id, codigo, nombre FROM catalogo_cuentas WHERE es_hoja = 1 AND activo = 1 ORDER BY codigo'
)->fetchAll();

$totalCuentas = (int)db()->query('SELECT COUNT(*) FROM catalogo_cuentas WHERE es_hoja = 1 AND activo = 1')->fetchColumn();

require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <div>
        <h4 class="mb-0"><i class="bi bi-journal-text me-1"></i> Libro Diario</h4>
        <small class="text-muted">Aqui queda registrada <strong>toda</strong> la contabilidad, asiento por asiento.</small>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="<?= e(url('reportes/balance_comprobacion.php')) ?>"><i class="bi bi-clipboard-check"></i> Comprobar que cuadra</a>
        <a class="btn btn-primary" href="<?= e(url('diario/nuevo.php')) ?>"><i class="bi bi-plus-lg"></i> Registrar un asiento</a>
    </div>
</div>

<div class="que-es-esto mb-3 no-print">
    <i class="bi bi-info-circle"></i>
    <div>
        Cada fila es un <strong>asiento</strong>: un hecho economico completo, con su fecha, su concepto y sus dos
        totales (Debito y Credito), que siempre son iguales. Use los filtros de abajo para encontrar uno especifico.
    </div>
</div>

<form method="get" class="card mb-3 no-print">
    <div class="card-header"><i class="bi bi-funnel me-1"></i> Filtrar la lista</div>
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label small mb-0" for="f_desde">Desde la fecha</label>
                <input type="date" id="f_desde" name="desde" class="form-control form-control-sm" value="<?= e($desde) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-0" for="f_hasta">Hasta la fecha</label>
                <input type="date" id="f_hasta" name="hasta" class="form-control form-control-sm" value="<?= e($hasta) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-0" for="f_q">Buscar por texto</label>
                <input type="search" id="f_q" name="q" class="form-control form-control-sm" value="<?= e($busqueda) ?>" placeholder="Ej. compra, factura 001">
                <div class="form-text">Busca en el concepto, el documento o el numero.</div>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-0" for="f_cuenta">Solo una cuenta</label>
                <select name="cuenta" id="f_cuenta" class="form-select form-select-sm">
                    <option value="0">Todas las cuentas</option>
                    <?php foreach ($cuentasFiltro as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $cuentaFiltro === (int)$c['id'] ? 'selected' : '' ?>>
                            <?= e($c['codigo']) ?> &middot; <?= e($c['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Muestra solo los asientos que tocan esa cuenta.</div>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-sm btn-primary" type="submit">Ver resultados</button>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('diario/asientos.php')) ?>">Quitar filtros</a>
            </div>
        </div>
    </div>
</form>

<?php if ($desde !== '' || $hasta !== '' || $busqueda !== '' || $cuentaFiltro > 0): ?>
    <div class="alert alert-info py-2 no-print">
        <i class="bi bi-funnel-fill"></i>
        Esta viendo <strong>solo <?= count($asientos) ?></strong> asiento(s) con filtro aplicado.
        <a href="<?= e(url('diario/asientos.php')) ?>" class="alert-link">Ver todos los asientos</a>.
    </div>
<?php endif; ?>

<div class="card">
    <div class="table-responsive">
        <table class="table tabla-contables table-hover table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:90px">N.</th>
                    <th style="width:120px">Fecha</th>
                    <th>Concepto</th>
                    <th style="width:130px">Documento</th>
                    <th class="num" style="width:80px">Lineas</th>
                    <th class="num" style="width:140px">Total debito</th>
                    <th class="num" style="width:140px">Total credito</th>
                    <th style="width:150px" class="no-print">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$asientos): ?>
                <tr><td colspan="8">
                    <div class="vacio-amable">
                        <?php if ($desde !== '' || $hasta !== '' || $busqueda !== '' || $cuentaFiltro > 0): ?>
                            <i class="bi bi-funnel"></i>
                            <h5>Ningun asiento coincide con el filtro</h5>
                            <p class="mb-2">Pruebe con otras fechas o quite el filtro para ver todo el libro.</p>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('diario/asientos.php')) ?>">Quitar filtros</a>
                        <?php else: ?>
                            <i class="bi bi-journal-plus"></i>
                            <h5>El Libro Diario todavia esta vacio</h5>
                            <p class="mb-2">Registre el primer movimiento para empezar a llevar la contabilidad.</p>
                            <a class="btn btn-sm btn-primary" href="<?= e(url('diario/nuevo.php')) ?>">Registrar el primer asiento</a>
                        <?php endif; ?>
                    </div>
                </td></tr>
            <?php endif; ?>
            <?php foreach ($asientos as $a): ?>
                <tr>
                    <td><a class="fw-semibold" href="<?= e(url('diario/ver.php?id=' . $a['id'])) ?>"><?= (int)$a['numero'] ?></a></td>
                    <td><?= e(fechaCorta($a['fecha'])) ?></td>
                    <td>
                        <?= e($a['concepto']) ?>
                        <?php if ($a['tipo'] !== 'ORDINARIO'): ?>
                            <span class="badge bg-secondary"><?= e($a['tipo']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="small"><?= e($a['documento']) ?></td>
                    <td class="num"><?= (int)$a['lineas'] ?></td>
                    <td class="num"><?= money($a['total_debe']) ?></td>
                    <td class="num"><?= money($a['total_haber']) ?></td>
                    <td class="no-print text-nowrap">
                        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('diario/ver.php?id=' . $a['id'])) ?>" title="Ver el detalle del asiento">
                            <i class="bi bi-eye"></i> Ver
                        </a>
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('diario/editar.php?id=' . $a['id'])) ?>" title="Corregir este asiento">
                            <i class="bi bi-pencil"></i> Editar
                        </a>
                        <form class="d-inline" method="post" action="<?= e(url('diario/eliminar.php')) ?>"
                              onsubmit="return confirm('Desea eliminar el asiento N. <?= (int)$a['numero'] ?>? Esta accion no se puede deshacer.')">
                            <?= csrfCampo() ?>
                            <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" title="Eliminar este asiento">
                                <i class="bi bi-trash"></i> Borrar
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <?php if ($asientos): ?>
                <tfoot class="marca-total">
                    <tr>
                        <td colspan="5" class="text-end">Total de los asientos mostrados</td>
                        <td class="num"><?= money($totalDebe) ?></td>
                        <td class="num"><?= money($totalHaber) ?></td>
                        <td class="no-print"></td>
                    </tr>
                    <tr>
                        <td colspan="5" class="text-end">¿Cuadra?</td>
                        <td colspan="3" class="num <?= abs($totalDebe - $totalHaber) < 0.009 ? 'cuadra' : 'no-cuadra' ?>">
                            <?= abs($totalDebe - $totalHaber) < 0.009 ? 'Si, cuadra: ' . money($totalDebe) : 'No cuadra, sobran ' . money($totalDebe - $totalHaber) ?>
                        </td>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<div class="row g-3 mt-1 no-print">
    <div class="col-md-4">
        <div class="card"><div class="card-body">
            <div class="text-muted small"><i class="bi bi-diagram-3 me-1"></i> Cuentas de detalle disponibles</div>
            <div class="fs-4"><?= $totalCuentas ?></div>
            <div class="text-muted small">Son las unicas donde se pueden anotar movimientos.</div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card"><div class="card-body">
            <div class="text-muted small"><i class="bi bi-journal-text me-1"></i> Asientos en todo el libro</div>
            <div class="fs-4"><?= (int)db()->query("SELECT COUNT(*) FROM asientos WHERE estado = 'REGISTRADO'")->fetchColumn() ?></div>
            <div class="text-muted small">Sin importar el filtro que este puesto.</div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card"><div class="card-body">
            <div class="text-muted small"><i class="bi bi-cash-stack me-1"></i> Suma de todos los debitos</div>
            <div class="fs-5"><?= money(totalDebeGlobal()) ?></div>
            <div class="text-muted small">Debe igual al total de creditos si todo esta bien.</div>
        </div></div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
