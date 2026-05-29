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

$success = '';
$error = '';
$import_results = [];

// Procesar formularios
if ($_POST) {
    // Crear ubicación
    if (isset($_POST['crear_ubicacion'])) {
        $sector = trim($_POST['sector']);
        $ubicacion = trim($_POST['ubicacion']);
        $activo = $_POST['activo'] ?? '1';
        
        if (empty($sector)) {
            $error = "El nombre del sector es obligatorio";
        } elseif (empty($ubicacion)) {
            $error = "La ubicación es obligatoria";
        } elseif (ubicacionExists($conn, $sector, $ubicacion)) {
            $error = "Ya existe una ubicación con ese sector y nombre";
        } else {
            if (createUbicacion($conn, $sector, $ubicacion, $activo)) {
                $success = "Ubicación creada correctamente";
            } else {
                $error = "Error al crear la ubicación";
            }
        }
    }
    
    // Editar ubicación
    if (isset($_POST['editar_ubicacion'])) {
        $id = $_POST['ubicacion_id'];
        $sector = trim($_POST['sector']);
        $ubicacion = trim($_POST['ubicacion']);
        $activo = $_POST['activo'] ?? '1';
        
        if (empty($sector)) {
            $error = "El nombre del sector es obligatorio";
        } elseif (empty($ubicacion)) {
            $error = "La ubicación es obligatoria";
        } elseif (ubicacionExists($conn, $sector, $ubicacion, $id)) {
            $error = "Ya existe una ubicación con ese sector y nombre";
        } else {
            if (updateUbicacion($conn, $id, $sector, $ubicacion, $activo)) {
                $success = "Ubicación actualizada correctamente";
            } else {
                $error = "Error al actualizar la ubicación";
            }
        }
    }
    
    // Eliminar ubicación
    if (isset($_POST['eliminar_ubicacion'])) {
        $id = $_POST['ubicacion_id'];
        
        if (deleteUbicacion($conn, $id)) {
            $success = "Ubicación eliminada correctamente";
        } else {
            $error = "Error al eliminar la ubicación";
        }
    }
}

// Procesar importación CSV
if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
    $import_results = importarUbicacionesCSV($conn, $_FILES['csv_file']);
    if ($import_results['success_count'] > 0) {
        $success = "Importación completada: {$import_results['success_count']} ubicaciones importadas, {$import_results['error_count']} errores";
    } else {
        $error = "No se pudieron importar ubicaciones. " . ($import_results['errors'][0] ?? 'Verifique el formato del archivo');
    }
}

