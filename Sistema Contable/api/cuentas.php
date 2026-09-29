<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$padre = param('padre');
$busqueda = param('q');
$nivel = param('nivel');
$soloHojas = param('hojas') === '1';

try {
    $sql = 'SELECT id, codigo, nombre, nivel, tipo, naturaleza, es_hoja, padre_codigo
            FROM catalogo_cuentas WHERE activo = 1';
    $params = [];

    if ($padre !== '') {
        $sql .= ' AND (padre_codigo = ? OR codigo LIKE ?)';
        $params[] = $padre;
        $params[] = $padre . '%';
    }
    if ($nivel !== '') {
        $sql .= ' AND nivel = ?';
        $params[] = (int)$nivel;
    }
    if ($soloHojas) {
        $sql .= ' AND es_hoja = 1';
    }
    if ($busqueda !== '') {
        $sql .= ' AND (codigo LIKE ? OR nombre LIKE ?)';
        $params[] = $busqueda . '%';
        $params[] = '%' . $busqueda . '%';
    }

    $sql .= ' ORDER BY codigo LIMIT 800';

    $st = db()->prepare($sql);
    $st->execute($params);
    $filas = $st->fetchAll();

    $cuentas = array_map(static function (array $f): array {
        return [
            'id'            => (int)$f['id'],
            'codigo'        => $f['codigo'],
            'nombre'        => $f['nombre'],
            'esc_nombre'    => htmlspecialchars($f['nombre'], ENT_QUOTES, 'UTF-8'),
            'nivel'         => (int)$f['nivel'],
            'tipo'          => $f['tipo'],
            'naturaleza'    => $f['naturaleza'],
            'es_hoja'       => (int)$f['es_hoja'],
            'padre_codigo'  => (string)($f['padre_codigo'] ?? ''),
        ];
    }, $filas);

    echo json_encode([
        'ok'      => true,
        'total'   => count($cuentas),
        'cuentas' => $cuentas,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
