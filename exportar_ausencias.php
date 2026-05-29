<?php
require_once 'includes/auth.php';
$auth = new Auth();

if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    header("Location: index.php");
    exit;
}

// Obtener filtros del formulario
$filtros = [
    'estado' => $_GET['estado'] ?? '',
    'fecha_desde' => $_GET['fecha_desde'] ?? '',
    'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
    'busqueda' => $_GET['busqueda'] ?? ''
];

// Obtener datos filtrados
$ausencias = $auth->getAusenciasParaExportar($filtros);

// Configurar headers para descarga CSV
$filename = 'ausencias_' . date('Y-m-d_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: no-cache');

// Crear output
$output = fopen('php://output', 'w');

// BOM para UTF-8 (ayuda con Excel y caracteres especiales)
fwrite($output, "\xEF\xBB\xBF");




// Actualizar los headers
$headers = [
    'ID',
    'Profesional',
    'Especialidad', 
    'Motivo',
    'Fecha Inicio',
    'Fecha Fin',
    'Días',
    'Detalle',
    'Reporte', 
    'Estado',
    'Usuario Registro',
    'Fecha Registro',
    'Usuario Modificación',
    'Fecha Modificación'
];



// Función para calcular días de ausencia
function calcularDiasAusencia($fecha_inicio, $fecha_fin) {
    $inicio = new DateTime($fecha_inicio);
    $fin = new DateTime($fecha_fin);
    $diferencia = $inicio->diff($fin);
    return $diferencia->days + 1; // +1 para incluir el día inicial
}
// Función para limpiar y formatear datos para CSV
function limpiarParaCSV($valor) {
    if (is_null($valor)) return '';
    // Escapar comillas y caracteres problemáticos
    $valor = str_replace('"', '""', $valor);
    // Eliminar saltos de línea y tabs
    $valor = preg_replace('/[\r\n\t]+/', ' ', $valor);
    return $valor;
}
// Llenar datos
fputcsv($output, $headers, ';');

foreach ($ausencias as $ausencia) {
    $fila = [
        $ausencia['id'],
        $ausencia['profesional_nombre'],
        $ausencia['especialidad_nombre'],
        $auth->getNombreMotivo($ausencia['motivo']),
        date('d/m/Y', strtotime($ausencia['fecha_inicio'])),
        date('d/m/Y', strtotime($ausencia['fecha_fin'])),
        calcularDiasAusencia($ausencia['fecha_inicio'], $ausencia['fecha_fin']),
        $ausencia['detalle'] ?? '',
		$ausencia['reporte'] ?? '', 
        $auth->getNombreEstado($ausencia['estado']),
        $ausencia['usuario_registro'],
        date('d/m/Y H:i', strtotime($ausencia['timestamp_registro'])),
        $ausencia['usuario_modificacion'] ?? 'No modificado',
        $ausencia['timestamp_modificacion'] ? date('d/m/Y H:i', strtotime($ausencia['timestamp_modificacion'])) : 'No modificado'
    ];
    
    fputcsv($output, $fila, ';');
}

fclose($output);
exit;
?>