// Funciones auxiliares
function ubicacionExists($conn, $sector, $ubicacion, $exclude_id = null) {
    $sql = "SELECT COUNT(*) FROM ubicaciones WHERE sector = :sector AND ubicacion = :ubicacion";
    $params = [':sector' => $sector, ':ubicacion' => $ubicacion];
    
    if ($exclude_id) {
        $sql .= " AND id != :id";
        $params[':id'] = $exclude_id;
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn() > 0;
}

function createUbicacion($conn, $sector, $ubicacion, $activo) {
    $sql = "INSERT INTO ubicaciones (sector, ubicacion, activo) 
            VALUES (:sector, :ubicacion, :activo)";
    $stmt = $conn->prepare($sql);
    return $stmt->execute([
        ':sector' => $sector,
        ':ubicacion' => $ubicacion,
        ':activo' => $activo
    ]);
}

function updateUbicacion($conn, $id, $sector, $ubicacion, $activo) {
    $sql = "UPDATE ubicaciones 
            SET sector = :sector, 
                ubicacion = :ubicacion, 
                activo = :activo 
            WHERE id = :id";
    $stmt = $conn->prepare($sql);
    return $stmt->execute([
        ':id' => $id,
        ':sector' => $sector,
        ':ubicacion' => $ubicacion,
        ':activo' => $activo
    ]);
}

function deleteUbicacion($conn, $id) {
    $sql = "DELETE FROM ubicaciones WHERE id = :id";
    $stmt = $conn->prepare($sql);
    return $stmt->execute([':id' => $id]);
}

function getAllUbicaciones($conn, $incluir_inactivas = true) {
    $sql = "SELECT * FROM ubicaciones";
    if (!$incluir_inactivas) {
        $sql .= " WHERE activo = 1";
    }
    $sql .= " ORDER BY sector, ubicacion";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUbicacionesPorSector($conn) {
    $sql = "SELECT sector, GROUP_CONCAT(ubicacion ORDER BY ubicacion SEPARATOR ', ') as ubicaciones 
            FROM ubicaciones WHERE activo = 1 GROUP BY sector ORDER BY sector";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function importarUbicacionesCSV($conn, $file) {
    $result = ['success_count' => 0, 'error_count' => 0, 'errors' => []];
    
    // Verificar tipo de archivo
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ['csv', 'txt'])) {
        $result['errors'][] = "Formato de archivo no válido. Use CSV o TXT";
        return $result;
    }
    
    // Abrir archivo
    if (($handle = fopen($file['tmp_name'], 'r')) !== false) {
        // Detectar delimitador (coma o punto y coma)
        $first_line = fgets($handle);
        rewind($handle);
        $delimiter = (strpos($first_line, ';') !== false) ? ';' : ',';
        
        $row = 0;
        while (($data = fgetcsv($handle, 1000, $delimiter)) !== false) {
            $row++;
            
            // Saltar encabezado si existe (detectar por contenido)
            if ($row == 1 && (strtolower($data[0]) == 'sector' || strtolower($data[0]) == 'sector;ubicacion')) {
                continue;
            }
            
            // Limpiar datos
            $sector = isset($data[0]) ? trim($data[0]) : '';
            $ubicacion = isset($data[1]) ? trim($data[1]) : '';
            $activo = isset($data[2]) ? (trim($data[2]) == 'activa' || trim($data[2]) == 'activo' || trim($data[2]) == '1' ? 1 : 0) : 1;
            
            // Validar datos
            if (empty($sector) || empty($ubicacion)) {
                $result['errors'][] = "Fila $row: Sector o ubicación vacía";
                $result['error_count']++;
                continue;
            }
            
            // Verificar si ya existe
            if (ubicacionExists($conn, $sector, $ubicacion)) {
                $result['errors'][] = "Fila $row: La ubicación '$ubicacion' en sector '$sector' ya existe";
                $result['error_count']++;
                continue;
            }
            
            // Insertar ubicación
            if (createUbicacion($conn, $sector, $ubicacion, $activo)) {
                $result['success_count']++;
            } else {
                $result['errors'][] = "Fila $row: Error al insertar '$ubicacion'";
                $result['error_count']++;
            }
        }
        fclose($handle);
    } else {
        $result['errors'][] = "No se pudo abrir el archivo";
    }
    
    return $result;
}

