<?php
require_once 'includes/auth.php';
$auth = new Auth();

if (!$auth->isLoggedIn()) {
    header("Location: index.php");
    exit;
}

require_once 'config/database.php';
$database = new Database();
$conn = $database->getConnection();

// Procesar anulación de ausencia con detalle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['anular_ausencia'])) {
    $ausencia_id = $_POST['ausencia_id'];
    $detalle_anulacion = trim($_POST['detalle_anulacion'] ?? '');
    
    // Validar que se haya ingresado un detalle
    if (empty($detalle_anulacion)) {
        $error = "Debe ingresar un detalle para anular el bloqueo";
    } else {
        try {
            // Primero obtener el detalle actual de la ausencia
            $query_get = "SELECT detalle FROM ausencias WHERE id = :id";
            $stmt_get = $conn->prepare($query_get);
            $stmt_get->bindParam(':id', $ausencia_id);
            $stmt_get->execute();
            $ausencia_actual = $stmt_get->fetch(PDO::FETCH_ASSOC);
            
            // Construir el nuevo detalle concatenando el anterior con el nuevo
            $detalle_actual = $ausencia_actual['detalle'] ?? '';
            $fecha_actual = date('Y-m-d H:i:s');
            $usuario = $_SESSION['username'];
            
            $nuevo_detalle = $detalle_actual;
            if (!empty($detalle_actual)) {
                $nuevo_detalle .= "\n\n--- ANULACIÓN EL {$fecha_actual} POR {$usuario} ---\n";
            } else {
                $nuevo_detalle = "--- ANULACIÓN EL {$fecha_actual} POR {$usuario} ---\n";
            }
            $nuevo_detalle .= "Motivo de anulación: {$detalle_anulacion}";
            
            // Actualizar la ausencia con estado 'anulado' y el nuevo detalle
            $update_query = "UPDATE ausencias SET estado = 'anulado', detalle = :detalle, usuario_modificacion = :usuario, timestamp_modificacion = CURRENT_TIMESTAMP WHERE id = :id";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bindParam(':detalle', $nuevo_detalle);
            $update_stmt->bindParam(':usuario', $_SESSION['username']);
            $update_stmt->bindParam(':id', $ausencia_id);
            
            if ($update_stmt->execute()) {
                $success = "Ausencia anulada correctamente con el detalle registrado";
                // Recargar la página para mostrar los cambios
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            } else {
                $error = "Error al anular la ausencia";
            }
        } catch(PDOException $exception) {
            $error = "Error al anular ausencia: " . $exception->getMessage();
        }
    }
}

