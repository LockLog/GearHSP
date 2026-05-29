<?php
require_once 'includes/auth.php';
$auth = new Auth();

if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    header("Location: index.php");
    exit;
}

require_once 'config/database.php';
$database = new Database();
$conn = $database->getConnection();

// Procesar cambio de estado
if ($_POST && isset($_POST['cambiar_estado'])) {
    $ausencia_id = $_POST['ausencia_id'];
    $nuevo_estado = $_POST['nuevo_estado'];
    
    if ($auth->cambiarEstadoAusencia($ausencia_id, $nuevo_estado, $_SESSION['username'])) {
        $success = "Estado de la ausencia actualizado correctamente a: " . $auth->getNombreEstado($nuevo_estado);
    } else {
        $error = "Error al actualizar el estado de la ausencia";
    }
}

// Procesar verificación automática de reportes
if (isset($_GET['verificar_reportes'])) {
    $resultado = verificarYActualizarReportes($conn, $_SESSION['username']);
    
    if ($resultado['actualizados'] > 0) {
        $success = "✅ Se actualizaron " . $resultado['actualizados'] . " ausencias a estado 'Respaldo'";
        if ($resultado['no_encontrados'] > 0) {
            $success .= " (" . $resultado['no_encontrados'] . " no tenían reporte)";
        }
    } else {
        $info = "ℹ️ No se encontraron ausencias para actualizar automáticamente";
    }
}

// Obtener filtros de la URL
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$filtro_busqueda = isset($_GET['busqueda']) ? $_GET['busqueda'] : '';
$filtro_fecha = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';

// Configuración de paginación
$registros_por_pagina = isset($_GET['registros']) ? (int)$_GET['registros'] : 50;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Obtener ausencias filtradas y paginadas
$ausencias = $auth->getAusenciasFiltradasPaginadas($filtro_estado, $filtro_busqueda, $filtro_fecha, $offset, $registros_por_pagina);
$total_ausencias = $auth->getTotalAusenciasFiltradas($filtro_estado, $filtro_busqueda, $filtro_fecha);
$total_paginas = ceil($total_ausencias / $registros_por_pagina);

// Obtener todas las ausencias (para estadísticas)
$todasAusencias = $auth->getAllAusencias();

// Contar ausencias con estado 'enviadocc' que tienen reporte
$enviadocc_con_reporte = 0;
foreach ($todasAusencias as $ausencia) {
    if ($ausencia['estado'] === 'enviadocc' && !empty($ausencia['reporte'])) {
        $enviadocc_con_reporte++;
    }
}

