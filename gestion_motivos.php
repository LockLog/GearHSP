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
    // Crear motivo
    if (isset($_POST['crear_motivo'])) {
        $motivo = trim($_POST['motivo']);
        $clasificacion = trim($_POST['clasificacion']);
        $tipo = trim($_POST['tipo']);
        $estado = $_POST['estado'];
        
        // Validaciones
        if (empty($motivo)) {
            $error = "El nombre del motivo es obligatorio";
        } elseif (empty($clasificacion)) {
            $error = "La clasificación es obligatoria";
        } elseif (empty($tipo)) {
            $error = "El tipo es obligatorio";
        } elseif (motivoExists($conn, $motivo)) {
            $error = "Ya existe un motivo con ese nombre";
        } else {
            if (createMotivo($conn, $motivo, $clasificacion, $tipo, $estado)) {
                $success = "Motivo creado correctamente";
            } else {
                $error = "Error al crear el motivo";
            }
        }
    }
    
    // Editar motivo
    if (isset($_POST['editar_motivo'])) {
        $id = $_POST['motivo_id'];
        $motivo = trim($_POST['motivo']);
        $clasificacion = trim($_POST['clasificacion']);
        $tipo = trim($_POST['tipo']);
        $estado = $_POST['estado'];
        
        // Validaciones
        if (empty($motivo)) {
            $error = "El nombre del motivo es obligatorio";
        } elseif (empty($clasificacion)) {
            $error = "La clasificación es obligatoria";
        } elseif (empty($tipo)) {
            $error = "El tipo es obligatorio";
        } elseif (motivoExists($conn, $motivo, $id)) {
            $error = "Ya existe un motivo con ese nombre";
        } else {
            if (updateMotivo($conn, $id, $motivo, $clasificacion, $tipo, $estado)) {
                $success = "Motivo actualizado correctamente";
            } else {
                $error = "Error al actualizar el motivo";
            }
        }
    }
    
    // Eliminar motivo
    if (isset($_POST['eliminar_motivo'])) {
        $id = $_POST['motivo_id'];
        
        if (deleteMotivo($conn, $id)) {
            $success = "Motivo eliminado correctamente";
        } else {
            $error = "Error al eliminar el motivo";
        }
    }
}