// Obtener ausencias del usuario actual
try {
    // Verificar si hay un filtro de búsqueda
    $search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
    $search_condition = '';
    $params = [':usuario' => $_SESSION['username']];
    
    if (!empty($search_term)) {
        $search_condition = " AND (
            p.nombre LIKE :search OR 
            e.nombre LIKE :search OR 
            a.motivo LIKE :search OR 
            a.estado LIKE :search OR
            DATE_FORMAT(a.fecha_inicio, '%d/%m/%Y') LIKE :search OR
            DATE_FORMAT(a.fecha_fin, '%d/%m/%Y') LIKE :search OR
            DATE_FORMAT(a.timestamp_registro, '%d/%m/%Y %H:%i') LIKE :search
        )";
        $params[':search'] = '%' . $search_term . '%';
    }
    
    $query = "SELECT a.*, p.nombre as profesional_nombre, e.nombre as especialidad_nombre 
              FROM ausencias a 
              JOIN profesionales p ON a.profesional_id = p.id 
              JOIN especialidades e ON a.especialidad_id = e.id 
              WHERE a.usuario_registro = :usuario 
              {$search_condition}
              ORDER BY a.timestamp_registro DESC";
    
    $stmt = $conn->prepare($query);
    
    // Vincular parámetros
    foreach ($params as $key => &$val) {
        $stmt->bindParam($key, $val);
    }
    
    $stmt->execute();
    $ausencias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $exception) {
    $error = "Error al cargar ausencias: " . $exception->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bloqueos de Agenda</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 content-area">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-list me-2"></i>Bloqueos Registrados</h1>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <!-- Filtro de búsqueda -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-8">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-search"></i>
                                    </span>
                                    <input type="text" 
                                           class="form-control" 
                                           name="search" 
                                           placeholder="Buscar en todos los campos..." 
                                           value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                                    <?php if (!empty($search_term)): ?>
                                        <a href="<?php echo strtok($_SERVER['REQUEST_URI'], '?'); ?>" class="btn btn-outline-secondary">
                                            <i class="fas fa-times"></i> Limpiar
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i>Buscar
                                </button>
                                <?php if (!empty($search_term)): ?>
                                    <span class="ms-2 text-muted">
                                        <i class="fas fa-filter me-1"></i>
                                        Filtrando por: "<?php echo htmlspecialchars($search_term); ?>"
                                    </span>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>Profesional</th>
                                <th>Especialidad</th>
                                <th>Motivo</th>
                                <th>Fecha Inicio</th>
                                <th>Fecha Fin</th>
                                <th>Estado</th>
                                <th>Registrado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($ausencias)): ?>
                                <tr>
                                    <td colspan="8" class="text-center">
                                        <?php if (!empty($search_term)): ?>
                                            No se encontraron resultados para "<?php echo htmlspecialchars($search_term); ?>"
                                        <?php else: ?>
                                            No hay bloqueos de agenda registrados
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($ausencias as $ausencia): ?>
                                <tr>
                                    <td><?php echo $ausencia['profesional_nombre']; ?></td>
                                    <td><?php echo $ausencia['especialidad_nombre']; ?></td>
                                    <td>
                                        <?php 
                                        $motivos = [
                                            'permiso' => 'Permiso Administrativo',
                                            'vacaciones' => 'Vacaciones',
                                            'licencia' => 'Licencia',
                                            'reunion' => 'Reunión',
                                            'capacitacion' => 'Capacitación',
                                            'turno' => 'Turno',
                                            'pabellon' => 'Pabellón',
                                            'modificacion'=>'Modificación',
                                            'reduccion'=>'Reducción',
                                            'renuncia'=>'Renuncia',
                                            'permiso sin goce de sueldo' => 'Permiso sin goce de sueldo',
                                            'descanso compensatorio' => 'Descanso Compensatorio',
                                            'cupo reservado' => 'Cupo Reservado',
                                            'equipo en reparacion' => 'Equipo en Reparación',
											'comision de servicio' => 'Comisión de Servicio'
                                        ];
                                        echo $motivos[$ausencia['motivo']] ?? $ausencia['motivo'];
                                        ?>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($ausencia['fecha_inicio'])); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($ausencia['fecha_fin'])); ?></td>
                                    <td>
                                        <?php
                                        $estados = [
                                            'pendiente' => 'warning',
                                            'bloqueado'=> 'secondary',
                                            'reagendamiento'=> 'warning',
                                            'requierebox' => 'warning',
                                            'boxdisponible' => 'warning',
                                            'boxnodisponible' => 'warning',
                                            'enviadocc' => 'info',
                                            'notificado' => 'info',
                                            'respaldo' => 'success',
                                            'anular' => 'danger',
                                            'anulado' => 'danger'
                                        ];
                                        $clase = $estados[$ausencia['estado']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $clase; ?>">
                                            <?php echo ucfirst($ausencia['estado']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($ausencia['timestamp_registro'])); ?></td>
                                    <td>
                                        <?php if ($ausencia['estado'] !== 'anulado'): ?>
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-danger" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#anularAusenciaModal"
                                                data-ausencia-id="<?php echo $ausencia['id']; ?>"
                                                data-profesional="<?php echo htmlspecialchars($ausencia['profesional_nombre']); ?>"
                                                data-fecha-inicio="<?php echo date('d/m/Y', strtotime($ausencia['fecha_inicio'])); ?>"
                                                data-fecha-fin="<?php echo date('d/m/Y', strtotime($ausencia['fecha_fin'])); ?>">
                                            <i class="fas fa-times"></i> Anular
                                        </button>
                                        <?php else: ?>
                                            <span class="text-muted">Anulado</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal para anular ausencia con detalle -->
    <div class="modal fade" id="anularAusenciaModal" tabindex="-1" aria-labelledby="anularAusenciaModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="anularAusenciaModalLabel">
                        <i class="fas fa-exclamation-triangle me-2"></i>Anular Bloqueo
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="" id="formAnularAusencia">
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Información del bloqueo:</strong><br>
                            <span id="infoProfesional"></span><br>
                            <span id="infoFechas"></span>
                        </div>
                        
                        <div class="mb-3">
                            <label for="detalle_anulacion" class="form-label fw-bold">
                                Detalle de la anulación <span class="text-danger">*</span>
                            </label>
                            <textarea class="form-control" 
                                      id="detalle_anulacion" 
                                      name="detalle_anulacion" 
                                      rows="4" 
                                      placeholder="Explique detalladamente el motivo por el cual se anula este bloqueo..."
                                      required></textarea>
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Este detalle se agregará al campo "detalle" existente de la ausencia.
                            </small>
                        </div>
                        
                        <input type="hidden" name="ausencia_id" id="ausencia_id" value="">
                        <input type="hidden" name="anular_ausencia" value="1">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Cancelar
                        </button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-check-circle me-1"></i>Confirmar Anulación
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include 'includes/modals.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    <script src="js/script.js"></script>
    
    <script>
    // Script para manejar el modal de anulación
    document.addEventListener('DOMContentLoaded', function() {
        const anularModal = document.getElementById('anularAusenciaModal');
        
        if (anularModal) {
            anularModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const ausenciaId = button.getAttribute('data-ausencia-id');
                const profesional = button.getAttribute('data-profesional');
                const fechaInicio = button.getAttribute('data-fecha-inicio');
                const fechaFin = button.getAttribute('data-fecha-fin');
                
                const infoProfesional = document.getElementById('infoProfesional');
                const infoFechas = document.getElementById('infoFechas');
                const ausenciaIdInput = document.getElementById('ausencia_id');
                
                infoProfesional.innerHTML = '<i class="fas fa-user me-1"></i> <strong>Profesional:</strong> ' + profesional;
                infoFechas.innerHTML = '<i class="fas fa-calendar me-1"></i> <strong>Período:</strong> ' + fechaInicio + ' - ' + fechaFin;
                ausenciaIdInput.value = ausenciaId;
            });
            
            // Limpiar el textarea cuando se cierra el modal
            anularModal.addEventListener('hidden.bs.modal', function() {
                document.getElementById('detalle_anulacion').value = '';
            });
        }
    });
    </script>
</body>
</html>