// Función para verificar y actualizar reportes
function verificarYActualizarReportes($conn, $usuario) {
    $resultado = [
        'actualizados' => 0,
        'no_encontrados' => 0,
        'errores' => 0
    ];
    
    try {
        $sql = "SELECT a.id, a.reporte, a.usuario_modificacion 
                FROM ausencias a 
                WHERE a.estado = 'enviadocc' 
                AND a.reporte IS NOT NULL 
                AND a.reporte != ''";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $ausencias = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($ausencias as $ausencia) {
            $reporte = trim($ausencia['reporte']);
            
            $sql_check = "SELECT COUNT(*) as existe 
                         FROM reportes 
                         WHERE Num_Reporte = :reporte";
            
            $stmt_check = $conn->prepare($sql_check);
            $stmt_check->execute([':reporte' => $reporte]);
            $existe = $stmt_check->fetchColumn();
            
            if ($existe > 0) {
                $sql_update = "UPDATE ausencias 
                              SET estado = 'respaldo', 
                                  usuario_modificacion = :usuario,
                                  timestamp_modificacion = NOW()
                              WHERE id = :id 
                              AND estado = 'enviadocc'";
                
                $stmt_update = $conn->prepare($sql_update);
                $stmt_update->execute([
                    ':usuario' => $usuario,
                    ':id' => $ausencia['id']
                ]);
                
                if ($stmt_update->rowCount() > 0) {
                    $resultado['actualizados']++;
                    
                    try {
                        $sql_log = "INSERT INTO logs_auditoria 
                                   (usuario, accion, tabla, registro_id, detalles, timestamp) 
                                   VALUES (:usuario, 'ACTUALIZACION_AUTO', 'ausencias', :id, 
                                          'Cambio automático de enviadocc a respaldo - Reporte: ' || :reporte, NOW())";
                        $stmt_log = $conn->prepare($sql_log);
                        $stmt_log->execute([
                            ':usuario' => $usuario,
                            ':id' => $ausencia['id'],
                            ':reporte' => $reporte
                        ]);
                    } catch (Exception $e) {
                        // Silenciar error si no existe tabla de logs
                    }
                } else {
                    $resultado['errores']++;
                }
            } else {
                $resultado['no_encontrados']++;
            }
        }
        
        return $resultado;
        
    } catch (PDOException $e) {
        error_log("Error en verificación automática: " . $e->getMessage());
        return $resultado;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="img/favicon.png" type="image/png">
    <title>Gestión de Ausencias | Gear-HSP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">

    <style>
        .badge-estado {
            font-size: 0.8em;
            padding: 0.2em 0.2em;
        }
        .dropdown-estado {
            min-width: 200px;
        }
        .estado-pendiente { background-color: #ffc107; color: #000; }
        .estado-requierebox { background-color: #ffc107; color: #000; }
        .estado-bloqueado { background-color: #6c757d; color: #fff; }
        .estado-boxdisponible { background-color: #ffc107; color: #000; }
        .estado-boxnodisponible { background-color: #ffc107; color: #000; }       
        .estado-enviadocc { background-color: #0dcaf0; color: #000; }
        .estado-notificado { background-color: #0d6efd; color: #fff; }
        .estado-respaldo { background-color: #198754; color: #fff; }
        .estado-anular { background-color: #dc3545; color: #fff; }
        .estado-anulado { background-color: #dc3545; color: #fff; }
        .auto-update-card {
            border-left: 4px solid #198754;
            background-color: #f8f9fa;
        }
        .btn-auto-update {
            position: relative;
            overflow: hidden;
        }
        .btn-auto-update .spinner-border {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            display: none;
        }
        .btn-auto-update.processing {
            color: transparent !important;
        }
        .btn-auto-update.processing .spinner-border {
            display: block;
        }
        
        /* Estilos de paginación */
        .pagination-container {
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .pagination {
            margin-bottom: 0;
        }
        .page-item.active .page-link {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        .page-link {
            color: #0d6efd;
            cursor: pointer;
        }
        .page-link.disabled {
            cursor: not-allowed;
            opacity: 0.5;
        }
        .page-info {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .registros-select {
            width: auto;
            display: inline-block;
            margin-left: 10px;
        }
        
        /* Resaltado de filas */
        .table-responsive tbody tr {
            transition: background-color 0.2s;
        }
        .table-responsive tbody tr:hover {
            background-color: rgba(13, 110, 253, 0.05);
        }
        
        /* Indicador de filtros activos */
        .filtros-activos {
            margin-top: 10px;
            padding: 8px;
            background-color: #e7f3ff;
            border-radius: 5px;
            font-size: 0.9rem;
        }
        
        .badge-filtro {
            background-color: #0d6efd;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            margin-right: 5px;
            display: inline-block;
            font-size: 0.8rem;
        }
        
        .btn-limpiar-filtros {
            font-size: 0.8rem;
            padding: 2px 8px;
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
                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($info)): ?>
                    <div class="alert alert-info alert-dismissible fade show mt-3" role="alert">
                        <i class="fas fa-info-circle me-2"></i><?php echo $info; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Header -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-calendar-times me-2"></i>Gestión de Ausencias
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-success btn-auto-update" id="btnAutoUpdate" 
                                    <?php echo $enviadocc_con_reporte == 0 ? 'disabled' : ''; ?>
                                    title="Verificar reportes y actualizar estados automáticamente" hidden="true">
                                <i class="fas fa-robot me-1"></i>Verificar Reportes
                                <?php if ($enviadocc_con_reporte > 0): ?>
                                    <span class="badge bg-white text-success ms-1"><?php echo $enviadocc_con_reporte; ?></span>
                                <?php endif; ?>
                                <span class="spinner-border spinner-border-sm"></span>
                            </button>
                        </div>
                        <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Volver al Dashboard
                        </a>
                    </div>
                </div>

                <!-- Card de Actualización Automática -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card auto-update-card">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-9">
                                        <h6 class="card-title mb-1">
                                            <i class="fas fa-sync-alt me-2 text-success"></i>
                                            Actualización Automática de Estados
                                        </h6>
                                        <p class="card-text small text-muted mb-0">
                                            Esta función verifica automáticamente las ausencias con estado <span class="badge bg-info">Enviado a CC</span> 
                                            que tienen número de reporte. Si el reporte existe en la base de datos de atenciones, 
                                            el estado se cambiará automáticamente a <span class="badge bg-success">Respaldo</span>.
                                        </p>
                                    </div>
                                    <div class="col-md-3 text-end">
                                        <div class="d-grid">
                                            <button type="button" class="btn btn-outline-success btn-sm" 
                                                    onclick="mostrarDetallesActualizacion()">
                                                <i class="fas fa-info-circle me-1"></i>Ver detalles
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Estadísticas Rápidas -->
                <div class="row mb-5">
                    <div class="col-auto" id="cardEst">
                        <div class="card text-center">
                            <div class="card-body py-2 auto-width-card">
                                <span class="badge bg-warning badge-estado">Pendiente</span>
                                <h5 class="mt-2">
                                    <?php echo count(array_filter($todasAusencias, function($a) { return $a['estado'] === 'pendiente'; })); ?>
                                </h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-auto">
                        <div class="card text-center">
                            <div class="card-body py-2 auto-width-card">
                                <span class="badge bg-secondary badge-estado">Bloqueado</span>
                                <h5 class="mt-2">
                                    <?php echo count(array_filter($todasAusencias, function($a) { return $a['estado'] === 'bloqueado'; })); ?>
                                </h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-auto">
                        <div class="card text-center">
                            <div class="card-body py-2 auto-width-card">
                                <span class="badge bg-warning badge-estado">Requiere Box</span>
                                <h5 class="mt-2">
                                    <?php echo count(array_filter($todasAusencias, function($a) { return $a['estado'] === 'requierebox'; })); ?>
                                </h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-auto">
                        <div class="card text-center">
                            <div class="card-body py-2 auto-width-card">
                                <span class="badge bg-warning badge-estado">Box Disponible</span>
                                <h5 class="mt-2">
                                    <?php echo count(array_filter($todasAusencias, function($a) { return $a['estado'] === 'boxdisponible'; })); ?>
                                </h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-auto">
                        <div class="card text-center">
                            <div class="card-body py-2">
                                <span class="badge bg-warning badge-estado">Box No Disponible</span>
                                <h5 class="mt-2">
                                    <?php echo count(array_filter($todasAusencias, function($a) { return $a['estado'] === 'boxnodisponible'; })); ?>
                                </h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-auto">
                        <div class="card text-center">
                            <div class="card-body py-2">
                                <span class="badge bg-info badge-estado">En Reagendamiento</span>
                                <h5 class="mt-2">
                                    <?php echo count(array_filter($todasAusencias, function($a) { return $a['estado'] === 'reagendamiento'; })); ?>
                                </h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-auto">
                        <div class="card text-center">
                            <div class="card-body py-2">
                                <span class="badge bg-info badge-estado">Enviado a CC</span>
                                <h5 class="mt-2">
                                    <?php echo count(array_filter($todasAusencias, function($a) { return $a['estado'] === 'enviadocc'; })); ?>
                                </h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-auto">
                        <div class="card text-center">
                            <div class="card-body py-2">
                                <span class="badge bg-primary badge-estado">Notificado</span>
                                <h5 class="mt-2">
                                    <?php echo count(array_filter($todasAusencias, function($a) { return $a['estado'] === 'notificado'; })); ?>
                                </h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-auto">
                        <div class="card text-center">
                            <div class="card-body py-2">
                                <span class="badge bg-success badge-estado">Respaldo</span>
                                <h5 class="mt-2">
                                    <?php echo count(array_filter($todasAusencias, function($a) { return $a['estado'] === 'respaldo'; })); ?>
                                </h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-auto">
                        <div class="card text-center">
                            <div class="card-body py-2">
                                <span class="badge bg-danger badge-estado">Anular</span>
                                <h5 class="mt-2">
                                    <?php echo count(array_filter($todasAusencias, function($a) { return $a['estado'] === 'anular'; })); ?>
                                </h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-auto">
                        <div class="card text-center">
                            <div class="card-body py-2">
                                <span class="badge bg-danger badge-estado">Anulado</span>
                                <h5 class="mt-2">
                                    <?php echo count(array_filter($todasAusencias, function($a) { return $a['estado'] === 'anulado'; })); ?>
                                </h5>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtros Mejorados -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title"><i class="fas fa-filter me-2"></i>Filtros</h6>
                                <form method="GET" action="" id="filtrosForm">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <label class="form-label">Estado</label>
                                            <select class="form-select" name="estado" id="filtroEstado">
                                                <option value="">Todos los estados</option>
                                                <option value="pendiente" <?php echo $filtro_estado == 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                                                <option value="bloqueado" <?php echo $filtro_estado == 'bloqueado' ? 'selected' : ''; ?>>Bloqueado</option>
                                                <option value="requierebox" <?php echo $filtro_estado == 'requierebox' ? 'selected' : ''; ?>>Requiere Box</option>
                                                <option value="boxdisponible" <?php echo $filtro_estado == 'boxdisponible' ? 'selected' : ''; ?>>Box Disponible</option>
                                                <option value="boxnodisponible" <?php echo $filtro_estado == 'boxnodisponible' ? 'selected' : ''; ?>>Box No Disponible</option>
                                                <option value="reagendamiento" <?php echo $filtro_estado == 'reagendamiento' ? 'selected' : ''; ?>>Enviado a Reagendamiento</option>
                                                <option value="enviadocc" <?php echo $filtro_estado == 'enviadocc' ? 'selected' : ''; ?>>Enviado a CC</option>
                                                <option value="notificado" <?php echo $filtro_estado == 'notificado' ? 'selected' : ''; ?>>Notificado</option>
                                                <option value="respaldo" <?php echo $filtro_estado == 'respaldo' ? 'selected' : ''; ?>>Respaldo</option>
                                                <option value="anular" <?php echo $filtro_estado == 'anular' ? 'selected' : ''; ?>>Anular</option>
                                                <option value="anulado" <?php echo $filtro_estado == 'anulado' ? 'selected' : ''; ?>>Anulado</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <label class="form-label">Búsqueda</label>
                                            <input type="text" class="form-control" name="busqueda" id="filtroBusqueda" 
                                                   placeholder="Buscar por profesional, especialidad o motivo..."
                                                   value="<?php echo htmlspecialchars($filtro_busqueda); ?>">
                                        </div>
                                        
                                        <div class="col-md-3">
                                            <label class="form-label">Fecha desde</label>
                                            <input type="date" class="form-control" name="fecha_desde" id="filtroFecha" 
                                                   value="<?php echo htmlspecialchars($filtro_fecha); ?>">
                                        </div>
                                        
                                        <div class="col-md-2">
                                            <label class="form-label">&nbsp;</label>
                                            <div>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-search me-1"></i>Filtrar
                                                </button>
                                                <button type="button" class="btn btn-outline-secondary" onclick="limpiarFiltros()">
                                                    <i class="fas fa-eraser me-1"></i>Limpiar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Exportación rápida -->
                                    <div class="row mt-3">
                                        <div class="col-md-12">
                                            <button type="button" class="btn btn-outline-success btn-sm" onclick="exportarRapido()" title="Exportar con filtros actuales">
                                                <i class="fas fa-download me-1"></i>Exportar Resultados
                                            </button>
                                        </div>
                                    </div>
                                </form>
                                
                                <!-- Indicador de filtros activos -->
                                <?php if (!empty($filtro_estado) || !empty($filtro_busqueda) || !empty($filtro_fecha)): ?>
                                <div class="filtros-activos mt-3">
                                    <i class="fas fa-filter me-2"></i>Filtros activos:
                                    <?php if (!empty($filtro_estado)): ?>
                                        <span class="badge-filtro">
                                            <i class="fas fa-tag me-1"></i>Estado: <?php echo $auth->getNombreEstado($filtro_estado); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($filtro_busqueda)): ?>
                                        <span class="badge-filtro">
                                            <i class="fas fa-search me-1"></i>Búsqueda: <?php echo htmlspecialchars($filtro_busqueda); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($filtro_fecha)): ?>
                                        <span class="badge-filtro">
                                            <i class="fas fa-calendar me-1"></i>Desde: <?php echo date('d/m/Y', strtotime($filtro_fecha)); ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="text-muted ms-2">
                                        <i class="fas fa-chart-line me-1"></i>Resultados: <?php echo $total_ausencias; ?> ausencias
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabla de Ausencias -->
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list me-2"></i>Lista de Ausencias
                            <?php if ($total_ausencias > 0): ?>
                                <span class="badge bg-secondary ms-2"><?php echo $total_ausencias; ?> resultados</span>
                            <?php endif; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($ausencias)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <p class="text-muted">
                                    <?php if (!empty($filtro_estado) || !empty($filtro_busqueda) || !empty($filtro_fecha)): ?>
                                        No hay ausencias que coincidan con los filtros seleccionados
                                    <?php else: ?>
                                        No hay ausencias registradas
                                    <?php endif; ?>
                                </p>
                                <?php if (!empty($filtro_estado) || !empty($filtro_busqueda) || !empty($filtro_fecha)): ?>
                                    <button class="btn btn-sm btn-outline-primary" onclick="limpiarFiltros()">
                                        <i class="fas fa-eraser me-1"></i>Limpiar filtros
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover" id="tablaAusencias">
                                    <thead>
                                        <tr>
                                            <th>Profesional</th>
                                            <th>Unidad</th>
                                            <th>Motivo</th>
                                            <th>F. Inicio</th>
                                            <th>F. Fin</th>
                                            <th>Estado</th>
                                            <th>Reporte</th>
                                            <th>Ingresado</th>
                                            <th>Actualizado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ausencias as $ausencia): 
                                            $estados_permitidos = $auth->getFlujoEstados($ausencia['estado']);
                                            $es_candidata_auto = ($ausencia['estado'] === 'enviadocc' && !empty($ausencia['reporte']));
                                        ?>
                                        <tr class="<?php echo $es_candidata_auto ? 'table-warning' : ''; ?>" 
                                            data-reporte="<?php echo htmlspecialchars($ausencia['reporte'] ?? ''); ?>">
                                            <td><?php echo htmlspecialchars($ausencia['profesional_nombre']); ?></td>
                                            <td><?php echo htmlspecialchars($ausencia['especialidad_nombre']); ?></td>
                                            <td>
                                                <?php 
                                                $motivos = [
                                                    'permiso' => 'Permiso Admin',
                                                    'vacaciones' => 'Vacaciones',
                                                    'licencia' => 'Licencia',
                                                    'reunion' => 'Reunión',
                                                    'capacitacion' => 'Capacitación',
                                                    'turno' => 'Turno',
                                                    'pabellon' => 'Pabellón',
                                                    'modificacion'=>'Modificación',
                                                    'reduccion'=>'Reducción',
                                                    'renuncia'=>'Renuncia',
                                                    'cupo reservado'=>'Cupo Reservado'
                                                ];
                                                echo $motivos[$ausencia['motivo']] ?? $ausencia['motivo'];
                                                ?>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($ausencia['fecha_inicio'])); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($ausencia['fecha_fin'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $auth->getClaseEstado($ausencia['estado']); ?> badge-estado">
                                                    <?php echo $auth->getNombreEstado($ausencia['estado']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!empty($ausencia['reporte'])): ?>
                                                    <span class="badge bg-light text-dark" 
                                                          title="Click para verificar este reporte"
                                                          style="cursor: pointer;"
                                                          onclick="verificarReporteIndividual('<?php echo $ausencia['id']; ?>', '<?php echo htmlspecialchars($ausencia['reporte']); ?>')">
                                                        <?php echo htmlspecialchars($ausencia['reporte']); ?>
                                                        <?php if ($ausencia['estado'] === 'enviadocc'): ?>
                                                            <i class="fas fa-search ms-1 text-info"></i>
                                                        <?php endif; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">Sin reporte</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small>
                                                    <?php echo htmlspecialchars($ausencia['usuario_registro']); ?><br>
                                                    <span class="text-muted"><?php echo date('d/m/Y H:i', strtotime($ausencia['timestamp_registro'])); ?></span>
                                                </small>
                                            </td>
                                            <td>
                                                <?php if ($ausencia['usuario_modificacion']): ?>
                                                    <small>
                                                        <?php echo htmlspecialchars($ausencia['usuario_modificacion']); ?><br>
                                                        <span class="text-muted"><?php echo date('d/m/Y H:i', strtotime($ausencia['timestamp_modificacion'])); ?></span>
                                                    </small>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <td class="text-nowrap">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" 
                                                                <?php echo empty($estados_permitidos) ? 'disabled' : ''; ?>>
                                                            <i class="fas fa-cog"></i>
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-estado">
                                                            <?php foreach ($estados_permitidos as $estado_permitido): ?>
                                                            <li>
                                                                <form method="POST" style="display: inline;">
                                                                    <input type="hidden" name="ausencia_id" value="<?php echo $ausencia['id']; ?>">
                                                                    <input type="hidden" name="nuevo_estado" value="<?php echo $estado_permitido; ?>">
                                                                    <button type="submit" name="cambiar_estado" 
                                                                            class="dropdown-item text-<?php echo $auth->getClaseEstado($estado_permitido); ?>"
                                                                            onclick="return confirm('¿Cambiar estado a <?php echo $auth->getNombreEstado($estado_permitido); ?>?')">
                                                                        <i class="fas fa-arrow-right me-2"></i>
                                                                        <?php echo $auth->getNombreEstado($estado_permitido); ?>
                                                                    </button>
                                                                </form>
                                                            </li>
                                                            <?php endforeach; ?>
                                                            <?php if (!empty($estados_permitidos)): ?>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <?php endif; ?>
                                                        </ul>
                                                    </div>
                                                    
                                                    <button type="button" class="btn btn-outline-info btn-ver-detalles" 
                                                            data-ausencia-id="<?php echo $ausencia['id']; ?>"
                                                            title="Ver detalles">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    
                                                    <?php if ($ausencia['estado'] === 'enviadocc' && !empty($ausencia['reporte'])): ?>
                                                    <button type="button" class="btn btn-outline-success btn-verificar-reporte" 
                                                            data-ausencia-id="<?php echo $ausencia['id']; ?>"
                                                            data-reporte="<?php echo htmlspecialchars($ausencia['reporte']); ?>"
                                                            title="Verificar este reporte individualmente">
                                                        <i class="fas fa-search"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Paginación Mejorada con preservación de filtros -->
                            <?php if ($total_paginas > 1): ?>
                            <div class="pagination-container mt-3">
                                <div class="page-info">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Mostrando <?php echo count($ausencias); ?> de <?php echo $total_ausencias; ?> resultados
                                    (Página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?>)
                                </div>
                                
                                <nav aria-label="Navegación de páginas">
                                    <ul class="pagination pagination-sm mb-0">
                                        <?php
                                        // Función para generar URL con filtros
                                        function buildUrl($pagina, $registros, $estado, $busqueda, $fecha) {
                                            $params = [];
                                            if ($pagina != 1) $params['pagina'] = $pagina;
                                            if ($registros != 50) $params['registros'] = $registros;
                                            if (!empty($estado)) $params['estado'] = $estado;
                                            if (!empty($busqueda)) $params['busqueda'] = $busqueda;
                                            if (!empty($fecha)) $params['fecha_desde'] = $fecha;
                                            return '?' . http_build_query($params);
                                        }
                                        ?>
                                        
                                        <!-- Primera página -->
                                        <li class="page-item <?php echo $pagina_actual <= 1 ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="<?php echo buildUrl(1, $registros_por_pagina, $filtro_estado, $filtro_busqueda, $filtro_fecha); ?>">&laquo;</a>
                                        </li>
                                        
                                        <!-- Anterior -->
                                        <li class="page-item <?php echo $pagina_actual <= 1 ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="<?php echo buildUrl($pagina_actual - 1, $registros_por_pagina, $filtro_estado, $filtro_busqueda, $filtro_fecha); ?>">&lsaquo;</a>
                                        </li>
                                        
                                        <?php
                                        $inicio_paginacion = max(1, $pagina_actual - 2);
                                        $fin_paginacion = min($total_paginas, $pagina_actual + 2);
                                        
                                        if ($inicio_paginacion > 1) {
                                            echo '<li class="page-item"><a class="page-link" href="' . buildUrl(1, $registros_por_pagina, $filtro_estado, $filtro_busqueda, $filtro_fecha) . '">1</a></li>';
                                            if ($inicio_paginacion > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        }
                                        
                                        for ($i = $inicio_paginacion; $i <= $fin_paginacion; $i++) {
                                            echo '<li class="page-item ' . ($pagina_actual == $i ? 'active' : '') . '">
                                                    <a class="page-link" href="' . buildUrl($i, $registros_por_pagina, $filtro_estado, $filtro_busqueda, $filtro_fecha) . '">' . $i . '</a>
                                                  </li>';
                                        }
                                        
                                        if ($fin_paginacion < $total_paginas) {
                                            if ($fin_paginacion < $total_paginas - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                            echo '<li class="page-item"><a class="page-link" href="' . buildUrl($total_paginas, $registros_por_pagina, $filtro_estado, $filtro_busqueda, $filtro_fecha) . '">' . $total_paginas . '</a></li>';
                                        }
                                        ?>
                                        
                                        <!-- Siguiente -->
                                        <li class="page-item <?php echo $pagina_actual >= $total_paginas ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="<?php echo buildUrl($pagina_actual + 1, $registros_por_pagina, $filtro_estado, $filtro_busqueda, $filtro_fecha); ?>">&rsaquo;</a>
                                        </li>
                                        
                                        <!-- Última página -->
                                        <li class="page-item <?php echo $pagina_actual >= $total_paginas ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="<?php echo buildUrl($total_paginas, $registros_por_pagina, $filtro_estado, $filtro_busqueda, $filtro_fecha); ?>">&raquo;</a>
                                        </li>
                                    </ul>
                                </nav>
                                
                                <div class="registros-per-page">
                                    <label class="me-2 small">Ver:</label>
                                    <select class="form-select form-select-sm registros-select" id="registrosPorPagina" style="width: auto;">
                                        <option value="10" <?php echo $registros_por_pagina == 10 ? 'selected' : ''; ?>>10</option>
                                        <option value="25" <?php echo $registros_por_pagina == 25 ? 'selected' : ''; ?>>25</option>
                                        <option value="50" <?php echo $registros_por_pagina == 50 ? 'selected' : ''; ?>>50</option>
                                        <option value="100" <?php echo $registros_por_pagina == 100 ? 'selected' : ''; ?>>100</option>
                                        <option value="200" <?php echo $registros_por_pagina == 200 ? 'selected' : ''; ?>>200</option>
                                    </select>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <?php include 'includes/modals.php'; ?>

    <!-- Modal para ver detalles -->
    <div class="modal fade" id="detallesModal" tabindex="-1" aria-labelledby="detallesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detallesModalLabel">
                        <i class="fas fa-info-circle me-2"></i>Detalles de la Ausencia
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="detallesContenido">
                    <!-- Los detalles se cargarán aquí via JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para detalles de actualización -->
    <div class="modal fade" id="detallesActualizacionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-info-circle me-2"></i>Detalles de Actualización Automática
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-lightbulb me-2"></i>
                        Esta función realiza las siguientes acciones automáticamente:
                    </div>
                    
                    <ol>
                        <li><strong>Busca todas las ausencias</strong> con estado <span class="badge bg-info">"Enviado a CC"</span></li>
                        <li><strong>Verifica que tengan número de reporte</strong> asignado</li>
                        <li><strong>Consulta la tabla "reportes"</strong> para encontrar coincidencias</li>
                        <li><strong>Actualiza el estado</strong> a <span class="badge bg-success">"Respaldo"</span> solo si el reporte existe en la base de datos</li>
                        <li><strong>Registra auditoría</strong> con usuario y fecha de la actualización</li>
                    </ol>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary" onclick="iniciarActualizacionAutomatica()">
                        <i class="fas fa-play me-1"></i>Ejecutar Verificación
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    
    <script>
        // ============================================
        // FUNCIONALIDAD DE FILTROS
        // ============================================
        
        function limpiarFiltros() {
            window.location.href = window.location.pathname;
        }
        
        function exportarRapido() {
            const form = document.getElementById('filtrosForm');
            const formData = new FormData(form);
            const params = new URLSearchParams();
            
            const estado = formData.get('estado');
            const busqueda = formData.get('busqueda');
            const fecha_desde = formData.get('fecha_desde');
            
            if (estado) params.append('estado', estado);
            if (busqueda) params.append('busqueda', busqueda);
            if (fecha_desde) params.append('fecha_desde', fecha_desde);
            
            window.open('exportar_ausencias.php?' + params.toString(), '_blank');
        }
        
        // Cambio de registros por página
        document.getElementById('registrosPorPagina')?.addEventListener('change', function() {
            const registros = this.value;
            const url = new URL(window.location.href);
            url.searchParams.set('registros', registros);
            url.searchParams.set('pagina', 1);
            window.location.href = url.toString();
        });
        
        // ============================================
        // FUNCIONALIDAD DE ACTUALIZACIÓN AUTOMÁTICA
        // ============================================
        
        function mostrarDetallesActualizacion() {
            const modal = new bootstrap.Modal(document.getElementById('detallesActualizacionModal'));
            modal.show();
        }
        
        function iniciarActualizacionAutomatica() {
            const btn = document.getElementById('btnAutoUpdate');
            btn.classList.add('processing');
            btn.disabled = true;
            
            const modal = bootstrap.Modal.getInstance(document.getElementById('detallesActualizacionModal'));
            modal.hide();
            
            const alertArea = document.querySelector('.content-area');
            const processingAlert = document.createElement('div');
            processingAlert.className = 'alert alert-info alert-dismissible fade show mt-3';
            processingAlert.innerHTML = `
                <div class="d-flex align-items-center">
                    <div class="spinner-border spinner-border-sm me-3" role="status">
                        <span class="visually-hidden">Procesando...</span>
                    </div>
                    <div>
                        <strong>Verificando reportes...</strong>
                        <p class="mb-0 small">Estamos consultando la base de datos para actualizar estados automáticamente.</p>
                    </div>
                </div>
            `;
            alertArea.insertBefore(processingAlert, alertArea.firstChild);
            
            fetch('api/verificar_reportes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'accion=verificar_reportes'
            })
            .then(response => response.json())
            .then(data => {
                processingAlert.remove();
                
                const resultAlert = document.createElement('div');
                
                if (data.success) {
                    resultAlert.className = 'alert alert-success alert-dismissible fade show mt-3';
                    let message = `<strong>✅ Verificación completada</strong><br>`;
                    message += `Actualizadas: <strong>${data.actualizados}</strong> ausencia(s)<br>`;
                    
                    if (data.no_encontrados > 0) {
                        message += `No encontrados: <strong>${data.no_encontrados}</strong> reporte(s)<br>`;
                    }
                    
                    if (data.errores > 0) {
                        message += `Errores: <strong>${data.errores}</strong><br>`;
                    }
                    
                    resultAlert.innerHTML = `
                        ${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        <div class="mt-2">
                            <button type="button" class="btn btn-sm btn-outline-success" onclick="location.reload()">
                                <i class="fas fa-sync-alt me-1"></i>Actualizar página
                            </button>
                        </div>
                    `;
                } else {
                    resultAlert.className = 'alert alert-danger alert-dismissible fade show mt-3';
                    resultAlert.innerHTML = `
                        <strong>❌ Error en la verificación</strong><br>
                        ${data.error || 'Error desconocido'}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                }
                
                alertArea.insertBefore(resultAlert, alertArea.firstChild);
                
                setTimeout(() => {
                    btn.classList.remove('processing');
                    btn.disabled = false;
                }, 2000);
            })
            .catch(error => {
                console.error('Error:', error);
                processingAlert.remove();
                
                const errorAlert = document.createElement('div');
                errorAlert.className = 'alert alert-danger alert-dismissible fade show mt-3';
                errorAlert.innerHTML = `
                    <strong>❌ Error de conexión</strong><br>
                    No se pudo completar la verificación. Intente nuevamente.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                
                alertArea.insertBefore(errorAlert, alertArea.firstChild);
                
                btn.classList.remove('processing');
                btn.disabled = false;
            });
        }
        
        // Verificación individual de reporte
        function verificarReporteIndividual(ausenciaId, reporte) {
            if (!confirm(`¿Verificar el reporte "${reporte}" individualmente?`)) {
                return;
            }
            
            const btn = document.querySelector(`.btn-verificar-reporte[data-ausencia-id="${ausenciaId}"]`);
            if (btn) {
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                btn.disabled = true;
            }
            
            fetch('api/verificar_reportes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `accion=verificar_individual&ausencia_id=${ausenciaId}&reporte=${encodeURIComponent(reporte)}`
            })
            .then(response => response.json())
            .then(data => {
                if (btn) {
                    btn.innerHTML = '<i class="fas fa-search"></i>';
                    btn.disabled = false;
                }
                
                const alertArea = document.querySelector('.content-area');
                const resultAlert = document.createElement('div');
                
                if (data.success) {
                    resultAlert.className = 'alert alert-success alert-dismissible fade show mt-3';
                    let message = `<strong>✅ Verificación individual completada</strong><br>`;
                    
                    if (data.actualizado) {
                        message += `El estado ha sido actualizado a <span class="badge bg-success">Respaldo</span><br>`;
                        message += `<small class="text-muted">Reporte: ${reporte}</small>`;
                        
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    } else {
                        message += `<strong>No se encontró el reporte</strong> en la base de datos<br>`;
                        message += `<small class="text-muted">Reporte: ${reporte}</small>`;
                    }
                    
                    resultAlert.innerHTML = `
                        ${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                } else {
                    resultAlert.className = 'alert alert-danger alert-dismissible fade show mt-3';
                    resultAlert.innerHTML = `
                        <strong>❌ Error en la verificación</strong><br>
                        ${data.error || 'Error desconocido'}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                }
                
                alertArea.insertBefore(resultAlert, alertArea.firstChild);
            })
            .catch(error => {
                console.error('Error:', error);
                if (btn) {
                    btn.innerHTML = '<i class="fas fa-search"></i>';
                    btn.disabled = false;
                }
                
                const alertArea = document.querySelector('.content-area');
                const errorAlert = document.createElement('div');
                errorAlert.className = 'alert alert-danger alert-dismissible fade show mt-3';
                errorAlert.innerHTML = `
                    <strong>❌ Error de conexión</strong><br>
                    No se pudo completar la verificación.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                
                alertArea.insertBefore(errorAlert, alertArea.firstChild);
            });
        }
        
        // ============================================
        // FUNCIONALIDAD DE DETALLES
        // ============================================
        
        function verDetalles(ausenciaId) {
            const modalElement = document.getElementById('detallesModal');
            if (!modalElement) {
                console.error('Modal no encontrado');
                return;
            }
            
            fetch(`api/get_detalles_ausencia.php?id=${ausenciaId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const ausencia = data.ausencia;
                        
                        const modalContent = `
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Información General</h6>
                                    <p><strong>Profesional:</strong> ${ausencia.profesional_nombre}</p>
                                    <p><strong>Especialidad:</strong> ${ausencia.especialidad_nombre}</p>
                                    <p><strong>Motivo:</strong> ${ausencia.motivo_nombre}</p>
                                    <p><strong>Período:</strong> ${ausencia.fecha_inicio} a ${ausencia.fecha_fin}</p>
                                    <p><strong>Días:</strong> ${ausencia.dias} día(s)</p>
                                </div>
                                <div class="col-md-6">
                                    <h6>Estado y Auditoría</h6>
                                    <p><strong>Estado:</strong> <span class="badge bg-${ausencia.estado_clase}">${ausencia.estado_nombre}</span></p>
                                    <p><strong>Registrado por:</strong> ${ausencia.usuario_registro}</p>
                                    <p><strong>Fecha registro:</strong> ${ausencia.timestamp_registro}</p>
                                    ${ausencia.usuario_modificacion ? 
                                        `<p><strong>Modificado por:</strong> ${ausencia.usuario_modificacion}</p>
                                         <p><strong>Última modificación:</strong> ${ausencia.timestamp_modificacion}</p>` 
                                        : ''}
                                </div>
                            </div>
                            <div class="mt-3">
                                <h6>Detalles</h6>
                                <div class="bg-light p-3 rounded">
                                    ${ausencia.detalle || 'Sin detalles adicionales'}
                                </div>
                            </div>
                            ${ausencia.reporte ? 
                                `<div class="mt-3">
                                    <h6>Reporte</h6>
                                    <div class="bg-light p-3 rounded">
                                        ${ausencia.reporte}
                                    </div>
                                 </div>` 
                                : ''}
                        `;
                        
                        document.getElementById('detallesContenido').innerHTML = modalContent;
                        const modal = new bootstrap.Modal(modalElement);
                        modal.show();
                    } else {
                        document.getElementById('detallesContenido').innerHTML = `
                            <div class="alert alert-danger">
                                Error: ${data.error}
                            </div>
                        `;
                        const modal = new bootstrap.Modal(modalElement);
                        modal.show();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('detallesContenido').innerHTML = `
                        <div class="alert alert-danger">
                            Error de conexión: ${error.message}
                        </div>
                    `;
                    const modal = new bootstrap.Modal(modalElement);
                    modal.show();
                });
        }
        
        // ============================================
        // INICIALIZACIÓN
        // ============================================
        
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Inicializando gestión de ausencias...');
            
            // Delegación de eventos para botones de detalles
            document.addEventListener('click', function(e) {
                const btnDetalles = e.target.closest('.btn-ver-detalles');
                if (btnDetalles) {
                    const ausenciaId = btnDetalles.getAttribute('data-ausencia-id');
                    if (ausenciaId) {
                        verDetalles(ausenciaId);
                    }
                }
            });
            
            // Delegación de eventos para verificación individual
            document.addEventListener('click', function(e) {
                const btnVerificar = e.target.closest('.btn-verificar-reporte');
                if (btnVerificar) {
                    const ausenciaId = btnVerificar.getAttribute('data-ausencia-id');
                    const reporte = btnVerificar.getAttribute('data-reporte');
                    if (ausenciaId && reporte) {
                        verificarReporteIndividual(ausenciaId, reporte);
                    }
                }
            });
            
            // Event listener para botón de actualización automática
            const btnAutoUpdate = document.getElementById('btnAutoUpdate');
            if (btnAutoUpdate) {
                btnAutoUpdate.addEventListener('click', function() {
                    if (!this.disabled) {
                        iniciarActualizacionAutomatica();
                    }
                });
            }
            
            console.log('Sistema inicializado correctamente');
        });
    </script>
    
    <script src="js/script_ausencias.js"></script>
</body>
<footer class="text-center py-4">Patricio Corrotea | Gestión de la Demanda</footer>
</html>