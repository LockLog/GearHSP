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
    // Crear actividad
    if (isset($_POST['crear_actividad'])) {
        $actividad = trim($_POST['actividad']);
        $clasificacion = trim($_POST['clasificacion']);
        $tipo = trim($_POST['tipo']);
        $estado = $_POST['estado'];
        
        // Validaciones
        if (empty($actividad)) {
            $error = "El nombre de la actividad es obligatorio";
        } elseif (empty($clasificacion)) {
            $error = "La clasificación es obligatoria";
        } elseif (empty($tipo)) {
            $error = "El tipo es obligatorio";
        } elseif (actividadExists($conn, $actividad)) {
            $error = "Ya existe una actividad con ese nombre";
        } else {
            if (createActividad($conn, $actividad, $clasificacion, $tipo, $estado)) {
                $success = "Actividad creada correctamente";
            } else {
                $error = "Error al crear la actividad";
            }
        }
    }
    
    // Editar actividad
    if (isset($_POST['editar_actividad'])) {
        $id = $_POST['actividad_id'];
        $actividad = trim($_POST['actividad']);
        $clasificacion = trim($_POST['clasificacion']);
        $tipo = trim($_POST['tipo']);
        $estado = $_POST['estado'];
        
        // Validaciones
        if (empty($actividad)) {
            $error = "El nombre de la actividad es obligatorio";
        } elseif (empty($clasificacion)) {
            $error = "La clasificación es obligatoria";
        } elseif (empty($tipo)) {
            $error = "El tipo es obligatorio";
        } elseif (actividadExists($conn, $actividad, $id)) {
            $error = "Ya existe una actividad con ese nombre";
        } else {
            if (updateActividad($conn, $id, $actividad, $clasificacion, $tipo, $estado)) {
                $success = "Actividad actualizada correctamente";
            } else {
                $error = "Error al actualizar la actividad";
            }
        }
    }
    
    // Eliminar actividad
    if (isset($_POST['eliminar_actividad'])) {
        $id = $_POST['actividad_id'];
        
        if (deleteActividad($conn, $id)) {
            $success = "Actividad eliminada correctamente";
        } else {
            $error = "Error al eliminar la actividad";
        }
    }
}

