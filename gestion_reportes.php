<?php

require_once 'includes/auth.php';
$auth = new Auth();

if (!$auth->isLoggedIn()) {
    header("Location: index.php");
    exit;
}

if (!($auth->isAdmin() || $auth->isUGD())) {
    header("Location: dashboard.php");
    exit;
}

require_once 'config/database.php';
$database = new Database();
$conn = $database->getConnection();

$success = '';
$error = '';

// Configurar zona horaria
date_default_timezone_set('America/Santiago');

// Procesar filtros con seguridad
$filtro_rut = htmlspecialchars($_GET['filtro_rut'] ?? '', ENT_QUOTES, 'UTF-8');
$filtro_agenda = htmlspecialchars($_GET['filtro_agenda'] ?? '', ENT_QUOTES, 'UTF-8');
$filtro_grupo = htmlspecialchars($_GET['filtro_grupo'] ?? '', ENT_QUOTES, 'UTF-8');
$filtro_estado = htmlspecialchars($_GET['filtro_estado'] ?? '', ENT_QUOTES, 'UTF-8');
$filtro_fecha_desde = $_GET['filtro_fecha_desde'] ?? '';
$filtro_fecha_hasta = $_GET['filtro_fecha_hasta'] ?? '';
$filtro_sicarchivada = $_GET['filtro_sicarchivada'] ?? '';

// Procesar exportación a CSV
if (isset($_GET['exportar']) && $_GET['exportar'] == 'csv') {
    exportarReportesCSV($conn, [
        'filtro_rut' => $filtro_rut,
        'filtro_agenda' => $filtro_agenda,
        'filtro_grupo' => $filtro_grupo,
        'filtro_estado' => $filtro_estado,
        'filtro_fecha_desde' => $filtro_fecha_desde,
        'filtro_fecha_hasta' => $filtro_fecha_hasta,
        'filtro_sicarchivada' => $filtro_sicarchivada
    ]);
    exit;
}

// Procesar formulario para editar reporte
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_reporte'])) {
    $reporte_id = $_POST['reporte_id'];
    $estado_registro = $_POST['estado_registro'];
    $sicarchivada = $_POST['sicarchivada'] ?? 'No';
    $usuario_actualiza = $_SESSION['user_id'];
    
    // Validar que el reporte exista
    $query_check = "SELECT id FROM reportes WHERE id = :id";
    $stmt_check = $conn->prepare($query_check);
    $stmt_check->bindParam(':id', $reporte_id);
    $stmt_check->execute();
    
    if ($stmt_check->rowCount() == 0) {
        $error = "El reporte no existe o ha sido eliminado";
    } else {
        // Obtener username del usuario actual
        $query_usuario = "SELECT username FROM usuarios WHERE id = :id";
        $stmt_usuario = $conn->prepare($query_usuario);
        $stmt_usuario->bindParam(':id', $usuario_actualiza);
        $stmt_usuario->execute();
        $usuario_data = $stmt_usuario->fetch(PDO::FETCH_ASSOC);
        $username_actualiza = $usuario_data['username'] ?? 'sistema';
        
        // Validaciones
        if (empty($estado_registro)) {
            $error = "El estado del reporte es obligatorio";
        } else {
            try {
                // Registrar cambio en log
                logCambioEstado($conn, $reporte_id, $_SESSION['user_id'], $estado_registro, $sicarchivada);
                
                // Actualizar reporte
                $query = "UPDATE reportes 
                          SET Estado_Registro = :estado_registro, 
                              SICarchivada = :sicarchivada,
                              Usuario_Actualiza = :username_actualiza, 
                              Fecha_Actualiza = NOW()
                          WHERE id = :id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':estado_registro', $estado_registro);
                $stmt->bindParam(':sicarchivada', $sicarchivada);
                $stmt->bindParam(':username_actualiza', $username_actualiza);
                $stmt->bindParam(':id', $reporte_id);
                
                if ($stmt->execute()) {
                    $success = "Reporte actualizado correctamente";
                    // Redirigir para limpiar POST y mantener filtros
                    $redirect_url = "Location: gestion_reportes.php?filtro_rut=" . urlencode($filtro_rut) . 
                                   "&filtro_agenda=" . urlencode($filtro_agenda) . 
                                   "&filtro_grupo=" . urlencode($filtro_grupo) .
                                   "&filtro_estado=" . urlencode($filtro_estado) .
                                   "&filtro_fecha_desde=" . urlencode($filtro_fecha_desde) .
                                   "&filtro_fecha_hasta=" . urlencode($filtro_fecha_hasta) .
                                   "&filtro_sicarchivada=" . urlencode($filtro_sicarchivada);
                    header($redirect_url);
                    exit;
                } else {
                    $error = "Error al actualizar el reporte";
                }
            } catch (PDOException $e) {
                $error = "Error en la base de datos: " . $e->getMessage();
            }
        }
    }
}

// Paginación
$limit = 50; // Registros por página
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Construir consulta SQL con filtros
$where_conditions = [];
$params = [];

// Filtrar por RUT
if (!empty($filtro_rut)) {
    $where_conditions[] = "r.RUT LIKE :rut";
    $params[':rut'] = '%' . $filtro_rut . '%';
}

// Filtrar por Agenda
if (!empty($filtro_agenda)) {
    $where_conditions[] = "r.Agenda LIKE :agenda";
    $params[':agenda'] = '%' . $filtro_agenda . '%';
}

// Filtrar por Grupo (NUEVO)
if (!empty($filtro_grupo)) {
    $where_conditions[] = "r.Grupo LIKE :grupo";
    $params[':grupo'] = '%' . $filtro_grupo . '%';
}

// Filtrar por Estado
if (!empty($filtro_estado) && $filtro_estado !== 'todos') {
    $where_conditions[] = "r.Estado_Registro = :estado";
    $params[':estado'] = $filtro_estado;
}

// Filtrar por SICarchivada (NUEVO)
if (!empty($filtro_sicarchivada) && $filtro_sicarchivada !== 'todos') {
    $where_conditions[] = "r.SICarchivada = :sicarchivada";
    $params[':sicarchivada'] = $filtro_sicarchivada;
}

// Filtrar por fecha (NUEVO)
if (!empty($filtro_fecha_desde)) {
    $where_conditions[] = "DATE(r.Fecha_Atencion) >= :fecha_desde";
    $params[':fecha_desde'] = $filtro_fecha_desde;
}

if (!empty($filtro_fecha_hasta)) {
    $where_conditions[] = "DATE(r.Fecha_Atencion) <= :fecha_hasta";
    $params[':fecha_hasta'] = $filtro_fecha_hasta;
}

// Construir la consulta base
$query_base = "SELECT r.*, u.username 
               FROM reportes r 
               LEFT JOIN usuarios u ON r.Usuario_Actualiza = u.username";

// Consulta para contar total de registros (para paginación)
$query_count = $query_base;
if (!empty($where_conditions)) {
    $query_count .= " WHERE " . implode(" AND ", $where_conditions);
}

