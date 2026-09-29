<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$tituloPagina = 'Inicio';

$totalDebe = totalDebeGlobal();
$totalHaber = totalHaberGlobal();
$cuadra = abs($totalDebe - $totalHaber) < 0.009;

$asientos = db()->query(
    "SELECT a.*, (SELECT COUNT(*) FROM asiento_partidas pp WHERE pp.asiento_id = a.id) AS lineas
     FROM asientos a WHERE a.estado = 'REGISTRADO'
     ORDER BY a.fecha DESC, a.numero DESC LIMIT 8"
)->fetchAll();

$saldosClase = saldosPorCodigo(null, null, ['RESULTADO_ACREEDOR', 'RESULTADO_DEUDOR'], 2);
$ingresos = saldoDe($saldosClase, '5', 'acreedor');
$costos = saldoDe($saldosClase, '4', 'deudor');
$utilidad = $ingresos - $costos;

$totalCuentas = (int)db()->query('SELECT COUNT(*) FROM catalogo_cuentas WHERE activo = 1')->fetchColumn();
$totalHojas = (int)db()->query('SELECT COUNT(*) FROM catalogo_cuentas WHERE activo = 1 AND es_hoja = 1')->fetchColumn();
$totalAsientos = (int)db()->query("SELECT COUNT(*) FROM asientos WHERE estado = 'REGISTRADO'")->fetchColumn();

$saldosGrupo = saldosPorCodigo(null, null, null, 1);
$activo = ($saldosGrupo['#1']['neto'] ?? 0.0);
$pasivo = saldoDe($saldosGrupo, '2', 'acreedor');
$capital = saldoDe($saldosGrupo, '3', 'acreedor');

require __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-3">
    <div>
        <h3 class="mb-1">Bienvenido al Sistema Contable</h3>
        <small class="text-muted">
            <?= e(empresa()['nombre']) ?> &middot; <?= e(empresa()['carrera']) ?> &middot; <?= e(fechaLarga(hoy())) ?>
        </small>
    </div>
    <a class="btn btn-primary btn-lg" href="<?= e(url('diario/nuevo.php')) ?>">
        <i class="bi bi-plus-lg"></i> Registrar un asiento
    </a>
</div>