// Funciones auxiliares
function actividadExists($conn, $nombre, $exclude_id = null) {
    $sql = "SELECT COUNT(*) FROM actividades WHERE actividad = :nombre";
    $params = [':nombre' => $nombre];
    
    if ($exclude_id) {
        $sql .= " AND id != :id";
        $params[':id'] = $exclude_id;
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn() > 0;
}

function createActividad($conn, $actividad, $clasificacion, $tipo, $estado) {
    $sql = "INSERT INTO actividades (actividad, clasificacion, tipo, estado) 
            VALUES (:actividad, :clasificacion, :tipo, :estado)";
    $stmt = $conn->prepare($sql);
    return $stmt->execute([
        ':actividad' => $actividad,
        ':clasificacion' => $clasificacion,
        ':tipo' => $tipo,
        ':estado' => $estado
    ]);
}

function updateActividad($conn, $id, $actividad, $clasificacion, $tipo, $estado) {
    $sql = "UPDATE actividades 
            SET actividad = :actividad, 
                clasificacion = :clasificacion, 
                tipo = :tipo,
                estado = :estado 
            WHERE id = :id";
    $stmt = $conn->prepare($sql);
    return $stmt->execute([
        ':id' => $id,
        ':actividad' => $actividad,
        ':clasificacion' => $clasificacion,
        ':tipo' => $tipo,
        ':estado' => $estado
    ]);
}

function deleteActividad($conn, $id) {
    $sql = "DELETE FROM actividades WHERE id = :id";
    $stmt = $conn->prepare($sql);
    return $stmt->execute([':id' => $id]);
}

function getAllActividades($conn, $incluir_inactivas = true) {
    $sql = "SELECT * FROM actividades";
    if (!$incluir_inactivas) {
        $sql .= " WHERE estado = 'activa'";
    }
    $sql .= " ORDER BY id";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener lista de actividades
$actividades = getAllActividades($conn, true);

// Obtener estadísticas por clasificación y tipo
$clasificaciones = [];
$tipos = [];
foreach ($actividades as $actividad) {
    if (!in_array($actividad['clasificacion'], $clasificaciones)) {
        $clasificaciones[] = $actividad['clasificacion'];
    }
    if (!in_array($actividad['tipo'], $tipos)) {
        $tipos[] = $actividad['tipo'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="img/favicon.png" type="image/png">
    <title>Gestión de Actividades | Gear-HSP</title>
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
        .badge-clasificacion {
            font-size: 0.75rem;
            padding: 0.35rem 0.65rem;
        }
        .badge-tipo {
            font-size: 0.75rem;
            padding: 0.35rem 0.65rem;
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
                        <i class="fas fa-tasks me-2"></i>Gestión de Actividades
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#crearActividadModal">
                            <i class="fas fa-plus me-2"></i>Nueva Actividad
                        </button>
                    </div>
                </div>

                <!-- Estadísticas -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card text-center card-stats shadow-sm">
                            <div class="card-body">
                                <i class="fas fa-tasks fa-2x text-primary mb-2"></i>
                                <h3 class="text-primary"><?php echo count($actividades); ?></h3>
                                <p class="mb-0 text-muted">Total Actividades</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-center card-stats shadow-sm">
                            <div class="card-body">
                                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                <h3 class="text-success"><?php echo count(array_filter($actividades, function($a) { return $a['estado'] == 'activa'; })); ?></h3>
                                <p class="mb-0 text-muted">Actividades Activas</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-center card-stats shadow-sm">
                            <div class="card-body">
                                <i class="fas fa-tags fa-2x text-warning mb-2"></i>
                                <h3 class="text-warning"><?php echo count($clasificaciones); ?></h3>
                                <p class="mb-0 text-muted">Clasificaciones</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-center card-stats shadow-sm">
                            <div class="card-body">
                                <i class="fas fa-layer-group fa-2x text-info mb-2"></i>
                                <h3 class="text-info"><?php echo count($tipos); ?></h3>
                                <p class="mb-0 text-muted">Tipos de Actividad</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabla de Actividades -->
                <div class="card dashboard-card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list me-2"></i>Lista de Actividades
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($actividades)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-tasks fa-4x text-muted mb-3"></i>
                                <p class="text-muted">No hay actividades registradas</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#crearActividadModal">
                                    <i class="fas fa-plus me-2"></i>Crear Primera Actividad
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-striped">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Actividad</th>
                                            <th>Clasificación</th>
                                            <th>Tipo</th>
                                            <th>Estado</th>
                                            <th>Fecha Creación</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($actividades as $actividad): ?>
                                        <tr>
                                            <td><?php echo $actividad['id']; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($actividad['actividad']); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge bg-info badge-clasificacion">
                                                    <i class="fas fa-tag me-1"></i>
                                                    <?php echo htmlspecialchars($actividad['clasificacion']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary badge-tipo">
                                                    <i class="fas fa-layer-group me-1"></i>
                                                    <?php echo htmlspecialchars($actividad['tipo']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $actividad['estado'] == 'activa' ? 'badge-activa' : 'badge-inactiva'; ?>">
                                                    <i class="fas <?php echo $actividad['estado'] == 'activa' ? 'fa-check-circle' : 'fa-circle'; ?> me-1"></i>
                                                    <?php echo ucfirst($actividad['estado']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo date('d/m/Y H:i', strtotime($actividad['created_at'])); ?>
                                                </small>
                                            </td>
                                            <td class="table-actions">
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary" 
                                                            onclick='editarActividad(<?php echo json_encode($actividad); ?>)'
                                                            title="Editar actividad">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-outline-danger" 
                                                            onclick="eliminarActividad(<?php echo $actividad['id']; ?>, '<?php echo htmlspecialchars($actividad['actividad']); ?>')"
                                                            title="Eliminar actividad">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
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

    <!-- Modal Crear Actividad -->
    <div class="modal fade" id="crearActividadModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-plus-circle me-2"></i>Nueva Actividad
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="actividad" class="form-label">Nombre de la Actividad *</label>
                            <input type="text" class="form-control" id="actividad" name="actividad" required 
                                   placeholder="Ej: Consulta médica, Procedimiento, etc.">
                        </div>
                        <div class="mb-3">
                            <label for="clasificacion" class="form-label">Clasificación *</label>
                            <input type="text" class="form-control" id="clasificacion" name="clasificacion" required
                                   placeholder="Ej: Asistencial, Administrativa, Docencia">
                        </div>
                        <div class="mb-3">
                            <label for="tipo" class="form-label">Tipo *</label>
                            <input type="text" class="form-control" id="tipo" name="tipo" required
                                   placeholder="Ej: Urgencia, Hospitalización, Ambulatorio">
                        </div>
                        <div class="mb-3">
                            <label for="estado" class="form-label">Estado</label>
                            <select class="form-select" id="estado" name="estado">
                                <option value="activa">Activa</option>
                                <option value="inactiva">Inactiva</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="crear_actividad" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar Actividad -->
    <div class="modal fade" id="editarActividadModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Editar Actividad
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" id="edit_id" name="actividad_id">
                        <div class="mb-3">
                            <label for="edit_actividad" class="form-label">Nombre de la Actividad *</label>
                            <input type="text" class="form-control" id="edit_actividad" name="actividad" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_clasificacion" class="form-label">Clasificación *</label>
                            <input type="text" class="form-control" id="edit_clasificacion" name="clasificacion" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_tipo" class="form-label">Tipo *</label>
                            <input type="text" class="form-control" id="edit_tipo" name="tipo" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_estado" class="form-label">Estado</label>
                            <select class="form-select" id="edit_estado" name="estado">
                                <option value="activa">Activa</option>
                                <option value="inactiva">Inactiva</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="editar_actividad" class="btn btn-warning">
                            <i class="fas fa-save me-2"></i>Actualizar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Eliminar -->
    <div class="modal fade" id="eliminarActividadModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>Confirmar Eliminación
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro que desea eliminar la actividad <strong id="eliminar_nombre"></strong>?</p>
                    <p class="text-danger">Esta acción no se puede deshacer.</p>
                    <form method="POST" action="">
                        <input type="hidden" id="eliminar_id" name="actividad_id">
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" name="eliminar_actividad" class="btn btn-danger">
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
        function editarActividad(actividad) {
            document.getElementById('edit_id').value = actividad.id;
            document.getElementById('edit_actividad').value = actividad.actividad;
            document.getElementById('edit_clasificacion').value = actividad.clasificacion;
            document.getElementById('edit_tipo').value = actividad.tipo;
            document.getElementById('edit_estado').value = actividad.estado;
            
            new bootstrap.Modal(document.getElementById('editarActividadModal')).show();
        }
        
        function eliminarActividad(id, nombre) {
            document.getElementById('eliminar_id').value = id;
            document.getElementById('eliminar_nombre').textContent = nombre;
            new bootstrap.Modal(document.getElementById('eliminarActividadModal')).show();
        }
    </script>
</body>
<footer class="text-center py-4">Patricio Corrotea | Gestión de la Demanda</footer>
</html>