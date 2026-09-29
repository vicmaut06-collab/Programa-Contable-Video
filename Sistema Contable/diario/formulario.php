<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

$asiento = [
    'id'        => 0,
    'numero'    => 0,
    'fecha'     => hoy(),
    'concepto'  => '',
    'documento' => '',
    'tipo'      => 'ORDINARIO',
];
$partidas = [];

if (isset($_GET['id'])) {
    $asiento = obtenerAsiento((int)$_GET['id']);
    if (!$asiento) {
        flash('error', 'El asiento solicitado no existe.');
        redirect('diario/asientos.php');
    }
    $partidas = obtenerPartidas((int)$asiento['id']);
}

$tituloPagina = $asiento['id'] ? 'Editar Asiento' : 'Registrar Asiento';

$grupos = db()->query(
    'SELECT codigo, nombre FROM catalogo_cuentas WHERE nivel = 1 AND activo = 1 ORDER BY codigo'
)->fetchAll();

$cuentas = db()->query(
    'SELECT id, LEFT(codigo, 1) AS grupo
     FROM catalogo_cuentas
     WHERE es_hoja = 1 AND activo = 1
     ORDER BY codigo'
)->fetchAll();

$grupoDeCuenta = [];
foreach ($cuentas as $c) {
    $grupoDeCuenta[(int)$c['id']] = $c['grupo'];
}

function opcionesGruposHtml(array $grupos, string $seleccionado): string
{
    $html = '<option value="">Elija el tipo de cuenta</option>';
    foreach ($grupos as $g) {
        $html .= '<option value="' . e($g['codigo']) . '"' . ($g['codigo'] === $seleccionado ? ' selected' : '') . '>'
            . e($g['codigo']) . ' &middot; ' . e($g['nombre']) . '</option>';
    }
    return $html;
}

$opcionesGrupos = opcionesGruposHtml($grupos, '');

$numeroSugerido = $asiento['id']
    ? (int)$asiento['numero']
    : (int)(db()->query('SELECT COALESCE(MAX(numero), 0) FROM asientos')->fetchColumn() + 1);

if (!$partidas) {
    $partidas = [
        ['cuenta_id' => '', 'debe' => '', 'haber' => ''],
        ['cuenta_id' => '', 'debe' => '', 'haber' => ''],
    ];
}

/** Estructura visual de una partida. Se reutiliza para pintar y para la plantilla. */
function bloquePartida(int $indice, string $gruposHtml, string $debe, string $haber, string $cuentaId = ''): string
{
    return '<div class="partida partida-vacia" data-cuenta="' . e($cuentaId) . '">'
        . '<div class="partida-cabecera">'
        . '<span class="partida-etiqueta num-linea">Partida ' . $indice . '</span>'
        . '<span class="partida-marca"></span>'
        . '<button type="button" class="btn btn-sm btn-outline-secondary quitar-linea">'
        . '<i class="bi bi-trash3"></i> Quitar esta partida</button>'
        . '</div>'
        . '<div class="partida-cuerpo">'
        . '<div class="partida-columnas">'
        . '<div class="campo">'
        . '<label class="campo-titulo"><span class="campo-paso">1</span> Tipo de cuenta</label>'
        . '<select name="grupo[]" class="form-select sel-grupo">' . $gruposHtml . '</select>'
        . '</div>'
        . '<div class="campo">'
        . '<label class="campo-titulo"><span class="campo-paso">2</span> Cuenta de mayor</label>'
        . '<select class="form-select sel-mayor" disabled>'
        . '<option value="">Primero elija el tipo de cuenta</option>'
        . '</select>'
        . '</div>'
        . '<div class="campo">'
        . '<label class="campo-titulo"><span class="campo-paso">3</span> Sub cuenta</label>'
        . '<select name="cuenta_id[]" class="form-select sel-cuenta" disabled>'
        . '<option value="">Primero elija la cuenta de mayor</option>'
        . '</select>'
        . '</div>'
        . '</div>'
        . '<div class="partida-importes">'
        . '<div class="campo campo-debe">'
        . '<label class="campo-titulo"><i class="bi bi-arrow-down-left-circle"></i> Escribe aqui el <strong>Debe</strong></label>'
        . '<input type="text" inputmode="decimal" name="debe[]" class="form-control debe text-end"'
        . ' value="' . $debe . '" placeholder="0.00" autocomplete="off">'
        . '<small class="campo-ayuda">El dinero <strong>entra</strong> a esta cuenta.</small>'
        . '</div>'
        . '<div class="campo campo-haber">'
        . '<label class="campo-titulo"><i class="bi bi-arrow-up-right-circle"></i> Escribe aqui el <strong>Haber</strong></label>'
        . '<input type="text" inputmode="decimal" name="haber[]" class="form-control haber text-end"'
        . ' value="' . $haber . '" placeholder="0.00" autocomplete="off">'
        . '<small class="campo-ayuda">El dinero <strong>sale</strong> de esta cuenta.</small>'
        . '</div>'
        . '</div>'
        . '<p class="partida-pista"></p>'
        . '<p class="partida-resumen"></p>'
        . '</div>'
        . '</div>';
}

