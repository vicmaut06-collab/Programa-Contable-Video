<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

$tituloPagina = 'Estado de Resultados';

$desde = param('desde');
$hasta = param('hasta');

$saldos = saldosPorCodigo($desde, $hasta, ['RESULTADO_DEUDOR', 'RESULTADO_ACREEDOR'], 6);

function filasEstado(array $saldos, string $tipo, string $columna, string $prefijo, int $nivelMin, int $nivelMax): string
{
    $html = '';
    foreach ($saldos as $s) {
        if ($s['tipo'] !== $tipo || !str_starts_with($s['codigo'], $prefijo)) {
            continue;
        }
        if ((int)$s['nivel'] < $nivelMin || (int)$s['nivel'] > $nivelMax) {
            continue;
        }
        $valor = (float)$s[$columna];
        if ($valor == 0.0) {
            continue;
        }
        $html .= '<tr>'
            . '<td style="width:110px">' . e($s['codigo']) . '</td>'
            . '<td>' . guiones((int)$s['nivel']) . e($s['nombre']) . '</td>'
            . '<td class="num">' . money($valor) . '</td>'
            . '</tr>';
    }
    return $html;
}

$ingresos = saldoDe($saldos, '5', 'acreedor');
$costos = saldoDe($saldos, '4', 'deudor');
$utilidad = $ingresos - $costos;

$totalVentas = saldoDe($saldos, '51', 'acreedor');
$totalCostoVentas = saldoDe($saldos, '4101', 'deudor');
$totalGastosAdmin = saldoDe($saldos, '4102', 'deudor');
$totalGastosVenta = saldoDe($saldos, '4103', 'deudor');
$totalOtrosCostos = saldoDe($saldos, '42', 'deudor');

$baseIngresos = $ingresos > 0 ? $ingresos : 1;

require __DIR__ . '/../includes/header.php';
?>

    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <div>
            <h4 class="mb-0"><i class="bi bi-file-earmark-spreadsheet me-1"></i> Estado de Resultados</h4>
            <small class="text-muted">Responde: ¿la institucion gano dinero o perdio durante el periodo?</small>
        </div>
        <a class="btn btn-outline-secondary" href="<?= e(url('index.php')) ?>"><i class="bi bi-arrow-left"></i> Volver al inicio</a>
    </div>

    <div class="que-es-esto mb-3 no-print">
        <i class="bi bi-info-circle"></i>
        <div>
            Se restan los <strong>Costos y gastos</strong> (codigo 4) de los <strong>Ingresos</strong> (codigo 5).
            Si el resultado es positivo hay <strong>utilidad</strong> (gano dinero); si es negativo hay
            <strong>perdida</strong>. Solo mira el periodo que elija, no el saldo acumulado de las cuentas.
        </div>
    </div>

<form method="get" class="card mb-3 no-print">
    <div class="card-header"><i class="bi bi-sliders me-1"></i> Periodo del reporte</div>
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label small mb-0" for="er_desde">Desde la fecha</label>
                <input type="date" id="er_desde" name="desde" class="form-control form-control-sm" value="<?= e($desde) ?>">
                <div class="form-text">Dejelo vacio para empezar desde el primer asiento.</div>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-0" for="er_hasta">Hasta la fecha</label>
                <input type="date" id="er_hasta" name="hasta" class="form-control form-control-sm" value="<?= e($hasta) ?>">
                <div class="form-text">Vacio significa hasta hoy.</div>
            </div>
            <div class="col-md-8 d-flex gap-2 justify-content-end">
                <button class="btn btn-sm btn-primary" type="submit">Ver el estado de resultados</button>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('reportes/estado_resultados.php')) ?>">Quitar filtros</a>
                <button class="btn btn-sm btn-outline-primary" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
            </div>
        </div>
    </div>
</form>