<div class="row g-3 mb-3">
    <?php
    $indicadores = [
        ['Asientos registrados', 'Cuantos movimientos hay en el libro', number_format($totalAsientos), 'bi-journal-text', 'diario/asientos.php', 'primary'],
        ['Cuentas de detalle', 'Las que admiten movimientos', number_format($totalHojas) . ' de ' . number_format($totalCuentas), 'bi-diagram-3', 'mayor/index.php', 'info'],
        ['Partida doble', 'Debito y Credito son iguales', money($totalDebe), 'bi-check2-circle', 'reportes/balance_comprobacion.php', $cuadra ? 'success' : 'danger'],
        ['Utilidad del ejercicio', 'Ingresos menos costos y gastos', money($utilidad), 'bi-graph-up-arrow', 'reportes/estado_resultados.php', $utilidad >= 0 ? 'success' : 'danger'],
    ];
    foreach ($indicadores as $ind):
    ?>
        <div class="col-md-6 col-xl-3">
            <a class="text-decoration-none" href="<?= e(url($ind[4])) ?>">
                <div class="card h-100 tarjeta-indicador">
                    <div class="card-body d-flex gap-3 align-items-center">
                        <div class="rounded-circle bg-<?= $ind[5] ?> bg-opacity-10 text-<?= $ind[5] ?> d-flex align-items-center justify-content-center" style="width:44px;height:44px">
                            <i class="bi <?= $ind[3] ?> fs-5"></i>
                        </div>
                        <div>
                            <div class="text-muted small"><?= e($ind[0]) ?></div>
                            <div class="fw-bold fs-6"><?= e($ind[2]) ?></div>
                            <div class="text-muted small"><?= e($ind[1]) ?></div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clock-history me-1"></i> Ultimos asientos registrados</span>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('diario/asientos.php')) ?>">Ver todos</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm tabla-contables align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:60px">N.</th>
                            <th style="width:100px">Fecha</th>
                            <th>Concepto</th>
                            <th class="num" style="width:130px">Debito</th>
                            <th class="num" style="width:130px">Credito</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$asientos): ?>
                        <tr><td colspan="5">
                            <div class="vacio-amable">
                                <i class="bi bi-journal-x"></i>
                                <h5>Aun no hay asientos</h5>
                                <p class="mb-2">En cuanto registre el primero, aparecera aqui con su fecha y sus totales.</p>
                                <a class="btn btn-sm btn-primary" href="<?= e(url('diario/nuevo.php')) ?>">Registrar el primero</a>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($asientos as $a): ?>
                        <tr>
                            <td><a class="fw-semibold text-decoration-none" href="<?= e(url('diario/ver.php?id=' . $a['id'])) ?>"><?= (int)$a['numero'] ?></a></td>
                            <td><?= e(fechaCorta($a['fecha'])) ?></td>
                            <td class="small"><?= e($a['concepto']) ?></td>
                            <td class="num"><?= money($a['total_debe']) ?></td>
                            <td class="num"><?= money($a['total_haber']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-compass me-1"></i> ¿Que quiere hacer?</div>
            <div class="list-group list-group-flush">
                <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                   href="<?= e(url('diario/nuevo.php')) ?>">
                    <span><i class="bi bi-plus-circle me-2 text-primary"></i>Registrar una compra, un pago, un ingreso</span>
                    <i class="bi bi-chevron-right"></i>
                </a>
                <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                   href="<?= e(url('diario/asientos.php')) ?>">
                    <span><i class="bi bi-journal-text me-2 text-primary"></i>Ver o corregir los asientos que ya escribi</span>
                    <span class="badge bg-primary"><?= $totalAsientos ?></span>
                </a>
                <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                   href="<?= e(url('mayor/index.php')) ?>">
                    <span><i class="bi bi-list-ul me-2 text-primary"></i>Ver todo el movimiento de una cuenta</span>
                    <i class="bi bi-chevron-right"></i>
                </a>
                <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                   href="<?= e(url('reportes/balance_general.php')) ?>">
                    <span><i class="bi bi-file-earmark-bar-graph me-2 text-primary"></i>Ver que tengo: Activo, Pasivo y Capital</span>
                    <i class="bi bi-chevron-right"></i>
                </a>
                <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                   href="<?= e(url('reportes/estado_resultados.php')) ?>">
                    <span><i class="bi bi-file-earmark-spreadsheet me-2 text-primary"></i>Ver si gane o perdi en el periodo</span>
                    <i class="bi bi-chevron-right"></i>
                </a>
                <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                   href="<?= e(url('reportes/balance_comprobacion.php')) ?>">
                    <span><i class="bi bi-clipboard-check me-2 text-primary"></i>Comprobar que los libros estan cuadrados</span>
                    <i class="bi bi-chevron-right"></i>
                </a>
                <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                   href="<?= e(url('catalogo/index.php')) ?>">
                    <span><i class="bi bi-search me-2 text-primary"></i>Buscar un codigo de cuenta</span>
                    <i class="bi bi-chevron-right"></i>
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-speedometer2 me-1"></i> Como va la contabilidad</div>
            <div class="card-body">
                <p class="text-muted small">
                    Estas son las cifras que alimentan los reportes. Se calculan solas a partir de los asientos.
                </p>
                <table class="table table-sm mb-0">
                    <tr><td>Total de debitos</td><td class="text-end"><?= money($totalDebe) ?></td></tr>
                    <tr><td>Total de creditos</td><td class="text-end"><?= money($totalHaber) ?></td></tr>
                    <tr>
                        <td>Partida doble</td>
                        <td class="text-end <?= $cuadra ? 'cuadra' : 'no-cuadra' ?>"><?= $cuadra ? 'Cuadra' : 'DESCUADRADA' ?></td>
                    </tr>
                    <tr><td>Activo (grupo 1)</td><td class="text-end"><?= money($activo) ?></td></tr>
                    <tr><td>Pasivo (grupo 2)</td><td class="text-end"><?= money($pasivo) ?></td></tr>
                    <tr><td>Capital (grupo 3)</td><td class="text-end"><?= money($capital) ?></td></tr>
                    <tr><td>Total de ingresos (grupo 5)</td><td class="text-end"><?= money($ingresos) ?></td></tr>
                    <tr><td>Total de costos y gastos (grupo 4)</td><td class="text-end"><?= money($costos) ?></td></tr>
                    <tr class="marca-total">
                        <td>Utilidad del ejercicio</td>
                        <td class="text-end <?= $utilidad < 0 ? 'no-cuadra' : 'cuadra' ?>"><?= money($utilidad) ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
