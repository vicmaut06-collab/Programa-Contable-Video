<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('diario/asientos.php');
}

if (!csrfValido()) {
    flash('error', 'La sesion expiro. Vuelva a intentar el registro del asiento.');
    redirect('diario/asientos.php');
}

$id = (int)($_POST['id'] ?? 0);
$numero = (int)($_POST['numero'] ?? 0);
$fecha = trim((string)($_POST['fecha'] ?? ''));
$concepto = trim((string)($_POST['concepto'] ?? ''));
$documento = trim((string)($_POST['documento'] ?? ''));
$tipo = in_array($_POST['tipo'] ?? '', ['ORDINARIO', 'APERTURA', 'AJUSTE', 'CIERRE'], true) ? (string)$_POST['tipo'] : 'ORDINARIO';

$gruposPost = (array)($_POST['grupo'] ?? []);
$cuentasPost = (array)($_POST['cuenta_id'] ?? []);
$debesPost = (array)($_POST['debe'] ?? []);
$haberesPost = (array)($_POST['haber'] ?? []);

$errores = [];

if ($numero < 1) {
    $errores[] = 'El numero de asiento debe ser mayor que cero.';
}
if ($fecha === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    $errores[] = 'La fecha del asiento es obligatoria y debe tener el formato AAAA-MM-DD.';
}
if (mb_strlen($concepto) < 5) {
    $errores[] = 'El concepto es obligatorio y debe tener al menos 5 caracteres.';
}

$verificaNumero = db()->prepare('SELECT id FROM asientos WHERE numero = ? AND id <> ?');
$verificaNumero->execute([$numero, $id]);
if ($verificaNumero->fetch()) {
    $errores[] = 'Ya existe un asiento registrado con el numero ' . $numero . '. Elija otro numero.';
}

$filas = [];
$totalDebe = 0.0;
$totalHaber = 0.0;
$linea = 0;

foreach ($cuentasPost as $i => $cuentaId) {
    $debe = (float)str_replace(',', '', (string)($debesPost[$i] ?? '0'));
    $haber = (float)str_replace(',', '', (string)($haberesPost[$i] ?? '0'));
    $cuentaId = (int)$cuentaId;

    if ($cuentaId === 0 && $debe == 0.0 && $haber == 0.0) {
        continue;
    }
    $linea++;

    if ($cuentaId === 0) {
        $errores[] = 'Linea ' . $linea . ': debe seleccionar una cuenta.';
        continue;
    }

    $st = db()->prepare('SELECT id, codigo, nombre, es_hoja, activo FROM catalogo_cuentas WHERE id = ?');
    $st->execute([$cuentaId]);
    $cuenta = $st->fetch();
    if (!$cuenta || (int)$cuenta['activo'] !== 1) {
        $errores[] = 'Linea ' . $linea . ': la cuenta seleccionada no existe o esta inactiva.';
        continue;
    }
    if ((int)$cuenta['es_hoja'] !== 1) {
        $errores[] = 'Linea ' . $linea . ': la cuenta ' . $cuenta['codigo'] . ' es un grupo o cuenta de nivel superior. Registre el movimiento en una cuenta de detalle.';
        continue;
    }
    if ($debe < 0 || $haber < 0) {
        $errores[] = 'Linea ' . $linea . ': los importes no pueden ser negativos.';
        continue;
    }
    if ($debe > 0 && $haber > 0) {
        $errores[] = 'Linea ' . $linea . ': no se permite debito y credito en la misma linea.';
        continue;
    }
    if ($debe == 0.0 && $haber == 0.0) {
        $errores[] = 'Linea ' . $linea . ': debe indicar el importe en la columna Debito o en la columna Credito.';
        continue;
    }

    $debe = round($debe, 2);
    $haber = round($haber, 2);
    $totalDebe += $debe;
    $totalHaber += $haber;

    $filas[] = [
        'linea'        => $linea,
        'cuenta_id'    => (int)$cuenta['id'],
        'cuenta_codigo' => $cuenta['codigo'],
        'cuenta_nombre' => $cuenta['nombre'],
        'debe'         => $debe,
        'haber'        => $haber,
    ];
}

if (count($filas) < 2) {
    $errores[] = 'El asiento debe tener al menos dos partidas con importe.';
}
if (abs($totalDebe - $totalHaber) > 0.009) {
    $errores[] = 'El asiento no cumple la partida doble: total debito ' . money($totalDebe)
        . ' y total credito ' . money($totalHaber) . '.';
}
if ($totalDebe <= 0) {
    $errores[] = 'El total del asiento debe ser mayor que cero.';
}
if (count($filas) > MAX_LINEAS) {
    $errores[] = 'Un asiento no puede tener mas de ' . MAX_LINEAS . ' partidas.';
}