// Consulta para obtener datos con paginación
$query_data = $query_base;
if (!empty($where_conditions)) {
    $query_data .= " WHERE " . implode(" AND ", $where_conditions);
}
$query_data .= " ORDER BY r.Fecha_Atencion ASC LIMIT :limit OFFSET :offset";//antes DESC

// Obtener total de registros para paginación
try {
    $stmt_count = $conn->prepare($query_count);
    foreach ($params as $key => $value) {
        $stmt_count->bindValue($key, $value);
    }
    $stmt_count->execute();
    $total_registros = $stmt_count->rowCount();
    $total_paginas = ceil($total_registros / $limit);
} catch (PDOException $e) {
    $total_registros = 0;
    $total_paginas = 1;
}

// Obtener lista de reportes con filtros y paginación
try {
    $stmt = $conn->prepare($query_data);
    
    // Asignar parámetros de filtros
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    // Asignar parámetros de paginación
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $reportes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Error al cargar reportes: " . $e->getMessage();
    $reportes = [];
}

// Obtener estadísticas totales para los cards
try {
    // Total de reportes
    $query_total = "SELECT COUNT(*) as total FROM reportes";
    $stmt_total = $conn->prepare($query_total);
    $stmt_total->execute();
    $total_general = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Reportes por estado
    $query_estados = "SELECT Estado_Registro, COUNT(*) as cantidad 
                      FROM reportes 
                      GROUP BY Estado_Registro";
    $stmt_estados = $conn->prepare($query_estados);
    $stmt_estados->execute();
    $estados = $stmt_estados->fetchAll(PDO::FETCH_ASSOC);
    
    // Convertir a array asociativo para fácil acceso
    $estados_array = [];
    foreach ($estados as $estado) {
        $estados_array[$estado['Estado_Registro']] = $estado['cantidad'];
    }
    
    // Obtener lista de grupos únicos para filtro (NUEVO)
    $query_grupos = "SELECT DISTINCT Grupo FROM reportes WHERE Grupo IS NOT NULL AND Grupo != '' ORDER BY Grupo";
    $stmt_grupos = $conn->prepare($query_grupos);
    $stmt_grupos->execute();
    $grupos = $stmt_grupos->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    $total_general = 0;
    $estados_array = [];
    $grupos = [];
}

// Inicializar contadores para evitar errores de array indefinido
$contadores_estados = [
    'pendiente' => $estados_array['pendiente'] ?? 0,
    'sin_agenda' => $estados_array['sin_agenda'] ?? 0,
    'reagendado' => $estados_array['reagendado'] ?? 0,
    'revisar_sic' => $estados_array['revisar_sic'] ?? 0,
	'revisar_control' => $estados_array['revisar_control'] ?? 0,
	'revisar_ges' => $estados_array['revisar_ges'] ?? 0
];

/**
 * Función para exportar reportes a CSV
 */
function exportarReportesCSV($conn, $filtros) {
    $where_conditions = [];
    $params = [];
    
    // Aplicar mismos filtros que en la vista
    if (!empty($filtros['filtro_rut'])) {
        $where_conditions[] = "r.RUT LIKE :rut";
        $params[':rut'] = '%' . $filtros['filtro_rut'] . '%';
    }
    if (!empty($filtros['filtro_agenda'])) {
        $where_conditions[] = "r.Agenda LIKE :agenda";
        $params[':agenda'] = '%' . $filtros['filtro_agenda'] . '%';
    }
    if (!empty($filtros['filtro_grupo'])) {
        $where_conditions[] = "r.Grupo LIKE :grupo";
        $params[':grupo'] = '%' . $filtros['filtro_grupo'] . '%';
    }
    if (!empty($filtros['filtro_estado']) && $filtros['filtro_estado'] !== 'todos') {
        $where_conditions[] = "r.Estado_Registro = :estado";
        $params[':estado'] = $filtros['filtro_estado'];
    }
    if (!empty($filtros['filtro_sicarchivada']) && $filtros['filtro_sicarchivada'] !== 'todos') {
        $where_conditions[] = "r.SICarchivada = :sicarchivada";
        $params[':sicarchivada'] = $filtros['filtro_sicarchivada'];
    }
    if (!empty($filtros['filtro_fecha_desde'])) {
        $where_conditions[] = "DATE(r.Fecha_Atencion) >= :fecha_desde";
        $params[':fecha_desde'] = $filtros['filtro_fecha_desde'];
    }
    if (!empty($filtros['filtro_fecha_hasta'])) {
        $where_conditions[] = "DATE(r.Fecha_Atencion) <= :fecha_hasta";
        $params[':fecha_hasta'] = $filtros['filtro_fecha_hasta'];
    }
    
    $query = "SELECT 
                r.id,
                r.Grupo,
                r.Tipo_Reporte,
                r.Fecha_Atencion,
                r.RUT,
                r.Agenda,
                r.Profesional,
                r.Paciente,
                r.Estado_Registro,
                r.SICarchivada,
                r.Fecha_Actualiza,
                r.Usuario_Actualiza,
                u.username as Usuario_Nombre
              FROM reportes r 
              LEFT JOIN usuarios u ON r.Usuario_Actualiza = u.username";
    
    if (!empty($where_conditions)) {
        $query .= " WHERE " . implode(" AND ", $where_conditions);
    }
    
    $query .= " ORDER BY r.Fecha_Actualiza DESC";
    
    try {
        $stmt = $conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $reportes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Configurar headers para descarga CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=reportes_' . date('Y-m-d_H-i-s') . '.csv');
        
        $output = fopen('php://output', 'w');
        
        // Encabezados del CSV
        fputcsv($output, [
            'ID', 'Grupo', 'Tipo Reporte', 'Fecha Atención', 'RUT', 'Agenda', 
            'Profesional', 'Paciente', 'Estado', 'Interconsulta Archivada',
            'Fecha Actualización', 'Usuario Actualiza', 'Nombre Usuario'
        ]);
        
        // Datos
        foreach ($reportes as $reporte) {
            fputcsv($output, [
                $reporte['id'],
                $reporte['Grupo'] ?? '',
                $reporte['Tipo_Reporte'] ?? '',
                $reporte['Fecha_Atencion'] ?? '',
                $reporte['RUT'] ?? '',
                $reporte['Agenda'] ?? '',
                $reporte['Profesional'] ?? '',
                $reporte['Paciente'] ?? '',
                $reporte['Estado_Registro'] ?? '',
                $reporte['SICarchivada'] ?? 'No',
                $reporte['Fecha_Actualiza'] ?? '',
                $reporte['Usuario_Actualiza'] ?? '',
                $reporte['Usuario_Nombre'] ?? ''
            ]);
        }
        
        fclose($output);
        exit;
        
    } catch (PDOException $e) {
        die("Error al exportar: " . $e->getMessage());
    }
}

