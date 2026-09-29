<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

$tituloPagina = 'Balance General';

$desde = param('desde');
$hasta = param('hasta');
$incluirResultado = param('resultado', '1') === '1';

$saldos = saldosPorCodigo($desde, $hasta, ['ACTIVO', 'PASIVO', 'PATRIMONIO', 'RESULTADO_DEUDOR', 'RESULTADO_ACREEDOR'], 6);

$totalActivoCorriente = saldoDe($saldos, '11', 'deudor');
$totalActivoNoCorriente = saldoDe($saldos, '12', 'deudor');
$totalActivo = $totalActivoCorriente + $totalActivoNoCorriente;

$totalPasivoCorriente = saldoDe($saldos, '21', 'acreedor');
$totalPasivoNoCorriente = saldoDe($saldos, '22', 'acreedor');
$totalPasivo = $totalPasivoCorriente + $totalPasivoNoCorriente;

$totalPatrimonio = saldoDe($saldos, '3', 'acreedor');
$totalIngresos = saldoDe($saldos, '5', 'acreedor');
$totalGastos = saldoDe($saldos, '4', 'deudor');
$utilidad = $totalIngresos - $totalGastos;

$totalPasivoCapital = $totalPasivo + $totalPatrimonio + ($incluirResultado ? $utilidad : 0);
$diferencia = $totalActivo - $totalPasivoCapital;
$cuadra = abs($diferencia) < 0.009;

function filasNivel(array $saldos, string $prefijo, int $desdeNivel, int $hastaNivel, string $columna): string
{
    $html = '';
    foreach ($saldos as $s) {
        if ((int)$s['nivel'] < $desdeNivel || (int)$s['nivel'] > $hastaNivel) {
            continue;
        }
        if ($prefijo !== '' && !str_starts_with($s['codigo'], $prefijo)) {
            continue;
        }
        $valor = (float)$s[$columna];
        $clase = (int)$s['nivel'] === $hastaNivel ? 'marca-grupo' : '';
        $html .= '<tr class="' . $clase . '">'
            . '<td style="width:110px">' . e($s['codigo']) . '</td>'
            . '<td>' . guiones((int)$s['nivel']) . e($s['nombre']) . '</td>'
            . '<td class="num">' . ($valor > 0 ? money($valor) : '') . '</td>'
            . '</tr>';
    }
    return $html;
}

function totalFila(string $etiqueta, float $valor, string $clase = 'marca-total'): string
{
    return '<tr class="' . $clase . '"><td></td><td>' . $etiqueta . '</td><td class="num">'
        . money($valor) . '</td></tr>';
}

require __DIR__ . '/../includes/header.php';
?>

    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <div>
            <h4 class="mb-0"><i class="bi bi-file-earmark-bar-graph me-1"></i> Balance General</h4>
            <small class="text-muted">La fotografia de la institucion: lo que tiene, lo que debe y lo que le pertenece.</small>
        </div>
        <a class="btn btn-outline-secondary" href="<?= e(url('index.php')) ?>"><i class="bi bi-arrow-left"></i> Volver al inicio</a>
    </div>

    <div class="que-es-esto mb-3 no-print">
        <i class="bi bi-info-circle"></i>
        <div>
            Este reporte responde una sola pregunta: <strong>¿que tiene la institucion y de donde salio?</strong>
            A la izquierda esta el <strong>Activo</strong> (lo que se posee) y a la derecha el
            <strong>Pasivo y el Capital</strong> (de donde vino). Siempre cumple la ecuacion
            <strong>Activo = Pasivo + Capital</strong>; si no cuadra, hay un error en los asientos.
        </div>
    </div>

    <div class="leyenda-columnas no-print">
        <span><b>Activo:</b> efectivo, cuentas por cobrar, inventario, equipos.</span>
        <span><b>Pasivo:</b> proveedores, prestamos y deudas pendientes de pago.</span>
        <span><b>Capital:</b> lo aportado por los socios mas las utilidades acumuladas.</span>
    </div>

<form method="get" class="card mb-3 no-print">
    <div class="card-header"><i class="bi bi-sliders me-1"></i> Periodo y opciones</div>
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label small mb-0" for="bg_desde">Desde la fecha</label>
                <input type="date" id="bg_desde" name="desde" class="form-control form-control-sm" value="<?= e($desde) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-0" for="bg_hasta">Hasta la fecha</label>
                <input type="date" id="bg_hasta" name="hasta" class="form-control form-control-sm" value="<?= e($hasta) ?>">
            </div>
            <div class="col-md-3 d-flex align-items-center">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="resultado" value="1" id="chkResultado" <?= $incluirResultado ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="chkResultado">Sumar la utilidad del ejercicio al capital</label>
                </div>
            </div>
            <div class="col-md-5 d-flex gap-2 justify-content-end">
                <button class="btn btn-sm btn-primary" type="submit">Ver el balance</button>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('reportes/balance_general.php')) ?>">Quitar filtros</a>
                <button class="btn btn-sm btn-outline-primary" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
            </div>
        </div>
    </div>