if ($errores) {
    foreach ($errores as $error) {
        flash('error', $error);
    }
    redirect($id ? 'diario/editar.php?id=' . $id : 'diario/nuevo.php');
}

$pdo = db();
try {
    $pdo->beginTransaction();

    $esEdicion = $id > 0;

    if ($esEdicion) {
        $existe = $pdo->prepare('SELECT id FROM asientos WHERE id = ?');
        $existe->execute([$id]);
        if (!$existe->fetch()) {
            throw new RuntimeException('el asiento que intenta editar no existe');
        }

        $actualizaAsiento = $pdo->prepare(
            'UPDATE asientos
             SET numero = ?, fecha = ?, concepto = ?, documento = ?, tipo = ?, total_debe = ?, total_haber = ?
             WHERE id = ?'
        );
        $actualizaAsiento->execute([$numero, $fecha, $concepto, $documento ?: null, $tipo, $totalDebe, $totalHaber, $id]);
        $asientoId = $id;

        $borraPartidas = $pdo->prepare('DELETE FROM asiento_partidas WHERE asiento_id = ?');
        $borraPartidas->execute([$asientoId]);
    } else {
        $guardaAsiento = $pdo->prepare(
            'INSERT INTO asientos (numero, fecha, concepto, documento, tipo, total_debe, total_haber)
             VALUES (?, ?, ?, ?, ?, ?, ?) RETURNING id'
        );
        $guardaAsiento->execute([$numero, $fecha, $concepto, $documento ?: null, $tipo, $totalDebe, $totalHaber]);
        $asientoId = (int)$guardaAsiento->fetchColumn();
    }

    $guardaPartida = $pdo->prepare(
        'INSERT INTO asiento_partidas (asiento_id, linea, cuenta_id, cuenta_codigo, cuenta_nombre, debe, haber)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $saldos = [];
    foreach ($filas as $f) {
        $guardaPartida->execute([
            $asientoId, $f['linea'], $f['cuenta_id'], $f['cuenta_codigo'], $f['cuenta_nombre'], $f['debe'], $f['haber'],
        ]);
        if (!isset($saldos[$f['cuenta_id']])) {
            $saldos[$f['cuenta_id']] = ['debe' => 0.0, 'haber' => 0.0];
        }
        $saldos[$f['cuenta_id']]['debe'] += $f['debe'];
        $saldos[$f['cuenta_id']]['haber'] += $f['haber'];
    }

    if ($esEdicion) {
        recalcularSaldos();
        $pdo->commit();
    } else {
        actualizarSaldos($pdo, $saldos, $fecha);
        $pdo->commit();
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash('error', 'No se pudo guardar el asiento: ' . $e->getMessage());
    redirect($id ? 'diario/editar.php?id=' . $id : 'diario/nuevo.php');
}

flash('exito', 'Asiento N. ' . $numero . ($id > 0 ? ' actualizado' : ' registrado') . ' correctamente. Los saldos de las cuentas fueron actualizados.');
redirect('diario/ver.php?id=' . $asientoId);

function actualizarSaldos(PDO $pdo, array $saldos, string $fecha): void
{
    if (!$saldos) {
        return;
    }

    $obtiene = $pdo->prepare('SELECT total_debe, total_haber FROM saldos_cuentas WHERE cuenta_id = ?');
    $upsert = $pdo->prepare(
        'INSERT INTO saldos_cuentas (cuenta_id, total_debe, total_haber, saldo_deudor, saldo_acreedor, ultimo_movimiento)
         VALUES (?, ?, ?, ?, ?, ?)
         ON CONFLICT (cuenta_id) DO UPDATE SET
            total_debe = EXCLUDED.total_debe,
            total_haber = EXCLUDED.total_haber,
            saldo_deudor = EXCLUDED.saldo_deudor,
            saldo_acreedor = EXCLUDED.saldo_acreedor,
            ultimo_movimiento = EXCLUDED.ultimo_movimiento'
    );

    foreach ($saldos as $cuentaId => $mov) {
        $obtiene->execute([$cuentaId]);
        $actual = $obtiene->fetch();

        $totalDebe = round((float)($actual['total_debe'] ?? 0) + $mov['debe'], 2);
        $totalHaber = round((float)($actual['total_haber'] ?? 0) + $mov['haber'], 2);
        $neto = round($totalDebe - $totalHaber, 2);
        $deudor = $neto > 0 ? $neto : 0.0;
        $acreedor = $neto < 0 ? abs($neto) : 0.0;

        $upsert->execute([$cuentaId, $totalDebe, $totalHaber, $deudor, $acreedor, $fecha]);
    }
}
