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

// Obtener lista de especialidades para los formularios
$especialidades = $auth->getAllEspecialidades();

// Procesar filtros
$filtro_profesional = $_GET['filtro_profesional'] ?? '';
$filtro_especialidad = $_GET['filtro_especialidad'] ?? '';

// Procesar formularios
if ($_POST) {
    // Crear profesional
    if (isset($_POST['crear_profesional'])) {
        $nombre = trim($_POST['nombre']);
		$rut = trim($_POST['rut']);
		$estamento = trim($_POST['estamento']);
        $especialidades_seleccionadas = $_POST['especialidades'] ?? [];
        $especialidad_principal_id = $_POST['especialidad_principal'] ?? null;
        
        // Validaciones
        if (empty($nombre)) {
            $error = "El nombre del profesional es obligatorio";
        } elseif (empty($especialidades_seleccionadas)) {
            $error = "Debe seleccionar al menos una unidad";
        } elseif ($auth->profesionalExists($nombre)) {
            $error = "Ya existe un profesional con ese nombre";
        } else {
            $profesional_id = $auth->createProfesional($nombre, $rut, $estamento, $especialidades_seleccionadas, $especialidad_principal_id);
            if ($profesional_id) {
                $success = "Profesional creado correctamente";
            } else {
                $error = "Error al crear el profesional";
            }
        }
    }
    
    // Editar profesional
    if (isset($_POST['editar_profesional'])) {
        $profesional_id = $_POST['profesional_id'];
        $nombre = trim($_POST['nombre']);
		$rut = trim($_POST['rut']);
		$estamento = trim($_POST['estamento']);
        $especialidades_seleccionadas = $_POST['especialidades'] ?? [];
        $especialidad_principal_id = $_POST['especialidad_principal'] ?? null;
        $activo = isset($_POST['activo']) ? 1 : 0;
        
        // Validaciones
        if (empty($nombre)) {
            $error = "El nombre del profesional es obligatorio";
        } elseif (empty($especialidades_seleccionadas)) {
            $error = "Debe seleccionar al menos una especialidad";
        } elseif ($auth->profesionalExists($nombre, $profesional_id)) {
            $error = "Ya existe un profesional con ese nombre";
        } else {
            if ($auth->updateProfesional($profesional_id, $nombre, $rut, $estamento, $especialidades_seleccionadas, $especialidad_principal_id, $activo)) {
                $success = "Profesional actualizado correctamente";
            } else {
                $error = "Error al actualizar el profesional";
            }
        }
    }
    
    // Eliminar profesional
    if (isset($_POST['eliminar_profesional'])) {
        $profesional_id = $_POST['profesional_id'];
        
        if ($auth->deleteProfesional($profesional_id)) {
            $success = "Profesional eliminado correctamente";
        } else {
            $error = "Error al eliminar el profesional. Asegúrese de que no tenga ausencias registradas.";
        }
    }
}

// Obtener lista de profesionales con filtros aplicados
$profesionales = $auth->getAllProfesionales(true); // Incluir inactivos

