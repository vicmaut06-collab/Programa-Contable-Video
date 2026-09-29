<?php
if (!defined('APP_NAME')) {
    exit;
}
$paginaActual = basename($_SERVER['PHP_SCRIPT_NAME'] ?? '');
$emp = empresa();
$reportes = [
    ['url' => 'reportes/balance_general.php', 'label' => 'Balance General'],
    ['url' => 'reportes/estado_resultados.php', 'label' => 'Estado de Resultados'],
    ['url' => 'reportes/balance_comprobacion.php', 'label' => 'Balance de Comprobacion'],
    ['url' => 'reportes/indice_cuentas.php', 'label' => 'Indice de Cuentas'],
];
$dirActual = trim(str_replace('\\', '/', dirname(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''))), '/');
$dirActual = trim(preg_replace('#^' . preg_quote(BASE_URL, '#') . '#', '', $dirActual), '/');
$dirItem = $dirActual === '' ? 'raiz' : $dirActual;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($tituloPagina ?? APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="icon" href="<?= e(url('assets/favicon.svg')) ?>" type="image/svg+xml">
    <link href="<?= e(asset('assets/css/estilos.css')) ?>" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark no-print">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?= e(url('index.php')) ?>">
            <i class="bi bi-journal-bookmark-fill"></i> <?= e(APP_NAME) ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuPrincipal">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="menuPrincipal">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?= $dirItem === 'raiz' ? 'active' : '' ?>" href="<?= e(url('index.php')) ?>">
                        <i class="bi bi-house-door"></i> Inicio
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $dirItem === 'diario' ? 'active' : '' ?>" href="<?= e(url('diario/asientos.php')) ?>">
                        <i class="bi bi-journal-text"></i> Libro Diario
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $dirItem === 'mayor' ? 'active' : '' ?>" href="<?= e(url('mayor/index.php')) ?>">
                        <i class="bi bi-list-ul"></i> Libro Mayor
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= $dirItem === 'reportes' ? 'active' : '' ?>" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-file-earmark-bar-graph"></i> Reportes
                    </a>
                    <ul class="dropdown-menu">
                        <?php foreach ($reportes as $r): ?>
                            <li><a class="dropdown-item" href="<?= e(url($r['url'])) ?>"><?= e($r['label']) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $dirItem === 'catalogo' ? 'active' : '' ?>" href="<?= e(url('catalogo/index.php')) ?>">
                        <i class="bi bi-diagram-3"></i> Catalogo
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $dirItem === 'ajustes' ? 'active' : '' ?>" href="<?= e(url('ajustes/index.php')) ?>">
                        <i class="bi bi-gear"></i> Ajustes
                    </a>
                </li>
            </ul>
            <div class="d-flex align-items-center gap-3">
                <a class="btn btn-sm btn-light text-primary fw-semibold" href="<?= e(url('diario/nuevo.php')) ?>">
                    <i class="bi bi-plus-lg"></i> Nuevo asiento
                </a>
                <span class="navbar-text text-white-50 small d-none d-lg-inline">
                    <?= e($emp['asignatura']) ?> &middot; <?= e($emp['carrera']) ?>
                </span>
            </div>
        </div>
    </div>
</nav>

<?php foreach (tomarFlashes() as $flash): ?>
    <div class="container-fluid mt-3 no-print">
        <div class="alert alert-<?= e($flash['tipo'] === 'error' ? 'danger' : ($flash['tipo'] === 'advertencia' ? 'warning' : 'success')) ?> alert-dismissible fade show">
            <?= e($flash['mensaje']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
<?php endforeach; ?>

<main class="container-fluid py-3">
