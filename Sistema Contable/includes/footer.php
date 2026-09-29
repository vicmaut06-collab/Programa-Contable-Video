<?php
if (!defined('APP_NAME')) {
    exit;
}
?>
</main>

<footer class="container-fluid py-3 text-center text-muted small no-print border-top">
    Sistema Contable &middot; <?= e(empresa()['asignatura']) ?> &middot; <?= e(empresa()['carrera']) ?>
    &middot; MariaDB / PHP <?= e(PHP_VERSION) ?>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(url('assets/js/asiento.js')) ?>"></script>
<?php if (!empty($scriptsExtra)) { ?>
    <?= $scriptsExtra ?>
<?php } ?>
</body>
</html>
