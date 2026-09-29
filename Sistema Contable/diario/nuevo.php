<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

$id = (int)param('id', '0');
$asiento = obtenerAsiento($id);
$partidas = [];

if ($asiento) {
    $partidas = obtenerPartidas($id);
    $tituloPagina = 'Editar Asiento N. ' . $asiento['numero'];
}

require __DIR__ . '/formulario.php';
