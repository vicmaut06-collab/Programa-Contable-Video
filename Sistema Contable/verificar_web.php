<?php
/**
 * Verificacion rapida de que el sitio responde. Uso interno de los .bat
 *   php verificar_web.php http://localhost/SistemaContable/
 * Sale con codigo 0 si la pagina contesto, 1 si no.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

$url = $argv[1] ?? 'http://localhost/SistemaContable/';

$contexto = stream_context_create([
    'http' => [
        'timeout'     => 10,
        'ignore_errors' => true,
    ],
]);

$respuesta = @file_get_contents($url, false, $contexto);

if ($respuesta === false) {
    fwrite(STDERR, "No se pudo abrir $url\n");
    exit(1);
}

$codigo = 0;
foreach ($http_response_header ?? [] as $linea) {
    if (preg_match('#^HTTP/\S+\s+(\d{3})#', $linea, $m)) {
        $codigo = (int)$m[1];
    }
}

if ($codigo >= 400) {
    fwrite(STDERR, "El sitio respondio con codigo $codigo\n");
    exit(1);
}

exit(0);
