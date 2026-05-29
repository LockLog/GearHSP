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
    // Crear especialidad REM
    if (isset($_POST['crear_especialidad_rem'])) {
        $especialidad_rem = trim($_POST['especialidad_rem']);
        $rendimiento_cn = floatval($_POST['rendimiento_cn']);
        $rendimiento_cr = floatval($_POST['rendimiento_cr']);
        $meta_cn = floatval($_POST['meta_cn']);
        $meta_amb = floatval($_POST['meta_amb']);
        $estado = $_POST['estado'];
        
        // Validaciones
        if (empty($especialidad_rem)) {
            $error = "El nombre de la especialidad REM es obligatorio";
        } elseif (especialidadRemExists($conn, $especialidad_rem)) {
            $error = "Ya existe una especialidad REM con ese nombre";
        } else {
            if (createEspecialidadRem($conn, $especialidad_rem, $rendimiento_cn, $rendimiento_cr, $meta_cn, $meta_amb, $estado)) {
                $success = "Especialidad REM creada correctamente";
            } else {
                $error = "Error al crear la especialidad REM";
            }
        }
    }
    
    // Editar especialidad REM
    if (isset($_POST['editar_especialidad_rem'])) {
        $id = $_POST['especialidad_rem_id'];
        $especialidad_rem = trim($_POST['especialidad_rem']);
        $rendimiento_cn = floatval($_POST['rendimiento_cn']);
        $rendimiento_cr = floatval($_POST['rendimiento_cr']);
        $meta_cn = floatval($_POST['meta_cn']);
        $meta_amb = floatval($_POST['meta_amb']);
        $estado = $_POST['estado'];
        
        // Validaciones
        if (empty($especialidad_rem)) {
            $error = "El nombre de la especialidad REM es obligatorio";
        } elseif (especialidadRemExists($conn, $especialidad_rem, $id)) {
            $error = "Ya existe una especialidad REM con ese nombre";
        } else {
            if (updateEspecialidadRem($conn, $id, $especialidad_rem, $rendimiento_cn, $rendimiento_cr, $meta_cn, $meta_amb, $estado)) {
                $success = "Especialidad REM actualizada correctamente";
            } else {
                $error = "Error al actualizar la especialidad REM";
            }
        }
    }
    
    // Eliminar especialidad REM
    if (isset($_POST['eliminar_especialidad_rem'])) {
        $id = $_POST['especialidad_rem_id'];
        
        if (deleteEspecialidadRem($conn, $id)) {
            $success = "Especialidad REM eliminada correctamente";
        } else {
            $error = "Error al eliminar la especialidad REM";
        }
    }
}