<div class="reporte card">
    <div class="encabezado-reporte text-center">
        <div class="fw-bold"><?= e(empresa()['nombre']) ?></div>
        <div><?= e(empresa()['carrera']) ?></div>
        <h1 class="mt-2 mb-1">Estado de Resultados</h1>
        <div class="small">
            Del <?= e($desde ? fechaCorta($desde) : 'inicio del ejercicio') ?> al <?= e(fechaCorta($hasta ?: hoy())) ?>
            &middot; Expresado en dolares
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <h2 class="text-success">Ingresos (codigo 5)</h2>
            <table class="table table-sm tabla-contables mb-0">
                <tbody>
                    <tr class="marca-grupo"><td style="width:110px">51</td><td>INGRESOS POR VENTAS</td><td class="num"><?= money($totalVentas) ?></td></tr>
                    <?= filasEstado($saldos, 'RESULTADO_ACREEDOR', 'acreedor', '51', 4, 6) ?>
                    <tr class="marca-grupo"><td style="width:110px">52</td><td>CUENTA DE CIERRE</td><td class="num"></td></tr>
                    <?= filasEstado($saldos, 'RESULTADO_ACREEDOR', 'acreedor', '52', 4, 6) ?>
                </tbody>
                <tfoot>
                    <tr class="marca-total-final">
                        <td></td><td>Total de ingresos</td>
                        <td class="num"><?= money($ingresos) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="col-md-6">
            <h2 class="text-danger">Costos y gastos (codigo 4)</h2>
            <table class="table table-sm tabla-contables mb-0">
                <tbody>
                    <tr class="marca-grupo"><td style="width:110px">4101</td><td>Costo de ventas</td><td class="num"><?= money($totalCostoVentas) ?></td></tr>
                    <?= filasEstado($saldos, 'RESULTADO_DEUDOR', 'deudor', '4101', 4, 6) ?>
                    <tr class="marca-grupo"><td style="width:110px">4102</td><td>Gastos administrativos</td><td class="num"><?= money($totalGastosAdmin) ?></td></tr>
                    <?= filasEstado($saldos, 'RESULTADO_DEUDOR', 'deudor', '4102', 4, 6) ?>
                    <tr class="marca-grupo"><td style="width:110px">4103</td><td>Gastos de venta</td><td class="num"><?= money($totalGastosVenta) ?></td></tr>
                    <?= filasEstado($saldos, 'RESULTADO_DEUDOR', 'deudor', '4103', 4, 6) ?>
                    <tr class="marca-grupo"><td style="width:110px">42</td><td>Otros costos y gastos</td><td class="num"><?= money($totalOtrosCostos) ?></td></tr>
                    <?= filasEstado($saldos, 'RESULTADO_DEUDOR', 'deudor', '42', 4, 6) ?>
                </tbody>
                <tfoot>
                    <tr class="marca-total-final">
                        <td></td><td>Total de costos y gastos</td>
                        <td class="num"><?= money($costos) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-md-8 offset-md-4">
            <table class="table table-sm tabla-contables">
                <tbody>
                    <tr class="marca-total">
                        <td>Total de ingresos (5)</td>
                        <td class="num"><?= money($ingresos) ?></td>
                        <td class="num"><?= num(($ingresos / $baseIngresos) * 100) ?>%</td>
                    </tr>
                    <tr class="marca-total">
                        <td>(-) Total de costos y gastos (4)</td>
                        <td class="num"><?= money($costos) ?></td>
                        <td class="num"><?= num(($costos / $baseIngresos) * 100) ?>%</td>
                    </tr>
                    <tr class="marca-total-final">
                        <td><?= $utilidad >= 0 ? 'UTILIDAD DEL EJERCICIO (5 - 4)' : 'PERDIDA DEL EJERCICIO (5 - 4)' ?></td>
                        <td class="num"><?= money(abs($utilidad)) ?></td>
                        <td class="num"><?= num((abs($utilidad) / $baseIngresos) * 100) ?>%</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-6 small text-muted">Elaborado por: <?= e(empresa()['responsable'] ?: 'Alumno(a)') ?></div>
        <div class="col-6 small text-muted text-end">Utilidad = Ingresos (codigo 5) - Costos y gastos (codigo 4)</div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
