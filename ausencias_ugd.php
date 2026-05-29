<?php
require_once 'includes/auth.php';
$auth = new Auth();

if (!$auth->isLoggedIn()) {
    header("Location: index.php");
    exit;
}

// Solo administradores pueden gestionar ausencias
if (!($auth->isAdmin() || $auth->isUGD())) {
    header("Location: dashboard.php");
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

// Obtener todas las ausencias
$ausencias = $auth->getAllAusencias();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="icon" href="img/favicon.png" type="image/png">
    <title>Bloqueos de Agenda | Gear-HSP</title>
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

                <!-- Header -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-calendar-times me-2"></i>Bloqueos de Agenda
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Volver al Dashboard
                        </a>
                    </div>
                </div>

                <!-- Estadísticas Rápidas -->
                <div class="row mb-5" >
                    <div class="col-auto" id="cardEst">
                        <div class="card text-center">
                            <div class="card-body py-2 auto-width-card">
                                <span class="badge bg-warning badge-estado">Pendiente</span>
                                <h5 class="mt-2">
                                    <?php echo count(array_filter($ausencias, function($a) { return $a['estado'] === 'pendiente'; })); ?>
                                </h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-auto" >
                        <div class="card text-center">
                            <div class="card-body py-2 auto-width-card">
                                <span class="badge bg-secondary badge-estado">Bloqueado</span>
                                <h5 class="mt-2">
                                    <?php echo count(array_filter($ausencias, function($a) { return $a['estado'] === 'bloqueado'; })); ?>
                                </h5>
                            </div>
                        </div>
                    </div>
					<div class="col-auto">
                        <div class="card text-center">
                            <div class="card-body py-2 auto-width-card">
                                <span class="badge bg-warning badge-estado">Requiere Box</span>
                                <h5 class="mt-2">
                                    <?php echo count(array_filter($ausencias, function($a) { return $a['estado'] === 'requierebox'; })); ?>
                                </h5>
                            </div>
                        </div>
                    </div>
					<div class="col-auto">
                        <div class="card text-center">
                            <div class="card-body py-2 auto-width-card">
                                <span class="badge bg-warning badge-estado">Box Disponible</span>
                                <h5 class="mt-2">
                                    <?php echo count(array_filter($ausencias, function($a) { return $a['estado'] === 'boxdisponible'; })); ?>
                                </h5>
                            </div>
                        </div>
                    </div>
					<div class="col-auto">
                        <div class="card text-center">
                            <div class="card-body py-2">
                                <span class="badge bg-warning badge-estado">Box No Disponible</span>
                                <h5 class="mt-2">
                                    <?php echo count(array_filter($ausencias, function($a) { return $a['estado'] === 'boxnodisponible'; })); ?>
                                </h5>
                            </div>
                        </div>
                    </div>
					<div class="col-auto">
                        <div class="card text-center">
                            <div class="card-body py-2">
                                <span class="badge bg-info badge-estado">En Reagendamiento</span>
                                <h5 class="mt-2">
                                    <?php echo count(array_filter($ausencias, function($a) { return $a['estado'] === 'reagendamiento'; })); ?>
                                </h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-auto">
                        <div class="card text-center">
                            <div class="card-body py-2">
                                <span class="badge bg-info badge-estado">Enviado a CC</span>
                                <h5 class="mt-2">
                                    <?php echo count(array_filter($ausencias, function($a) { return $a['estado'] === 'enviadocc'; })); ?>
                                </h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-auto">
                        <div class="card text-center">
                            <div class="card-body py-2">
                                <span class="badge bg-primary badge-estado">Notificado</span>
                                <h5 class="mt-2">
                                    <?php echo count(array_filter($ausencias, function($a) { return $a['estado'] === 'notificado'; })); ?>
                                </h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-auto">
                        <div class="card text-center">
                            <div class="card-body py-2">
                                <span class="badge bg-success badge-estado">Respaldo</span>
                                <h5 class="mt-2">
                                    <?php echo count(array_filter($ausencias, function($a) { return $a['estado'] === 'respaldo'; })); ?>
                                </h5>
                            </div>
                        </div>
                    </div>
					<div class="col-auto">
                        <div class="card text-center">
                            <div class="card-body py-2">
                                <span class="badge bg-danger badge-estado">Anular</span>
                                <h5 class="mt-2">
                                    <?php echo count(array_filter($ausencias, function($a) { return $a['estado'] === 'anular'; })); ?>
                                </h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-auto">
                        <div class="card text-center">
                            <div class="card-body py-2">
                                <span class="badge bg-danger badge-estado">Anulado</span>
                                <h5 class="mt-2">
                                    <?php echo count(array_filter($ausencias, function($a) { return $a['estado'] === 'anulado'; })); ?>
                                </h5>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title"><i class="fas fa-filter me-2"></i>Filtros</h6>
                                <div class="row">
                                    <div class="col-md-3">
                                        <select class="form-select" id="filtroEstado">
                                            <option value="">Todos los estados</option>
                                            <option value="pendiente">Pendiente</option>
                                            <option value="bloqueado">Bloqueado</option>
											<option value="requierebox">Requiere Box</option>
											<option value="boxdisponible">Box Disponible</option>
											<option value="boxnodisponible">Box No Disponible</option>
											<option value="reagendamiento">Enviado a Reagendamiento</option>
                                            <option value="enviadocc">Enviado a CC</option>
                                            <option value="notificado">Notificado</option>
                                            <option value="respaldo">Respaldo</option>
											<option value="anular">Anular</option>
                                            <option value="anulado">Anulado</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <input type="text" class="form-control" id="filtroBusqueda" placeholder="Buscar por profesional, especialidad o motivo...">
                                    </div>
									<script>
									// Función para exportación rápida
										function exportarRapido() {
											// Crear formulario temporal
											const form = document.createElement('form');
											form.method = 'GET';
											form.action = 'exportar_ausencias.php';
											form.target = '_blank';
											
											// Obtener filtros actuales
											const filtroEstado = document.getElementById('filtroEstado');
											const filtroBusqueda = document.getElementById('filtroBusqueda');
											const filtroFecha = document.getElementById('filtroFecha');
											
											if (filtroEstado && filtroEstado.value) {
												const input = document.createElement('input');
												input.type = 'hidden';
												input.name = 'estado';
												input.value = filtroEstado.value;
												form.appendChild(input);
											}
											
											if (filtroBusqueda && filtroBusqueda.value) {
												const input = document.createElement('input');
												input.type = 'hidden';
												input.name = 'busqueda';
												input.value = filtroBusqueda.value;
												form.appendChild(input);
											}
											
											if (filtroFecha && filtroFecha.value) {
												const input = document.createElement('input');
												input.type = 'hidden';
												input.name = 'fecha_desde';
												input.value = filtroFecha.value;
												form.appendChild(input);
											}
											
											document.body.appendChild(form);
											form.submit();
											document.body.removeChild(form);
										}
									</script>
									<div class="col-md-3">
										<!-- sección de exportación rápida -->
										<button type="button" class="btn btn-outline-success ms-2" onclick="exportarRapido()" title="Exportar con filtros actuales">
											<i class="fas fa-bolt me-1"></i>Exportar
										</button>
                                    </div>
									<div class="col-md-3">
                                        <input type="date" class="form-control" id="filtroFecha" placeholder="Filtrar por fecha" hidden="true">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

				
                <!-- Tabla de Ausencias -->
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list me-2"></i>Lista de Ausencias
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($ausencias)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No hay ausencias registradas</p>
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
                                        ?>
                                        <tr>
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
											<td><?php echo htmlspecialchars($ausencia['reporte']); ?></td>
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
                                            
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <!--<button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" 
                                                            <?php echo empty($estados_permitidos) ? 'disabled' : ''; ?>>
                                                        <i class="fas fa-cog"></i>
                                                    </button>-->
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
													<div class="btn-group btn-group-sm">
														<!-- Botón Ver Detalles -->
														<button type="button" class="btn btn-outline-info btn-ver-detalles" 
																data-ausencia-id="<?php echo $ausencia['id']; ?>"
																title="Ver detalles">
															<i class="fas fa-eye"></i>
														</button>
													</div>
                                                </div>
                                            </td>
											<script>
											// Event listeners para los botones de detalles
											document.addEventListener('DOMContentLoaded', function() {
												// Usar event delegation para los botones de detalles
												document.addEventListener('click', function(e) {
													if (e.target.closest('.btn-ver-detalles')) {
														const button = e.target.closest('.btn-ver-detalles');
														const ausenciaId = button.getAttribute('data-ausencia-id');
														if (ausenciaId && typeof verDetalles === 'function') {
															verDetalles(ausenciaId);
														}
													}
												});
											});
											</script>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
							<!-- Modal de exportación - Verificar este ID -->
							<div class="modal fade" id="exportarModal">

							<!-- Formulario de exportación - Verificar este ID -->
							<form id="formExportar" action="exportar_ausencias.php" method="GET" target="_blank">

							<!-- Botón de exportación - Verificar este ID -->
							<button type="submit" class="btn btn-success" id="btnExportar">
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
                <!--<div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>-->
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
	
	<script>
			// Función para cargar profesionales según especialidad seleccionada
		function cargarProfesionalesPorEspecialidad(especialidadId) {
			const selectProfesional = document.getElementById('profesional_id');
			const helpText = document.getElementById('profesional-help');
			
			if (!especialidadId) {
				selectProfesional.innerHTML = '<option value="">Primero seleccione una especialidad</option>';
				selectProfesional.disabled = true;
				helpText.textContent = 'Seleccione una unidad para ver los profesionales disponibles';
				return;
			}
			
			// Mostrar loading
			selectProfesional.innerHTML = '<option value="">Cargando profesionales...</option>';
			selectProfesional.disabled = true;
			helpText.textContent = 'Cargando profesionales...';
			
			// Hacer petición AJAX
			fetch(`api/get_profesionales.php?especialidad_id=${especialidadId}`)
				.then(response => response.json())
				.then(data => {
					if (data.success) {
						if (data.profesionales.length > 0) {
							selectProfesional.innerHTML = '<option value="">Seleccione un profesional</option>';
							data.profesionales.forEach(profesional => {
								const option = document.createElement('option');
								option.value = profesional.id;
								option.textContent = profesional.nombre;
								selectProfesional.appendChild(option);
							});
							selectProfesional.disabled = false;
							helpText.textContent = `${data.profesionales.length} profesional(es) disponible(s)`;
						} else {
							selectProfesional.innerHTML = '<option value="">No hay profesionales para esta especialidad</option>';
							selectProfesional.disabled = true;
							helpText.textContent = 'No hay profesionales asignados a esta especialidad';
						}
					} else {
						throw new Error(data.error || 'Error desconocido');
					}
				})
				.catch(error => {
					console.error('Error:', error);
					selectProfesional.innerHTML = '<option value="">Error al cargar profesionales</option>';
					selectProfesional.disabled = true;
					helpText.textContent = 'Error al cargar profesionales: ' + error.message;
				});
		}

		// Función para calcular días entre fechas
		function calcularDiasAusencia() {
			const fechaInicio = document.getElementById('fecha_inicio');
			const fechaFin = document.getElementById('fecha_fin');
			const diasCalculados = document.getElementById('dias-calculados');
			
			if (fechaInicio.value && fechaFin.value) {
				const inicio = new Date(fechaInicio.value);
				const fin = new Date(fechaFin.value);
				
				if (fin >= inicio) {
					const diferencia = fin.getTime() - inicio.getTime();
					const dias = Math.ceil(diferencia / (1000 * 3600 * 24)) + 1;
					diasCalculados.textContent = `Días: ${dias}`;
					diasCalculados.className = 'form-text text-success';
				} else {
					diasCalculados.textContent = 'La fecha fin no puede ser anterior a la fecha inicio';
					diasCalculados.className = 'form-text text-danger';
				}
			} else {
				diasCalculados.textContent = 'Días: 0';
				diasCalculados.className = 'form-text';
			}
		}

		// Validación del formulario de registro
		function validarFormularioAusencia() {
			   console.log('Buscando elementos del formulario...');
			
			const elementos = [
				'formularioAusencia', 'btnGuardarAusencia', 'btnCancelarAusencia',
				'fecha_inicio', 'fecha_fin', 'tipo_ausencia', 'motivo'
			];
			
			elementos.forEach(id => {
				const elemento = document.getElementById(id);
				console.log(`${id}:`, elemento ? 'Encontrado' : 'NO ENCONTRADO');
			});
			const especialidad = document.getElementById('especialidad_id');
			const profesional = document.getElementById('profesional_id');
			const fechaInicio = document.getElementById('fecha_inicio');
			const fechaFin = document.getElementById('fecha_fin');
			const motivo = document.getElementById('motivo');
			const btnRegistrar = document.getElementById('btnRegistrarAusencia');
			
			const hoy = new Date().toISOString().split('T')[0];
			
			// Establecer fecha mínima como hoy
			if (fechaInicio) {
				fechaInicio.min = hoy;
			}
			
			// Calcular días cuando cambien las fechas
			if (fechaInicio && fechaFin) {
				fechaInicio.addEventListener('change', calcularDiasAusencia);
				fechaFin.addEventListener('change', calcularDiasAusencia);
			}
			
			// Validar en tiempo real
						
			function inicializarValidacionTiempoReal() {
				const form = document.getElementById('formRegistrarAusencia');
				const btnRegistrar = document.getElementById('btnRegistrar'); // Asegurar que existe
				
				if (!form || !btnRegistrar) {
					console.warn('Elementos necesarios para validación no encontrados');
					return;
				}

				// Obtener referencias a los campos una vez
				const campos = {
					especialidad: document.getElementById('especialidad'),
					profesional: document.getElementById('profesional'),
					fechaInicio: document.getElementById('fecha_inicio'),
					fechaFin: document.getElementById('fecha_fin'),
					motivo: document.getElementById('motivo')
				};

				function validarCampos() {
					const todosLlenos = Object.values(campos).every(campo => 
						campo && campo.value.trim() !== ''
					);
					btnRegistrar.disabled = !todosLlenos;
				}

				// Validar inicialmente
				validarCampos();

				// Agregar event listener para cambios
				form.addEventListener('input', validarCampos);
				form.addEventListener('change', validarCampos);
			}

			// Ejecutar cuando el DOM esté listo
			document.addEventListener('DOMContentLoaded', inicializarValidacionTiempoReal);
		}

		

		// Inicializar cuando el DOM esté listo
		document.addEventListener('DOMContentLoaded', function() {
			validarFormularioAusencia();
			
			// Si el modal se abre, resetear el formulario
			const modalRegistrar = document.getElementById('registrarAusenciaModal');
			if (modalRegistrar) {
				modalRegistrar.addEventListener('show.bs.modal', function() {
					// Resetear selects
					const selectProfesional = document.getElementById('profesional_id');
					if (selectProfesional) {
						selectProfesional.innerHTML = '<option value="">Primero seleccione una especialidad</option>';
						selectProfesional.disabled = true;
					}
					
					const helpText = document.getElementById('profesional-help');
					if (helpText) {
						helpText.textContent = 'Seleccione una especialidad para ver los profesionales disponibles';
					}
					
					const diasCalculados = document.getElementById('dias-calculados');
					if (diasCalculados) {
						diasCalculados.textContent = 'Días: 0';
						diasCalculados.className = 'form-text';
					}
				});
				
				modalRegistrar.addEventListener('hidden.bs.modal', function() {
					const form = document.getElementById('formRegistrarAusencia');
					if (form) {
						form.reset();
					}
				});
			}
		});

			//inicializar filtros
function inicializarFiltros() {
    console.log('Inicializando filtros...');
    
    const filtroEstado = document.getElementById('filtroEstado');
    const filtroFecha = document.getElementById('filtroFecha');
    const filtroBusqueda = document.getElementById('filtroBusqueda');
    const tabla = document.getElementById('tablaAusencias');
    
    // Verificar que los elementos existen
    if (!filtroEstado || !filtroFecha || !filtroBusqueda || !tabla) {
        console.error('Elementos faltantes:', {
            filtroEstado: !!filtroEstado,
            filtroFecha: !!filtroFecha,
            filtroBusqueda: !!filtroBusqueda,
            tabla: !!tabla
        });
        return;
    }
    
    const tbody = tabla.getElementsByTagName('tbody')[0];
    if (!tbody) {
        console.error('No se encontró el tbody de la tabla');
        return;
    }

    function aplicarFiltros() {
        console.log('=== APLICANDO FILTROS ===');
        
        const estado = filtroEstado.value.toLowerCase();
        const fecha = filtroFecha.value;
        const busqueda = filtroBusqueda.value.toLowerCase();
        
        console.log('Valores de filtros:', { estado, fecha, busqueda });
        
        const filas = tbody.getElementsByTagName('tr');
        let filasVisibles = 0;

        console.log(`Total de filas a filtrar: ${filas.length}`);

        for (let i = 0; i < filas.length; i++) {
            const fila = filas[i];
            const celdas = fila.getElementsByTagName('td');
            
            // Saltar fila de "no results"
            if (fila.classList.contains('no-results')) {
                fila.style.display = 'none';
                continue;
            }
            
            if (celdas.length === 0) {
                console.log('Fila sin celdas, omitiendo:', fila);
                fila.style.display = 'none';
                continue;
            }
            
            // DEBUG: Mostrar contenido de la primera fila para referencia
            if (i === 0) {
                console.log('Estructura de primera fila:');
                for (let j = 0; j < celdas.length; j++) {
                    console.log(`Celda ${j}:`, celdas[j].textContent.trim());
                }
            }

            // Obtener datos de la fila - AJUSTA ESTOS ÍNDICES
            const estadoFila = celdas[5]?.textContent?.toLowerCase().trim() || ''; // Ajustar índice
            const fechaFila = celdas[3]?.textContent?.trim() || ''; // Ajustar índice
            const textoFila = fila.textContent.toLowerCase();

            let mostrar = true;

            // Filtro por estado
            if (estado && estado !== 'todos' && estado !== '') {
                if (!estadoFila.includes(estado)) {
                    mostrar = false;
                    console.log(`Fila ${i} oculta por estado: ${estadoFila} no incluye ${estado}`);
                }
            }

            // Filtro por fecha
            if (fecha) {
                // Normalizar fechas para comparación
                const fechaFilaNormalizada = fechaFila.split(' ')[0]; // Tomar solo la parte de la fecha si hay hora
                if (fechaFilaNormalizada !== fecha) {
                    mostrar = false;
                    console.log(`Fila ${i} oculta por fecha: ${fechaFilaNormalizada} != ${fecha}`);
                }
            }

            // Filtro de búsqueda general
            if (busqueda) {
                if (!textoFila.includes(busqueda)) {
                    mostrar = false;
                    console.log(`Fila ${i} oculta por búsqueda: no incluye ${busqueda}`);
                }
            }

            // Aplicar visualización
            fila.style.display = mostrar ? '' : 'none';
            if (mostrar) {
                filasVisibles++;
                console.log(`Fila ${i} MOSTRADA:`, textoFila.substring(0, 50));
            }
        }
        
        console.log(`Resultado: ${filasVisibles} filas visibles de ${filas.length}`);
        
        // Mostrar mensaje si no hay resultados
        mostrarMensajeSinResultados(filasVisibles === 0);
    }

    function mostrarMensajeSinResultados(sinResultados) {
        // Remover mensaje anterior si existe
        const mensajeAnterior = tbody.querySelector('.no-results');
        if (mensajeAnterior) {
            mensajeAnterior.remove();
        }
        
        if (sinResultados) {
            const tr = document.createElement('tr');
            tr.className = 'no-results';
            const colSpan = tabla.querySelector('thead th').length || 10;
            tr.innerHTML = `<td colspan="${colSpan}" class="text-center text-muted py-4">No se encontraron resultados con los filtros aplicados</td>`;
            tbody.appendChild(tr);
            console.log('Mostrando mensaje: No hay resultados');
        }
    }

    // Agregar event listeners
    filtroEstado.addEventListener('change', aplicarFiltros);
    filtroFecha.addEventListener('change', aplicarFiltros);
    filtroBusqueda.addEventListener('input', aplicarFiltros);
    
    console.log('Event listeners de filtros configurados');
    
    // Aplicar filtros iniciales
    aplicarFiltros();
}
			
			// Verificar elementos críticos
			const elementosCriticos = [
				'tablaAusencias',
				'filtroEstado', 
				'filtroFecha',
				'filtroBusqueda',
				'exportarModal'
			];
			
			elementosCriticos.forEach(id => {
				const elemento = document.getElementById(id);
				if (!elemento) {
					console.warn('Elemento no encontrado:', id);
				}
			});
	

			// verDetalles
function verDetalles(ausenciaId) {
    console.log('Función verDetalles ejecutada con ID:', ausenciaId);
    
    const modalElement = document.getElementById('detallesModal');
    if (!modalElement) {
        console.error('ERROR: No se encontró el modal con ID "detallesModal"');
        alert('Error: No se puede abrir el modal. Contacte al administrador.');
        return;
    }

    fetch(`api/get_detalles_ausencia.php?id=${ausenciaId}`)
        .then(response => response.json())
        .then(data => {
            console.log('Datos recibidos:', data);
            
            if (data.success) {
                const ausencia = data.ausencia;
                
                // CORRIGE LA SINTAXIS AQUÍ
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
            console.error('Error en fetch:', error);
            document.getElementById('detallesContenido').innerHTML = `
                <div class="alert alert-danger">
                    Error de conexión: ${error.message}
                </div>
            `;
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
        });
}
			// SOLO ESTE event listener - eliminar los duplicados
			document.addEventListener('DOMContentLoaded', function() {
				console.log('Inicializando sistema de gestión de ausencias...');
				
				// Inicializar filtros
				inicializarFiltros();
				
				// Inicializar formulario
				validarFormularioAusencia();
				
				// Solo llamar a inicializarExportacion si existe
				if (typeof inicializarExportacion === 'function') {
					inicializarExportacion();
				}
				
				console.log('Sistema inicializado correctamente');
			});
    </script>
	<script src="js/script_ausencias.js"></script>
	<script>
		// Verificar que las funciones estén disponibles
		document.addEventListener('DOMContentLoaded', function() {
			console.log('=== VERIFICACIÓN GESTIÓN AUSENCIAS ===');
			console.log('verDetalles:', typeof verDetalles === 'function' ? '✅ DISPONIBLE' : '❌ NO DISPONIBLE');
			console.log('toggleEditarReporte:', typeof toggleEditarReporte === 'function' ? '✅ DISPONIBLE' : '❌ NO DISPONIBLE');
			console.log('guardarReporte:', typeof guardarReporte === 'function' ? '✅ DISPONIBLE' : '❌ NO DISPONIBLE');
		});
	</script>
</body>
<footer class="text-center py-4">Patricio Corrotea | Gestión de la Demanda</footer>
</html>