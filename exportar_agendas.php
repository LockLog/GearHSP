<?php
require_once 'includes/auth.php';
require_once 'config/database.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    header("Location: index.php");
    exit;
}

// Obtener parámetros de filtro
$filtros = [
    'especialidad' => $_GET['filtro_especialidad'] ?? '',
    'profesional' => $_GET['filtro_profesional'] ?? '',
    'estado' => $_GET['filtro_estado'] ?? '',
    'fecha' => $_GET['filtro_fecha'] ?? '',
    'busqueda' => $_GET['filtro_busqueda'] ?? ''
];

// Obtener agendas filtradas
$agendas = $auth->getAgendasFiltradas($filtros);

// Configurar headers para descarga
$filename = 'agendas_' . date('Y-m-d_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: no-cache');


// Crear output
$output = fopen('php://output', 'w');

// BOM para UTF-8 (ayuda con Excel y caracteres especiales)
fwrite($output, "\xEF\xBB\xBF");

// Headers CSV
$headers= [
    'ID',
    'Especialidad',
    'Profesional', 
    'Horas Contrato',
	'Estamento',
    'Fecha Inicio',
    'Estado',
    'Usuario Registro',
    'Fecha Registro',
    'Usuario Modificación',
    'Fecha Modificación'
];
// Función para limpiar y formatear datos para CSV
function limpiarParaCSV($valor) {
    if (is_null($valor)) return '';
    // Escapar comillas y caracteres problemáticos
    $valor = str_replace('"', '""', $valor);
    // Eliminar saltos de línea y tabs
    $valor = preg_replace('/[\r\n\t]+/', ' ', $valor);
    return $valor;
}
// Datos CSV

fputcsv($output, $headers, ';');

foreach ($agendas as $agenda) {
    $fila= [
        $agenda['id'],
        $agenda['especialidad_nombre'],
        $agenda['profesional_nombre'],
        $agenda['horas_contrato'] . ' horas',
		$agenda['estamento'],
        date('d/m/Y', strtotime($agenda['fecha_inicio'])),
        $auth->getNombreEstadoAgenda($agenda['estado']),
        $agenda['usuario_registro'],
        date('d/m/Y H:i', strtotime($agenda['timestamp_registro'])),
        $agenda['usuario_modificacion'] ?: 'N/A',
        $agenda['timestamp_modificacion'] ? date('d/m/Y H:i', strtotime($agenda['timestamp_modificacion'])) : 'N/A'
    ];
	    fputcsv($output, $fila, ';');
}

fclose($output);
exit;
?>