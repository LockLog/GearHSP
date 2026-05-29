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

// Obtener estadísticas
$stats = [];
$ausencias_recientes = [];
$profesionales = [];
$especialidades = [];
$usuarios = [];

try {
    // Total profesionales
    $query = "SELECT COUNT(*) as total FROM profesionales WHERE activo = 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['profesionales'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Total ausencias activas
    $query = "SELECT COUNT(*) as total FROM ausencias WHERE estado = 'pendiente' AND fecha_fin >= CURDATE()";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['ausencias_activas'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
	
	// Total ausencias finalizadas
    $query = "SELECT COUNT(*) as finalizadas FROM ausencias WHERE estado = 'respaldo' AND fecha_fin >= CURDATE()";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['ausencias_finalizadas'] = $stmt->fetch(PDO::FETCH_ASSOC)['finalizadas'];

	// Total agendas pendientes
    $query = "SELECT COUNT(*) as pendientes FROM agendas WHERE estado = 'pendiente'";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['agendasPendientes'] = $stmt->fetch(PDO::FETCH_ASSOC)['pendientes'];
	
	// Total agendas en revision
    $query = "SELECT COUNT(*) as revision FROM agendas WHERE estado = 'revision'";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['agendasRevision'] = $stmt->fetch(PDO::FETCH_ASSOC)['revision'];
	
	// Total agendas autorizadas
    $query = "SELECT COUNT(*) as autorizadas FROM agendas WHERE estado = 'autorizada'";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['agendasAutorizadas'] = $stmt->fetch(PDO::FETCH_ASSOC)['autorizadas'];
	
	// Total agendas implementadas
    $query = "SELECT COUNT(*) as implementadas FROM agendas WHERE estado = 'implementada'";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['agendasImplementadas'] = $stmt->fetch(PDO::FETCH_ASSOC)['implementadas'];
	
    // Total usuarios
    $query = "SELECT COUNT(*) as total FROM usuarios WHERE activo = 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['usuarios'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Total especialidades
    $query = "SELECT COUNT(*) as total FROM especialidades WHERE activo = 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['especialidades'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Ausencias recientes
    $query = "SELECT a.*, p.nombre as profesional_nombre, e.nombre as especialidad_nombre 
              FROM ausencias a 
              JOIN profesionales p ON a.profesional_id = p.id 
              JOIN especialidades e ON a.especialidad_id = e.id 
              ORDER BY a.timestamp_registro DESC LIMIT 5";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $ausencias_recientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $exception) {
    $error = "Error al cargar datos: " . $exception->getMessage();
}

// Procesar formularios
if ($_POST) {
	// En la sección de procesamiento de formularios, verificar la relación profesional-especialidad
	if (isset($_POST['registrar_ausencia'])) {
		try {
			$profesional_id = $_POST['profesional_id'];
			$especialidad_id = $_POST['especialidad_id'];
			
			// Verificar que la especialidad pertenece al profesional
			$check_query = "SELECT COUNT(*) as count FROM profesional_especialidad 
						   WHERE profesional_id = :profesional_id AND especialidad_id = :especialidad_id AND activo = 1";
			$check_stmt = $conn->prepare($check_query);
			$check_stmt->bindParam(':profesional_id', $profesional_id);
			$check_stmt->bindParam(':especialidad_id', $especialidad_id);
			$check_stmt->execute();
			$result = $check_stmt->fetch(PDO::FETCH_ASSOC);
			
			if ($result['count'] == 0) {
				$error = "Error: La especialidad seleccionada no pertenece al profesional elegido";
			} else {
				$query = "INSERT INTO ausencias (profesional_id, especialidad_id, motivo, fecha_inicio, fecha_fin, detalle, reporte, usuario_registro) 
						  VALUES (:profesional_id, :especialidad_id, :motivo, :fecha_inicio, :fecha_fin, :detalle, :reporte, :usuario_registro)";
				
				$stmt = $conn->prepare($query);
				$stmt->bindParam(':profesional_id', $profesional_id);
				$stmt->bindParam(':especialidad_id', $especialidad_id);
				$stmt->bindParam(':motivo', $_POST['motivo']);
				$stmt->bindParam(':fecha_inicio', $_POST['fecha_inicio']);
				$stmt->bindParam(':fecha_fin', $_POST['fecha_fin']);
				$stmt->bindParam(':detalle', $_POST['detalle']);
				$stmt->bindParam(':reporte', $_POST['reporte']);
				$stmt->bindParam(':usuario_registro', $_SESSION['username']);
				
				if ($stmt->execute()) {
					$success = "Ausencia registrada correctamente";
					header("Location: dashboard.php?success=1");
					exit;
				}
			}
		} catch(PDOException $exception) {
			$error = "Error al registrar ausencia: " . $exception->getMessage();
		}
	}

    if (isset($_POST['crear_usuario']) && $auth->isAdmin()) {
        if ($auth->createUser($_POST['username'], $_POST['password'], $_POST['nombre_completo'], $_POST['email'], $_POST['rol'])) {
            $success = "Usuario creado correctamente";
            header("Location: dashboard.php?success=1");
            exit;
        } else {
            $error = "Error al crear usuario";
        }
    }
	
}

// Obtener profesionales y especialidades para los formularios
try {
    $query = "SELECT * FROM profesionales WHERE activo = 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $profesionales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $query = "SELECT * FROM especialidades WHERE activo = 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $especialidades = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $usuarios = $auth->getUsers();
} catch(PDOException $exception) {
    $error = "Error al cargar datos: " . $exception->getMessage();
}

// Mostrar mensaje de éxito si viene por GET
if (isset($_GET['success'])) {
    $success = "Operación realizada correctamente";
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="icon" href="img/favicon.png" type="image/png">
    <title>Dashboard | Gear-HSP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
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

                <!-- Dashboard Header -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-digital-tachograph me-2"></i>Dashboard | Gear-HSP
                    </h1>
					<!--<div class="mb-3">
                            <i class="fas fa-exclamation-triangle" style="color: red;"></i>
							<span style="color: red;">Debido al corte de luz, Gear no estará disponible este miercoles 25-03-2026 entre 10 y 15 horas.</span>
                    </div>-->

                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#registrarAusenciaModal">
                            <i class="fas fa-plus me-1"></i>Nueva Ausencia
                        </button>
                    </div>
                </div>

                <!-- Dashboard Stats -->
                <div class="row mt-4">
                    <div class="col-md-2">
                        <div class="stats-card bg-3">
                            <i class="fas fa-calendar-times"></i>
                            <h4><?php echo $stats['ausencias_activas'] ?? 0; ?></h4>
                            <p>Bloqueos Pendientes</p>
                        </div>
                    </div>	
					<div class="col-md-2">
                        <div class="stats-card bg-2">
                            <i class="fas fa-calendar-times"></i>
                            <h4><?php echo $stats['ausencias_finalizadas'] ?? 0; ?></h4>
                            <p>Bloqueos Finalizados</p>
                        </div>
                    </div>						
					<div class="col-md-2">
                        <div class="stats-card bg-4">
                            <i class="fas fa-calendar-plus"></i>
                            <h4><?php echo $stats['agendasPendientes'] ?? 0; ?></h4>
                            <p>Agendas Pendientes</p>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stats-card bg-1">
                            <i class="fas fa-calendar-alt"></i>
                            <h4><?php echo $stats['agendasRevision'] ?? 0; ?></h4>
                            <p>Agendas en Revisión</p>
                        </div>
                    </div>
					<div class="col-md-2">
                        <div class="stats-card bg-2">
                            <i class="fas fas fa-calendar-check"></i>
                            <h4><?php echo $stats['agendasAutorizadas'] ?? 0; ?></h4>
                            <p>Agendas Autorizadas</p>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stats-card bg-2">
                            <i class="fas fas fa-calendar-check"></i>
                            <h4><?php echo $stats['agendasImplementadas'] ?? 0; ?></h4>
                            <p>Agendas Implementadas</p>
                        </div>
                    </div>
                </div>

				<!-- Acciones rápidas -->
                <div class="row mt-4">
                    <div class="col-md-4">
                        <div class="card dashboard-card">
                            <div class="card-body text-center">
                                <i class="fas fa-calendar-times fa-2x text-primary mb-3"></i>
                                <h5>Registrar Ausencia</h5>
                                <p class="text-muted">Registre una nueva solicitud de bloqueo</p>
								<p>&nbsp;</p> 
                                <button class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#registrarAusenciaModal">
                                    <i class="fas fa-calendar-times me-2"></i>Nueva Ausencia
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card dashboard-card">
                            <div class="card-body text-center">
                                <i class="fas fa-list fa-2x text-success mb-3"></i>
                                <h5>Mis Ausencias</h5>
                                <p class="text-muted ">Vea el historial de sus ausencias</p>
								 <p>&nbsp;</p> 
                                <a href="ausencias.php" class="btn btn-success w-100">
                                    <i class="fas fa-eye me-2"></i>Ver Historial
                                </a>
                            </div>
                        </div>
                    </div>
				 <!-- Nueva tarjeta de búsqueda de profesionales y agendas -->
					<div class="col-md-4">
						<div class="card dashboard-card">
							<div class="card-body text-center">
								<i class="fas fa-search fa-2x text-warning mb-3"></i>
								<h5>Buscar Agenda</h5>
								<p class="text-muted ">Consulte agendas por profesional</p>
								<div class="mb-3">
									<select class="form-select" id="buscarProfesionalSelect" style="text-align-last: center;">
										<option value="">Seleccione un profesional</option>
										<?php
										// Consultar profesionales para el select
										try {
											$query_profesionales = "SELECT id, nombre, rut FROM profesionales WHERE activo = 1 ORDER BY nombre";
											$stmt_prof = $conn->prepare($query_profesionales);
											$stmt_prof->execute();
											$profesionales_lista = $stmt_prof->fetchAll(PDO::FETCH_ASSOC);
											foreach ($profesionales_lista as $prof) {
												echo '<option value="' . $prof['id'] . '">' . htmlspecialchars($prof['nombre']) . ' (' . htmlspecialchars($prof['rut']) . ')</option>';
											}
										} catch(PDOException $e) {
											echo '<option value="">Error al cargar profesionales</option>';
										}
										?>
									</select>
								</div>
								<div class="mb-3" id="agendaSelectContainer" style="display:none;">
									<label class="form-label small text-muted">Seleccione una agenda:</label>
									<select class="form-select" id="agendaSelect">
										<option value="">Cargando agendas...</option>
									</select>
								</div>
								<button class="btn btn-warning w-100" id="btnVerResumen" disabled>
									<i class="fas fa-eye me-2"></i>Ver Resumen de Agenda
								</button>
							</div>
						</div>
					</div>
				</div>

				<script>
				// Script para la búsqueda de profesionales y agendas
				document.addEventListener('DOMContentLoaded', function() {
					const selectProfesional = document.getElementById('buscarProfesionalSelect');
					const agendaContainer = document.getElementById('agendaSelectContainer');
					const selectAgenda = document.getElementById('agendaSelect');
					const btnVerResumen = document.getElementById('btnVerResumen');
					
					// Función para traducir estados
					function traducirEstado(estado) {
						const estados = {
							'pendiente': 'Pendiente',
							'revision': 'En Revisión',
							'boxnodisponible': 'Box No Disponible',
							'autorizada': 'Autorizada',
							'implementada': 'Implementada',
							'anulada': 'Anulada'
						};
						return estados[estado] || estado;
					}
					
					// Al seleccionar un profesional, cargar sus agendas
					selectProfesional.addEventListener('change', function() {
						const profesionalId = this.value;
						
						if (!profesionalId) {
							agendaContainer.style.display = 'none';
							selectAgenda.innerHTML = '<option value="">Cargando agendas...</option>';
							btnVerResumen.disabled = true;
							return;
						}
						
						// Mostrar contenedor de agendas y deshabilitar botón
						agendaContainer.style.display = 'block';
						btnVerResumen.disabled = true;
						selectAgenda.innerHTML = '<option value="">Cargando agendas...</option>';
						selectAgenda.disabled = true;
						
						// Consultar agendas del profesional
						fetch(`api/get_agendas_profesional.php?profesional_id=${profesionalId}`)
							.then(response => response.json())
							.then(data => {
								if (data.success && data.agendas && data.agendas.length > 0) {
									selectAgenda.innerHTML = '<option value="">Seleccione una agenda</option>';
									selectAgenda.disabled = false;
									
									data.agendas.forEach(agenda => {
										const option = document.createElement('option');
										option.value = agenda.id;
										const estadoTexto = traducirEstado(agenda.estado);
										const fechaInicio = agenda.fecha_inicio ? new Date(agenda.fecha_inicio).toLocaleDateString('es-CL') : 'Sin fecha';
										option.textContent = `Agenda #${agenda.id} - ${estadoTexto} (Inicio: ${fechaInicio}) - ${agenda.horas_contrato} hrs`;
										selectAgenda.appendChild(option);
									});
									
									// Si solo hay una agenda, seleccionarla automáticamente y habilitar botón
									if (data.agendas.length === 1) {
										selectAgenda.value = data.agendas[0].id;
										btnVerResumen.disabled = false;
									}
								} else {
									selectAgenda.innerHTML = '<option value="">No hay agendas disponibles</option>';
									selectAgenda.disabled = true;
									btnVerResumen.disabled = true;
								}
							})
							.catch(error => {
								console.error('Error al cargar agendas:', error);
								selectAgenda.innerHTML = '<option value="">Error al cargar agendas</option>';
								selectAgenda.disabled = true;
								btnVerResumen.disabled = true;
							});
					});
					
					// Habilitar botón cuando se selecciona una agenda
					selectAgenda.addEventListener('change', function() {
						btnVerResumen.disabled = !this.value;
					});
					
					// Redirigir al resumen de agenda
					btnVerResumen.addEventListener('click', function() {
						const agendaId = selectAgenda.value;
						if (agendaId) {
							window.location.href = `resumen_agenda.php?id=${agendaId}`;
						}
					});
				});
				</script>
                
				<!-- Estadisticas recientes -->
				<div class="row mt-4">
					<div class="col-12">
						<div class="card dashboard-card">
							<div class="card-header d-flex justify-content-between align-items-center">
								<h5 class="card-title mb-0">
									<i class="fas fa-history me-2"></i>Bloqueos de Agenda Recientes
								</h5>
								<a href="ausencias.php" class="btn btn-sm btn-outline-primary">
									<i class="fas fa-list me-1"></i>Ver Todas
								</a>
							</div>
							<div class="card-body">
								<?php if (empty($ausencias_recientes)): ?>
									<div class="text-center py-4">
										<i class="fas fa-inbox fa-3x text-muted mb-3"></i>
										<p class="text-muted">No hay ausencias registradas</p>
									</div>
								<?php else: ?>
									<div class="table-responsive">
										<table class="table table-hover">
											<thead>
												<tr>
													<th>Profesional</th>
													<th>Especialidad</th>
													<th>Motivo</th>
													<th>Fecha Inicio</th>
													<th>Fecha Fin</th>
													<th>Estado</th>
												</tr>
											</thead>
											<tbody>
												<?php foreach ($ausencias_recientes as $ausencia): ?>
												<tr>
													<td><?php echo htmlspecialchars($ausencia['profesional_nombre']); ?></td>
													<td><?php echo htmlspecialchars($ausencia['especialidad_nombre']); ?></td>
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
															'equipo en reparacion' => 'Equipo en Reparación'
														];
														echo $motivos[$ausencia['motivo']] ?? $ausencia['motivo'];
														?>
													</td>
													<td><?php echo date('d/m/Y', strtotime($ausencia['fecha_inicio'])); ?></td>
													<td><?php echo date('d/m/Y', strtotime($ausencia['fecha_fin'])); ?></td>
													<td>
														<span class="badge bg-<?php echo $auth->getClaseEstado($ausencia['estado']); ?>">
															<?php echo $auth->getNombreEstado($ausencia['estado']); ?>
														</span>
													</td>
												</tr>
												<?php endforeach; ?>
											</tbody>
										</table>
									</div>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</div>  
			</div>				
			</main>
        </div>
	</div>


                

             
    <!-- Modales -->
    <?php include 'includes/modals.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
	<!-- Font Awesome -->
	<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    <script src="js/script.js"></script>
<script>

// Función global para cargar especialidades
function cargarEspecialidadesProfesional(profesionalId) {
    console.log('🔧 cargarEspecialidadesProfesional ejecutada con ID:', profesionalId);
    
    const selectEspecialidad = document.getElementById('especialidad_id');
    if (!selectEspecialidad) {
        console.error('❌ No se encontró el elemento especialidad_id');
        return;
    }
    
    if (!profesionalId) {
        selectEspecialidad.innerHTML = '<option value="">Primero seleccione un profesional</option>';
        selectEspecialidad.disabled = true;
        return;
    }
    
    selectEspecialidad.innerHTML = '<option value="">Cargando...</option>';
    selectEspecialidad.disabled = true;
    
    // Petición simple a la API
    fetch(`api/get_especialidades_profesional.php?profesional_id=${profesionalId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.especialidades) {
                selectEspecialidad.innerHTML = '<option value="">Seleccione especialidad</option>';
                data.especialidades.forEach(esp => {
                    const option = document.createElement('option');
                    option.value = esp.id;
                    option.textContent = esp.nombre + (esp.es_principal ? ' ★' : '');
                    if (esp.es_principal) option.selected = true;
                    selectEspecialidad.appendChild(option);
                });
                selectEspecialidad.disabled = false;
            } else {
                selectEspecialidad.innerHTML = '<option value="">Error al cargar</option>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            selectEspecialidad.innerHTML = '<option value="">Error de conexión</option>';
        });
}

// Hacer la función disponible globalmente
window.cargarEspecialidadesProfesional = cargarEspecialidadesProfesional;

console.log('✅ cargarEspecialidadesProfesional definida y disponible');
</script>
<script>
// Script de diagnóstico completo
document.addEventListener('DOMContentLoaded', function() {
    console.log('=== DIAGNÓSTICO COMPLETO ===');
    
    // 1. Verificar que la función existe
    console.log('1. Función cargarEspecialidadesProfesional:', 
        typeof cargarEspecialidadesProfesional === 'function' ? '✅ DISPONIBLE' : '❌ NO DISPONIBLE'
    );
    
    // 2. Verificar elementos del formulario
    const elementosFormulario = {
        'profesional_id': 'Select profesional',
        'especialidad_id': 'Select especialidad', 
        'fecha_inicio': 'Fecha inicio',
        'fecha_fin': 'Fecha fin',
        'motivo': 'Select motivo',
        'btnRegistrarAusencia': 'Botón registrar',
        'formRegistrarAusencia': 'Formulario completo',
        'registrarAusenciaModal': 'Modal'
    };
    
    console.log('2. Elementos del formulario:');
    for (const [id, desc] of Object.entries(elementosFormulario)) {
        const elemento = document.getElementById(id);
        console.log(`   ${elemento ? '✅' : '❌'} ${desc} (${id}):`, 
                    elemento ? 'ENCONTRADO' : 'NO ENCONTRADO');
    }
    
    // 3. Verificar event listener en select profesional
    const selectProfesional = document.getElementById('profesional_id');
    if (selectProfesional) {
        console.log('3. Event listener en select profesional: ✅ CONFIGURADO');
        
        // Probar la función manualmente
        selectProfesional.addEventListener('change', function() {
            console.log('🎯 Select profesional cambiado a:', this.value);
            if (typeof cargarEspecialidadesProfesional === 'function') {
                cargarEspecialidadesProfesional(this.value);
            } else {
                console.error('❌ Función no disponible cuando se necesita');
            }
        });
    } else {
        console.log('3. Event listener en select profesional: ❌ NO CONFIGURADO (elemento no existe)');
    }
    
    // 4. Probar la API
    console.log('4. Probando conexión con API...');
    fetch('api/get_especialidades_profesional.php?profesional_id=1')
        .then(response => {
            console.log('   ✅ API responde:', response.status, response.statusText);
            return response.json();
        })
        .then(data => {
            console.log('   ✅ Estructura de datos API:', data.success ? 'CORRECTA' : 'ERROR');
        })
        .catch(error => {
            console.log('   ❌ Error API:', error.message);
        });
    
    console.log('=== FIN DIAGNÓSTICO ===');
});
</script>
</body>
<footer class="text-center py-4">Patricio Corrotea | Gestión de la Demanda</footer>
</html>