// Funciones auxiliares
function especialidadRemExists($conn, $nombre, $exclude_id = null) {
    $sql = "SELECT COUNT(*) FROM especialidades_rem WHERE especialidad_rem = :nombre";
    $params = [':nombre' => $nombre];
    
    if ($exclude_id) {
        $sql .= " AND id != :id";
        $params[':id'] = $exclude_id;
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn() > 0;
}

function createEspecialidadRem($conn, $nombre, $rend_cn, $rend_cr, $meta_cn, $meta_amb, $estado) {
    $sql = "INSERT INTO especialidades_rem (especialidad_rem, rendimiento_cn, rendimiento_cr, meta_cn, meta_amb, estado) 
            VALUES (:nombre, :rend_cn, :rend_cr, :meta_cn, :meta_amb, :estado)";
    $stmt = $conn->prepare($sql);
    return $stmt->execute([
        ':nombre' => $nombre,
        ':rend_cn' => $rend_cn,
        ':rend_cr' => $rend_cr,
        ':meta_cn' => $meta_cn,
        ':meta_amb' => $meta_amb,
        ':estado' => $estado
    ]);
}

function updateEspecialidadRem($conn, $id, $nombre, $rend_cn, $rend_cr, $meta_cn, $meta_amb, $estado) {
    $sql = "UPDATE especialidades_rem 
            SET especialidad_rem = :nombre, 
                rendimiento_cn = :rend_cn, 
                rendimiento_cr = :rend_cr,
                meta_cn = :meta_cn,
                meta_amb = :meta_amb,
                estado = :estado 
            WHERE id = :id";
    $stmt = $conn->prepare($sql);
    return $stmt->execute([
        ':id' => $id,
        ':nombre' => $nombre,
        ':rend_cn' => $rend_cn,
        ':rend_cr' => $rend_cr,
        ':meta_cn' => $meta_cn,
        ':meta_amb' => $meta_amb,
        ':estado' => $estado
    ]);
}

function deleteEspecialidadRem($conn, $id) {
    $sql = "DELETE FROM especialidades_rem WHERE id = :id";
    $stmt = $conn->prepare($sql);
    return $stmt->execute([':id' => $id]);
}

function getAllEspecialidadesRem($conn, $incluir_inactivas = true) {
    $sql = "SELECT * FROM especialidades_rem";
    if (!$incluir_inactivas) {
        $sql .= " WHERE estado = 'activa'";
    }
    $sql .= " ORDER BY id";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener lista de especialidades REM
$especialidades_rem = getAllEspecialidadesRem($conn, true);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="img/favicon.png" type="image/png">
    <title>Gestión de Especialidades REM | Gear-HSP</title>
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
        .meta-badge {
            font-size: 0.8rem;
        }
        .card-stats {
            transition: transform 0.3s;
        }
        .card-stats:hover {
            transform: translateY(-5px);
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
                        <i class="fas fa-chart-line me-2"></i>Gestión de Especialidades REM
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#crearEspecialidadRemModal">
                            <i class="fas fa-plus me-2"></i>Nueva Especialidad REM
                        </button>
                    </div>
                </div>

                <!-- Estadísticas -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card text-center card-stats shadow-sm">
                            <div class="card-body">
                                <i class="fas fa-stethoscope fa-2x text-primary mb-2"></i>
                                <h3 class="text-primary"><?php echo count($especialidades_rem); ?></h3>
                                <p class="mb-0 text-muted">Total Especialidades</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-center card-stats shadow-sm">
                            <div class="card-body">
                                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                <h3 class="text-success"><?php echo count(array_filter($especialidades_rem, function($e) { return $e['estado'] == 'activa'; })); ?></h3>
                                <p class="mb-0 text-muted">Especialidades Activas</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-center card-stats shadow-sm">
                            <div class="card-body">
                                <i class="fas fa-chart-line fa-2x text-warning mb-2"></i>
                                <h3 class="text-warning"><?php 
                                    $total_cn = array_sum(array_column($especialidades_rem, 'rendimiento_cn'));
                                    echo number_format($total_cn, 1);
                                ?></h3>
                                <p class="mb-0 text-muted">Rendimiento CN Promedio</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-center card-stats shadow-sm">
                            <div class="card-body">
                                <i class="fas fa-chart-bar fa-2x text-info mb-2"></i>
                                <h3 class="text-info"><?php 
                                    $total_cr = array_sum(array_column($especialidades_rem, 'rendimiento_cr'));
                                    echo number_format($total_cr, 1);
                                ?></h3>
                                <p class="mb-0 text-muted">Rendimiento CR Promedio</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabla de Especialidades REM -->
                <div class="card dashboard-card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list me-2"></i>Lista de Especialidades REM
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($especialidades_rem)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-chart-line fa-4x text-muted mb-3"></i>
                                <p class="text-muted">No hay especialidades REM registradas</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#crearEspecialidadRemModal">
                                    <i class="fas fa-plus me-2"></i>Crear Primera Especialidad
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-striped">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Especialidad REM</th>
                                            <th>Rendimiento CN</th>
                                            <th>Rendimiento CR</th>
                                            <th>Meta CN</th>
                                            <th>Meta AMB</th>
                                            <th>Estado</th>
                                            <th>Fecha Creación</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($especialidades_rem as $especialidad): ?>
                                        <tr>
                                            <td><?php echo $especialidad['id']; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($especialidad['especialidad_rem']); ?></strong>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="progress flex-grow-1 me-2" style="height: 8px; width: 80px;">
                                                        <div class="progress-bar bg-success" role="progressbar" 
                                                             style="width: <?php echo $especialidad['rendimiento_cn']; ?>"
                                                             aria-valuenow="<?php echo $especialidad['rendimiento_cn']; ?>" 
                                                             aria-valuemin="0" aria-valuemax="100"></div>
                                                    </div>
                                                    <span><?php echo number_format($especialidad['rendimiento_cn'], 1); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="progress flex-grow-1 me-2" style="height: 8px; width: 80px;">
                                                        <div class="progress-bar bg-info" role="progressbar" 
                                                             style="width: <?php echo $especialidad['rendimiento_cr']; ?>"
                                                             aria-valuenow="<?php echo $especialidad['rendimiento_cr']; ?>" 
                                                             aria-valuemin="0" aria-valuemax="100"></div>
                                                    </div>
                                                    <span><?php echo number_format($especialidad['rendimiento_cr'], 1); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary meta-badge">
                                                    <?php echo number_format($especialidad['meta_cn'], 1); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary meta-badge">
                                                    <?php echo number_format($especialidad['meta_amb'], 1); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $especialidad['estado'] == 'activa' ? 'badge-activa' : 'badge-inactiva'; ?>">
                                                    <i class="fas <?php echo $especialidad['estado'] == 'activa' ? 'fa-check-circle' : 'fa-circle'; ?> me-1"></i>
                                                    <?php echo ucfirst($especialidad['estado']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo date('d/m/Y H:i', strtotime($especialidad['created_at'])); ?>
                                                </small>
                                            </td>
                                            <td class="table-actions">
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary" 
                                                            onclick='editarEspecialidadRem(<?php echo json_encode($especialidad); ?>)'
                                                            title="Editar especialidad">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-outline-danger" 
                                                            onclick="eliminarEspecialidadRem(<?php echo $especialidad['id']; ?>, '<?php echo htmlspecialchars($especialidad['especialidad_rem']); ?>')"
                                                            title="Eliminar especialidad">
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

    <!-- Modales -->
    <!-- Modal Crear Especialidad REM -->
    <div class="modal fade" id="crearEspecialidadRemModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-plus-circle me-2"></i>Nueva Especialidad REM
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="especialidad_rem" class="form-label">Nombre Especialidad REM *</label>
                            <input type="text" class="form-control" id="especialidad_rem" name="especialidad_rem" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="rendimiento_cn" class="form-label">Rendimiento CN</label>
                                <input type="number" step="0.1" class="form-control" id="rendimiento_cn" name="rendimiento_cn" value="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="rendimiento_cr" class="form-label">Rendimiento CR</label>
                                <input type="number" step="0.1" class="form-control" id="rendimiento_cr" name="rendimiento_cr" value="0" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="meta_cn" class="form-label">Meta CN (%)</label>
                                <input type="number" step="0.1" class="form-control" id="meta_cn" name="meta_cn" value="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="meta_amb" class="form-label">Meta AMB (%)</label>
                                <input type="number" step="0.1" class="form-control" id="meta_amb" name="meta_amb" value="0" required>
                            </div>
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
                        <button type="submit" name="crear_especialidad_rem" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar Especialidad REM -->
    <div class="modal fade" id="editarEspecialidadRemModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Editar Especialidad REM
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" id="edit_id" name="especialidad_rem_id">
                        <div class="mb-3">
                            <label for="edit_especialidad_rem" class="form-label">Nombre Especialidad REM *</label>
                            <input type="text" class="form-control" id="edit_especialidad_rem" name="especialidad_rem" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_rendimiento_cn" class="form-label">Rendimiento CN</label>
                                <input type="number" step="0.1" class="form-control" id="edit_rendimiento_cn" name="rendimiento_cn" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_rendimiento_cr" class="form-label">Rendimiento CR</label>
                                <input type="number" step="0.1" class="form-control" id="edit_rendimiento_cr" name="rendimiento_cr" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_meta_cn" class="form-label">Meta CN (%)</label>
                                <input type="number" step="0.1" class="form-control" id="edit_meta_cn" name="meta_cn" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_meta_amb" class="form-label">Meta AMB (%)</label>
                                <input type="number" step="0.1" class="form-control" id="edit_meta_amb" name="meta_amb" required>
                            </div>
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
                        <button type="submit" name="editar_especialidad_rem" class="btn btn-warning">
                            <i class="fas fa-save me-2"></i>Actualizar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Eliminar -->
    <div class="modal fade" id="eliminarEspecialidadRemModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>Confirmar Eliminación
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro que desea eliminar la especialidad REM <strong id="eliminar_nombre"></strong>?</p>
                    <p class="text-danger">Esta acción no se puede deshacer.</p>
                    <form method="POST" action="">
                        <input type="hidden" id="eliminar_id" name="especialidad_rem_id">
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" name="eliminar_especialidad_rem" class="btn btn-danger">
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
        function editarEspecialidadRem(especialidad) {
            document.getElementById('edit_id').value = especialidad.id;
            document.getElementById('edit_especialidad_rem').value = especialidad.especialidad_rem;
            document.getElementById('edit_rendimiento_cn').value = especialidad.rendimiento_cn;
            document.getElementById('edit_rendimiento_cr').value = especialidad.rendimiento_cr;
            document.getElementById('edit_meta_cn').value = especialidad.meta_cn;
            document.getElementById('edit_meta_amb').value = especialidad.meta_amb;
            document.getElementById('edit_estado').value = especialidad.estado;
            
            new bootstrap.Modal(document.getElementById('editarEspecialidadRemModal')).show();
        }
        
        function eliminarEspecialidadRem(id, nombre) {
            document.getElementById('eliminar_id').value = id;
            document.getElementById('eliminar_nombre').textContent = nombre;
            new bootstrap.Modal(document.getElementById('eliminarEspecialidadRemModal')).show();
        }
    </script>
</body>
<footer class="text-center py-4">Patricio Corrotea | Gestión de la Demanda</footer>
</html>