$filasPartida = '';
foreach ($partidas as $i => $p) {
    $cuentaId = (string)$p['cuenta_id'];
    $debe = (float)$p['debe'];
    $haber = (float)$p['haber'];
    $filasPartida .= bloquePartida(
        $i + 1,
        opcionesGruposHtml($grupos, $grupoDeCuenta[$cuentaId] ?? ''),
        $debe > 0 ? e(num($debe, 2)) : '',
        $haber > 0 ? e(num($haber, 2)) : '',
        $cuentaId
    );
}

require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-1"><?= $asiento['id'] ? 'Editando el asiento N. ' . e($asiento['numero']) : 'Registrar un asiento nuevo' ?></h4>
        <small class="text-muted">Complete los dos pasos. El sistema le avisa si algo no cuadra, asi que no se preocupe por las reglas.</small>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e(url('diario/asientos.php')) ?>"><i class="bi bi-arrow-left"></i> Volver al Libro Diario</a>
</div>

<form method="post" action="<?= e(url('diario/guardar.php')) ?>" id="form-asiento" class="no-print">
    <?= csrfCampo() ?>
    <input type="hidden" name="id" value="<?= (int)$asiento['id'] ?>">

    <div class="card mb-3">
        <div class="card-header"><span class="paso-numero">1</span> Datos del asiento</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label" for="numero">Numero de asiento</label>
                    <input type="number" name="numero" id="numero" class="form-control" min="1" value="<?= $numeroSugerido ?>" required>
                    <div class="form-text">Se propone el siguiente numero libre.</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="fecha">Fecha</label>
                    <input type="date" name="fecha" id="fecha" class="form-control" value="<?= e($asiento['fecha']) ?>" required>
                    <div class="form-text">El dia en que ocurrio el movimiento.</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="tipo">Tipo de operacion</label>
                    <select name="tipo" id="tipo" class="form-select">
                        <?php foreach (['ORDINARIO', 'APERTURA', 'AJUSTE', 'CIERRE'] as $t): ?>
                            <option value="<?= $t ?>" <?= $asiento['tipo'] === $t ? 'selected' : '' ?>><?= e(ucfirst(strtolower($t))) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Si no tiene idea, deje <strong>Ordinario</strong>.</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="documento">Documento o referencia</label>
                    <input type="text" name="documento" id="documento" class="form-control" maxlength="50"
                           value="<?= e($asiento['documento']) ?>" placeholder="Factura 123, cheque 45...">
                    <div class="form-text">Opcional. Sirve para rastrear el papel que respalda el asiento.</div>
                </div>
                <div class="col-12">
                    <label class="form-label" for="concepto">Concepto <span class="text-danger">*</span></label>
                    <textarea name="concepto" id="concepto" rows="2" class="form-control" maxlength="500" required
                              placeholder="Por ejemplo: compra de papeleria de oficina con factura 123"><?= e($asiento['concepto']) ?></textarea>
                    <div class="form-text">Escriba en una frase que paso. Este texto aparecera en el Libro Diario y en el Libro Mayor.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <span class="paso-numero">2</span> Las partidas del asiento
            <span class="header-boton">
                <button type="button" class="btn btn-sm btn-primary" id="agregar-linea">
                    <i class="bi bi-plus-lg"></i> Agregar otra partida
                </button>
            </span>
        </div>
        <div class="card-body card-body-explica">
            <i class="bi bi-lightbulb"></i>
            <div>
                Cada partida es un renglon del asiento: dice <strong>a que cuenta va el dinero</strong> y
                <strong>si entra (Debe) o sale (Haber)</strong>. Solo escribe el importe en <em>un</em> lado.
                Un ejemplo clasico es la compra de utiles: el Debito va al gasto y el Credito al efectivo.
            </div>
        </div>

        <div class="partidas-toolbar">
            <div class="input-group input-group-sm filtro-cuentas">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search" id="buscar-cuenta" class="form-control"
                       placeholder="Filtrar la lista de cuentas por nombre o codigo">
            </div>
            <small class="text-muted">El filtro solo esconde opciones, no cambia lo que ya eligio.</small>
        </div>

        <div id="partidas" class="partidas">
            <?= $filasPartida ?>
        </div>

        <div class="partidas-agregar">
            <button type="button" class="btn btn-outline-primary" id="agregar-linea-2">
                <i class="bi bi-plus-circle"></i> Agregar otra partida
            </button>
        </div>

        <div class="resumen-partida" id="resumen-partida">
            <div class="resumen-bloque">
                <span class="resumen-rotulo">Suma del Debe</span>
                <span class="resumen-valor" id="total-debe">$ 0.00</span>
            </div>
            <div class="resumen-bloque">
                <span class="resumen-rotulo">Suma del Haber</span>
                <span class="resumen-valor" id="total-haber">$ 0.00</span>
            </div>
            <div class="resumen-bloque resumen-calculado">
                <span class="resumen-rotulo">Diferencia (Debe - Haber)</span>
                <span class="resumen-valor" id="diferencia">$ 0.00</span>
            </div>
            <div class="resumen-estado">
                <span class="badge bg-warning text-dark" id="aviso-partida">Todavia no cuadra</span>
                <small class="resumen-mensaje" id="mensaje-partida"></small>
            </div>
        </div>
    </div>

    <div class="ayuda-reglas mb-4">
        <details>
            <summary><i class="bi bi-question-circle"></i> No entiendo algo: las tres reglas basicas</summary>
            <ol>
                <li><strong>Minimo dos partidas.</strong> Un asiento siempre mueve dinero de un lado a otro.</li>
                <li><strong>Debe = Haber.</strong> Los dos totales deben quedar exactamente iguales.</li>
                <li><strong>Solo cuentas de detalle.</strong> No se puede marcar un grupo como "11 Activo Corriente";
                    hay que elegir una cuenta concreta como "11010101 Caja Principal".</li>
            </ol>
            <p class="mb-0">La barra de arriba del formulario se pone verde cuando el asiento ya cuadra.</p>
        </details>
    </div>

    <div class="acciones-finales">
        <button type="submit" class="btn btn-primary btn-lg">
            <i class="bi bi-check2-circle"></i> Guardar asiento
        </button>
        <a class="btn btn-outline-secondary btn-lg" href="<?= e(url('diario/asientos.php')) ?>">Cancelar y volver</a>
    </div>
</form>

<template id="plantilla-partida">
    <?= bloquePartida(1, $opcionesGrupos, '', '') ?>
</template>

<?php require __DIR__ . '/../includes/footer.php'; ?>