/**
 * Función para registrar cambios de estado (logging)
 */
function logCambioEstado($conn, $reporte_id, $usuario_id, $nuevo_estado, $sicarchivada) {
    try {
        $query = "INSERT INTO logs_cambios_estado 
                  (reporte_id, usuario_id, estado_anterior, estado_nuevo, sicarchivada, fecha_cambio) 
                  VALUES (:reporte_id, :usuario_id, 
                  (SELECT Estado_Registro FROM reportes WHERE id = :reporte_id2),
                  :estado_nuevo, :sicarchivada, NOW())";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':reporte_id', $reporte_id);
        $stmt->bindParam(':reporte_id2', $reporte_id);
        $stmt->bindParam(':usuario_id', $usuario_id);
        $stmt->bindParam(':estado_nuevo', $nuevo_estado);
        $stmt->bindParam(':sicarchivada', $sicarchivada);
        $stmt->execute();
    } catch (PDOException $e) {
        // Silenciar error de logging para no interrumpir flujo principal
        error_log("Error en logCambioEstado: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="img/favicon.png" type="image/png">
    <title>Gestión de Reportes | Gear-HSP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .table-actions {
            white-space: nowrap;
        }
        .badge-estado {
            padding: 0.5em 1em;
            font-size: 0.85em;
        }
        .badge-pendiente {
            background-color: #ffc107;
            color: #000;
        }
        .badge-reagendado {
            background-color: #28a745;
        }
        .badge-sin-agenda {
            background-color: #dc3545;
        }
        .badge-revisar-sic {
            background-color: #17a2b8;
        }
		.badge-revisar-control {
            background-color: #17a2b8;
        }
		        .badge-revisar-ges {
            background-color: #17a2b8;
        }
        .badge-sic-si {
            background-color: #198754;
            color: white;
        }
        .badge-sic-no {
            background-color: #6c757d;
            color: white;
        }
        .reporte-card {
            border-left: 4px solid;
        }
        .reporte-card.pendiente {
            border-left-color: #ffc107;
        }
        .reporte-card.reagendado {
            border-left-color: #28a745;
        }
        .reporte-card.sin-agenda {
            border-left-color: #dc3545;
        }
        .reporte-card.revisar-sic {
            border-left-color: #17a2b8;
        }
		.reporte-card.revisar-control {
            border-left-color: #17a2b8;
        }
		.reporte-card.revisar-ges {
            border-left-color: #17a2b8;
        }
        .filter-section {
            background-color: #f8f9fa;
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border: 1px solid #dee2e6;
        }
        .filter-header {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #495057;
        }
        .active-filters {
            background-color: #e7f1ff;
            border: 1px solid #b6d4fe;
            border-radius: 0.375rem;
            padding: 0.75rem;
            margin-bottom: 1rem;
        }
        .filter-badge {
            background-color: #0d6efd;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.875rem;
            margin-right: 0.5rem;
            margin-bottom: 0.25rem;
            display: inline-block;
        }
        .texto-no-disponible {
            color: #6c757d;
            font-style: italic;
        }
        .dashboard-card {
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .content-area {
            padding-top: 1rem;
        }
        .texto-pequeno {
            font-size: 0.85rem;
        }
        .pagination-container {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }
        .export-btn {
            margin-left: 10px;
        }
        .date-filter-group {
            display: flex;
            gap: 10px;
            align-items: end;
        }
        .date-filter-item {
            flex: 1;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php include 'includes/header.php'; ?>

    <!-- Main Content -->
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>

            <!-- Content Area -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 content-area">
                <!-- Alertas -->
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Header -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-file-alt me-2"></i>Gestión de Reportes de Ausencia
                    </h1>
					<!--<div class="mb-3">
                            <i class="fas fa-exclamation-triangle" style="color: red;"></i>
							<span style="color: red;">Debido al corte de luz, Gear no estará disponible este miercoles 25-03-2026 entre 10 y 15 horas.</span>
                    </div>-->
                    <!--<div class="btn-group">
                        <a href="gestion_reportes.php?exportar=csv<?php 
                            echo !empty($filtro_rut) ? '&filtro_rut=' . urlencode($filtro_rut) : '';
                            echo !empty($filtro_agenda) ? '&filtro_agenda=' . urlencode($filtro_agenda) : '';
                            echo !empty($filtro_grupo) ? '&filtro_grupo=' . urlencode($filtro_grupo) : '';
                            echo !empty($filtro_estado) && $filtro_estado !== 'todos' ? '&filtro_estado=' . urlencode($filtro_estado) : '';
                            echo !empty($filtro_fecha_desde) ? '&filtro_fecha_desde=' . urlencode($filtro_fecha_desde) : '';
                            echo !empty($filtro_fecha_hasta) ? '&filtro_fecha_hasta=' . urlencode($filtro_fecha_hasta) : '';
                            echo !empty($filtro_sicarchivada) && $filtro_sicarchivada !== 'todos' ? '&filtro_sicarchivada=' . urlencode($filtro_sicarchivada) : '';
                        ?>" 
                           class="btn btn-success btn-sm export-btn">
                            <i class="fas fa-file-export me-2"></i>Exportar CSV
                        </a>
                    </div>-->
                </div>
				<!-- Estadísticas con Filtros Aplicados -->
				<div class="row mb-4">
					<div class="col-md-12 mb-0">
						<div class="card dashboard-card">
							<!--<div class="card-header bg-primary text-white">
								<h5 class="card-title mb-0">
									<i class="fas fa-chart-bar me-2"></i>Estadísticas según Filtros Aplicados
									<?php if (!empty($filtro_rut) || !empty($filtro_agenda) || !empty($filtro_grupo) || (!empty($filtro_estado) && $filtro_estado !== 'todos') || !empty($filtro_fecha_desde) || !empty($filtro_fecha_hasta) || (!empty($filtro_sicarchivada) && $filtro_sicarchivada !== 'todos')): ?>
										<span class="badge bg-warning text-dark ms-2">
											<i class="fas fa-filter me-1"></i>Filtros Activos
										</span>
									<?php endif; ?>
								</h5>
							</div>-->
							<div class="card-body">
								<?php
								// Obtener estadísticas según los filtros aplicados
								try {
									$where_conditions_stats = [];
									$params_stats = [];
									
									// Aplicar mismos filtros que en la vista principal
									if (!empty($filtro_rut)) {
										$where_conditions_stats[] = "r.RUT LIKE :rut_stats";
										$params_stats[':rut_stats'] = '%' . $filtro_rut . '%';
									}
									if (!empty($filtro_agenda)) {
										$where_conditions_stats[] = "r.Agenda LIKE :agenda_stats";
										$params_stats[':agenda_stats'] = '%' . $filtro_agenda . '%';
									}
									if (!empty($filtro_grupo)) {
										$where_conditions_stats[] = "r.Grupo LIKE :grupo_stats";
										$params_stats[':grupo_stats'] = '%' . $filtro_grupo . '%';
									}
									if (!empty($filtro_estado) && $filtro_estado !== 'todos') {
										$where_conditions_stats[] = "r.Estado_Registro = :estado_stats";
										$params_stats[':estado_stats'] = $filtro_estado;
									}
									if (!empty($filtro_sicarchivada) && $filtro_sicarchivada !== 'todos') {
										$where_conditions_stats[] = "r.SICarchivada = :sicarchivada_stats";
										$params_stats[':sicarchivada_stats'] = $filtro_sicarchivada;
									}
									if (!empty($filtro_fecha_desde)) {
										$where_conditions_stats[] = "DATE(r.Fecha_Atencion) >= :fecha_desde_stats";
										$params_stats[':fecha_desde_stats'] = $filtro_fecha_desde;
									}
									if (!empty($filtro_fecha_hasta)) {
										$where_conditions_stats[] = "DATE(r.Fecha_Atencion) <= :fecha_hasta_stats";
										$params_stats[':fecha_hasta_stats'] = $filtro_fecha_hasta;
									}
									
									$where_clause = !empty($where_conditions_stats) ? "WHERE " . implode(" AND ", $where_conditions_stats) : "";
									
									// Total de reportes con filtros
									$query_total_filtrados = "SELECT COUNT(*) as total FROM reportes r " . $where_clause;
									$stmt_total_filtrados = $conn->prepare($query_total_filtrados);
									foreach ($params_stats as $key => $value) {
										$stmt_total_filtrados->bindValue($key, $value);
									}
									$stmt_total_filtrados->execute();
									$total_filtrados = $stmt_total_filtrados->fetch(PDO::FETCH_ASSOC)['total'];
									
									// Reportes por estado con filtros
									$query_estados_filtrados = "SELECT r.Estado_Registro, COUNT(*) as cantidad 
																FROM reportes r 
																" . $where_clause . " 
																GROUP BY r.Estado_Registro";
									$stmt_estados_filtrados = $conn->prepare($query_estados_filtrados);
									foreach ($params_stats as $key => $value) {
										$stmt_estados_filtrados->bindValue($key, $value);
									}
									$stmt_estados_filtrados->execute();
									$estados_filtrados = $stmt_estados_filtrados->fetchAll(PDO::FETCH_ASSOC);
									
									// Convertir a array asociativo
									$estados_filtrados_array = [];
									foreach ($estados_filtrados as $estado) {
										$estados_filtrados_array[$estado['Estado_Registro']] = $estado['cantidad'];
									}
									
									// Inicializar contadores con valores filtrados
									$contadores_filtrados = [
										'pendiente' => $estados_filtrados_array['pendiente'] ?? 0,
										'sin_agenda' => $estados_filtrados_array['sin_agenda'] ?? 0,
										'reagendado' => $estados_filtrados_array['reagendado'] ?? 0,
										'revisar_sic' => $estados_filtrados_array['revisar_sic'] ?? 0,
										'revisar_control' => $estados_filtrados_array['revisar_control'] ?? 0,
										'revisar_ges' => $estados_filtrados_array['revisar_ges'] ?? 0
									];
									
								} catch (PDOException $e) {
									$total_filtrados = 0;
									$contadores_filtrados = [
										'pendiente' => 0,
										'sin_agenda' => 0,
										'reagendado' => 0,
										'revisar_sic' => 0,
										'revisar_control' => 0,
										'revisar_ges' => 0
									];
								}
								?>
								
								<!-- Resumen de filtros -->
								<div class="row mb-0">
									<div class="col-md-12">
										<div class="alert alert-info">
											<div class="d-flex justify-content-between align-items-center flex-wrap">
												<div>
													<i class="fas fa-chart-line me-2"></i>
													<strong>Total de registros con filtros aplicados:</strong>
													<span class="badge bg-primary ms-2 fs-6"><?php echo number_format($total_filtrados); ?></span>
												</div>
												<?php if (!empty($filtro_rut) || !empty($filtro_agenda) || !empty($filtro_grupo) || (!empty($filtro_estado) && $filtro_estado !== 'todos') || !empty($filtro_fecha_desde) || !empty($filtro_fecha_hasta) || (!empty($filtro_sicarchivada) && $filtro_sicarchivada !== 'todos')): ?>
													<div>
														<small class="text-muted">
															<i class="fas fa-info-circle me-1"></i>
															Comparado con el total general: <?php echo number_format($total_general); ?> registros
														</small>
													</div>
												<?php endif; ?>
											</div>
										</div>
									</div>
								</div>
								
								<!-- Cards de indicadores con filtros -->
								<div class="row">
									<div class="col-md-2">
										<div class="card text-center border-warning">
											<div class="card-body">
												<h3 class="text-warning"><?php echo number_format($contadores_filtrados['pendiente']); ?></h3>
												<p class="mb-0">Pendientes</p>
												<small class="text-muted">
													<?php 
													$porcentaje = $total_filtrados > 0 ? round(($contadores_filtrados['pendiente'] / $total_filtrados) * 100, 1) : 0;
													echo $porcentaje . '% del total filtrado';
													?>
												</small>
											</div>
										</div>
									</div>
									<div class="col-md-2">
										<div class="card text-center border-info">
											<div class="card-body">
												<h3 class="text-info"><?php echo number_format($contadores_filtrados['reagendado']); ?></h3>
												<p class="mb-0">Reagendados</p>
												<small class="text-muted">
													<?php 
													$porcentaje = $total_filtrados > 0 ? round(($contadores_filtrados['reagendado'] / $total_filtrados) * 100, 1) : 0;
													echo $porcentaje . '% del total filtrado';
													?>
												</small>
											</div>
										</div>
									</div>
									<div class="col-md-2">
										<div class="card text-center border-success">
											<div class="card-body">
												<h3 class="text-success"><?php echo number_format($contadores_filtrados['sin_agenda']); ?></h3>
												<p class="mb-0">Sin Agenda</p>
												<small class="text-muted">
													<?php 
													$porcentaje = $total_filtrados > 0 ? round(($contadores_filtrados['sin_agenda'] / $total_filtrados) * 100, 1) : 0;
													echo $porcentaje . '% del total filtrado';
													?>
												</small>
											</div>
										</div>
									</div>
									<div class="col-md-2">
										<div class="card text-center border-danger">
											<div class="card-body">
												<h3 class="text-danger"><?php echo number_format($contadores_filtrados['revisar_sic']); ?></h3>
												<p class="mb-0">Revisar SIC</p>
												<small class="text-muted">
													<?php 
													$porcentaje = $total_filtrados > 0 ? round(($contadores_filtrados['revisar_sic'] / $total_filtrados) * 100, 1) : 0;
													echo $porcentaje . '% del total filtrado';
													?>
												</small>
											</div>
										</div>
									</div>
									<div class="col-md-2">
										<div class="card text-center border-danger">
											<div class="card-body">
												<h3 class="text-danger"><?php echo number_format($contadores_filtrados['revisar_control']); ?></h3>
												<p class="mb-0">Revisar Control</p>
												<small class="text-muted">
													<?php 
													$porcentaje = $total_filtrados > 0 ? round(($contadores_filtrados['revisar_control'] / $total_filtrados) * 100, 1) : 0;
													echo $porcentaje . '% del total filtrado';
													?>
												</small>
											</div>
										</div>
									</div>
									<div class="col-md-2">
										<div class="card text-center border-danger">
											<div class="card-body">
												<h3 class="text-danger"><?php echo number_format($contadores_filtrados['revisar_ges']); ?></h3>
												<p class="mb-0">Revisar GES</p>
												<small class="text-muted">
													<?php 
													$porcentaje = $total_filtrados > 0 ? round(($contadores_filtrados['revisar_ges'] / $total_filtrados) * 100, 1) : 0;
													echo $porcentaje . '% del total filtrado';
													?>
												</small>
											</div>
										</div>
									</div>
								</div>
								
								<!-- Mensaje cuando no hay resultados con filtros -->
								<?php if ($total_filtrados == 0 && (!empty($filtro_rut) || !empty($filtro_agenda) || !empty($filtro_grupo) || (!empty($filtro_estado) && $filtro_estado !== 'todos') || !empty($filtro_fecha_desde) || !empty($filtro_fecha_hasta) || (!empty($filtro_sicarchivada) && $filtro_sicarchivada !== 'todos'))): ?>
									<div class="alert alert-warning mt-3">
										<i class="fas fa-exclamation-triangle me-2"></i>
										No se encontraron registros con los filtros aplicados.
										<a href="gestion_reportes.php" class="alert-link">Limpiar filtros</a> para ver todos los registros.
									</div>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</div>
                <!-- Filtros -->
                <div class="filter-section">
                    <h6 class="filter-header">
                        <i class="fas fa-search me-2"></i>Buscar y Filtrar Reportes
                    </h6>
                    <form method="GET" class="row g-3" id="filtroForm">
                        <div class="col-md-2">
                            <label for="filtro_rut" class="form-label">Buscar por RUT</label>
                            <input type="text" class="form-control" id="filtro_rut" name="filtro_rut" 
                                   value="<?php echo htmlspecialchars($filtro_rut); ?>" 
                                   placeholder="Ej: 13424633-2">
                        </div>
                        <div class="col-md-3">
                            <label for="filtro_agenda" class="form-label">Buscar por Agenda</label>
                            <input type="text" class="form-control" id="filtro_agenda" name="filtro_agenda" 
                                   value="<?php echo htmlspecialchars($filtro_agenda); ?>" 
                                   placeholder="Ej: POLI-TRAUMATOLOGIA">
                        </div>
                        <div class="col-md-3">
                            <label for="filtro_grupo" class="form-label">Filtrar por Grupo</label>
                            <select class="form-select" id="filtro_grupo" name="filtro_grupo">
                                <option value="">Todos los grupos</option>
                                <?php foreach ($grupos as $grupo): ?>
                                    <option value="<?php echo htmlspecialchars($grupo); ?>" 
                                        <?php echo $filtro_grupo === $grupo ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($grupo); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="filtro_estado" class="form-label">Filtrar por Estado</label>
                            <select class="form-select" id="filtro_estado" name="filtro_estado">
                                <option value="todos">Todos los estados</option>
                                <option value="pendiente" <?php echo $filtro_estado === 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
								<option value="reagendado" <?php echo $filtro_estado === 'reagendado' ? 'selected' : ''; ?>>Reagendado</option>
                                <option value="sin_agenda" <?php echo $filtro_estado === 'sin_agenda' ? 'selected' : ''; ?>>Sin Agenda</option>
                                <option value="revisar_sic" <?php echo $filtro_estado === 'revisar_sic' ? 'selected' : ''; ?>>Revisar SIC</option>
                                <option value="revisar_control" <?php echo $filtro_estado === 'revisar_control' ? 'selected' : ''; ?>>Revisar Control</option>
								<option value="revisar_ges" <?php echo $filtro_estado === 'revisar_ges' ? 'selected' : ''; ?>>Revisar GES</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="filtro_sicarchivada" class="form-label">Interconsulta Archivada</label>
                            <select class="form-select" id="filtro_sicarchivada" name="filtro_sicarchivada">
                                <option value="todos">Todas</option>
                                <option value="Si" <?php echo $filtro_sicarchivada === 'Si' ? 'selected' : ''; ?>>Sí</option>
                                <option value="No" <?php echo $filtro_sicarchivada === 'No' ? 'selected' : ''; ?>>No</option>
                            </select>
                        </div>
                        
                      
                        <div class="col-md-12 d-flex justify-content-between mt-3">
                            <div class="btn-group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i>Aplicar Filtros
                                </button>
                                <!--<button type="button" class="btn btn-outline-secondary" onclick="limpiarFiltros()">
                                    <i class="fas fa-broom me-2"></i>Limpiar Filtros
                                </button>-->
                            </div>
                            <a href="gestion_reportes.php?exportar=csv<?php 
                                echo !empty($filtro_rut) ? '&filtro_rut=' . urlencode($filtro_rut) : '';
                                echo !empty($filtro_agenda) ? '&filtro_agenda=' . urlencode($filtro_agenda) : '';
                                echo !empty($filtro_grupo) ? '&filtro_grupo=' . urlencode($filtro_grupo) : '';
                                echo !empty($filtro_estado) && $filtro_estado !== 'todos' ? '&filtro_estado=' . urlencode($filtro_estado) : '';
                                echo !empty($filtro_fecha_desde) ? '&filtro_fecha_desde=' . urlencode($filtro_fecha_desde) : '';
                                echo !empty($filtro_fecha_hasta) ? '&filtro_fecha_hasta=' . urlencode($filtro_fecha_hasta) : '';
                                echo !empty($filtro_sicarchivada) && $filtro_sicarchivada !== 'todos' ? '&filtro_sicarchivada=' . urlencode($filtro_sicarchivada) : '';
                            ?>" 
                               class="btn btn-success">
                                <i class="fas fa-file-export me-2"></i>Exportar CSV
                            </a>
                        </div>
                    </form>
                </div>

              <!-- Mostrar filtros activos -->
                <?php if (!empty($filtro_rut) || !empty($filtro_agenda) || !empty($filtro_grupo) || (!empty($filtro_estado) && $filtro_estado !== 'todos') || !empty($filtro_fecha_desde) || !empty($filtro_fecha_hasta) || (!empty($filtro_sicarchivada) && $filtro_sicarchivada !== 'todos')): ?>
                <div class="active-filters">
                    <h6><i class="fas fa-filter me-2"></i>Filtros Activos:</h6>
                    <?php if (!empty($filtro_rut)): ?>
                        <span class="filter-badge">
                            <i class="fas fa-id-card me-1"></i>RUT: <?php echo htmlspecialchars($filtro_rut); ?>
                        </span>
                    <?php endif; ?>
                    
                    <?php if (!empty($filtro_agenda)): ?>
                        <span class="filter-badge">
                            <i class="fas fa-calendar-alt me-1"></i>Agenda: <?php echo htmlspecialchars($filtro_agenda); ?>
                        </span>
                    <?php endif; ?>
                    
                    <?php if (!empty($filtro_grupo)): ?>
                        <span class="filter-badge">
                            <i class="fas fa-users me-1"></i>Grupo: <?php echo htmlspecialchars($filtro_grupo); ?>
                        </span>
                    <?php endif; ?>
                    
                    <?php if (!empty($filtro_estado) && $filtro_estado !== 'todos'): ?>
                        <span class="filter-badge">
                            <i class="fas fa-circle me-1"></i>Estado: <?php echo ucfirst(str_replace('_', ' ', $filtro_estado)); ?>
                        </span>
                    <?php endif; ?>
                    
                    <?php if (!empty($filtro_sicarchivada) && $filtro_sicarchivada !== 'todos'): ?>
                        <span class="filter-badge">
                            <i class="fas fa-archive me-1"></i>Interconsulta: <?php echo htmlspecialchars($filtro_sicarchivada); ?>
                        </span>
                    <?php endif; ?>
                    
                    <?php if (!empty($filtro_fecha_desde) || !empty($filtro_fecha_hasta)): ?>
                        <span class="filter-badge">
                            <i class="fas fa-calendar me-1"></i>Fecha: 
                            <?php echo !empty($filtro_fecha_desde) ? htmlspecialchars($filtro_fecha_desde) : 'Inicio'; ?> 
                            - 
                            <?php echo !empty($filtro_fecha_hasta) ? htmlspecialchars($filtro_fecha_hasta) : 'Fin'; ?>
                        </span>
                    <?php endif; ?>
                    
                    <a href="gestion_reportes.php" class="btn btn-sm btn-outline-danger">
                        <i class="fas fa-times me-1"></i>Limpiar todos
                    </a>
                </div>
                <?php endif; ?>
              
                <!-- Tabla de Reportes -->
                <div class="card dashboard-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list me-2"></i>Lista de Reportes
                            <?php if (!empty($filtro_rut) || !empty($filtro_agenda) || !empty($filtro_grupo) || (!empty($filtro_estado) && $filtro_estado !== 'todos') || !empty($filtro_fecha_desde) || !empty($filtro_fecha_hasta) || (!empty($filtro_sicarchivada) && $filtro_sicarchivada !== 'todos')): ?>
                                <span class="badge bg-primary ms-2">Filtrados</span>
                            <?php endif; ?>
                        </h5>
                        <div class="text-muted">
                            <small>
                                <i class="fas fa-database me-1"></i>
                                Mostrando <?php echo count($reportes); ?> de <?php echo $total_registros; ?> registro(s)
                                <?php if ($total_paginas > 1): ?>
                                    | Página <?php echo $page; ?> de <?php echo $total_paginas; ?>
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($reportes)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-file-alt fa-4x text-muted mb-3"></i>
                                <h4 class="text-muted">
                                    <?php if (!empty($filtro_rut) || !empty($filtro_agenda) || !empty($filtro_grupo) || (!empty($filtro_estado) && $filtro_estado !== 'todos') || !empty($filtro_fecha_desde) || !empty($filtro_fecha_hasta) || (!empty($filtro_sicarchivada) && $filtro_sicarchivada !== 'todos')) : ?>
                                        No se encontraron reportes con los filtros aplicados
                                    <?php else: ?>
                                        No hay reportes registrados
                                    <?php endif; ?>
                                </h4>
                                <p class="text-muted mb-4">
                                    <?php if (!empty($filtro_rut) || !empty($filtro_agenda) || !empty($filtro_grupo) || (!empty($filtro_estado) && $filtro_estado !== 'todos') || !empty($filtro_fecha_desde) || !empty($filtro_fecha_hasta) || (!empty($filtro_sicarchivada) && $filtro_sicarchivada !== 'todos')): ?>
                                        Intenta con otros criterios de búsqueda
                                    <?php else: ?>
                                        Los reportes se importan automáticamente
                                    <?php endif; ?>
                                </p>
                                <?php if (!empty($filtro_rut) || !empty($filtro_agenda) || !empty($filtro_grupo) || (!empty($filtro_estado) && $filtro_estado !== 'todos') || !empty($filtro_fecha_desde) || !empty($filtro_fecha_hasta) || (!empty($filtro_sicarchivada) && $filtro_sicarchivada !== 'todos')): ?>
                                    <a href="gestion_reportes.php" class="btn btn-outline-primary">
                                        <i class="fas fa-times me-2"></i>Ver todos los reportes
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-striped">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Grupo</th>
                                            <th>Tipo Reporte</th>
                                            <th>Fecha Atención</th>
                                            <th>RUT</th>
                                            <th>Agenda</th>
                                            <th>Profesional</th>
                                            <th>Paciente</th>
                                            <th>Estado</th>
                                            <th>SIC Archivada</th>
                                            <th>Última Actualización</th>
                                            <th>Actualizado por</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reportes as $reporte): 
                                            // Extraer valores con verificación segura
                                            $id = $reporte['id'] ?? 0;
                                            $grupo = $reporte['Grupo'] ?? 'No disponible';
                                            $tipo_reporte = $reporte['Tipo_Reporte'] ?? 'No disponible';
                                            $fecha_atencion = $reporte['Fecha_Atencion'] ?? 'No disponible';
                                            $rut = $reporte['RUT'] ?? 'No disponible';
                                            $agenda = $reporte['Agenda'] ?? 'No disponible';
                                            $profesional = $reporte['Profesional'] ?? 'No disponible';
                                            $paciente = $reporte['Paciente'] ?? 'No disponible';
                                            $estado_registro = $reporte['Estado_Registro'] ?? 'pendiente';
                                            $sicarchivada = $reporte['SICarchivada'] ?? 'No';
                                            $fecha_actualiza = $reporte['Fecha_Actualiza'] ?? date('Y-m-d H:i:s');
                                            $usuario_actualiza = $reporte['Usuario_Actualiza'] ?? 'sistema';
                                            $username = $reporte['username'] ?? $usuario_actualiza;
                                            
                                            // Determinar clase CSS según estado
                                            $estado_class = '';
                                            switch(strtolower($estado_registro)) {
                                                case 'pendiente':
                                                    $estado_class = 'pendiente';
                                                    break;
                                                case 'reagendado':
                                                case 'reagendado':
                                                    $estado_class = 'reagendado';
                                                    break;
                                                case 'sin-agenda':
                                                    $estado_class = 'sin-agenda';
                                                    break;
                                                case 'revisar-sic':
                                                    $estado_class = 'revisar-sic';
                                                    break;
												case 'revisar-control':
                                                    $estado_class = 'revisar-control';
                                                    break;
												case 'revisar-ges':
                                                    $estado_class = 'revisar-ges';
                                                    break;
                                                default:
                                                    $estado_class = 'pendiente';
                                            }
                                        ?>
                                        <tr class="reporte-card <?php echo $estado_class; ?>">
                                            <td><?php echo htmlspecialchars($id); ?></td>
                                            <td>
                                                <?php if ($grupo === 'No disponible' || empty($grupo)): ?>
                                                    <span class="texto-no-disponible">N/A</span>
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($grupo); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($tipo_reporte === 'No disponible' || empty($tipo_reporte)): ?>
                                                    <span class="texto-no-disponible">N/A</span>
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($tipo_reporte); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="texto-pequeno">
                                                <?php if ($fecha_atencion === 'No disponible' || empty($fecha_atencion)): ?>
                                                    <span class="texto-no-disponible">No disponible</span>
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars(date('d/m/Y', strtotime($fecha_atencion))); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($rut === 'No disponible' || empty($rut)): ?>
                                                    <span class="texto-no-disponible">No disponible</span>
                                                <?php else: ?>
                                                    <span><?php echo htmlspecialchars($rut); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($agenda === 'No disponible' || empty($agenda)): ?>
                                                    <span class="texto-no-disponible">No disponible</span>
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($agenda); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($profesional === 'No disponible' || empty($profesional)): ?>
                                                    <span class="texto-no-disponible">N/A</span>
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($profesional); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="texto-pequeno">
                                                <?php if ($paciente === 'No disponible' || empty($paciente)): ?>
                                                    <span class="texto-no-disponible">No disponible</span>
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($paciente); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-estado badge-<?php echo $estado_class; ?>">
                                                    <?php 
                                                    $estado_texto = ucfirst(str_replace('_', ' ', $estado_registro));
                                                    echo htmlspecialchars($estado_texto); 
                                                    ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $sicarchivada === 'Si' ? 'badge-sic-si' : 'badge-sic-no'; ?>">
                                                    <?php echo htmlspecialchars($sicarchivada); ?>
                                                </span>
                                            </td>
                                            <td class="texto-pequeno">
                                                <?php 
                                                if (!empty($fecha_actualiza) && $fecha_actualiza !== '0000-00-00 00:00:00') {
                                                    try {
                                                        echo date('d/m/Y H:i', strtotime($fecha_actualiza));
                                                    } catch (Exception $e) {
                                                        echo '<span class="texto-no-disponible">' . htmlspecialchars($fecha_actualiza) . '</span>';
                                                    }
                                                } else {
                                                    echo '<span class="texto-no-disponible">No disponible</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php if (empty($username) || $username === 'sistema'): ?>
                                                    <span class="texto-no-disponible">Sistema</span>
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($username); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="table-actions">
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary" 
                                                            onclick="editarReporte(<?php echo $id; ?>, '<?php echo htmlspecialchars($rut, ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($agenda, ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($estado_registro, ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($sicarchivada, ENT_QUOTES, 'UTF-8'); ?>')"
                                                            title="Cambiar estado e interconsulta"
                                                            aria-label="Editar reporte <?php echo $id; ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Paginación -->
                            <?php if ($total_paginas > 1): ?>
                            <div class="pagination-container">
                                <nav aria-label="Paginación de reportes">
                                    <ul class="pagination">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page-1; ?><?php 
                                                    echo !empty($filtro_rut) ? '&filtro_rut=' . urlencode($filtro_rut) : '';
                                                    echo !empty($filtro_agenda) ? '&filtro_agenda=' . urlencode($filtro_agenda) : '';
                                                    echo !empty($filtro_grupo) ? '&filtro_grupo=' . urlencode($filtro_grupo) : '';
                                                    echo !empty($filtro_estado) && $filtro_estado !== 'todos' ? '&filtro_estado=' . urlencode($filtro_estado) : '';
                                                    echo !empty($filtro_fecha_desde) ? '&filtro_fecha_desde=' . urlencode($filtro_fecha_desde) : '';
                                                    echo !empty($filtro_fecha_hasta) ? '&filtro_fecha_hasta=' . urlencode($filtro_fecha_hasta) : '';
                                                    echo !empty($filtro_sicarchivada) && $filtro_sicarchivada !== 'todos' ? '&filtro_sicarchivada=' . urlencode($filtro_sicarchivada) : '';
                                                ?>">
                                                    <i class="fas fa-chevron-left"></i> Anterior
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $page - 2); $i <= min($total_paginas, $page + 2); $i++): ?>
                                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?><?php 
                                                    echo !empty($filtro_rut) ? '&filtro_rut=' . urlencode($filtro_rut) : '';
                                                    echo !empty($filtro_agenda) ? '&filtro_agenda=' . urlencode($filtro_agenda) : '';
                                                    echo !empty($filtro_grupo) ? '&filtro_grupo=' . urlencode($filtro_grupo) : '';
                                                    echo !empty($filtro_estado) && $filtro_estado !== 'todos' ? '&filtro_estado=' . urlencode($filtro_estado) : '';
                                                    echo !empty($filtro_fecha_desde) ? '&filtro_fecha_desde=' . urlencode($filtro_fecha_desde) : '';
                                                    echo !empty($filtro_fecha_hasta) ? '&filtro_fecha_hasta=' . urlencode($filtro_fecha_hasta) : '';
                                                    echo !empty($filtro_sicarchivada) && $filtro_sicarchivada !== 'todos' ? '&filtro_sicarchivada=' . urlencode($filtro_sicarchivada) : '';
                                                ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_paginas): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page+1; ?><?php 
                                                    echo !empty($filtro_rut) ? '&filtro_rut=' . urlencode($filtro_rut) : '';
                                                    echo !empty($filtro_agenda) ? '&filtro_agenda=' . urlencode($filtro_agenda) : '';
                                                    echo !empty($filtro_grupo) ? '&filtro_grupo=' . urlencode($filtro_grupo) : '';
                                                    echo !empty($filtro_estado) && $filtro_estado !== 'todos' ? '&filtro_estado=' . urlencode($filtro_estado) : '';
                                                    echo !empty($filtro_fecha_desde) ? '&filtro_fecha_desde=' . urlencode($filtro_fecha_desde) : '';
                                                    echo !empty($filtro_fecha_hasta) ? '&filtro_fecha_hasta=' . urlencode($filtro_fecha_hasta) : '';
                                                    echo !empty($filtro_sicarchivada) && $filtro_sicarchivada !== 'todos' ? '&filtro_sicarchivada=' . urlencode($filtro_sicarchivada) : '';
                                                ?>">
                                                    Siguiente <i class="fas fa-chevron-right"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal Editar Reporte -->
    <div class="modal fade" id="editarReporteModal" tabindex="-1" aria-labelledby="editarReporteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" id="editar_reporte_id" name="reporte_id">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editarReporteModalLabel">
                            <i class="fas fa-edit me-2"></i>Cambiar Estado del Reporte
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">RUT</label>
                            <input type="text" class="form-control" id="editar_rut" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Agenda</label>
                            <input type="text" class="form-control" id="editar_agenda" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="editar_estado_registro" class="form-label">Nuevo Estado *</label>
                            <select class="form-select" id="editar_estado_registro" name="estado_registro" required>
                                <option value="pendiente">Pendiente</option>
								<option value="reagendado">Reagendado</option>
                                <option value="sin_agenda">Sin Agenda</option>
                                <option value="revisar_sic">Revisar SIC</option>
								<option value="revisar_control">Revisar Control</option>
								<option value="revisar_ges">Revisar GES</option>
								<option value="fallecido">Fallecido</option>
								<option value="rechazo">Rechazo</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="editar_sicarchivada" class="form-label">Interconsulta Archivada *</label>
                            <select class="form-select" id="editar_sicarchivada" name="sicarchivada" required>
                                <option value="Si">Sí</option>
                                <option value="No">No</option>
                            </select>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Al cambiar el estado, se registrará automáticamente tu usuario y la fecha de actualización.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="editar_reporte" class="btn btn-primary">Actualizar Estado</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Función para editar reporte
        function editarReporte(id, rut, agenda, estadoActual, sicarchivada) {
            document.getElementById('editar_reporte_id').value = id;
            document.getElementById('editar_rut').value = rut;
            document.getElementById('editar_agenda').value = agenda;
            document.getElementById('editar_estado_registro').value = estadoActual;
            document.getElementById('editar_sicarchivada').value = sicarchivada;
            
            const modal = new bootstrap.Modal(document.getElementById('editarReporteModal'));
            modal.show();
        }

        // Función para limpiar filtros
        function limpiarFiltros() {
            document.getElementById('filtro_rut').value = '';
            document.getElementById('filtro_agenda').value = '';
            document.getElementById('filtro_grupo').value = '';
            document.getElementById('filtro_estado').value = 'todos';
            document.getElementById('filtro_sicarchivada').value = 'todos';
            document.getElementById('filtro_fecha_desde').value = '';
            document.getElementById('filtro_fecha_hasta').value = '';
            document.getElementById('filtroForm').submit();
        }

        // Función para limpiar solo fechas
        function limpiarFechas() {
            document.getElementById('filtro_fecha_desde').value = '';
            document.getElementById('filtro_fecha_hasta').value = '';
        }

        // Configurar placeholders y mejoras UX
        document.addEventListener('DOMContentLoaded', function() {
            // Guardar filtros en localStorage para persistencia entre páginas
            const filtroForm = document.getElementById('filtroForm');
            if (filtroForm) {
                // Cargar filtros guardados
                const savedFilters = JSON.parse(localStorage.getItem('reportes_filters') || '{}');
                if (savedFilters.filtro_rut) document.getElementById('filtro_rut').value = savedFilters.filtro_rut;
                if (savedFilters.filtro_agenda) document.getElementById('filtro_agenda').value = savedFilters.filtro_agenda;
                if (savedFilters.filtro_grupo) document.getElementById('filtro_grupo').value = savedFilters.filtro_grupo;
                if (savedFilters.filtro_estado) document.getElementById('filtro_estado').value = savedFilters.filtro_estado;
                if (savedFilters.filtro_sicarchivada) document.getElementById('filtro_sicarchivada').value = savedFilters.filtro_sicarchivada;

                // Guardar filtros al enviar el formulario
                filtroForm.addEventListener('submit', function() {
                    const filters = {
                        filtro_rut: document.getElementById('filtro_rut').value,
                        filtro_agenda: document.getElementById('filtro_agenda').value,
                        filtro_grupo: document.getElementById('filtro_grupo').value,
                        filtro_estado: document.getElementById('filtro_estado').value,
                        filtro_sicarchivada: document.getElementById('filtro_sicarchivada').value,
                        filtro_fecha_desde: document.getElementById('filtro_fecha_desde').value,
                        filtro_fecha_hasta: document.getElementById('filtro_fecha_hasta').value
                    };
                    localStorage.setItem('reportes_filters', JSON.stringify(filters));
                });
            }

            // Auto-enfocar el primer campo de filtro si no hay filtros activos
            <?php if (empty($filtro_rut) && empty($filtro_agenda) && empty($filtro_grupo) && (empty($filtro_estado) || $filtro_estado === 'todos') && (empty($filtro_sicarchivada) || $filtro_sicarchivada === 'todos') && empty($filtro_fecha_desde) && empty($filtro_fecha_hasta)): ?>
                setTimeout(function() {
                    document.getElementById('filtro_rut').focus();
                }, 100);
            <?php endif; ?>
            
            // Configurar fecha máxima como hoy para filtros de fecha
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('filtro_fecha_desde').max = today;
            document.getElementById('filtro_fecha_hasta').max = today;
            
            // Validar que fecha desde <= fecha hasta
            const fechaDesde = document.getElementById('filtro_fecha_desde');
            const fechaHasta = document.getElementById('filtro_fecha_hasta');
            
            fechaDesde.addEventListener('change', function() {
                fechaHasta.min = this.value;
                if (fechaHasta.value && fechaHasta.value < this.value) {
                    fechaHasta.value = this.value;
                }
            });
            
            fechaHasta.addEventListener('change', function() {
                if (fechaDesde.value && this.value < fechaDesde.value) {
                    this.value = fechaDesde.value;
                }
            });
        });

        // Permitir usar Enter para aplicar filtros
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.target.closest('.modal')) {
                // Solo aplicar si estamos en un campo de filtro
                if (e.target.matches('#filtro_rut, #filtro_agenda, #filtro_grupo, #filtro_estado, #filtro_sicarchivada, #filtro_fecha_desde, #filtro_fecha_hasta')) {
                    e.preventDefault();
                    document.getElementById('filtroForm').submit();
                }
            }
        });
        
        // Confirmar antes de cambiar a estado cancelado
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('#editarReporteModal form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const estado = document.getElementById('editar_estado_registro').value;
                    const sicarchivada = document.getElementById('editar_sicarchivada').value;
                    
                    if (estado === 'cancelado') {
                        if (!confirm('¿Estás seguro de cambiar el estado a "Cancelado"? Esta acción no se puede deshacer fácilmente.')) {
                            e.preventDefault();
                            return false;
                        }
                    }
                    
                    // Validar interconsulta archivada para estados completados
                    if (estado === 'completado' && sicarchivada === 'No') {
                        if (!confirm('El reporte se marcará como "Completado" pero la interconsulta NO está archivada. ¿Deseas continuar?')) {
                            e.preventDefault();
                            return false;
                        }
                    }
                    
                    return true;
                });
            }
        });
    </script>
</body>
<footer class="text-center py-4">Patricio Corrotea | Gestión de la Demanda</footer>
</html>