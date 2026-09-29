<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

function e(mixed $valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function url(string $ruta = ''): string
{
    return BASE_URL . '/' . ltrim($ruta, '/');
}

/**
 * URL de un archivo de estilos o script con una version que cambia sola.
 * Sin esto el navegador se queda con el archivo viejo guardado en su cache
 * y la pagina sigue mostrando la version anterior del sistema.
 */
function asset(string $ruta): string
{
    $base = url($ruta);
    $archivo = __DIR__ . '/../' . ltrim($ruta, '/');
    $version = is_file($archivo) ? (string)filemtime($archivo) : (string)ASSET_VERSION;
    return $base . '?v=' . $version;
}

function redirect(string $ruta): never
{
    header('Location: ' . url($ruta));
    exit;
}

function money(float|int|string|null $valor): string
{
    return MONEDA . ' ' . number_format((float)$valor, 2, '.', ',');
}

function num(float|int|string|null $valor, int $dec = 2): string
{
    return number_format((float)$valor, $dec, '.', ',');
}

function fechaLarga(?string $fecha): string
{
    if (!$fecha) {
        return '';
    }
    $meses = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $ts = strtotime($fecha);
    return (int)date('d', $ts) . ' de ' . $meses[(int)date('n', $ts)] . ' de ' . date('Y', $ts);
}

function fechaCorta(?string $fecha): string
{
    return $fecha ? date('d/m/Y', strtotime($fecha)) : '';
}

function hoy(): string
{
    return date('Y-m-d');
}

function nivelNombre(int $nivel): string
{
    return [1 => 'CLASE', 2 => 'GRUPO', 4 => 'AUXILIAR', 6 => 'CUENTA', 8 => 'DETALLE'][$nivel] ?? 'CUENTA';
}

function tipoNombre(string $tipo): string
{
    return [
        'ACTIVO'            => 'Activo',
        'PASIVO'            => 'Pasivo',
        'PATRIMONIO'        => 'Capital Contable',
        'RESULTADO_DEUDOR'  => 'Costos y Gastos',
        'RESULTADO_ACREEDOR'=> 'Ingresos',
        'ORDEN'             => 'Cuentas de Orden',
    ][$tipo] ?? $tipo;
}

function tipoClase(string $tipo): string
{
    return [
        'ACTIVO'            => 'primary',
        'PASIVO'            => 'success',
        'PATRIMONIO'        => 'info',
        'RESULTADO_DEUDOR'  => 'danger',
        'RESULTADO_ACREEDOR'=> 'warning',
        'ORDEN'             => 'secondary',
    ][$tipo] ?? 'secondary';
}

function naturalezaTexto(string $nat, string $tipo): string
{
    $deudor = ['ACTIVO', 'RESULTADO_DEUDOR'];
    return in_array($tipo, $deudor, true) ? 'Deudor' : 'Acreedor';
}

function guiones(int $nivel): string
{
    return str_repeat('   ', max(0, (int)(($nivel - 2) / 2)));
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrfCampo(): string
{
    return '<input type="hidden" name="csrf" value="' . csrfToken() . '">';
}

function csrfValido(): bool
{
    return isset($_POST['csrf']) && hash_equals((string)($_SESSION['csrf'] ?? ''), (string)$_POST['csrf']);
}

function requireCsrf(): void
{
    if (!csrfValido()) {
        flash('error', 'La sesion expiro o el formulario no es valido. Intente nuevamente.');
        redirect($_SERVER['PHP_SELF'] ?? 'index.php');
    }
}

function flash(string $tipo, string $mensaje): void
{
    $_SESSION['flash'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

function tomarFlashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function empresa(): array
{
    static $fila = null;
    if ($fila === null) {
        $fila = db()->query('SELECT * FROM empresa WHERE id = 1')->fetch() ?: [
            'nombre' => 'UNIVERSIDAD CATOLICA DE EL SALVADOR',
            'carrera' => 'CONTABILIDAD 1',
            'asignatura' => 'TECNICAS CONTABLES',
            'responsable' => '',
        ];
    }
    return $fila;
}

function totalDebeGlobal(?string $desde = null, ?string $hasta = null): float
{
    $sql = "SELECT COALESCE(SUM(p.debe), 0) AS t FROM asiento_partidas p
            INNER JOIN asientos a ON a.id = p.asiento_id
            WHERE a.estado = 'REGISTRADO'";
    $params = [];
    if ($desde) {
        $sql .= ' AND a.fecha >= ?';
        $params[] = $desde;
    }
    if ($hasta) {
        $sql .= ' AND a.fecha <= ?';
        $params[] = $hasta;
    }
    $st = db()->prepare($sql);
    $st->execute($params);
    return (float)$st->fetch()['t'];
}

function totalHaberGlobal(?string $desde = null, ?string $hasta = null): float
{
    $sql = "SELECT COALESCE(SUM(p.haber), 0) AS t FROM asiento_partidas p
            INNER JOIN asientos a ON a.id = p.asiento_id
            WHERE a.estado = 'REGISTRADO'";
    $params = [];
    if ($desde) {
        $sql .= ' AND a.fecha >= ?';
        $params[] = $desde;
    }
    if ($hasta) {
        $sql .= ' AND a.fecha <= ?';
        $params[] = $hasta;
    }
    $st = db()->prepare($sql);
    $st->execute($params);
    return (float)$st->fetch()['t'];
}

function saldosPorCuenta(?string $desde = null, ?string $hasta = null, ?array $tipos = null, int $nivelMax = 8): array
{
    $condiciones = ["a.estado = 'REGISTRADO'"];
    $params = [];
    if ($desde) {
        $condiciones[] = 'a.fecha >= :desde';
        $params[':desde'] = $desde;
    }
    if ($hasta) {
        $condiciones[] = 'a.fecha <= :hasta';
        $params[':hasta'] = $hasta;
    }

    $sql = "SELECT c.id, c.codigo, c.nombre, c.nivel, c.tipo, c.naturaleza, c.es_hoja,
                   COALESCE(SUM(m.debe), 0) AS debe,
                   COALESCE(SUM(m.haber), 0) AS haber
            FROM catalogo_cuentas c
            LEFT JOIN (
                SELECT p.cuenta_codigo AS codigo, SUM(p.debe) AS debe, SUM(p.haber) AS haber
                FROM asiento_partidas p
                INNER JOIN asientos a ON a.id = p.asiento_id
                WHERE " . implode(' AND ', $condiciones) . "
                GROUP BY p.cuenta_codigo
            ) m ON m.codigo LIKE c.codigo || '%'
            WHERE c.activo = 1 AND c.nivel <= :nivel";
    $params[':nivel'] = $nivelMax;

    if ($tipos) {
        $marcas = [];
        foreach (array_values($tipos) as $i => $t) {
            $marcas[] = ':t' . $i;
            $params[':t' . $i] = $t;
        }
        $sql .= ' AND c.tipo IN (' . implode(',', $marcas) . ')';
    }

    $sql .= ' GROUP BY c.id, c.codigo, c.nombre, c.nivel, c.tipo, c.naturaleza, c.es_hoja';
    $sql .= ' ORDER BY c.codigo';

    $st = db()->prepare($sql);
    $st->execute($params);
    $filas = $st->fetchAll();

    foreach ($filas as &$f) {
        $neto = round((float)$f['debe'] - (float)$f['haber'], 2);
        $f['deudor'] = $neto > 0 ? $neto : 0.0;
        $f['acreedor'] = $neto < 0 ? abs($neto) : 0.0;
        $f['neto'] = $neto;
    }

    return $filas;
}

function recalcularSaldos(): void
{
    db()->exec(
        "INSERT INTO saldos_cuentas (cuenta_id, total_debe, total_haber, saldo_deudor, saldo_acreedor, ultimo_movimiento)
         SELECT t.cuenta_id,
                COALESCE(t.total_debe, 0),
                COALESCE(t.total_haber, 0),
                CASE WHEN COALESCE(t.neto, 0) > 0 THEN t.neto ELSE 0 END,
                CASE WHEN COALESCE(t.neto, 0) < 0 THEN -t.neto ELSE 0 END,
                t.ultimo_movimiento
         FROM (
             SELECT p.cuenta_id,
                    SUM(p.debe) AS total_debe,
                    SUM(p.haber) AS total_haber,
                    SUM(p.debe - p.haber) AS neto,
                    MAX(a.fecha) AS ultimo_movimiento
             FROM asiento_partidas p
             INNER JOIN asientos a ON a.id = p.asiento_id
             WHERE a.estado = 'REGISTRADO'
             GROUP BY p.cuenta_id
         ) t
         ON CONFLICT (cuenta_id) DO UPDATE SET
            total_debe        = EXCLUDED.total_debe,
            total_haber       = EXCLUDED.total_haber,
            saldo_deudor      = EXCLUDED.saldo_deudor,
            saldo_acreedor    = EXCLUDED.saldo_acreedor,
            ultimo_movimiento = EXCLUDED.ultimo_movimiento"
    );
    db()->exec('DELETE FROM saldos_cuentas WHERE NOT EXISTS (SELECT 1 FROM asiento_partidas p WHERE p.cuenta_id = saldos_cuentas.cuenta_id)');
}

function saldosPorCodigo(?string $desde = null, ?string $hasta = null, ?array $tipos = null, int $nivelMax = 8): array
{
    $filas = saldosPorCuenta($desde, $hasta, $tipos, $nivelMax);
    $mapa = [];
    foreach ($filas as $f) {
        $mapa['#' . $f['codigo']] = $f;
    }
    return $mapa;
}

function saldoDe(array $saldos, string $codigo, string $columna): float
{
    return isset($saldos['#' . $codigo]) ? (float)$saldos['#' . $codigo][$columna] : 0.0;
}

function nivelResaltado(int $nivel): string
{
    return $nivel === 1 ? 'fw-bold' : ($nivel === 2 ? 'fw-semibold' : 'fw-normal');
}

function nivelClase(int $nivel): string
{
    return [1 => 'table-dark', 2 => 'table-light', 4 => '', 6 => '', 8 => ''][$nivel] ?? '';
}

function exportarCsv(string $nombreArchivo, array $encabezados, array $filas): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    $salida = fopen('php://output', 'w');
    fwrite($salida, "\xEF\xBB\xBF");
    fputcsv($salida, $encabezados, ';');
    foreach ($filas as $fila) {
        fputcsv($salida, $fila, ';');
    }
    fclose($salida);
    exit;
}

function param(string $clave, string $porDefecto = ''): string
{
    $valor = $_GET[$clave] ?? $_POST[$clave] ?? $porDefecto;
    return is_string($valor) ? trim($valor) : $porDefecto;
}

function obtenerPartidas(int $asientoId): array
{
    $st = db()->prepare('SELECT * FROM asiento_partidas WHERE asiento_id = ? ORDER BY linea');
    $st->execute([$asientoId]);
    return $st->fetchAll();
}

function obtenerAsiento(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM asientos WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}