// Aplicar filtros si existen
if (!empty($filtro_profesional) || !empty($filtro_especialidad)) {
    $profesionales = array_filter($profesionales, function($profesional) use ($filtro_profesional, $filtro_especialidad) {
        $cumple_filtro_profesional = true;
        $cumple_filtro_especialidad = true;
        
        // Filtrar por nombre del profesional
        if (!empty($filtro_profesional)) {
            $cumple_filtro_profesional = stripos($profesional['nombre'], $filtro_profesional) !== false;
        }
        
        // Filtrar por especialidad
        if (!empty($filtro_especialidad)) {
            $cumple_filtro_especialidad = false;
            foreach ($profesional['especialidades'] as $especialidad) {
                if (stripos($especialidad['nombre'], $filtro_especialidad) !== false) {
                    $cumple_filtro_especialidad = true;
                    break;
                }
            }
        }
        
        return $cumple_filtro_profesional && $cumple_filtro_especialidad;
    });
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="icon" href="img/favicon.png" type="image/png">
    <title>Gestión de Profesionales - Sistema de Ausencias</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .table-actions {
            white-space: nowrap;
        }
        .badge-activo {
            background-color: #28a745;
        }
        .badge-inactivo {
            background-color: #6c757d;
        }
        .profesional-avatar {
			width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #2c3e50;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .especialidad-badge {
            font-size: 0.75rem;
            margin: 1px;
        }
        .especialidad-principal {
            background-color: #007bff !important;
        }
        .especialidades-container {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 10px;
        }
        .especialidad-checkbox {
            margin-bottom: 5px;
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

                <!-- Header -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-user-md me-2"></i>Gestión de Profesionales
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#crearProfesionalModal">
                            <i class="fas fa-plus me-2"></i>Nuevo Profesional
                        </button>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="filter-section">
                    <h6 class="filter-header">
                        <i class="fas fa-filter me-2"></i>Filtrar Profesionales
                    </h6>
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="filtro_profesional" class="form-label">Buscar por Profesional</label>
                            <input type="text" class="form-control" id="filtro_profesional" name="filtro_profesional" 
                                   value="<?php echo htmlspecialchars($filtro_profesional); ?>" 
                                   placeholder="Ingrese nombre del profesional...">
                        </div>
                        <div class="col-md-4">
                            <label for="filtro_especialidad" class="form-label">Buscar por Unidad | Servicio</label>
                            <input type="text" class="form-control" id="filtro_especialidad" name="filtro_especialidad" 
                                   value="<?php echo htmlspecialchars($filtro_especialidad); ?>" 
                                   placeholder="Ingrese nombre de especialidad...">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="btn-group w-100">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i>Filtrar
                                </button>
                                <?php if (!empty($filtro_profesional) || !empty($filtro_especialidad)): ?>
                                    <a href="gestion_profesionales.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-2"></i>Limpiar
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                    <?php if (!empty($filtro_profesional) || !empty($filtro_especialidad)): ?>
                        <div class="mt-2">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Mostrando <?php echo count($profesionales); ?> profesional(es) 
                                <?php if (!empty($filtro_profesional)): ?>
                                    filtrado(s) por profesional: "<strong><?php echo htmlspecialchars($filtro_profesional); ?></strong>"
                                <?php endif; ?>
                                <?php if (!empty($filtro_especialidad)): ?>
                                    <?php if (!empty($filtro_profesional)): ?>y<?php endif; ?>
                                    filtrado(s) por especialidad: "<strong><?php echo htmlspecialchars($filtro_especialidad); ?></strong>"
                                <?php endif; ?>
                            </small>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Estadísticas -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-primary"><?php echo count($profesionales); ?></h3>
                                <p class="mb-0">Profesionales <?php echo (!empty($filtro_profesional) || !empty($filtro_especialidad)) ? 'Filtrados' : 'Totales'; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-success"><?php echo count(array_filter($profesionales, function($p) { return $p['activo'] == 1; })); ?></h3>
                                <p class="mb-0">Profesionales Activos</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-warning"><?php echo count(array_filter($profesionales, function($p) { return $p['activo'] == 0; })); ?></h3>
                                <p class="mb-0">Profesionales Inactivos</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-info"><?php echo array_sum(array_map(function($p) use ($auth) { return $auth->getAusenciasCountByProfesional($p['id']); }, $profesionales)); ?></h3>
                                <p class="mb-0">Total Ausencias</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabla de Profesionales -->
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list me-2"></i>Lista de Profesionales
                            <?php if (!empty($filtro_profesional) || !empty($filtro_especialidad)): ?>
                                <span class="badge bg-primary ms-2">Filtrados</span>
                            <?php endif; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($profesionales)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-user-md fa-3x text-muted mb-3"></i>
                                <p class="text-muted">
                                    <?php if (!empty($filtro_profesional) || !empty($filtro_especialidad)): ?>
                                        No se encontraron profesionales que coincidan con los criterios de búsqueda
                                    <?php else: ?>
                                        No hay profesionales registrados
                                    <?php endif; ?>
                                </p>
                                <?php if (!empty($filtro_profesional) || !empty($filtro_especialidad)): ?>
                                    <a href="gestion_profesionales.php" class="btn btn-outline-primary me-2">
                                        <i class="fas fa-times me-2"></i>Limpiar Filtros
                                    </a>
                                <?php endif; ?>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#crearProfesionalModal">
                                    <i class="fas fa-plus me-2"></i>Registrar Profesional
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-striped">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Profesional</th>
											<th>Rut</th>
											<th>Estamento</th>
                                            <th>Unidades</th>
                                            <th>Unidad Principal</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($profesionales as $profesional): 
                                            $ausencias_count = $auth->getAusenciasCountByProfesional($profesional['id']);
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="profesional-avatar me-3">
                                                        <?php echo strtoupper(substr($profesional['nombre'], 0, 2)); ?>
                                                    </div>
                                                    <div>
                                                        <label><?php echo htmlspecialchars($profesional['nombre']); ?></label>
                                                        <?php if ($ausencias_count > 0): ?>
                                                            <br>
                                                            <small class="text-muted"><?php echo $ausencias_count; ?> ausencia(s)</small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
											<td>
                                                <?php if ($profesional['rut']): ?>
                                                    <label>
                                                        <?php echo htmlspecialchars($profesional['rut']); ?>
                                                    </label>
                                                <?php else: ?>
                                                    <span class="text-muted">- No registrado -</span>
                                                <?php endif; ?>
                                            </td>
											<td>
                                                <?php if ($profesional['estamento']): ?>
                                                    <label>
                                                        <?php echo htmlspecialchars($profesional['estamento']); ?>
                                                    </label>
                                                <?php else: ?>
                                                    <span class="text-muted">- No registrado -</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($profesional['especialidades'])): ?>
                                                    <div class="d-flex flex-wrap">
                                                        <?php foreach ($profesional['especialidades'] as $especialidad): 
                                                            $esPrincipal = ($profesional['especialidad_principal_id'] == $especialidad['id']);
                                                        ?>
                                                        <span class="badge especialidad-badge <?php echo $esPrincipal ? 'especialidad-principal' : 'bg-secondary'; ?>">
                                                            <?php echo htmlspecialchars($especialidad['nombre']); ?>
                                                            <?php if ($esPrincipal): ?>
                                                                <i class="fas fa-star ms-1" style="font-size: 0.6rem;"></i>
                                                            <?php endif; ?>
                                                        </span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">- Sin especialidades -</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($profesional['especialidad_principal_nombre']): ?>
                                                    <span class="badge bg-primary">
                                                        <?php echo htmlspecialchars($profesional['especialidad_principal_nombre']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">- No asignada -</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $profesional['activo'] ? 'badge-activo' : 'badge-inactivo'; ?>">
                                                    <?php echo $profesional['activo'] ? 'Activo' : 'Inactivo'; ?>
                                                </span>
                                            </td>
                                            <td class="table-actions">
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary" 
                                                            onclick="editarProfesional(<?php echo $profesional['id']; ?>, event)"
                                                            title="Editar profesional">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php if ($ausencias_count == 0): ?>
                                                    <button class="btn btn-outline-danger" 
                                                            onclick="eliminarProfesional(<?php echo $profesional['id']; ?>, '<?php echo htmlspecialchars($profesional['nombre']); ?>')"
                                                            title="Eliminar profesional">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    <?php else: ?>
                                                    <button class="btn btn-outline-secondary" 
                                                            title="No se puede eliminar - Tiene ausencias registradas"
                                                            disabled>
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modales -->
    <?php include 'includes/modals_profesionales.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script_profesionales.js"></script>
</body>
</html>