// Funciones auxiliares
function motivoExists($conn, $nombre, $exclude_id = null) {
    $sql = "SELECT COUNT(*) FROM motivos WHERE motivo = :nombre";
    $params = [':nombre' => $nombre];
    
    if ($exclude_id) {
        $sql .= " AND id != :id";
        $params[':id'] = $exclude_id;
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn() > 0;
}

function createMotivo($conn, $motivo, $clasificacion, $tipo, $estado) {
    $sql = "INSERT INTO motivos (motivo, clasificacion, tipo, estado) 
            VALUES (:motivo, :clasificacion, :tipo, :estado)";
    $stmt = $conn->prepare($sql);
    return $stmt->execute([
        ':motivo' => $motivo,
        ':clasificacion' => $clasificacion,
        ':tipo' => $tipo,
        ':estado' => $estado
    ]);
}

function updateMotivo($conn, $id, $motivo, $clasificacion, $tipo, $estado) {
    $sql = "UPDATE motivos 
            SET motivo = :motivo, 
                clasificacion = :clasificacion, 
                tipo = :tipo,
                estado = :estado 
            WHERE id = :id";
    $stmt = $conn->prepare($sql);
    return $stmt->execute([
        ':id' => $id,
        ':motivo' => $motivo,
        ':clasificacion' => $clasificacion,
        ':tipo' => $tipo,
        ':estado' => $estado
    ]);
}

function deleteMotivo($conn, $id) {
    $sql = "DELETE FROM motivos WHERE id = :id";
    $stmt = $conn->prepare($sql);
    return $stmt->execute([':id' => $id]);
}

function getAllMotivos($conn, $incluir_inactivos = true) {
    $sql = "SELECT * FROM motivos";
    if (!$incluir_inactivos) {
        $sql .= " WHERE estado = 'activo'";
    }
    $sql .= " ORDER BY id";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener lista de motivos
$motivos = getAllMotivos($conn, true);

// Obtener estadísticas por clasificación y tipo
$clasificaciones = [];
$tipos = [];
foreach ($motivos as $motivo) {
    if (!in_array($motivo['clasificacion'], $clasificaciones)) {
        $clasificaciones[] = $motivo['clasificacion'];
    }
    if (!in_array($motivo['tipo'], $tipos)) {
        $tipos[] = $motivo['tipo'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="img/favicon.png" type="image/png">
    <title>Gestión de Motivos | Gear-HSP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .content-area {
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s;
        }
        .table-actions {
            white-space: nowrap;
        }
        .badge-activo {
            background-color: #28a745;
        }
        .badge-inactivo {
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
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -250px;
            }
            .sidebar.active {
                margin-left: 0;
            }
            .content-area {
                margin-left: 0;
            }
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
                        <i class="fas fa-calendar-times me-2"></i>Gestión de Motivos de Ausencia
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#crearMotivoModal">
                            <i class="fas fa-plus me-2"></i>Nuevo Motivo
                        </button>
                    </div>
                </div>

                <!-- Estadísticas -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card text-center card-stats shadow-sm">
                            <div class="card-body">
                                <i class="fas fa-list fa-2x text-primary mb-2"></i>
                                <h3 class="text-primary"><?php echo count($motivos); ?></h3>
                                <p class="mb-0 text-muted">Total Motivos</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-center card-stats shadow-sm">
                            <div class="card-body">
                                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                <h3 class="text-success"><?php echo count(array_filter($motivos, function($a) { return $a['estado'] == 'activo'; })); ?></h3>
                                <p class="mb-0 text-muted">Motivos Activos</p>
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
                                <p class="mb-0 text-muted">Tipos de Motivo</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabla de Motivos -->
                <div class="card dashboard-card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list me-2"></i>Lista de Motivos de Ausencia
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($motivos)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                                <p class="text-muted">No hay motivos registrados</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#crearMotivoModal">
                                    <i class="fas fa-plus me-2"></i>Crear Primer Motivo
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-striped">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Motivo</th>
                                            <th>Clasificación</th>
                                            <th>Tipo</th>
                                            <th>Estado</th>
                                            <th>Fecha Creación</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($motivos as $motivo): ?>
                                        <tr>
                                            <td><?php echo $motivo['id']; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($motivo['motivo']); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge bg-info badge-clasificacion">
                                                    <i class="fas fa-tag me-1"></i>
                                                    <?php echo htmlspecialchars($motivo['clasificacion']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary badge-tipo">
                                                    <i class="fas fa-layer-group me-1"></i>
                                                    <?php echo htmlspecialchars($motivo['tipo']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $motivo['estado'] == 'activo' ? 'badge-activo' : 'badge-inactivo'; ?>">
                                                    <i class="fas <?php echo $motivo['estado'] == 'activo' ? 'fa-check-circle' : 'fa-circle'; ?> me-1"></i>
                                                    <?php echo ucfirst($motivo['estado']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo date('d/m/Y H:i', strtotime($motivo['created_at'])); ?>
                                                </small>
                                            </td>
                                            <td class="table-actions">
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary" 
                                                            onclick='editarMotivo(<?php echo json_encode($motivo); ?>)'
                                                            title="Editar motivo">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-outline-danger" 
                                                            onclick="eliminarMotivo(<?php echo $motivo['id']; ?>, '<?php echo htmlspecialchars($motivo['motivo']); ?>')"
                                                            title="Eliminar motivo">
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

    <!-- Modal Crear Motivo -->
    <div class="modal fade" id="crearMotivoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-plus-circle me-2"></i>Nuevo Motivo de Ausencia
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="motivo" class="form-label">Nombre del Motivo *</label>
                            <input type="text" class="form-control" id="motivo" name="motivo" required 
                                   placeholder="Ej: Permiso, Vacaciones, Licencia médica, etc.">
                        </div>
                        <div class="mb-3">
                            <label for="clasificacion" class="form-label">Clasificación *</label>
                            <input type="text" class="form-control" id="clasificacion" name="clasificacion" required
                                   placeholder="Ej: Administrativo, Médico, Operativo">
                        </div>
                        <div class="mb-3">
                            <label for="tipo" class="form-label">Tipo *</label>
                            <input type="text" class="form-control" id="tipo" name="tipo" required
                                   placeholder="Ej: Personal, Laboral, Gestión">
                        </div>
                        <div class="mb-3">
                            <label for="estado" class="form-label">Estado</label>
                            <select class="form-select" id="estado" name="estado">
                                <option value="activo">Activo</option>
                                <option value="inactivo">Inactivo</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="crear_motivo" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar Motivo -->
    <div class="modal fade" id="editarMotivoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Editar Motivo de Ausencia
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" id="edit_id" name="motivo_id">
                        <div class="mb-3">
                            <label for="edit_motivo" class="form-label">Nombre del Motivo *</label>
                            <input type="text" class="form-control" id="edit_motivo" name="motivo" required>
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
                                <option value="activo">Activo</option>
                                <option value="inactivo">Inactivo</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="editar_motivo" class="btn btn-warning">
                            <i class="fas fa-save me-2"></i>Actualizar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Eliminar -->
    <div class="modal fade" id="eliminarMotivoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>Confirmar Eliminación
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro que desea eliminar el motivo <strong id="eliminar_nombre"></strong>?</p>
                    <p class="text-danger">Esta acción no se puede deshacer.</p>
                    <form method="POST" action="">
                        <input type="hidden" id="eliminar_id" name="motivo_id">
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" name="eliminar_motivo" class="btn btn-danger">
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
        function editarMotivo(motivo) {
            document.getElementById('edit_id').value = motivo.id;
            document.getElementById('edit_motivo').value = motivo.motivo;
            document.getElementById('edit_clasificacion').value = motivo.clasificacion;
            document.getElementById('edit_tipo').value = motivo.tipo;
            document.getElementById('edit_estado').value = motivo.estado;
            
            new bootstrap.Modal(document.getElementById('editarMotivoModal')).show();
        }
        
        function eliminarMotivo(id, nombre) {
            document.getElementById('eliminar_id').value = id;
            document.getElementById('eliminar_nombre').textContent = nombre;
            new bootstrap.Modal(document.getElementById('eliminarMotivoModal')).show();
        }
    </script>
</body>
<footer class="text-center py-4">Patricio Corrotea | Gestión de la Demanda</footer>
</html>