// Descargar plantilla CSV
if (isset($_GET['descargar_plantilla'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="plantilla_ubicaciones.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['sector', 'ubicacion', 'estado']);
    fputcsv($output, ['Piso1', 'Box 1', 'activa']);
    fputcsv($output, ['Piso1', 'Box 2', 'activa']);
    fputcsv($output, ['Piso2', 'Box 3', 'activa']);
    fputcsv($output, ['CAE', 'Cowork', 'activa']);
    fputcsv($output, ['CESAM', 'Box 5', 'inactiva']);
    fclose($output);
    exit;
}

// Obtener lista de ubicaciones
$ubicaciones = getAllUbicaciones($conn, true);

// Obtener estadísticas por sector
$sectores = [];
$ubicaciones_activas = 0;
foreach ($ubicaciones as $ubicacion) {
    if (!in_array($ubicacion['sector'], $sectores)) {
        $sectores[] = $ubicacion['sector'];
    }
    if ($ubicacion['activo'] == 1) {
        $ubicaciones_activas++;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="img/favicon.png" type="image/png">
    <title>Gestión de Ubicaciones | Gear-HSP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .table-actions {
            white-space: nowrap;
        }
        .badge-activa {
            background-color: #28a745;
        }
        .badge-inactiva {
            background-color: #6c757d;
        }
        .card-stats {
            transition: transform 0.3s;
        }
        .card-stats:hover {
            transform: translateY(-5px);
        }
        .badge-sector {
            font-size: 0.75rem;
            padding: 0.35rem 0.65rem;
            background-color: #17a2b8;
        }
        .filter-section {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .import-card {
            border: 2px dashed #6c757d;
            background-color: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s;
        }
        .import-card:hover {
            border-color: #007bff;
            background-color: #e7f1ff;
        }
        .import-card.dragover {
            border-color: #28a745;
            background-color: #d4edda;
        }
        .ubicacion-asignada {
            background-color: #d4edda;
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
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Mostrar errores de importación detallados -->
                <?php if (!empty($import_results['errors'])): ?>
                    <div class="alert alert-warning alert-dismissible fade show mt-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Detalles de importación:</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach (array_slice($import_results['errors'], 0, 10) as $error_msg): ?>
                                <li><?php echo htmlspecialchars($error_msg); ?></li>
                            <?php endforeach; ?>
                            <?php if (count($import_results['errors']) > 10): ?>
                                <li>... y <?php echo count($import_results['errors']) - 10; ?> errores más</li>
                            <?php endif; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Header -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-location-dot me-2"></i>Gestión de Ubicaciones
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importarUbicacionesModal">
                                <i class="fas fa-file-import me-2"></i>Importar CSV
                            </button>
                            <a href="?descargar_plantilla=1" class="btn btn-info">
                                <i class="fas fa-download me-2"></i>Descargar Plantilla
                            </a>
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#crearUbicacionModal">
                            <i class="fas fa-plus me-2"></i>Nueva Ubicación
                        </button>
                    </div>
                </div>

                <!-- Estadísticas -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card text-center card-stats shadow-sm">
                            <div class="card-body">
                                <i class="fas fa-location-dot fa-2x text-primary mb-2"></i>
                                <h3 class="text-primary"><?php echo count($ubicaciones); ?></h3>
                                <p class="mb-0 text-muted">Total Ubicaciones</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-center card-stats shadow-sm">
                            <div class="card-body">
                                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                <h3 class="text-success"><?php echo $ubicaciones_activas; ?></h3>
                                <p class="mb-0 text-muted">Ubicaciones Activas</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-center card-stats shadow-sm">
                            <div class="card-body">
                                <i class="fas fa-building fa-2x text-warning mb-2"></i>
                                <h3 class="text-warning"><?php echo count($sectores); ?></h3>
                                <p class="mb-0 text-muted">Sectores</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-center card-stats shadow-sm">
                            <div class="card-body">
                                <i class="fas fa-chart-line fa-2x text-info mb-2"></i>
                                <h3 class="text-info">
                                    <?php echo count($ubicaciones) > 0 ? round($ubicaciones_activas / count($ubicaciones) * 100) : 0; ?>%
                                </h3>
                                <p class="mb-0 text-muted">Tasa de Actividad</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtro por Sector -->
                <div class="filter-section">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <label for="filtroSector" class="form-label">
                                <i class="fas fa-filter me-1"></i>Filtrar por Sector
                            </label>
                            <select class="form-select" id="filtroSector">
                                <option value="">Todos los sectores</option>
                                <?php foreach ($sectores as $sector): ?>
                                    <option value="<?php echo htmlspecialchars($sector); ?>">
                                        <?php echo htmlspecialchars($sector); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="filtroEstado" class="form-label">
                                <i class="fas fa-toggle-on me-1"></i>Filtrar por Estado
                            </label>
                            <select class="form-select" id="filtroEstado">
                                <option value="">Todos</option>
                                <option value="1">Activas</option>
                                <option value="0">Inactivas</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="filtroBusqueda" class="form-label">
                                <i class="fas fa-search me-1"></i>Búsqueda
                            </label>
                            <input type="text" class="form-control" id="filtroBusqueda" 
                                   placeholder="Buscar ubicación...">
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-12 text-end">
                            <button class="btn btn-sm btn-outline-secondary" id="btnLimpiarFiltros">
                                <i class="fas fa-eraser me-1"></i>Limpiar Filtros
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tabla de Ubicaciones -->
                <div class="card dashboard-card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list me-2"></i>Lista de Ubicaciones
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($ubicaciones)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-location-dot fa-4x text-muted mb-3"></i>
                                <p class="text-muted">No hay ubicaciones registradas</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#crearUbicacionModal">
                                    <i class="fas fa-plus me-2"></i>Crear Primera Ubicación
                                </button>
                                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importarUbicacionesModal">
                                    <i class="fas fa-file-import me-2"></i>Importar CSV
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-striped" id="tablaUbicaciones">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Sector</th>
                                            <th>Ubicación</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ubicaciones as $ubicacion): ?>
                                        <tr data-sector="<?php echo htmlspecialchars($ubicacion['sector']); ?>"
                                            data-activo="<?php echo $ubicacion['activo']; ?>"
                                            data-ubicacion="<?php echo htmlspecialchars(strtolower($ubicacion['ubicacion'])); ?>">
                                            <td><?php echo $ubicacion['id']; ?></td>
                                            <td>
                                                <span class="badge badge-sector">
                                                    <i class="fas fa-building me-1"></i>
                                                    <?php echo htmlspecialchars($ubicacion['sector']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <i class="fas fa-location-dot text-primary me-1"></i>
                                                <strong><?php echo htmlspecialchars($ubicacion['ubicacion']); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $ubicacion['activo'] == 1 ? 'badge-activa' : 'badge-inactiva'; ?>">
                                                    <i class="fas <?php echo $ubicacion['activo'] == 1 ? 'fa-check-circle' : 'fa-circle'; ?> me-1"></i>
                                                    <?php echo $ubicacion['activo'] == 1 ? 'Activa' : 'Inactiva'; ?>
                                                </span>
                                            </td>
                                            <td class="table-actions">
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary" 
                                                            onclick='editarUbicacion(<?php echo json_encode($ubicacion); ?>)'
                                                            title="Editar ubicación">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-outline-danger" 
                                                            onclick="eliminarUbicacion(<?php echo $ubicacion['id']; ?>, '<?php echo htmlspecialchars($ubicacion['ubicacion']); ?>')"
                                                            title="Eliminar ubicación">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-3" id="contadorResultados">
                                <small class="text-muted">
                                    <i class="fas fa-chart-simple me-1"></i>
                                    Mostrando <strong id="totalFilas"><?php echo count($ubicaciones); ?></strong> de 
                                    <strong><?php echo count($ubicaciones); ?></strong> ubicaciones
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Resumen de Ubicaciones por Sector -->
                <div class="card dashboard-card shadow-sm mt-4">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-building me-2"></i>Ubicaciones por Sector
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php $sectores_data = getUbicacionesPorSector($conn); ?>
                            <?php foreach ($sectores_data as $sector_data): ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="card h-100">
                                        <div class="card-header bg-info text-white">
                                            <i class="fas fa-building me-1"></i>
                                            <strong><?php echo htmlspecialchars($sector_data['sector']); ?></strong>
                                        </div>
                                        <div class="card-body">
                                            <ul class="list-unstyled mb-0">
                                                <?php 
                                                $ubicaciones_list = explode(', ', $sector_data['ubicaciones']);
                                                foreach ($ubicaciones_list as $ubic): ?>
                                                    <li class="mb-1">
                                                        <i class="fas fa-location-dot text-primary me-1"></i>
                                                        <?php echo htmlspecialchars($ubic); ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal Crear Ubicación -->
    <div class="modal fade" id="crearUbicacionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-plus-circle me-2"></i>Nueva Ubicación
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="sector" class="form-label">Sector *</label>
                            <input type="text" class="form-control" id="sector" name="sector" required 
                                   placeholder="Ej: Piso1, Piso2, CAE, CESAM, etc."
                                   list="sectoresExistentes">
                            <datalist id="sectoresExistentes">
                                <?php foreach ($sectores as $sector): ?>
                                    <option value="<?php echo htmlspecialchars($sector); ?>">
                                <?php endforeach; ?>
                            </datalist>
                            <div class="form-text">Puede seleccionar un sector existente o crear uno nuevo.</div>
                        </div>
                        <div class="mb-3">
                            <label for="ubicacion" class="form-label">Nombre de la Ubicación *</label>
                            <input type="text" class="form-control" id="ubicacion" name="ubicacion" required 
                                   placeholder="Ej: Box 1, Box 2, Cowork, SPD40, etc.">
                            <div class="form-text">Ejemplos: Box 1, Box 2, Cowork, SPD40, SPN2, etc.</div>
                        </div>
                        <div class="mb-3">
                            <label for="activo" class="form-label">Estado</label>
                            <select class="form-select" id="activo" name="activo">
                                <option value="1">Activa</option>
                                <option value="0">Inactiva</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="crear_ubicacion" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Importar CSV -->
    <div class="modal fade" id="importarUbicacionesModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-file-import me-2"></i>Importar Ubicaciones desde CSV
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Formato del archivo CSV:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Columnas: <code>sector, ubicacion, estado</code></li>
                                <li>El estado es opcional (valores: "activa", "inactiva", "1", "0")</li>
                                <li>Delimitadores soportados: coma (,) o punto y coma (;)</li>
                                <li>Primera fila puede ser encabezado (se omitirá automáticamente)</li>
                            </ul>
                        </div>
                        
                        <div class="mb-3">
                            <label for="csv_file" class="form-label">Seleccionar archivo CSV</label>
                            <div class="import-card text-center p-4" id="dropZone">
                                <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-2"></i>
                                <p class="mb-1">Arrastre y suelte su archivo CSV aquí</p>
                                <p class="text-muted small">o</p>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="document.getElementById('csv_file_input').click()">
                                    <i class="fas fa-folder-open me-1"></i>Seleccionar archivo
                                </button>
                                <input type="file" class="d-none" id="csv_file_input" name="csv_file" accept=".csv,.txt">
                                <div id="fileInfo" class="mt-2 small text-muted"></div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="sobrescribir" name="sobrescribir">
                                <label class="form-check-label" for="sobrescribir">
                                    Sobrescribir ubicaciones existentes (mismo sector y nombre)
                                </label>
                                <div class="form-text">Si no marca esta opción, las ubicaciones duplicadas se omitirán.</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="?descargar_plantilla=1" class="btn btn-info">
                            <i class="fas fa-download me-2"></i>Descargar Plantilla
                        </a>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="importar_csv" class="btn btn-success" id="btnImportar" disabled>
                            <i class="fas fa-upload me-2"></i>Importar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar Ubicación -->
    <div class="modal fade" id="editarUbicacionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Editar Ubicación
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" id="edit_id" name="ubicacion_id">
                        <div class="mb-3">
                            <label for="edit_sector" class="form-label">Sector *</label>
                            <input type="text" class="form-control" id="edit_sector" name="sector" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_ubicacion" class="form-label">Nombre de la Ubicación *</label>
                            <input type="text" class="form-control" id="edit_ubicacion" name="ubicacion" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_activo" class="form-label">Estado</label>
                            <select class="form-select" id="edit_activo" name="activo">
                                <option value="1">Activa</option>
                                <option value="0">Inactiva</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="editar_ubicacion" class="btn btn-warning">
                            <i class="fas fa-save me-2"></i>Actualizar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Eliminar -->
    <div class="modal fade" id="eliminarUbicacionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>Confirmar Eliminación
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro que desea eliminar la ubicación <strong id="eliminar_nombre"></strong>?</p>
                    <p class="text-danger">Esta acción no se puede deshacer.</p>
                    <form method="POST" action="">
                        <input type="hidden" id="eliminar_id" name="ubicacion_id">
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" name="eliminar_ubicacion" class="btn btn-danger">
                                <i class="fas fa-trash me-2"></i>Eliminar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Funciones CRUD
        function editarUbicacion(ubicacion) {
            document.getElementById('edit_id').value = ubicacion.id;
            document.getElementById('edit_sector').value = ubicacion.sector;
            document.getElementById('edit_ubicacion').value = ubicacion.ubicacion;
            document.getElementById('edit_activo').value = ubicacion.activo;
            
            new bootstrap.Modal(document.getElementById('editarUbicacionModal')).show();
        }
        
        function eliminarUbicacion(id, nombre) {
            document.getElementById('eliminar_id').value = id;
            document.getElementById('eliminar_nombre').textContent = nombre;
            new bootstrap.Modal(document.getElementById('eliminarUbicacionModal')).show();
        }
        
        // Filtros en tiempo real
        document.addEventListener('DOMContentLoaded', function() {
            const filtroSector = document.getElementById('filtroSector');
            const filtroEstado = document.getElementById('filtroEstado');
            const filtroBusqueda = document.getElementById('filtroBusqueda');
            const btnLimpiarFiltros = document.getElementById('btnLimpiarFiltros');
            const totalFilasSpan = document.getElementById('totalFilas');
            const tabla = document.getElementById('tablaUbicaciones');
            
            function aplicarFiltros() {
                if (!tabla) return;
                
                const filas = tabla.querySelectorAll('tbody tr');
                let filasVisibles = 0;
                
                const sectorFiltro = filtroSector ? filtroSector.value : '';
                const estadoFiltro = filtroEstado ? filtroEstado.value : '';
                const busquedaFiltro = filtroBusqueda ? filtroBusqueda.value.toLowerCase() : '';
                
                filas.forEach(fila => {
                    let mostrar = true;
                    
                    if (sectorFiltro) {
                        const sectorCelda = fila.querySelector('td:nth-child(2) .badge');
                        const sectorTexto = sectorCelda ? sectorCelda.textContent.trim() : '';
                        if (sectorTexto !== sectorFiltro) {
                            mostrar = false;
                        }
                    }
                    
                    if (mostrar && estadoFiltro) {
                        const activo = fila.getAttribute('data-activo');
                        if (activo !== estadoFiltro) {
                            mostrar = false;
                        }
                    }
                    
                    if (mostrar && busquedaFiltro) {
                        const ubicacionCelda = fila.querySelector('td:nth-child(3)');
                        const ubicacionTexto = ubicacionCelda ? ubicacionCelda.textContent.toLowerCase() : '';
                        if (!ubicacionTexto.includes(busquedaFiltro)) {
                            mostrar = false;
                        }
                    }
                    
                    fila.style.display = mostrar ? '' : 'none';
                    if (mostrar) filasVisibles++;
                });
                
                if (totalFilasSpan) {
                    totalFilasSpan.textContent = filasVisibles;
                }
            }
            
            if (filtroSector) filtroSector.addEventListener('change', aplicarFiltros);
            if (filtroEstado) filtroEstado.addEventListener('change', aplicarFiltros);
            if (filtroBusqueda) filtroBusqueda.addEventListener('input', aplicarFiltros);
            
            if (btnLimpiarFiltros) {
                btnLimpiarFiltros.addEventListener('click', function() {
                    if (filtroSector) filtroSector.value = '';
                    if (filtroEstado) filtroEstado.value = '';
                    if (filtroBusqueda) filtroBusqueda.value = '';
                    aplicarFiltros();
                    
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-info alert-dismissible fade show mt-2';
                    alertDiv.innerHTML = `
                        <i class="fas fa-info-circle me-2"></i>Filtros limpiados correctamente
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    const filterSection = document.querySelector('.filter-section');
                    if (filterSection) {
                        filterSection.parentNode.insertBefore(alertDiv, filterSection.nextSibling);
                        setTimeout(() => alertDiv.remove(), 3000);
                    }
                });
            }
            
            // Drag & Drop para importación CSV
            const dropZone = document.getElementById('dropZone');
            const fileInput = document.getElementById('csv_file_input');
            const btnImportar = document.getElementById('btnImportar');
            const fileInfo = document.getElementById('fileInfo');
            
            if (dropZone && fileInput) {
                dropZone.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    this.classList.add('dragover');
                });
                
                dropZone.addEventListener('dragleave', function(e) {
                    e.preventDefault();
                    this.classList.remove('dragover');
                });
                
                dropZone.addEventListener('drop', function(e) {
                    e.preventDefault();
                    this.classList.remove('dragover');
                    const files = e.dataTransfer.files;
                    if (files.length > 0) {
                        fileInput.files = files;
                        actualizarInfoArchivo(files[0]);
                    }
                });
                
                fileInput.addEventListener('change', function() {
                    if (this.files.length > 0) {
                        actualizarInfoArchivo(this.files[0]);
                    }
                });
            }
            
            function actualizarInfoArchivo(file) {
                if (fileInfo && btnImportar) {
                    const extension = file.name.split('.').pop().toLowerCase();
                    if (extension === 'csv' || extension === 'txt') {
                        fileInfo.innerHTML = `<i class="fas fa-check-circle text-success"></i> Archivo seleccionado: ${file.name} (${(file.size / 1024).toFixed(2)} KB)`;
                        fileInfo.className = 'mt-2 small text-success';
                        btnImportar.disabled = false;
                    } else {
                        fileInfo.innerHTML = `<i class="fas fa-exclamation-triangle text-danger"></i> Formato no válido. Use archivos CSV o TXT.`;
                        fileInfo.className = 'mt-2 small text-danger';
                        btnImportar.disabled = true;
                    }
                }
            }
        });
    </script>
</body>
<footer class="text-center py-4">Patricio Corrotea | Gestión de la Demanda</footer>
</html>