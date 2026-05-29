<?php
require_once 'includes/auth.php';
$auth = new Auth();

if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    header("Location: index.php");
    exit;
}

// Obtener filtros
$filtros = [
    'estado' => $_GET['estado'] ?? '',
    'fecha_desde' => $_GET['fecha_desde'] ?? '',
    'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
    'busqueda' => $_GET['busqueda'] ?? ''
];

// Obtener datos
$ausencias = $auth->getAusenciasParaExportar($filtros);

// Crear CSV como alternativa
$filename = 'ausencias_' . date('Y-m-d_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// BOM para UTF-8
fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));

// Encabezados
fputcsv($output, [
    'ID', 'Profesional', 'Especialidad', 'Motivo', 
    'Fecha Inicio', 'Fecha Fin', 'Detalle', 'Estado',
    'Usuario Registro', 'Fecha Registro', 
    'Usuario Modificación', 'Fecha Modificación'
], ';');

// Datos
foreach ($ausencias as $ausencia) {
    fputcsv($output, [
        $ausencia['id'],
        $ausencia['profesional_nombre'],
        $ausencia['especialidad_nombre'],
        $auth->getNombreMotivo($ausencia['motivo']),
        $ausencia['fecha_inicio'],
        $ausencia['fecha_fin'],
        $ausencia['detalle'] ?? '',
        $auth->getNombreEstado($ausencia['estado']),
        $ausencia['usuario_registro'],
        $ausencia['timestamp_registro'],
        $ausencia['usuario_modificacion'] ?? '',
        $ausencia['timestamp_modificacion'] ?? ''
    ], ';');
}

fclose($output);
exit;
?>