<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

$tituloPagina = 'Ajustes';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValido()) {
        flash('error', 'La sesion expiro. Intente nuevamente.');
        redirect('ajustes/index.php');
    }

    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $carrera = trim((string)($_POST['carrera'] ?? ''));
    $asignatura = trim((string)($_POST['asignatura'] ?? ''));
    $responsable = trim((string)($_POST['responsable'] ?? ''));

    if ($nombre === '') {
        flash('advertencia', 'El nombre de la entidad no puede quedar vacio.');
    } else {
        db()->prepare('UPDATE empresa SET nombre = ?, carrera = ?, asignatura = ?, responsable = ? WHERE id = 1')
            ->execute([$nombre, $carrera, $asignatura, $responsable ?: null]);
        flash('exito', 'Datos de la entidad actualizados. Los reportes mostraran esta informacion.');
        redirect('ajustes/index.php');
    }
}

$emp = empresa();
$totalAsientos = (int)db()->query("SELECT COUNT(*) FROM asientos WHERE estado = 'REGISTRADO'")->fetchColumn();
$totalCuentas = (int)db()->query('SELECT COUNT(*) FROM catalogo_cuentas WHERE activo = 1')->fetchColumn();
$totalHojas = (int)db()->query('SELECT COUNT(*) FROM catalogo_cuentas WHERE activo = 1 AND es_hoja = 1')->fetchColumn();

require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0"><i class="bi bi-gear me-1"></i> Ajustes</h4>
        <small class="text-muted">Los datos de la institucion y el estado del sistema.</small>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e(url('index.php')) ?>"><i class="bi bi-arrow-left"></i> Volver al inicio</a>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-building me-1"></i> Datos de la institucion</div>
            <div class="card-body">
                <div class="que-es-esto mb-3">
                    <i class="bi bi-info-circle"></i>
                    <div>Esta informacion aparece en el encabezado de <strong>todos los reportes</strong> y en la
                        pantalla de inicio. Cambiela aqui una sola vez y se actualizara en todas partes.</div>
                </div>
                <form method="post">
                    <?= csrfCampo() ?>
                    <div class="mb-3">
                        <label class="form-label" for="a_nombre">Institucion o empresa</label>
                        <input type="text" id="a_nombre" name="nombre" class="form-control" maxlength="200"
                               value="<?= e($emp['nombre']) ?>" required>
                        <div class="form-text">Es el nombre que sale impreso en los reportes.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="a_carrera">Carrera o departamento</label>
                        <input type="text" id="a_carrera" name="carrera" class="form-control" maxlength="200"
                               value="<?= e($emp['carrera']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="a_asignatura">Asignatura</label>
                        <input type="text" id="a_asignatura" name="asignatura" class="form-control" maxlength="200"
                               value="<?= e($emp['asignatura']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="a_responsable">Elaborado por</label>
                        <input type="text" id="a_responsable" name="responsable" class="form-control" maxlength="200"
                               value="<?= e($emp['responsable']) ?>" placeholder="Nombre del alumno o alumna">
                        <div class="form-text">Aparece como autor en los reportes.</div>
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Guardar los cambios</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-hdd me-1"></i> Estado del sistema</div>
            <div class="card-body">
                <p class="small text-muted">Solo para consulta. Si algo no cuadra, el problema casi siempre esta en el servidor de base de datos.</p>
                <table class="table table-sm mb-0">
                    <tr><td>Servidor de base de datos</td><td class="text-end"><?= e(DB_HOST) ?> : <?= e(DB_PORT) ?></td></tr>
                    <tr><td>Base de datos</td><td class="text-end"><?= e(DB_NAME) ?></td></tr>
                    <tr><td>Usuario</td><td class="text-end"><?= e(DB_USER) ?></td></tr>
                    <tr><td>Version de PostgreSQL</td><td class="text-end"><?= e(db()->getAttribute(PDO::ATTR_SERVER_VERSION)) ?></td></tr>
                    <tr><td>Version de PHP</td><td class="text-end"><?= e(PHP_VERSION) ?></td></tr>
                    <tr><td>Cuentas del catalogo</td><td class="text-end"><?= $totalCuentas ?> (<?= $totalHojas ?> de detalle)</td></tr>
                    <tr><td>Asientos registrados</td><td class="text-end"><?= $totalAsientos ?></td></tr>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-tools me-1"></i> Mantenimiento</div>
            <div class="card-body">
                <p class="small text-muted">
                    Si falta alguna cuenta en el catalogo, vuelva a instalarlo. La operacion es segura:
                    <strong>no borra asientos</strong> y <strong>no duplica</strong> las cuentas que ya existen.
                </p>
                <a class="btn btn-outline-primary btn-sm" href="<?= e(url('instalar.php')) ?>" target="_blank">
                    <i class="bi bi-arrow-repeat"></i> Reinstalar catalogo de cuentas
                </a>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