</form>

<div class="reporte card">
    <div class="encabezado-reporte text-center">
        <div class="fw-bold"><?= e(empresa()['nombre']) ?></div>
        <div><?= e(empresa()['carrera']) ?></div>
        <h1 class="mt-2 mb-1">Balance General</h1>
        <div class="small">
            Al <?= e(fechaLarga($hasta ?: hoy())) ?>
            <?php if ($desde): ?> &middot;Movimientos del <?= e(fechaCorta($desde)) ?> al <?= e(fechaCorta($hasta ?: hoy())) ?> <?php endif; ?>
            &middot; Expresado en dolares
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <h2>Activo</h2>
            <table class="table table-sm tabla-contables mb-0">
                <tbody>
                    <tr class="marca-grupo"><td style="width:110px">11</td><td>ACTIVO CORRIENTE</td><td class="num"><?= money($totalActivoCorriente) ?></td></tr>
                    <?= filasNivel($saldos, '11', 4, 6, 'deudor') ?>
                    <?= totalFila('Total del activo corriente', $totalActivoCorriente) ?>
                    <tr class="marca-grupo"><td style="width:110px">12</td><td>ACTIVO NO CORRIENTE</td><td class="num"><?= money($totalActivoNoCorriente) ?></td></tr>
                    <?= filasNivel($saldos, '12', 4, 6, 'deudor') ?>
                    <?= totalFila('Total del activo no corriente', $totalActivoNoCorriente) ?>
                </tbody>
                <tfoot>
                    <?= totalFila('TOTAL ACTIVO (codigo 1)', $totalActivo, 'marca-total-final') ?>
                </tfoot>
            </table>
        </div>

        <div class="col-md-6">
            <h2>Pasivo y Capital Contable</h2>
            <table class="table table-sm tabla-contables mb-0">
                <tbody>
                    <tr class="marca-grupo"><td style="width:110px">21</td><td>PASIVO CORRIENTE</td><td class="num"><?= money($totalPasivoCorriente) ?></td></tr>
                    <?= filasNivel($saldos, '21', 4, 6, 'acreedor') ?>
                    <?= totalFila('Total del pasivo corriente', $totalPasivoCorriente) ?>
                    <tr class="marca-grupo"><td style="width:110px">22</td><td>PASIVO NO CORRIENTE</td><td class="num"><?= money($totalPasivoNoCorriente) ?></td></tr>
                    <?= filasNivel($saldos, '22', 4, 6, 'acreedor') ?>
                    <?= totalFila('Total del pasivo no corriente', $totalPasivoNoCorriente) ?>
                    <?= totalFila('TOTAL PASIVO (codigo 2)', $totalPasivo) ?>
                    <tr class="marca-grupo"><td style="width:110px">3</td><td>CAPITAL CONTABLE (codigo 3)</td><td class="num"><?= money($totalPatrimonio) ?></td></tr>
                    <?= filasNivel($saldos, '31', 4, 6, 'acreedor') ?>
                    <?= filasNivel($saldos, '32', 4, 6, 'acreedor') ?>
                    <?= totalFila('Subtotal del capital contable', $totalPatrimonio) ?>
                    <?php if ($incluirResultado): ?>
                        <tr>
                            <td style="width:110px">5 - 4</td>
                            <td>Utilidad (o perdida) del ejercicio</td>
                            <td class="num <?= $utilidad < 0 ? 'no-cuadra' : '' ?>"><?= money($utilidad) ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <?= totalFila('TOTAL PASIVO + CAPITAL', $totalPasivoCapital, 'marca-total-final') ?>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-md-6 offset-md-6">
            <table class="table table-sm tabla-contables">
                <tbody>
                    <tr class="<?= $cuadra ? 'marca-total' : '' ?>">
                        <td>TOTAL ACTIVO</td>
                        <td class="num"><?= money($totalActivo) ?></td>
                    </tr>
                    <tr>
                        <td>TOTAL PASIVO + CAPITAL</td>
                        <td class="num"><?= money($totalPasivoCapital) ?></td>
                    </tr>
                    <tr class="marca-total-final">
                        <td><?= $cuadra ? 'Activo = Pasivo + Capital (2 + 3)' : 'Diferencia (Activo - Pasivo - Capital)' ?></td>
                        <td class="num"><?= $cuadra ? money($totalActivo) . ' &nbsp; CUADRA' : money($diferencia) . ' &nbsp; NO CUADRA' ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-6 small text-muted">Elaborado por: <?= e(empresa()['responsable'] ?: 'Alumno(a)') ?></div>
        <div class="col-6 small text-muted text-end">Clasificacion automatica: 1 Activo = 2 Pasivo + 3 Capital &middot; 5 Ingresos - 4 Costos y gastos</div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
