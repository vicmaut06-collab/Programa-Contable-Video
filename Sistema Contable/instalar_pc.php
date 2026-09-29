<?php
/**
 * Instalador para una computadora nueva.
 *
 * Uso:  C:\xampp\php\php.exe -c C:\xampp\php\php.ini instalar_pc.php
 *
 * Solo se ejecuta por linea de comandos (nunca desde el navegador).
 * Deja el sistema listo: copia el proyecto, habilita pdo_pgsql, detecta el
 * puerto real de PostgreSQL, guarda la contrasena y crea la base de datos.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este archivo solo se ejecuta desde la consola.\n");
}

/* ------------------------------------------------------------------ */
/* Utilidades de salida                                                */
/* ------------------------------------------------------------------ */
$t = static function (string $s): void { echo $s . PHP_EOL; };
$ok = static function (string $s): void { echo "  [OK]   $s" . PHP_EOL; };
$fail = static function (string $s): void { echo "  [AVISO] $s" . PHP_EOL; };
$mal = static function (string $s): void { echo "  [FALLA] $s" . PHP_EOL; };
$titulo = static function (string $s): void {
    echo PHP_EOL . $s . PHP_EOL . str_repeat('-', mb_strlen($s)) . PHP_EOL;
};

/* ------------------------------------------------------------------ */
/* FASE 1: todo lo que no necesita pdo_pgsql                         */
/* ------------------------------------------------------------------ */
if (($argv[1] ?? '') !== '--fase2') {

    $origen  = str_replace('\\', '/', __DIR__);

    // XAMPP se deduce del propio PHP que esta corriendo: <xampp>\php\php.exe
    $xampp = str_replace('\\', '/', dirname(dirname(PHP_BINARY)));

    $titulo('1. XAMPP');
    if (!is_dir($xampp . '/apache') || !is_file($xampp . '/php/php.exe')) {
        if (is_dir('C:/xampp/apache')) {
            $xampp = 'C:/xampp';
        } else {
            $mal('No se encontro XAMPP (se busco en ' . $xampp . ' y en C:/xampp)');
            $t('');
            $t('  Instala XAMPP desde https://www.apachefriends.org/es/index.html');
            $t('  y vuelve a ejecutar este instalador.');
            exit(1);
        }
    }
    $ok('XAMPP en ' . $xampp);

    $titulo('2. Copiar el proyecto');
    $destino = $xampp . '/htdocs/SistemaContable';
    if (!is_dir($xampp . '/htdocs')) {
        mkdir($xampp . '/htdocs', 0777, true);
    }
    $yaEstaba = is_file($destino . '/index.php');
    if ($yaEstaba) {
        $t('  El proyecto ya estaba en htdocs; se actualizan los archivos.');
    }
    exec('robocopy "' . $origen . '" "' . $destino . '" /E /NFL /NDL /NJH /NJS /NP /XD .git node_modules 2>&1', $salida, $codigo);
    if ($codigo > 7) {
        $mal('No se pudieron copiar los archivos (codigo ' . $codigo . ').');
        exit(1);
    }
    $n = 0;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($destino, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile()) $n++;
    }
    $ok("$n archivos en " . $destino);

    $titulo('3. Habilitar PostgreSQL en el PHP de XAMPP');
    $ini = $xampp . '/php/php.ini';
    if (!is_file($ini)) {
        $mal('No se encontro php.ini en ' . $ini);
        exit(1);
    }
    $contenido = file_get_contents($ini);
    $lineas = preg_split('/\r\n|\r|\n/', $contenido);
    $cambios = [];
    foreach (['pdo_pgsql', 'pgsql'] as $ext) {
        $yaActiva = false;
        foreach ($lineas as $l) {
            if (preg_match('/^\s*extension\s*=\s*' . preg_quote($ext, '/') . '\s*$/i', $l)) {
                $yaActiva = true;
                break;
            }
        }
        if ($yaActiva) {
            $ok("extension=$ext ya estaba habilitada");
            continue;
        }
        $comentada = null;
        foreach ($lineas as $i => $l) {
            if (preg_match('/^\s*;\s*extension\s*=\s*' . preg_quote($ext, '/') . '\s*$/i', $l)) {
                $comentada = $i;
                break;
            }
        }
        if ($comentada !== null) {
            $lineas[$comentada] = "extension=$ext";
            $cambios[] = "extension=$ext (se descomento la lineaexisting)";
        } else {
            $lineas[] = "extension=$ext";
            $cambios[] = "extension=$ext (se agrego al final)";
        }
    }
    if ($cambios) {
        file_put_contents($ini, implode(PHP_EOL, $lineas));
        foreach ($cambios as $c) $ok($c);
        $fail('Reinicia Apache para que PHP cargue las extensiones.');
    } else {
        $ok('No hizo falta cambiar php.ini');
    }

    $titulo('4. Buscar PostgreSQL y decidir el puerto');
    $candidatos = [];
    exec('reg query "HKLM\\SOFTWARE\\PostgreSQL" /s /v Port 2>NUL', $reg, $rc);
    foreach ($reg as $linea) {
        if (preg_match('/Port\s+REG_DWORD\s+0x([0-9a-f]+)/i', $linea, $m)) {
            $p = hexdec($m[1]);
            if ($p > 0 && !in_array($p, $candidatos, true)) $candidatos[] = (int)$p;
        }
    }
    foreach ([5432, 5433] as $p) {
        if (!in_array($p, $candidatos, true)) $candidatos[] = $p;
    }
    $servicios = [];
    exec('sc query type= service state= all 2>NUL | findstr /C:"SERVICE_NAME: postgresql" 2>NUL', $sc);
    foreach ($sc as $l) {
        if (preg_match('/postgresql[\w-]*/i', $l, $m)) $servicios[] = $m[0];
    }
    if ($servicios) $t('  Servicios: ' . implode(', ', array_unique($servicios)));
    $t('  Puertos a probar: ' . implode(', ', $candidatos));

    $t('');
    $t('  Continuando con la deteccion de PostgreSQL...');
    $t('');

    // La fase 2 necesita pdo_pgsql, que recien se habilito. Se relanza con el php de XAMPP.
    $php = $xampp . '/php/php.exe';
    $cmd = escapeshellarg($php) . ' -c ' . escapeshellarg($ini) . ' ' . escapeshellarg(__FILE__) . ' --fase2'
        . ' ' . escapeshellarg($destino) . ' ' . escapeshellarg(implode(',', $candidatos));
    passthru($cmd, $cod);
    exit($cod);
}

