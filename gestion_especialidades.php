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

// Procesar formularios
if ($_POST) {
    // Crear especialidad
    if (isset($_POST['crear_especialidad'])) {
        $nombre = trim($_POST['nombre']);
        $descripcion = trim($_POST['descripcion']);
        
        // Validaciones
        if (empty($nombre)) {
            $error = "El nombre de la especialidad es obligatorio";
        } elseif ($auth->especialidadExists($nombre)) {
            $error = "Ya existe una especialidad con ese nombre";
        } else {
            if ($auth->createEspecialidad($nombre, $descripcion)) {
                $success = "Especialidad creada correctamente";
            } else {
                $error = "Error al crear la especialidad";
            }
        }
    }
    
    // Editar especialidad
    if (isset($_POST['editar_especialidad'])) {
        $especialidad_id = $_POST['especialidad_id'];
        $nombre = trim($_POST['nombre']);
        $descripcion = trim($_POST['descripcion']);
        $activo = isset($_POST['activo']) ? 1 : 0;
        
        // Validaciones
        if (empty($nombre)) {
            $error = "El nombre de la especialidad es obligatorio";
        } elseif ($auth->especialidadExists($nombre, $especialidad_id)) {
            $error = "Ya existe una especialidad con ese nombre";
        } else {
            if ($auth->updateEspecialidad($especialidad_id, $nombre, $descripcion, $activo)) {
                $success = "Especialidad actualizada correctamente";
            } else {
                $error = "Error al actualizar la especialidad";
            }
        }
    }
    
    // Eliminar especialidad
    if (isset($_POST['eliminar_especialidad'])) {
        $especialidad_id = $_POST['especialidad_id'];
        
        if ($auth->deleteEspecialidad($especialidad_id)) {
            $success = "Especialidad eliminada correctamente";
        } else {
            $error = "Error al eliminar la especialidad. Asegúrese de que no tenga profesionales asociados.";
        }
    }
}

// Obtener lista de especialidades
$especialidades = $auth->getAllEspecialidades(true); // Incluir inactivas
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="icon" href="img/favicon.png" type="image/png">
    <title>Gestión de Unidades | Gear-HSP</title>
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
        .profesionales-count {
            font-size: 0.8rem;
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
                        <i class="fas fa-briefcase-medical me-2"></i>Gestión de Unidades
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#crearEspecialidadModal">
                            <i class="fas fa-plus me-2"></i>Nueva Unidad
                        </button>
                    </div>
                </div>

                <!-- Estadísticas -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-primary"><?php echo count($especialidades); ?></h3>
                                <p class="mb-0">Total Unidades</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-success"><?php echo count(array_filter($especialidades, function($e) { return $e['activo'] == 1; })); ?></h3>
                                <p class="mb-0">Unidades Activas</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-warning"><?php echo count(array_filter($especialidades, function($e) { return $e['activo'] == 0; })); ?></h3>
                                <p class="mb-0">Unidades Inactivas</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-info"><?php echo array_sum(array_map(function($e) use ($auth) { return $auth->getProfesionalesCountByEspecialidad($e['id']); }, $especialidades)); ?></h3>
                                <p class="mb-0">Total Profesionales</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabla de Especialidades -->
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list me-2"></i>Lista de Unidades
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($especialidades)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-stethoscope fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No hay unidades registradas</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#crearEspecialidadModal">
                                    <i class="fas fa-plus me-2"></i>Crear Primera Unidad
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-striped">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Unidad</th>
                                            <th>Descripción</th>
                                            <th>Profesionales</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($especialidades as $especialidad): 
                                            $profesionales_count = $auth->getProfesionalesCountByEspecialidad($especialidad['id']);
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                              
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($especialidad['nombre']); ?></strong>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if (!empty($especialidad['descripcion'])): ?>
                                                    <small class="text-muted"><?php echo htmlspecialchars($especialidad['descripcion']); ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">- Sin descripción -</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info profesionales-count">
                                                    <?php echo $profesionales_count; ?> profesional(es)
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $especialidad['activo'] ? 'badge-activo' : 'badge-inactivo'; ?>">
                                                    <?php echo $especialidad['activo'] ? 'Activa' : 'Inactiva'; ?>
                                                </span>
                                            </td>
                                            <td class="table-actions">
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary" 
                                                            onclick="editarEspecialidad(<?php echo $especialidad['id']; ?>)"
                                                            title="Editar especialidad">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php if ($profesionales_count == 0): ?>
                                                    <button class="btn btn-outline-danger" 
                                                            onclick="eliminarEspecialidad(<?php echo $especialidad['id']; ?>, '<?php echo htmlspecialchars($especialidad['nombre']); ?>')"
                                                            title="Eliminar especialidad">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    <?php else: ?>
                                                    <button class="btn btn-outline-secondary" 
                                                            title="No se puede eliminar - Tiene profesionales asociados"
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
    <?php include 'includes/modals_especialidades.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script_especialidades.js"></script>
</body>
<footer class="text-center py-4">Patricio Corrotea | Gestión de la Demanda</footer>
</html>