/* ------------------------------------------------------------------ */
/* FASE 2: ya hay pdo_pgsql disponible                                 */
/* ------------------------------------------------------------------ */
$destino = $argv[2] ?? 'C:/xampp/htdocs/SistemaContable';
$candidatos = array_filter(array_map('intval', explode(',', $argv[3] ?? '5432,5433')));

function probar(string $host, int $puerto, string $usuario, string $clave): bool
{
    try {
        new PDO("pgsql:host=$host;port=$puerto;dbname=postgres", $usuario, $clave, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3,
        ]);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

$usuario  = 'postgres';
$clave    = '';
$puertoOk = null;

foreach ($candidatos as $p) {
    if (probar('127.0.0.1', $p, $usuario, '')) {
        $puertoOk = $p;
        break;
    }
}

if ($puertoOk === null) {
    $t('');
    $t('  PostgreSQL pide contrasena (es lo normal en una instalacion nueva).');
    $t('  Escribe la que le pusiste al usuario postgres.');
    $t('  Recuerda: no se muestra nada mientras escribes.');
    $t('');

    for ($intento = 1; $intento <= 3; $intento++) {
        fwrite(STDOUT, "  Contrasena (intento $intento de 3): ");
        $linea = fgets(STDIN);
        $clave = $linea === false ? '' : rtrim($linea, "\r\n");

        foreach ($candidatos as $p) {
            if (probar('127.0.0.1', $p, $usuario, $clave)) {
                $puertoOk = $p;
                break;
            }
        }
        if ($puertoOk !== null) {
            break;
        }
        $mal('Contrasena incorrecta o el puerto no es el correcto.');
        if ($intento < 3) {
            $t('');
        }
    }
}

if ($puertoOk === null) {
    $mal('No se pudo conectar a PostgreSQL.');
    $t('');
    $t('  Revisa estas dos cosas:');
    $t('   1. Que el servicio PostgreSQL este corriendo.');
    $t('      Cierra esta ventana, abre services.msc y busca "postgresql-x64-18"');
    $t('      (o cualquier version que tengas). Si esta detenido, presiona Iniciar.');
    $t('   2. Que la contrasena sea la del usuario postgres.');
    $t('');
    $t('  SI NO RECUERDAS LA CONTRASENA, recuperala asi:');
    $t('   a) Abre el Explorador de archivos y pega en la barra de direcciones:');
    $t('      C:\\Program Files\\PostgreSQL');
    $t('   b) Entra a la carpeta "data" y abre pg_hba.conf con el Bloc de notas.');
    $t('   c) En la linea que empieza con "host ... 127.0.0.1/32" cambia');
    $t('      la palabra "trust" por "scram-sha-256" y guarda.');
    $t('   d) Reinicia PostgreSQL desde services.msc.');
    $t('   e) Vuelve a ejecutar este instalador.');
    $t('');
    $t('  (El paso c es al reves: si PostgreSQL te pide contrasena pero el sistema');
    $t('  dice que no, el problema es que pg_hba.conf sigue en "trust".)');
    $t('');
    $t('  Nada se modifico: config/config.php quedo como estaba.');
    $t('');
    exit(1);
}
$ok("Conectado a PostgreSQL en el puerto $puertoOk");

$titulo('5. Guardar la conexion en config/config.php');
$cfgRuta = $destino . '/config/config.php';
$cfg = file_get_contents($cfgRuta);
$cfg = preg_replace("/define\('DB_PORT',\s*'[^']*'\)/", "define('DB_PORT', '$puertoOk')", $cfg);
$cfg = preg_replace("/define\('DB_PASS',\s*'[^']*'\)/", "define('DB_PASS', '" . addcslashes($clave, "'\\") . "')", $cfg);
file_put_contents($cfgRuta, $cfg);
$ok("DB_PORT = '$puertoOk'");
$ok('DB_PASS = ' . ($clave === '' ? '(vacia)' : '(guardada)'));

$titulo('6. Iniciar Apache');
exec('tasklist /fi "imagename eq httpd.exe" 2>NUL | findstr httpd.exe', $h, $rc);
if ($rc === 0) {
    $ok('Apache ya estaba corriendo');
} else {
    $xamppRaiz = str_replace('\\', '/', dirname(dirname(PHP_BINARY)));
    $httpd = $xamppRaiz . '/apache/bin/httpd.exe';
    if (!is_file($httpd)) {
        $mal('No se encontro ' . $httpd . '; abre XAMPP y presiona Start en Apache.');
    } else {
        $vbs = $destino . '/iniciar_apache.vbs';
        file_put_contents($vbs, "Set sh = CreateObject(\"WScript.Shell\")\r\n"
            . "sh.CurrentDirectory = \"$xamppRaiz/apache\"\r\n"
            . "sh.Run \"\"\"$httpd\"\" -d \"\"$xamppRaiz/apache\"\"\", 0, False\r\n");
        exec('wscript.exe //nologo ' . escapeshellarg($vbs));
        sleep(6);
        exec('tasklist /fi "imagename eq httpd.exe" 2>NUL | findstr httpd.exe', $h, $rc);
        $rc === 0 ? $ok('Apache iniciado') : $mal('Apache no arranco; abre XAMPP y presiona Start en Apache.');
    }
}

$titulo('7. Crear la base de datos y el catalogo');
$url = 'http://localhost/SistemaContable/instalar.php';
$html = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 30]]));

if ($html === false) {
    $mal('No se pudo abrir ' . $url);
    $t('  Presiona Start en Apache e intenta de nuevo.');
    exit(1);
}
if (strpos($html, 'alert-success') !== false) {
    preg_match('/Cuentas registradas en el catalogo:\s*(\d+)/', $html, $m1);
    preg_match('/Cuentas de detalle \(hojas\):\s*(\d+)/', $html, $m2);
    $ok('Base de datos creada');
    $ok('Catalogo: ' . ($m1[1] ?? '?') . ' cuentas, ' . ($m2[1] ?? '?') . ' de detalle');
} else {
    $mal('El instalador no termino bien. Revisa la pagina ' . $url);
    $txt = preg_replace('/\s+/', ' ', strip_tags($html));
    $t('  ' . mb_substr($txt, 0, 400));
    exit(1);
}

$titulo('Listo');
$t('  Abre:  http://localhost/SistemaContable/');
$t('  Para iniciar el sistema mas adelante, doble clic en INICIAR.bat');
$t('');
exit(0);
