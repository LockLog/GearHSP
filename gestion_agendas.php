<?php
require_once 'includes/auth.php';
$auth = new Auth();

if (!$auth->isLoggedIn()) {
    header("Location: index.php");
    exit;
}

// Solo administradores y gestores pueden gestionar agendas
if (!($auth->isAdmin() || $auth->isGestor())) {
    header("Location: dashboard.php");
    exit;
}

require_once 'config/database.php';
$database = new Database();
$conn = $database->getConnection();

// Configuración de paginación
$registros_por_pagina = isset($_GET['registros']) ? (int)$_GET['registros'] : 50;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Obtener total de agendas y agendas paginadas
$total_agendas = $auth->getTotalAgendas();
$total_paginas = ceil($total_agendas / $registros_por_pagina);

// Obtener agendas con paginación
$agendas = $auth->getAgendasPaginadas($offset, $registros_por_pagina);

// Procesar registro de nueva agenda - USAR PRG PATTERN
if ($_POST && isset($_POST['registrar_agenda'])) {
    $especialidad_id = $_POST['especialidad_id'];
    $profesional_id = $_POST['profesional_id'];
    $horas_contrato = $_POST['horas_contrato'];
	$estamento = $_POST['estamento'];
    $fecha_inicio = $_POST['fecha_inicio'];
    $estado = $_POST['estado'];
    $descripcion = $_POST['descripcion'];
	
    if ($auth->registrarAgenda($especialidad_id, $profesional_id, $horas_contrato, $estamento, $fecha_inicio, $estado, $descripcion, $_SESSION['username'])) {
        $_SESSION['success'] = "Agenda registrada correctamente";
    } else {
        $_SESSION['error'] = "Error al registrar la agenda";
    }
    
    // REDIRIGIR SIEMPRE después de POST
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Procesar cambio de estado - USAR PRG PATTERN
if ($_POST && isset($_POST['cambiar_estado'])) {
    $agenda_id = $_POST['agenda_id'];
    $nuevo_estado = $_POST['nuevo_estado'];
    
    if ($auth->cambiarEstadoAgenda($agenda_id, $nuevo_estado, $_SESSION['username'])) {
        $_SESSION['success'] = "Estado de la agenda actualizado correctamente a: " . $auth->getNombreEstadoAgenda($nuevo_estado);
    } else {
        $_SESSION['error'] = "Error al actualizar el estado de la agenda";
    }
    
    // REDIRIGIR SIEMPRE después de POST
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

if ($_POST && isset($_POST['copiar_detalles'])) {
    $agenda_origen_id = $_POST['agenda_origen_id'];
    $agenda_destino_id = $_POST['agenda_destino_id'];
    
    if ($auth->copiarDetallesAgenda($agenda_origen_id, $agenda_destino_id, $_SESSION['username'])) {
        $_SESSION['success'] = "Detalles de agenda copiados correctamente";
    } else {
        $_SESSION['error'] = "Error al copiar los detalles de agenda";
    }
    
    // REDIRIGIR SIEMPRE después de POST
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}
/*
// Obtener mensajes de sesión 
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';

// Limpiar mensajes de sesión después de mostrarlos
unset($_SESSION['success']);
unset($_SESSION['error']);
*/
// Obtener todas las agendas para estadísticas (sin paginación)
$todasAgendas = $auth->getAllAgendas();
// Obtener todas las agendas
$agendas = $auth->getAllAgendas();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="img/favicon.png" type="image/png">
    <title>Gestión de Agendas | Gear-HSP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
	<!-- Librerías para exportación -->
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
	<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        .badge-estado {
            font-size: 0.8em;
            padding: 0.4em 0.6em;
        }
        .dropdown-estado {
            min-width: 200px;
        }
        .estado-pendiente { background-color: #ffc107; color: #000; }
        .estado-revision { background-color: #0dcaf0; color: #000; }
        .estado-boxnodisponible { background-color: #fd7e14; color: #fff; }
        .estado-autorizada { background-color: #198754; color: #fff; }
        .estado-implementada { background-color: #20c997; color: #fff; }
        .estado-anulada { background-color: #dc3545; color: #fff; }
        
        .content-area {
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        .header {
            background-color: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        /* Estilos para los filtros */
        .filtro-activo {
            border-left: 4px solid #3498db !important;
            background-color: #f8f9fa !important;
        }
        
        .filtro-group {
            transition: all 0.3s ease;
        }
        
        .filtro-group:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
        
        /* Estilos para el modo edición */
        .modo-edicion {
            border: 2px solid #ffc107 !important;
            background-color: #fffbf0 !important;
        }
        
        .btn-group-sm .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        
        /* Información de cálculo en tiempo real */
        #infoCalculo {
            font-size: 0.875rem;
            padding: 0.5rem;
            background-color: #f8f9fa;
            border-radius: 0.375rem;
            border-left: 4px solid #0dcaf0;
        }
        
        /* Mejoras visuales para la tabla de actividades */
        #tablaActividades td, #tablaActividades th {
            vertical-align: middle;
        }
        
        #tablaActividades .btn-group {
            white-space: nowrap;
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
		
		.card-horarios {
            border-left: 4px solid #0dcaf0;
        }
        
        .horario-dia {
            border-bottom: 1px solid #e9ecef;
            padding: 0.5rem 0;
        }
        
        .horario-dia:last-child {
            border-bottom: none;
        }
        
        .espacio-libre {
            background-color: #d1ecf1;
            border-left: 3px solid #0dcaf0;
            padding: 0.25rem 0.5rem;
            margin: 0.25rem 0;
            border-radius: 0.25rem;
            font-size: 0.875rem;
        }
        
        .sin-espacios {
            color: #6c757d;
            font-style: italic;
        }
        
        .badge-horario {
            font-size: 0.75em;
            padding: 0.25em 0.5em;
        }

		.export-buttons .btn {
			border-radius: 4px !important;
			padding: 0.375rem 0.75rem;
			font-size: 0.875rem;
		}
		.export-buttons .btn i {
			font-size: 0.9em;
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
		}
		
		/* Estilos para la barra de progreso */
		.progress {
			background-color: #e9ecef;
			border-radius: 10px;
			overflow: hidden;
			box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);
		}

		.progress-bar {
			transition: width 0.5s ease-in-out;
			font-size: 0.8rem;
			font-weight: 600;
			line-height: 25px;
			white-space: nowrap;
		}

		.progress-bar.bg-success {
			background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
		}

		.progress-bar.bg-warning {
			background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
		}

		.progress-bar.bg-danger {
			background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
		}

		.progress-bar.bg-primary {
			background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
		}

		.progress-bar.bg-info {
			background: linear-gradient(135deg, #17a2b8 0%, #0dcaf0 100%);
		}

		/* Animación de pulso para cuando se completa */
		@keyframes pulse {
			0% { opacity: 1; }
			50% { opacity: 0.7; }
			100% { opacity: 1; }
		}

		.progress-bar.bg-success {
			animation: pulse 0.5s ease-in-out;
		}

		/* Estilo para la card de progreso */
		#cardProgresoHoras {
			border-left: 4px solid #0d6efd;
			background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
			transition: all 0.3s ease;
		}

		#cardProgresoHoras:hover {
			transform: translateY(-2px);
			box-shadow: 0 4px 12px rgba(0,0,0,0.1);
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

                <!-- Header -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-calendar-plus me-2"></i>Registro de Nueva Agenda
                    </h1>
                </div>
				<!--

                <!-- Formulario de Registro -->
				
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-edit me-2"></i>Complete los datos de la nueva agenda
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="agendaForm">
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label for="especialidad_id" class="form-label">Unidad | Servicio</label>
                                    <select class="form-select" id="especialidad_id" name="especialidad_id" required>
                                        <option value="">Seleccione su unidad o servicio</option>
                                        <?php
                                        $especialidades = $auth->getEspecialidades();
                                        foreach ($especialidades as $especialidad): ?>
                                            <option value="<?php echo $especialidad['id']; ?>">
                                                <?php echo htmlspecialchars($especialidad['nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="profesional_id" class="form-label">Profesional</label>
									<select class="form-select" id="profesional_id" name="profesional_id" required disabled>
										<option value="">Primero seleccione una unidad</option>
									</select>
									<!--<small id="profesional-help" class="form-text text-muted">
										Seleccione una unidad o servicio para ver los profesionales disponibles
									</small>-->
                                        <?php
                                        $profesionales = $auth->getProfesionales();
                                        foreach ($profesionales as $profesional): ?>
                                            <option value="<?php echo $profesional['id']; ?>">
                                                <?php echo htmlspecialchars($profesional['nombre'] ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
								<!-- mostrar estamento profesional -->
								<div class="col-md-3">
									<label for="estamento" class="form-label">Estamento</label>
									<input type="text" class="form-control" name="estamento" id="estamento" readonly >
									<small class="form-text text-muted">...</small>
								</div>
								<div class="col-md-2">
                                    <label for="horas_contrato" class="form-label">Horas de Contrato</label>
                                    <select class="form-select" id="horas_contrato" name="horas_contrato" required>
                                        <option value="">Seleccione</option>
                                        <option value="11">11 horas</option>
                                        <option value="22">22 horas</option>
                                        <option value="33">33 horas</option>
                                        <option value="44">44 horas</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="fecha_inicio" class="form-label">Fecha de Inicio</label>
                                    <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" required>
                                </div>	
								<div class="col-md-6">
									<label for="descripcion" class="form-label">Descripción</label>
									<textarea class="form-control" id="descripcion" name="descripcion" rows="1" placeholder="Descripción de la agenda..."></textarea>
								</div>
								
                                <div class="col-md-1">
                                    <label for="estado" class="form-label" hidden>Estado</label>
                                    <select class="form-select" id="estado" name="estado" required hidden>
                                        <option value="">Seleccione un estado</option>
                                        <option value="pendiente" selected>Pendiente</option>
                                        <option value="revision">En Revisión</option>
                                        <option value="boxnodisponible">Box No Disponible</option>
                                        <option value="autorizada">Autorizada</option>
                                        <option value="implementada">Implementada</option>
                                        <option value="anulada">Anulada</option>
                                    </select>
                                </div>	
									<div class="d-flex justify-content-end">
										<button type="button" class="btn btn-secondary me-2" id="btnLimpiar">Limpiar</button>
										<button type="submit" class="btn btn-primary" name="registrar_agenda">Guardar Agenda</button>
									</div>	
							</div>	
                        </form>
                    </div>
                </div>

                <!-- Estadísticas Rápidas -->
                <div class="row mb-4">
                    <?php
                    $estados_agenda = [
                        'pendiente' => ['bg-warning', 'Pendiente'],
                        'revision' => ['bg-info', 'En Revisión'],
                        'boxnodisponible' => ['bg-warning', 'Box No Disponible'],
                        'autorizada' => ['bg-success', 'Autorizada'],
                        'implementada' => ['bg-success', 'Implementada'],
                        'anulada' => ['bg-danger', 'Anulada']
                    ];
                    
                    foreach ($estados_agenda as $estado => $info): ?>
					<div class="col-md-2">
                        <div class="col-auto">
                            <div class="card text-center">
                                <div class="card-body py-2">
                                    <span class="badge <?php echo $info[0]; ?> badge-estado"><?php echo $info[1]; ?></span>
                                    <h5 class="mt-2">
                                        <?php echo count(array_filter($todasAgendas, function($a) use ($estado) { 
                                            return $a['estado'] === $estado; 
                                        })); ?>
                                    </h5>
                                </div>
                            </div>
                        </div>
					</div>
                    <?php endforeach; ?>
                </div>

                <!-- Filtros -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title"><i class="fas fa-filter me-2"></i>Filtros</h6>
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label for="filtroEspecialidad" class="form-label">Unidad | Servicio</label>
                                        <select class="form-select" id="filtroEspecialidad">
                                            <option value="">Todas las unidades</option>
                                            <?php
                                            $especialidades = $auth->getEspecialidades();
                                            foreach ($especialidades as $especialidad): ?>
                                                <option value="<?php echo htmlspecialchars($especialidad['nombre']); ?>">
                                                    <?php echo htmlspecialchars($especialidad['nombre']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="filtroBusqueda" class="form-label">Profesional</label>
                                        <input type="text" class="form-control" id="filtroBusqueda" 
                                               placeholder="Buscar en toda la tabla...">
                                    </div>									
                                    <div class="col-md-3">
                                        <label for="filtroEstado" class="form-label">Estado</label>
                                        <select class="form-select" id="filtroEstado">
                                            <option value="">Todos los estados</option>
                                            <option value="pendiente">Pendiente</option>
                                            <option value="revision">En Revisión</option>
                                            <option value="boxnodisponible">Box No Disponible</option>
                                            <option value="autorizada">Autorizada</option>
                                            <option value="implementada">Implementada</option>
                                            <option value="anulada">Anulada</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="filtroFecha" class="form-label">Fecha Inicio</label>
                                        <input type="date" class="form-control" id="filtroFecha">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="filtroProfesional" class="form-label" hidden>Profesional</label>
                                        <select class="form-select" id="filtroProfesional" hidden>
                                            <option value="" hidden>Todos los profesionales</option>
                                            <?php
                                            $profesionales = $auth->getProfesionales();
                                            foreach ($profesionales as $profesional): ?>
                                                <option value="<?php echo htmlspecialchars($profesional['nombre']); ?>" hidden>
                                                    <?php echo htmlspecialchars($profesional['nombre']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>        
                                    <div class="col-md-3">
                                        <label class="form-label d-block">&nbsp;</label>
                                        <button type="button" class="btn btn-outline-secondary w-100" id="btnLimpiarFiltros">
                                            <i class="fas fa-eraser me-1"></i>Limpiar Filtros
                                        </button>
                                    </div>
                                    

									<script>
									// Función para exportación rápida
										function exportarRapido() {
											// Crear formulario temporal
											const form = document.createElement('form');
											form.method = 'GET';
											form.action = 'exportar_agendas.php';
											form.target = '_blank';
											document.body.appendChild(form);
											form.submit();
											document.body.removeChild(form);
										}
									</script>
									<div class="col-md-3">
                                        <label class="form-label d-block">&nbsp;</label>
                                        <button type="button" class="btn btn-outline-success w-100"  onclick="exportarRapido()">
                                            <i class="fas fa-file-export me-1"></i>Exportar
                                        </button>
                                    </div>
                                </div>
								<!-- En la sección de Filtros, contador de resultados -->
								<div class="row mt-3">
									<div class="col-md-12">
										<div class="alert alert-primary py-2" id="contadorResultados">
											<div class="row align-items-center">
												<div class="col-md-8">
													<i class="fas fa-filter me-2"></i>
													Mostrando <strong id="totalFilas"><?php echo count($agendas); ?></strong> de 
													<strong><?php echo count($agendas); ?></strong> agendas
												</div>
												<div class="col-md-4 text-end">
													<small id="estadoFiltros" class="badge bg-light text-dark">
														<i class="fas fa-check me-1"></i>Sin filtros activos
													</small>
												</div>
											</div>
										</div>
									</div>
								</div>
                            </div>
                        </div>
                    </div>
                </div>

				<!-- Tabla de Agendas Registradas -->
				<div class="card">
					<div class="card-header d-flex justify-content-between align-items-center">
						<h5 class="card-title mb-0">
							<i class="fas fa-list me-2"></i>Agendas Registradas
						</h5>
						<div class="registros-info">
							<span class="badge bg-secondary">Mostrando <?php echo count($agendas); ?> de <?php echo $total_agendas; ?> registros</span>
						</div>
					</div>
					<div class="card-body">
						<?php if (empty($agendas)): ?>
							<div class="text-center py-4">
								<i class="fas fa-inbox fa-3x text-muted mb-3"></i>
								<p class="text-muted">No hay agendas registradas</p>
							</div>
						<?php else: ?>
							<div class="table-responsive">
								<table class="table table-hover" id="tablaAgendas">
									<thead>
										<tr>
											<th>ID</th>
											<th>Unidad | Servicio</th>
											<th>Profesional</th>
											<th>Contrato</th>
											<th>Estamento</th>
											<th>F. Inicio</th>
											<th>Estado</th>
											<th>Ingresado</th>
											<th>Actualizado</th>
											<th>Acciones</th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($agendas as $agenda): 
											$estados_permitidos = $auth->getFlujoEstadosAgenda($agenda['estado']);
										?>
										<tr>
											<td><?php echo $agenda['id']; ?></td>
											<td><?php echo htmlspecialchars($agenda['especialidad_nombre']); ?></td>
											<td><?php echo htmlspecialchars($agenda['profesional_nombre']); ?></td>
											<td><?php echo $agenda['horas_contrato']; ?> horas</td>
											<td><?php echo htmlspecialchars($agenda['estamento'] ?? '-'); ?></td>
											<td><?php echo date('d/m/Y', strtotime($agenda['fecha_inicio'])); ?></td>
											<td>
												<span class="badge bg-<?php echo $auth->getClaseEstadoAgenda($agenda['estado']); ?> badge-estado">
													<?php echo $auth->getNombreEstadoAgenda($agenda['estado']); ?>
												</span>
											</td>
											<td>
												<small>
													<?php echo htmlspecialchars($agenda['usuario_registro']); ?><br>
													<span class="text-muted"><?php echo date('d/m/Y H:i', strtotime($agenda['timestamp_registro'])); ?></span>
												</small>
											</td>
											<td>
												<?php if ($agenda['usuario_modificacion']): ?>
													<small>
														<?php echo htmlspecialchars($agenda['usuario_modificacion']); ?><br>
														<span class="text-muted"><?php echo date('d/m/Y H:i', strtotime($agenda['timestamp_modificacion'])); ?></span>
													</small>
												<?php else: ?>
													<span class="text-muted">-</span>
												<?php endif; ?>
											</td>
											
											<td>
												<div class="btn-group btn-group-sm">
													<!-- Botón para cambio de estado -->
													<button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" 
															<?php echo empty($estados_permitidos) ? 'disabled' : ''; ?>>
														<i class="fas fa-cog"></i>
													</button>
													<ul class="dropdown-menu dropdown-estado">
														<?php foreach ($estados_permitidos as $estado_permitido): ?>
														<li>
															<form method="POST" style="display: inline;">
																<input type="hidden" name="agenda_id" value="<?php echo $agenda['id']; ?>">
																<input type="hidden" name="nuevo_estado" value="<?php echo $estado_permitido; ?>">
																<button type="submit" name="cambiar_estado" 
																		class="dropdown-item text-<?php echo $auth->getClaseEstadoAgenda($estado_permitido); ?>"
																		onclick="return confirm('¿Cambiar estado a <?php echo $auth->getNombreEstadoAgenda($estado_permitido); ?>?')">
																	<i class="fas fa-arrow-right me-2"></i>
																	<?php echo $auth->getNombreEstadoAgenda($estado_permitido); ?>
																</button>
															</form>
														</li>
														<?php endforeach; ?>
													</ul>
													<button type="button" class="btn btn-outline-secondary btn-editar-agenda" 
															data-agenda-id="<?php echo $agenda['id']; ?>"
															data-especialidad="<?php echo htmlspecialchars($agenda['especialidad_nombre']); ?>"
															data-profesional="<?php echo htmlspecialchars($agenda['profesional_nombre']); ?>"
															data-horas-contrato="<?php echo $agenda['horas_contrato']; ?>"
															data-descripcion="<?php echo htmlspecialchars($agenda['descripcion']); ?>"
															data-estado="<?php echo $agenda['estado']; ?>"
															title="Editar Id Agenda">
														<i class="fas fa-edit"></i>
													</button>
													<!-- Botón para gestionar detalles de agenda -->
													<button type="button" class="btn btn-outline-info btn-gestionar-detalles" 
															data-agenda-id="<?php echo $agenda['id']; ?>"
															data-especialidad="<?php echo htmlspecialchars($agenda['especialidad_nombre']); ?>"
															data-profesional="<?php echo htmlspecialchars($agenda['profesional_nombre']); ?>"
															data-horas-contrato="<?php echo $agenda['horas_contrato']; ?>"
															data-descripcion="<?php echo htmlspecialchars($agenda['descripcion']); ?>"
															data-estamento="<?php echo htmlspecialchars($agenda['estamento'] ?? ''); ?>"
															data-estado="<?php echo htmlspecialchars($agenda['estado']); ?>"
															title="Gestionar detalles de agenda">
														<i class="fas fa-calendar-plus"></i>
													</button>
													<!-- Copiar detalles de agenda -->
													<button type="button" class="btn btn-outline-warning btn-copiar-detalles" 
															data-agenda-id="<?php echo $agenda['id']; ?>"
															data-especialidad="<?php echo htmlspecialchars($agenda['especialidad_nombre']); ?>"
															data-profesional="<?php echo htmlspecialchars($agenda['profesional_nombre']); ?>"
															data-horas-contrato="<?php echo $agenda['horas_contrato']; ?>"
															title="Copiar detalles a otra agenda">
														<i class="fas fa-copy"></i>
													</button>
													<!-- Botón para ver resumen de agenda -->
													<a href="resumen_agenda.php?id=<?php echo $agenda['id']; ?>" 
													   class="btn btn-outline-secondary btn-sm" 
													   target="_blank"
													   title="Ver resumen de agenda">
														<i class="fas fa-chalkboard-user"></i>
													</a>
												</div>
											</td>
										</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
							
							<!-- Paginación -->
							<div class="pagination-container mt-3">
								<div class="page-info">
									<i class="fas fa-info-circle me-1"></i>
									Página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?>
								</div>
								
								<nav aria-label="Navegación de páginas">
									<ul class="pagination pagination-sm mb-0">
										<!-- Botón Primera página -->
										<li class="page-item <?php echo $pagina_actual <= 1 ? 'disabled' : ''; ?>">
											<a class="page-link" href="?pagina=1<?php echo isset($_GET['registros']) ? '&registros=' . $_GET['registros'] : ''; ?>">
												<i class="fas fa-angle-double-left"></i>
											</a>
										</li>
										
										<!-- Botón Anterior -->
										<li class="page-item <?php echo $pagina_actual <= 1 ? 'disabled' : ''; ?>">
											<a class="page-link" href="?pagina=<?php echo $pagina_actual - 1; ?><?php echo isset($_GET['registros']) ? '&registros=' . $_GET['registros'] : ''; ?>">
												<i class="fas fa-angle-left"></i>
											</a>
										</li>
										
										<?php
										$inicio_paginacion = max(1, $pagina_actual - 2);
										$fin_paginacion = min($total_paginas, $pagina_actual + 2);
										
										// Mostrar primera página si no está en el rango
										if ($inicio_paginacion > 1) {
											echo '<li class="page-item"><a class="page-link" href="?pagina=1' . (isset($_GET['registros']) ? '&registros=' . $_GET['registros'] : '') . '">1</a></li>';
											if ($inicio_paginacion > 2) {
												echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
											}
										}
										
										// Páginas intermedias
										for ($i = $inicio_paginacion; $i <= $fin_paginacion; $i++) {
											echo '<li class="page-item ' . ($pagina_actual == $i ? 'active' : '') . '">
													<a class="page-link" href="?pagina=' . $i . (isset($_GET['registros']) ? '&registros=' . $_GET['registros'] : '') . '">' . $i . '</a>
												  </li>';
										}
										
										// Mostrar última página si no está en el rango
										if ($fin_paginacion < $total_paginas) {
											if ($fin_paginacion < $total_paginas - 1) {
												echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
											}
											echo '<li class="page-item"><a class="page-link" href="?pagina=' . $total_paginas . (isset($_GET['registros']) ? '&registros=' . $_GET['registros'] : '') . '">' . $total_paginas . '</a></li>';
										}
										?>
										
										<!-- Botón Siguiente -->
										<li class="page-item <?php echo $pagina_actual >= $total_paginas ? 'disabled' : ''; ?>">
											<a class="page-link" href="?pagina=<?php echo $pagina_actual + 1; ?><?php echo isset($_GET['registros']) ? '&registros=' . $_GET['registros'] : ''; ?>">
												<i class="fas fa-angle-right"></i>
											</a>
										</li>
										
										<!-- Botón Última página -->
										<li class="page-item <?php echo $pagina_actual >= $total_paginas ? 'disabled' : ''; ?>">
											<a class="page-link" href="?pagina=<?php echo $total_paginas; ?><?php echo isset($_GET['registros']) ? '&registros=' . $_GET['registros'] : ''; ?>">
												<i class="fas fa-angle-double-right"></i>
											</a>
										</li>
									</ul>
								</nav>
								
								<!-- Selector de registros por página -->
								<div class="registros-per-page">
									<label class="me-2 small">Registros por página:</label>
									<select class="form-select form-select-sm registros-select" id="registrosPorPagina" style="width: auto;">
										<option value="10" <?php echo $registros_por_pagina == 10 ? 'selected' : ''; ?>>10</option>
										<option value="15" <?php echo $registros_por_pagina == 15 ? 'selected' : ''; ?>>15</option>
										<option value="25" <?php echo $registros_por_pagina == 25 ? 'selected' : ''; ?>>25</option>
										<option value="50" <?php echo $registros_por_pagina == 50 ? 'selected' : ''; ?>>50</option>
										<option value="100" <?php echo $registros_por_pagina == 100 ? 'selected' : ''; ?>>100</option>
									</select>
								</div>
							</div>
						<?php endif; ?>
					</div>
				</div>
            </main>
        </div>
    </div>

    <!-- Modal para gestionar detalles de agenda  -->
    <div class="modal fade" id="modalDetallesAgenda" tabindex="-1" aria-labelledby="modalDetallesAgendaLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalDetallesAgendaLabel">
                        <i class="fas fa-calendar-plus me-2"></i>Gestión de Detalles de Agenda
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Información de la agenda -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="card bg-light">
                                <div class="card-body py-3">
                                    <div class="row">
                                        <div class="col-md-2">
                                            <strong>ID Agenda:</strong> <span id="detalleAgendaId">-</span>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Unidad | Servicio:</strong> <span id="detalleEspecialidad">-</span>
                                        </div>
                                        <div class="col-md-5">
                                            <strong>Profesional:</strong> <span id="detalleProfesional">-</span>
                                        </div>
                                        <div class="col-md-2">
                                            <strong>Contrato:</strong> <span id="detalleHorasContrato">-</span>
                                        </div>
										<div class="col-md-4">
                                            <strong>Estamento:</strong> <span id="detalleEstamento">-</span>
                                        </div>
										<div class="col-md-3">
                                            <strong>Descripción:</strong> <span id="detalleDescripcion">-</span>
											<span id="detalleEstado" hidden="false"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>  
                        </div>
                    </div>
					<!-- Barra de Progreso de Horas -->
					<div class="card mb-3" id="cardProgresoHoras">
						<div class="card-body py-3">
							<div class="row align-items-center">
								<div class="col-md-12">
									<div class="d-flex justify-content-between align-items-center mb-2">
										<div>
											<strong>
												<i class="fas fa-chart-line me-1 text-primary"></i>
												Asignación de horas contratadas
											</strong>
										</div>
										<div>
											<span id="progresoTextoHoras" class="badge bg-primary">0%</span>
										</div>
									</div>
									
									<!-- Barra de progreso principal -->
									<div class="progress mb-2" style="height: 25px;">
										<div id="barraProgresoHoras" 
											 class="progress-bar progress-bar-striped progress-bar-animated" 
											 role="progressbar" 
											 style="width: 0%;" 
											 aria-valuenow="0" 
											 aria-valuemin="0" 
											 aria-valuemax="100">
											<span id="barraTextoInterno" class="fw-bold">0 / 0 h</span>
										</div>
									</div>
									
									<!-- Subtítulo con detalles -->
									<div class="row mt-2">
										<div class="col-md-4">
											<small class="text-muted">
												<i class="fas fa-calendar-alt me-1"></i>
												<strong>Contrato:</strong> <span id="progresoHorasContrato">0</span> horas
											</small>
										</div>
										<div class="col-md-4">
											<small class="text-muted">
												<i class="fas fa-check-circle me-1"></i>
												<strong>Asignadas:</strong> <span id="progresoHorasAsignadas">0</span> horas
											</small>
										</div>
										<div class="col-md-4">
											<small id="progresoEstadoHoras" class="text-warning">
												<i class="fas fa-info-circle me-1"></i>
												<strong>Estado:</strong> Pendiente
											</small>
										</div>
									</div>
									
									<!-- Barra de advertencia por exceso -->
									<div id="excesoHorasAdvertencia" class="mt-2" style="display: none;">
										<div class="alert alert-danger alert-sm py-1 mb-0">
											<i class="fas fa-exclamation-triangle me-1"></i>
											<small><strong>Exceso de horas!</strong> Ha superado las horas contratadas en <span id="excesoHorasCantidad">0</span> horas</small>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>

                    <!-- HORARIOS POR DÍA -->
                    <div class="card card-horarios mb-4" id="cardHorarios" style="display: none;">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-clock me-2"></i>Resumen de Horarios por Día
                            </h6>
                        </div>
                        <div class="card-body" id="cuerpoHorarios">
                            <!-- La información de horarios se cargará aquí -->
                        </div>
                    </div>
                    <!-- Formulario para nueva actividad -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-plus-circle me-2"></i>Agregar Nueva Actividad
                            </h6>
                        </div>
                        <div class="card-body">
                            <form id="formNuevaActividad">
                                <input type="hidden" id="agendaId" name="agenda_id">
                                <input type="hidden" id="actividadEditId" name="actividad_edit_id" value="">
                                
                                <div class="row g-3">
                                    <div class="col-md-2">
                                        <label for="diaSemana" class="form-label">Día de la Semana</label>
                                        <select class="form-select" id="diaSemana" name="dia_semana" required>
                                            <option value="">Seleccionar</option>
                                            <option value="lunes">Lunes</option>
                                            <option value="martes">Martes</option>
                                            <option value="miercoles">Miércoles</option>
                                            <option value="jueves">Jueves</option>
                                            <option value="viernes">Viernes</option>
                                            <option value="sabado">Sábado</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="actividadId" class="form-label">Actividad</label>
                                        <select class="form-select" id="actividadId" name="actividad_id" required>
                                            <option value="">Cargando actividades...</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <label for="horaInicio" class="form-label">Hora Inicio</label>
                                        <input type="time" class="form-control" id="horaInicio" name="hora_inicio" required>
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <label for="horaFin" class="form-label">Hora Fin</label>
                                        <input type="time" class="form-control" id="horaFin" name="hora_fin" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="agendamiento" class="form-label">Tipo de Agendamiento</label>
                                        <select class="form-select" id="agendamiento" name="agendamiento" required >
                                            <option value="No Aplica" selected>No Aplica</option>
                                            <option value="Escalonado">Escalonado</option>
                                            <option value="En Bloque">En Bloque (1 hora)</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-5">
                                        <label for="detalle" class="form-label">Detalle</label>
                                        <textarea class="form-control" id="detalle" name="detalle" rows="1" placeholder="Descripción detallada de la actividad..."></textarea>
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <label for="rendimiento" class="form-label">Rendimiento</label>
                                        <input type="number" class="form-control" id="rendimiento" name="rendimiento" 
                                               min="0" max="500" step="0.01" value="0.00" required>
                                    </div>
                                    <div class="col-md-5">
                                        <label for="especialidadRemId" class="form-label">Item REM</label>
                                        <select class="form-select" id="especialidadRemId" name="especialidad_rem_id" style="font-size: 0.875em;">
                                            <option value="" selected>No Aplica</option>
                                        </select>	
                                    </div>
									<div class="col-md-2">
										<label for="sector" class="form-label">Sector</label>
										<select class="form-select" id="sector" name="sector" >
											<option value="">Seleccionar sector</option>
											<?php
											// Cargar sectores agrupados con sus ubicaciones
											try {
												$query_ubicaciones = "SELECT id, sector, ubicacion FROM ubicaciones WHERE activo = 1 ORDER BY sector, ubicacion";
												$stmt_ubicaciones = $conn->prepare($query_ubicaciones);
												$stmt_ubicaciones->execute();
												$todas_ubicaciones = $stmt_ubicaciones->fetchAll(PDO::FETCH_ASSOC);
												
												// Agrupar por sector
												$sectores_con_ubicaciones = [];
												foreach ($todas_ubicaciones as $ubi) {
													if (!isset($sectores_con_ubicaciones[$ubi['sector']])) {
														$sectores_con_ubicaciones[$ubi['sector']] = [];
													}
													$sectores_con_ubicaciones[$ubi['sector']][] = $ubi;
												}
												
												foreach ($sectores_con_ubicaciones as $sector_nombre => $ubicaciones_lista) {
													echo '<option value="' . htmlspecialchars($sector_nombre) . '">' 
														 . htmlspecialchars($sector_nombre) . ' (' . count($ubicaciones_lista) . ')</option>';
												}
											} catch(PDOException $e) {
												echo '<option value="">Error al cargar sectores</option>';
											}
											?>
										</select>
									</div>
									<div class="col-md-3">
										<label for="ubicacion" class="form-label">Ubicación / Box</label>
										<select class="form-select" id="ubicacion" name="ubicacion" >
											<option value="">Primero seleccione un sector</option>
										</select>
									</div>

									<script>
									// Versión optimizada con datos precargados
									document.addEventListener('DOMContentLoaded', function() {
										// Datos precargados desde PHP
										const ubicacionesData = <?php 
											$data_json = [];
											foreach ($todas_ubicaciones as $ubi) {
												$data_json[$ubi['sector']][] = [
													'id' => $ubi['id'],
													'ubicacion' => $ubi['ubicacion']
												];
											}
											echo json_encode($data_json);
										?>;
										
										const selectSector = document.getElementById('sector');
										const selectUbicacion = document.getElementById('ubicacion');
										
										function actualizarUbicaciones() {
											const sectorSeleccionado = selectSector.value;
											
											if (!sectorSeleccionado || !ubicacionesData[sectorSeleccionado]) {
												selectUbicacion.innerHTML = '<option value="">Primero seleccione un sector</option>';
												selectUbicacion.disabled = true;
												return;
											}
											
											const ubicaciones = ubicacionesData[sectorSeleccionado];
											selectUbicacion.innerHTML = '<option value="">Seleccione una ubicación</option>';
											
											ubicaciones.forEach(ubi => {
												const option = document.createElement('option');
												option.value = ubi.ubicacion;
												option.textContent = ubi.ubicacion;
												option.setAttribute('data-ubicacion-id', ubi.id);
												selectUbicacion.appendChild(option);
											});
											
											selectUbicacion.disabled = false;
										}
										
										if (selectSector) {
											selectSector.addEventListener('change', actualizarUbicaciones);
											
											// Si hay valor preseleccionado
											if (selectSector.value) {
												actualizarUbicaciones();
											}
										}
									});
									</script>
									<div class="col-md-2">
										<input type="text" id="rendimientoCNInput" class="form-control" readonly style="font-size: 0.775em; border:none;">
									</div>
									
                                    <div class="col-md-5">
                                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                            <button type="button" class="btn btn-secondary me-md-2" id="btnCancelarEdicion" style="display: none;">
                                                <i class="fas fa-times me-2"></i>Cancelar Edición
                                            </button>
                                            <button type="submit" class="btn btn-success" id="btnSubmitActividad">
                                                <i class="fas fa-plus me-2"></i>Agregar Actividad
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>


					<!-- Lista de actividades agregadas -->
					<div class="card">
						<div class="card-header">
							<h6 class="card-title mb-0">
								<i class="fas fa-list me-2"></i>Actividades Agregadas
								<span id="totalActividadesBadge" class="badge bg-secondary ms-2">0 actividades</span>
							</h6>
						</div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover" id="tablaActividades">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Día</th>
                                            <th>Actividad</th>
                                            <th>Detalle</th>
                                            <th>Horario</th>
                                            <th>Horas</th>
                                            <th>Rendimiento</th>
                                            <th>Cupos</th>
											<th>Agendamiento</th>
                                            <th>Ubicación</th>
                                            <th>Item REM</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="cuerpoActividades">
                                        <!-- Las actividades se cargarán aquí -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cerrar
                    </button>
                    <button type="button" class="btn btn-primary" id="btnGuardarTodo">
                        <i class="fas fa-save me-2"></i>Guardar Todo
                    </button>
									
                </div>
            </div>
        </div>
    </div>

	<!-- Modal para Copiar Detalles de Agenda -->
	<div class="modal fade" id="modalCopiarDetalles" tabindex="-1" aria-labelledby="modalCopiarDetallesLabel" aria-hidden="true">
		<div class="modal-dialog modal-lg">
			<div class="modal-content">
				<div class="modal-header bg-warning text-white">
					<h5 class="modal-title" id="modalCopiarDetallesLabel">
						<i class="fas fa-copy me-2"></i>Copiar Detalles de Agenda
					</h5>
					<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<!-- Información de la agenda origen -->
					<div class="card bg-light mb-4">
						<div class="card-body">
							<h6 class="card-title">Agenda Origen</h6>
							<div class="row">
								<div class="col-md-2">
									<strong>ID:</strong> <span id="copiarOrigenId">-</span>
								</div>
								<div class="col-md-4">
									<strong>Unidad:</strong> <span id="copiarOrigenEspecialidad">-</span>
								</div>
								<div class="col-md-4">
									<strong>Profesional:</strong> <span id="copiarOrigenProfesional">-</span>
								</div>
							</div>
						</div>
					</div>

					<!-- Selección de agenda destino -->
					<div class="mb-3">
						<label for="agendaDestino" class="form-label">Seleccionar Agenda Destino</label>
						<select class="form-select" id="agendaDestino" required>
							<option value="">Seleccione una agenda destino</option>
							<?php foreach ($agendas as $agenda_dest): ?>
								<option value="<?php echo $agenda_dest['id']; ?>">
									ID: <?php echo $agenda_dest['id']; ?> - 
									<?php echo htmlspecialchars($agenda_dest['especialidad_nombre']); ?> - 
									<?php echo htmlspecialchars($agenda_dest['profesional_nombre']); ?> -
									<?php echo $agenda_dest['horas_contrato']; ?>h
								</option>
							<?php endforeach; ?>
						</select>
						<div class="form-text">
							Seleccione la agenda a la que desea copiar los detalles. 
							<strong class="text-warning">Advertencia:</strong> Esto reemplazará los detalles existentes en la agenda destino.
						</div>
					</div>


					<!-- Resumen de actividades a copiar -->
					<div class="card mt-3">
						<div class="card-header">
							<h6 class="card-title mb-0">Resumen de Actividades a Copiar</h6>
						</div>
						<div class="card-body">
							<div id="resumenActividadesCopiar">
								<p class="text-muted">Se cargarán las actividades al seleccionar la agenda origen</p>
							</div>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
						<i class="fas fa-times me-2"></i>Cancelar
					</button>
					<button type="button" class="btn btn-warning" id="btnConfirmarCopia">
						<i class="fas fa-copy me-2"></i>Copiar Detalles
					</button>
				</div>
			</div>
		</div>
	</div>

		<!-- Modal para Copiar Actividad -->
		<div class="modal fade" id="modalCopiarActividad" tabindex="-1" aria-labelledby="modalCopiarActividadLabel" aria-hidden="true">
			<div class="modal-dialog modal-lg">
				<div class="modal-content">
					<div class="modal-header bg-info text-white">
						<h5 class="modal-title" id="modalCopiarActividadLabel">
							<i class="fas fa-copy me-2"></i>Copiar Actividad
						</h5>
						<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
					</div>
					<div class="modal-body">
						<!-- Información de la actividad origen -->
						<div class="card bg-light mb-3">
							<div class="card-body py-2">
								<h6 class="card-title mb-2">Actividad Original</h6>
								<div class="row small">
									<div class="col-md-2">
										<strong>Día:</strong> <span id="copiarActividadDiaOrigen">-</span>
									</div>
									<div class="col-md-4">
										<strong>Horario:</strong> <span id="copiarActividadHorarioOrigen">-</span>
									</div>
									<div class="col-md-6">
										<strong>Actividad:</strong> <span id="copiarActividadNombreOrigen">-</span>
									</div>
									<div class="col-md-4">
										<strong>Sector:</strong> <span id="copiarActividadSectorOrigen">-</span>
									</div>
									<div class="col-md-4">
										<strong>Ubicación:</strong> <span id="copiarActividadUbicacionOrigen">-</span>
									</div>
									<div class="col-md-4">
										<strong>Duración:</strong> <span id="copiarActividadDuracionOrigen">-</span>
									</div>
								</div>
							</div>
						</div>

						<!-- Formulario para nueva ubicación -->
						<form id="formCopiarActividad">
							<input type="hidden" id="actividadOrigenId" name="actividad_origen_id">
							<input type="hidden" id="ubicacionOrigenCompleta" name="ubicacion_origen_completa">
							
							<div class="row g-3">
								<div class="col-md-4">
									<label for="nuevoDiaSemana" class="form-label">Nuevo Día *</label>
									<select class="form-select" id="nuevoDiaSemana" name="nuevo_dia_semana" required>
										<option value="">Seleccionar día</option>
										<option value="lunes">Lunes</option>
										<option value="martes">Martes</option>
										<option value="miercoles">Miércoles</option>
										<option value="jueves">Jueves</option>
										<option value="viernes">Viernes</option>
										<option value="sabado">Sábado</option>
									</select>
								</div>
								<div class="col-md-4">
									<label for="nuevaHoraInicio" class="form-label">Nueva Hora Inicio *</label>
									<input type="time" class="form-control" id="nuevaHoraInicio" name="nueva_hora_inicio" required>
								</div>
								
								<div class="col-md-4">
									<label for="nuevaHoraFin" class="form-label">Nueva Hora Fin *</label>
									<input type="time" class="form-control" id="nuevaHoraFin" name="nueva_hora_fin" required>
								</div>
								<div class="col-md-6">
									<label for="nuevoSector" class="form-label">Nuevo Sector *</label>
									<select class="form-select" id="nuevoSector" name="nuevo_sector" >
										<option value="">Seleccionar sector</option>
										<?php
										// Cargar sectores desde la tabla ubicaciones
										try {
											$query_sectores = "SELECT DISTINCT sector FROM ubicaciones WHERE activo = 1 ORDER BY sector";
											$stmt_sectores = $conn->prepare($query_sectores);
											$stmt_sectores->execute();
											$sectores_copia = $stmt_sectores->fetchAll(PDO::FETCH_ASSOC);
											foreach ($sectores_copia as $sector_item) {
												echo '<option value="' . htmlspecialchars($sector_item['sector']) . '">' 
													 . htmlspecialchars($sector_item['sector']) . '</option>';
											}
										} catch(PDOException $e) {
											echo '<option value="">Error al cargar sectores</option>';
										}
										?>
									</select>
								</div>
								
								<div class="col-md-6">
									<label for="nuevaUbicacion" class="form-label">Nueva Ubicación *</label>
									<select class="form-select" id="nuevaUbicacion" name="nueva_ubicacion" >
										<option value="">Primero seleccione un sector</option>
									</select>
								</div>
								
								
								
								<div class="col-12">
									<div class="form-check">
										<input class="form-check-input" type="checkbox" id="ajustarRendimiento" checked>
										<label class="form-check-label" for="ajustarRendimiento">
											Calcular automáticamente horas y cupos según nuevo horario
										</label>
									</div>
								</div>
							</div>
						</form>

						<!-- Información de validación -->
						<div class="alert alert-info mt-3" id="infoValidacionCopiar" style="display: none;">
							<i class="fas fa-info-circle me-2"></i>
							<span id="textoValidacionCopiar"></span>
						</div>

						<!-- Previsualización de la nueva actividad -->
						<div class="card mt-3" id="previewNuevaActividad" style="display: none;">
							<div class="card-header">
								<h6 class="card-title mb-0">Previsualización</h6>
							</div>
							<div class="card-body">
								<div class="row small">
									<div class="col-md-4">
										<strong>Nuevo día:</strong> <span id="previewDia">-</span>
									</div>
									<div class="col-md-4">
										<strong>Nuevo horario:</strong> <span id="previewHorario">-</span>
									</div>
									<div class="col-md-4">
										<strong>Duración:</strong> <span id="previewDuracion">-</span>
									</div>
									<div class="col-md-6">
										<strong>Sector:</strong> <span id="previewSector">-</span>
									</div>
									<div class="col-md-6">
										<strong>Ubicación:</strong> <span id="previewUbicacion">-</span>
									</div>
									<div class="col-md-6">
										<strong>Cupos estimados:</strong> <span id="previewCupos">-</span>
									</div>
								</div>
							</div>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
							<i class="fas fa-times me-2"></i>Cancelar
						</button>
						<button type="button" class="btn btn-info" id="btnConfirmarCopiaActividad">
							<i class="fas fa-copy me-2"></i>Copiar Actividad
						</button>
					</div>
				</div>
			</div>
		</div>
	<!-- Modal para Editar Agenda (Descripción y Horas Contrato) -->
	<div class="modal fade" id="modalEditarAgenda" tabindex="-1" aria-labelledby="modalEditarAgendaLabel" aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header bg-secondary text-white">
					<h5 class="modal-title" id="modalEditarAgendaLabel">
						<i class="fas fa-edit me-2"></i>Editar Agenda
					</h5>
					<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<form id="formEditarAgenda" method="POST" action="">
					<div class="modal-body">
						<input type="hidden" name="editar_agenda" value="1">
						<input type="hidden" name="agenda_id" id="edit_agenda_id">
						
						<!-- Información de la agenda (solo lectura) -->
						<div class="card bg-light mb-3">
							<div class="card-body py-2">
								<div class="row">
									<div class="col-md-6">
										<small class="text-muted">ID Agenda:</small>
										<strong id="edit_agenda_id_display">-</strong>
									</div>
									<div class="col-md-6">
										<small class="text-muted">Estado:</small>
										<span id="edit_agenda_estado" class="badge"></span>
									</div>
									<div class="col-md-6">
										<small class="text-muted">Unidad | Servicio:</small>
										<strong id="edit_agenda_especialidad">-</strong>
									</div>
									<div class="col-md-6">
										<small class="text-muted">Profesional:</small>
										<strong id="edit_agenda_profesional">-</strong>
									</div>
								</div>
							</div>
						</div>
						
						<!-- Campos editables -->
						<div class="mb-3">
							<label for="edit_horas_contrato" class="form-label">
								Horas de Contrato <span class="text-danger">*</span>
							</label>
							<select class="form-select" id="edit_horas_contrato" name="horas_contrato" required>
								<option value="">Seleccione</option>
								<option value="11">11 horas</option>
								<option value="22">22 horas</option>
								<option value="33">33 horas</option>
								<option value="44">44 horas</option>
							</select>
						</div>
						
						<div class="mb-3">
							<label for="edit_descripcion" class="form-label">Descripción</label>
							<textarea class="form-control" id="edit_descripcion" name="descripcion" rows="4" 
									  placeholder="Descripción detallada de la agenda..."></textarea>
							<div class="form-text">
								<i class="fas fa-info-circle me-1"></i>
								Describa el propósito, objetivos o cualquier información relevante de esta agenda.
							</div>
						</div>
						
						<!-- Información de modificación -->
						<div class="alert alert-info mt-3">
							<small>
								<i class="fas fa-clock me-1"></i>
								Los cambios quedarán registrados con su usuario y fecha/hora de modificación.
							</small>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
							<i class="fas fa-times me-2"></i>Cancelar
						</button>
						<button type="submit" class="btn btn-primary" id="btnGuardarEdicionAgenda">
							<i class="fas fa-save me-2"></i>Guardar Cambios
						</button>
					</div>
				</form>
			</div>
		</div>
	</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
function deshabilitarBotonPorEstado() {
    // Buscar el elemento de estado - podría ser input, select, span, div, etc.
    let elementoEstado = document.getElementById('detalleEstado');
    let valorEstado = '';
    
    if (!elementoEstado) {
        console.error('Elemento detalleEstado no encontrado');
        return;
    }
    
    // Obtener valor según el tipo de elemento
    if (elementoEstado.tagName === 'INPUT' || elementoEstado.tagName === 'SELECT' || elementoEstado.tagName === 'TEXTAREA') {
        valorEstado = elementoEstado.value;
    } else {
        valorEstado = elementoEstado.textContent || elementoEstado.innerText;
    }
    
    // Limpiar y normalizar
    valorEstado = String(valorEstado).trim().toLowerCase();
    console.log('Estado detectado:', valorEstado);
    
    // Buscar botón
    const botonGuardar = document.getElementById('btnGuardarTodo');
    if (!botonGuardar) {
        console.error('Botón btnGuardarTodo no encontrado');
        return;
    }
    
    // Deshabilitar si corresponde
    if (valorEstado === 'autorizada' || valorEstado === 'anulada' || valorEstado === 'implementada') {
        botonGuardar.disabled = true;
        botonGuardar.classList.add('disabled');
        botonGuardar.setAttribute('title', 'Deshabilitado porque el estado es: ' + valorEstado);
        console.log('✓ Botón deshabilitado - Estado:', valorEstado);
    } else {
        botonGuardar.disabled = false;
        botonGuardar.classList.remove('disabled');
        botonGuardar.removeAttribute('title');
        console.log('✓ Botón habilitado - Estado:', valorEstado);
    }
}

// Ejecutar cuando el DOM esté listo
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', deshabilitarBotonPorEstado);
} else {
    deshabilitarBotonPorEstado();
}

// Si el estado puede cambiar, monitorear cambios
const elementoEstado = document.getElementById('detalleEstado');
if (elementoEstado) {
    // Para inputs, selects, textareas
    elementoEstado.addEventListener('change', deshabilitarBotonPorEstado);
    elementoEstado.addEventListener('input', deshabilitarBotonPorEstado);
    
    // Para otros elementos (MutationObserver)
    if (elementoEstado.tagName !== 'INPUT' && elementoEstado.tagName !== 'SELECT' && elementoEstado.tagName !== 'TEXTAREA') {
        const observer = new MutationObserver(function(mutations) {
            deshabilitarBotonPorEstado();
        });
        
        observer.observe(elementoEstado, {
            childList: true,
            characterData: true,
            subtree: true,
            characterDataOldValue: true
        });
    }
}
</script>
<script>
// Función para cargar el estamento del profesional seleccionado
function cargarEstamentoProfesional(profesionalId) {
    const estamentoInput = document.getElementById('estamento');
    const helpText = estamentoInput?.nextElementSibling;
    
    if (!estamentoInput) {
        console.warn('Campo de estamento no encontrado');
        return;
    }

    if (!profesionalId) {
        estamentoInput.value = '';
        if (helpText) helpText.textContent = 'Seleccione un profesional para ver su estamento';
        return;
    }

    // Mostrar loading
    estamentoInput.value = 'Cargando...';
    if (helpText) helpText.textContent = 'Cargando estamento...';

    // Hacer petición AJAX para obtener el estamento
    fetch(`api/get_estamento_profesional.php?profesional_id=${profesionalId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la respuesta del servidor');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                estamentoInput.value = data.estamento || 'No especificado';
                if (helpText) {
                    helpText.textContent = '';
                    helpText.className = 'form-text text-success';
                }
                console.log(`✅ Estamento cargado: ${data.estamento}`);
            } else {
                throw new Error(data.error || 'Error desconocido al cargar estamento');
            }
        })
        .catch(error => {
            console.error('Error al cargar estamento:', error);
            estamentoInput.value = 'Error al cargar';
            if (helpText) {
                helpText.textContent = 'Error: ' + error.message;
                helpText.className = 'form-text text-danger';
            }
        });
}

// Función para cargar profesionales según especialidad seleccionada 
function cargarProfesionalesPorEspecialidad(especialidadId) {
    const selectProfesional = document.getElementById('profesional_id');
    const estamentoInput = document.getElementById('estamento');
    const helpText = document.getElementById('profesional-help') || document.createElement('small');
    
    if (!helpText.id) {
        helpText.id = 'profesional-help';
        helpText.className = 'form-text';
        selectProfesional.parentNode.appendChild(helpText);
    }

    if (!especialidadId) {
        selectProfesional.innerHTML = '<option value="">Primero seleccione una unidad</option>';
        selectProfesional.disabled = true;
        helpText.textContent = 'Seleccione una unidad para ver los profesionales disponibles';
        
        // Limpiar estamento
        if (estamentoInput) {
            estamentoInput.value = '';
            estamentoInput.nextElementSibling.textContent = 'Seleccione un profesional para ver su estamento';
        }
        return;
    }

    // Mostrar loading
    selectProfesional.innerHTML = '<option value="">Cargando profesionales...</option>';
    selectProfesional.disabled = true;
    helpText.textContent = 'Cargando profesionales...';

    // Limpiar estamento durante la carga
    if (estamentoInput) {
        estamentoInput.value = '';
        estamentoInput.nextElementSibling.textContent = 'Cargando estamento...';
    }

    // Hacer petición AJAX
    fetch(`api/get_profesionales.php?especialidad_id=${especialidadId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la respuesta del servidor');
            }
            return response.json();
        })
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
                    helpText.className = 'form-text text-success';
                } else {
                    selectProfesional.innerHTML = '<option value="">No hay profesionales para esta unidad</option>';
                    selectProfesional.disabled = true;
                    helpText.textContent = 'No hay profesionales asignados a esta unidad';
                    helpText.className = 'form-text text-warning';
                    
                    // Limpiar estamento
                    if (estamentoInput) {
                        estamentoInput.value = '';
                        estamentoInput.nextElementSibling.textContent = 'No hay profesionales disponibles';
                    }
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
            helpText.className = 'form-text text-danger';
            
            // Limpiar estamento en caso de error
            if (estamentoInput) {
                estamentoInput.value = '';
                estamentoInput.nextElementSibling.textContent = 'Error al cargar profesionales';
            }
        });
}
    // Sistema de gestión de detalles de agenda 
    class GestorDetallesAgenda {
        constructor() {
            this.actividades = [];
            this.agendaActual = null;
            this.horasContrato = 0;
            this.modal = null;
            this.editandoActividadId = null;
			this.gestorCopiaActividad = null;
        }

        init() {
            console.log('Inicializando GestorDetallesAgenda...');
            this.bindEvents();
            this.cargarActividadesDisponibles();
            this.cargarEspecialidadesREM();
			this.gestorCopiaActividad = new GestorCopiaActividad(this);
			this.gestorCopiaActividad.init();
            console.log('GestorDetallesAgenda inicializado correctamente');
        }

        bindEvents() {
            console.log('Configurando event listeners...');
            
            // Evento para abrir el modal de detalles
            document.addEventListener('click', (e) => {
                if (e.target.closest('.btn-gestionar-detalles')) {
                    const button = e.target.closest('.btn-gestionar-detalles');
                    this.abrirModalDetalles(button);
					
                }
            });

            // Event listeners para elementos del modal (solo si existen)
            const formNuevaActividad = document.getElementById('formNuevaActividad');
            const btnCancelarEdicion = document.getElementById('btnCancelarEdicion');
            const btnGuardarTodo = document.getElementById('btnGuardarTodo');

            if (formNuevaActividad) {
                formNuevaActividad.addEventListener('submit', (e) => {
                    e.preventDefault();
                    if (this.editandoActividadId) {
                        this.actualizarActividad();
                    } else {
                        this.agregarActividad();
                    }
                });
            }

            if (btnCancelarEdicion) {
                btnCancelarEdicion.addEventListener('click', () => {
                    this.cancelarEdicion();
                });
            }

            if (btnGuardarTodo) {
                btnGuardarTodo.addEventListener('click', () => {
                    this.guardarTodo();
                });
            }

            // Event listeners para campos de cálculo
            const camposCalculo = ['horaInicio', 'horaFin', 'rendimiento', 'especialidadRemId'];
            camposCalculo.forEach(id => {
                const elemento = document.getElementById(id);
                if (elemento) {
                    elemento.addEventListener('change', () => this.calcularHorasYCupos());
                }
            });

            console.log('Event listeners configurados correctamente');
        }

			abrirModalDetalles(button) {
				console.log('Abriendo modal de detalles...', button);
				
				this.agendaActual = button.getAttribute('data-agenda-id');
				const especialidad = button.getAttribute('data-especialidad');
				const profesional = button.getAttribute('data-profesional');
				const descripcion = button.getAttribute('data-descripcion');
				const estamento = button.getAttribute('data-estamento');
				const estado = button.getAttribute('data-estado');
				this.horasContrato = parseInt(button.getAttribute('data-horas-contrato'));

				// Actualizar información en el modal
				const detalleAgendaId = document.getElementById('detalleAgendaId');
				const detalleEspecialidad = document.getElementById('detalleEspecialidad');
				const detalleProfesional = document.getElementById('detalleProfesional');
				const detalleDescripcion = document.getElementById('detalleDescripcion');
				const detalleEstamento = document.getElementById('detalleEstamento');
				const detalleHorasContrato = document.getElementById('detalleHorasContrato');
				const agendaIdInput = document.getElementById('agendaId');
				const detalleEstado = document.getElementById('detalleEstado');

				if (detalleAgendaId) detalleAgendaId.textContent = this.agendaActual;
				if (detalleEspecialidad) detalleEspecialidad.textContent = especialidad;
				if (detalleProfesional) detalleProfesional.textContent = profesional;
				if (detalleDescripcion) detalleDescripcion.textContent = descripcion || '-';
				if (detalleEstamento) detalleEstamento.textContent = estamento || '-';
				if (detalleHorasContrato) detalleHorasContrato.textContent = this.horasContrato + ' horas';
				if (agendaIdInput) agendaIdInput.value = this.agendaActual;
				if (detalleEstado) detalleEstado.textContent = estado;

				// Mostrar la card de progreso
				const cardProgreso = document.getElementById('cardProgresoHoras');
				if (cardProgreso) {
					cardProgreso.style.display = 'block';
				}

				// Limpiar formulario y lista
				this.limpiarFormulario();
				this.actividades = [];
				this.actualizarListaActividades();
				
				// Mostrar loading en la barra de progreso mientras se cargan los datos
				this.mostrarLoadingEnBarraProgreso();

				// Cargar actividades existentes y luego inicializar la barra
				this.cargarActividadesExistentes().then(() => {
					// Una vez cargadas las actividades, inicializar la barra de progreso
					this.inicializarBarraProgreso();
				}).catch(error => {
					console.error('Error al cargar actividades:', error);
					this.ocultarLoadingEnBarraProgreso();
				});

				// Cargar actividades disponibles filtradas por estamento
				if (estamento && estamento.trim() !== '') {
					this.cargarActividadesDisponibles(estamento);
				} else {
					this.cargarActividadesDisponibles();
				}
				
				// Mostrar modal
				const modalElement = document.getElementById('modalDetallesAgenda');
				if (modalElement) {
					this.modal = new bootstrap.Modal(modalElement);
					this.modal.show();
					console.log('Modal mostrado correctamente');
					
					// Evento cuando el modal se ha mostrado completamente
					modalElement.addEventListener('shown.bs.modal', () => {
						this.inicializarBarraProgreso();
					}, { once: true });
				} else {
					console.error('No se encontró el elemento modalDetallesAgenda');
				}
			}

        async cargarEspecialidadesREM() {
            try {
                const selectEspecialidadRem = document.getElementById('especialidadRemId');
                if (!selectEspecialidadRem) {
                    console.warn('Select de especialidades REM no encontrado');
                    return;
                }

                const response = await fetch('api/get_especialidades_rem.php');
                if (!response.ok) {
                    throw new Error(`Error HTTP: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (data.success) {
                    selectEspecialidadRem.innerHTML = '<option value="">No Aplica</option>';
                    
                    data.especialidades.forEach(especialidad => {
                        const option = document.createElement('option');
                        option.value = especialidad.id;
                        option.textContent = `${especialidad.especialidad_rem}`;
                        option.setAttribute('data-rendimiento-cn', especialidad.rendimiento_cn);
                        option.setAttribute('data-rendimiento-cr', especialidad.rendimiento_cr);
                        selectEspecialidadRem.appendChild(option);
                    });
					    // Añadir evento change al select
						selectEspecialidadRem.addEventListener('change', function() {
						const selectedOption = this.options[this.selectedIndex];
						const rendimientoCN = 'Ej.: CN= ' + selectedOption.getAttribute('data-rendimiento-cn') + ', CR= ' + selectedOption.getAttribute('data-rendimiento-cr');
						// Asignar al input text
						document.getElementById('rendimientoCNInput').value = rendimientoCN || '';
					})
                } else {
                    throw new Error(data.error || 'Error desconocido al cargar especialidades REM');
                }
            } catch (error) {
                console.error('Error al cargar especialidades REM:', error);
                const selectEspecialidadRem = document.getElementById('especialidadRemId');
                if (selectEspecialidadRem) {
                    selectEspecialidadRem.innerHTML = '<option value="">Error al cargar especialidades</option>';
                }
            }
        }

async cargarActividadesDisponibles(detalleEstamento = null) {
    try {
        const selectActividad = document.getElementById('actividadId');
        if (!selectActividad) {
            console.warn('Select de actividades no encontrado');
            return;
        }

        // Construir URL con parámetro tipo si está disponible
        let url = 'api/get_actividades.php';
        if (detalleEstamento) {
            url += `?tipo=${encodeURIComponent(detalleEstamento)}`;
        }

        const response = await fetch(url);
        if (!response.ok) {
            throw new Error(`Error HTTP: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            selectActividad.innerHTML = '<option value="">Seleccionar actividad</option>';
            
            if (data.actividades.length === 0) {
                const option = document.createElement('option');
                option.value = "";
                option.textContent = "No hay actividades disponibles para este estamento";
                option.disabled = true;
                selectActividad.appendChild(option);
                return;
            }
            
            data.actividades.forEach(actividad => {
                const option = document.createElement('option');
                option.value = actividad.id;
                option.textContent = `${actividad.actividad} (${actividad.clasificacion})`;
                option.setAttribute('data-clasificacion', actividad.clasificacion);
                selectActividad.appendChild(option);
            });
        } else {
            throw new Error(data.error || 'Error desconocido al cargar actividades');
        }
    } catch (error) {
        console.error('Error al cargar actividades:', error);
        const selectActividad = document.getElementById('actividadId');
        if (selectActividad) {
            selectActividad.innerHTML = '<option value="">Error al cargar actividades</option>';
        }
    }
}


			async cargarActividadesExistentes() {
				try {
					const response = await fetch(`api/get_detalles_agenda.php?agenda_id=${this.agendaActual}`);
					const data = await response.json();
					
					if (data.success) {
						// Convertir los valores numéricos de string a number
						this.actividades = data.detalles.map(actividad => ({
							...actividad,
							id: parseInt(actividad.id),
							actividad_id: parseInt(actividad.actividad_id),
							horas_calculadas: parseFloat(actividad.horas_calculadas) || 0,
							rendimiento: parseFloat(actividad.rendimiento) || 0.00,
							cupos_calculados: parseFloat(actividad.cupos_calculados) || 0,
							especialidad_rem_id: actividad.especialidad_rem_id ? parseInt(actividad.especialidad_rem_id) : null
						}));
						
						this.actualizarListaActividades();
						this.actualizarContadorHoras();
						
						return Promise.resolve();
					} else {
						throw new Error(data.error || 'Error al cargar actividades');
					}
				} catch (error) {
					console.error('Error al cargar actividades existentes:', error);
					this.mostrarError('Error al cargar las actividades: ' + error.message);
					return Promise.reject(error);
				}
			}

        calcularHorasYCupos() {
            const horaInicio = document.getElementById('horaInicio');
            const horaFin = document.getElementById('horaFin');
            const rendimiento = document.getElementById('rendimiento');
            const especialidadRemSelect = document.getElementById('especialidadRemId');
            
            if (!horaInicio || !horaFin || !rendimiento || !especialidadRemSelect) {
                return { horas: 0, cupos: 0 };
            }
            
            const horaInicioVal = horaInicio.value;
            const horaFinVal = horaFin.value;
            const rendimientoVal = parseFloat(rendimiento.value) || 0.00;
            const especialidadRemId = especialidadRemSelect.value;
            
            let horasCalculadas = 0;
            let cuposCalculados = 0;

            if (horaInicioVal && horaFinVal) {
                const inicio = new Date(`2000-01-01T${horaInicioVal}`);
                const fin = new Date(`2000-01-01T${horaFinVal}`);
                
                if (fin <= inicio) {
                    this.mostrarError('La hora fin debe ser posterior a la hora inicio');
                    return { horas: 0, cupos: 0 };
                }
                
                // Calcular diferencia en horas 
        const diferenciaMs = fin - inicio;
        const diferenciaHoras = diferenciaMs / (1000 * 60 * 60);
        horasCalculadas = Math.round(diferenciaHoras * 100) / 100;
        
        console.log(`Horas calculadas: ${horasCalculadas}, Rendimiento: ${rendimientoVal}`);
       
                
                cuposCalculados = horasCalculadas * rendimientoVal;
				console.log(`Cupos base: ${cuposCalculados}`);
                
                if (especialidadRemId) {
                    const selectedOption = especialidadRemSelect.selectedOptions[0];
                    const rendimientoCN = parseFloat(selectedOption.getAttribute('data-rendimiento-cn')) || 0.00;
					const rendimientoCR = parseFloat(selectedOption.getAttribute('data-rendimiento-cn')) || 0.00;
                    //cuposCalculados= cuposCalculados * rendimientoCN;
                }
                
                cuposCalculados = Math.round(cuposCalculados * 100) / 100;
				console.log(`Cupos finales: ${cuposCalculados}`);
            }
            
            const infoCalculo = document.getElementById('infoCalculo') || this.crearInfoCalculo();
            if (infoCalculo) {
                infoCalculo.innerHTML = `
                    <small class="text-muted" >
                        <strong>Horas:</strong> ${horasCalculadas.toFixed(2)}h | 
                        <strong>Cupos calculados:</strong> ${cuposCalculados.toFixed(2)}
                    </small>
                `;
            }
            
            return { horas: horasCalculadas, cupos: cuposCalculados };
        }

        crearInfoCalculo() {
            const form = document.getElementById('formNuevaActividad');
            if (!form) return null;
            
            const infoDiv = document.createElement('div');
            infoDiv.id = 'infoCalculo';
            infoDiv.className = 'mt-2';
			infoDiv.hidden=true;
            form.querySelector('.row').appendChild(infoDiv);
            return infoDiv;
        }

		agregarActividad() {
			const calculo = this.calcularHorasYCupos();
			
			if (calculo.horas <= 0) {
				this.mostrarError('Horario inválido. Verifique las horas de inicio y fin.');
				return;
			}

			const formData = new FormData(document.getElementById('formNuevaActividad'));
			
			// Obtener ubicación (puede ser vacío)
			let ubicacion = formData.get('ubicacion');
			if (!ubicacion) {
				ubicacion = ''; // O un valor por defecto como 'No especificada'
			}
			
			const nuevaActividad = {
				id: Date.now(),
				dia_semana: formData.get('dia_semana'),
				actividad_id: formData.get('actividad_id'),
				actividad_texto: document.getElementById('actividadId').selectedOptions[0]?.text || '',
				detalle: formData.get('detalle'),
				hora_inicio: formData.get('hora_inicio'),
				hora_fin: formData.get('hora_fin'),
				horas_calculadas: calculo.horas,
				rendimiento: parseFloat(formData.get('rendimiento')) || 0.00,
				especialidad_rem_id: formData.get('especialidad_rem_id') || null,
				especialidad_rem_texto: document.getElementById('especialidadRemId').selectedOptions[0]?.text || '',
				agendamiento: formData.get('agendamiento'),
				ubicacion: ubicacion,//formData.get('ubicacion'),
				cupos_calculados: calculo.cupos
			};

			// Validar actividad
			if (!this.validarActividad(nuevaActividad)) {
				return;
			}

			// Validar choque de horarios
			if (this.tieneChoqueHorario(nuevaActividad)) {
				this.mostrarError('Choque de horario: Ya existe una actividad en este horario y día.');
				return;
			}

			// Validar horas contratadas
			const totalHorasDespues = this.calcularTotalHoras() + nuevaActividad.horas_calculadas;
			if (totalHorasDespues > this.horasContrato) {
				const horasExcedidas = totalHorasDespues - this.horasContrato;
				if (!confirm(`⚠️ Advertencia: Al agregar esta actividad se excederán las horas contratadas por ${horasExcedidas.toFixed(2)} horas.\n\n¿Desea continuar de todos modos?`)) {
					return;
				}
			}

			this.actividades.push(nuevaActividad);
			this.actualizarListaActividades();
			this.actualizarContadorHoras();
			this.limpiarFormulario();
			
			this.mostrarExito('Actividad agregada correctamente | ' + totalHorasDespues + ' horas programadas');
		}

		tieneChoqueHorario(nuevaActividad) {
			return this.actividades.some(actividadExistente => {
				// Mismo día
				if (actividadExistente.dia_semana !== nuevaActividad.dia_semana) {
					return false;
				}

				// Convertir horas a minutos para comparación
				const convertirAMinutos = (hora) => {
					const [horas, minutos] = hora.split(':').map(Number);
					return horas * 60 + minutos;
				};

				const inicioExistente = convertirAMinutos(actividadExistente.hora_inicio);
				const finExistente = convertirAMinutos(actividadExistente.hora_fin);
				const inicioNueva = convertirAMinutos(nuevaActividad.hora_inicio);
				const finNueva = convertirAMinutos(nuevaActividad.hora_fin);

				// Verificar superposición
				const haySuperposicion = 
					(inicioNueva >= inicioExistente && inicioNueva < finExistente) ||
					(finNueva > inicioExistente && finNueva <= finExistente) ||
					(inicioNueva <= inicioExistente && finNueva >= finExistente);

				if (haySuperposicion) {
					console.log('Choque detectado:', {
						existente: actividadExistente,
						nueva: nuevaActividad
					});
				}

				return haySuperposicion;
			});
		}
		
				calcularTotalHoras() {
			return this.actividades.reduce((total, actividad) => total + actividad.horas_calculadas, 0);
		}
        validarActividad(actividad) {
            if (!actividad.dia_semana) {
                this.mostrarError('Seleccione un día de la semana');
                return false;
            }
            if (!actividad.actividad_id) {
                this.mostrarError('Seleccione una actividad');
                return false;
            }
            if (!actividad.hora_inicio || !actividad.hora_fin) {
                this.mostrarError('Complete las horas de inicio y fin');
                return false;
            }
            /*if (!actividad.ubicacion) {
                this.mostrarError('Seleccione una ubicación');
                return false;
            }*/
            return true;
        }

		actualizarListaActividades() {
			const cuerpoActividades = document.getElementById('cuerpoActividades');
			if (!cuerpoActividades) return;

			cuerpoActividades.innerHTML = '';

			// Verificar choques entre actividades existentes
			const actividadesConChoques = this.detectarTodosLosChoques();

			this.actividades.forEach(actividad => {
				const tieneChoque = actividadesConChoques.some(choque => 
					choque.actividad1.id === actividad.id || choque.actividad2.id === actividad.id
				);

				const fila = document.createElement('tr');
				if (tieneChoque) {
					fila.className = 'actividad-con-choque';
				}
				
				fila.innerHTML = `
					<td>${this.capitalizeFirst(actividad.dia_semana)}</td>
					<td>${actividad.actividad_texto}</td>
					<td>${actividad.detalle || '-'}</td>
					<td>${actividad.hora_inicio} - ${actividad.hora_fin}</td>
					<td>${actividad.horas_calculadas.toFixed(2)}</td>
					<td>${actividad.rendimiento.toFixed(2)}</td>
					<td>${actividad.cupos_calculados.toFixed(0)}</td>
					<td>${actividad.agendamiento}</td>
					<td>${actividad.ubicacion}</td>
					<td>${actividad.especialidad_rem_texto || '-'}</td>
					<td>
						<div class="btn-group btn-group-sm">
							<button type="button" class="btn btn-outline-warning btn-editar" data-id="${actividad.id}">
								<i class="fas fa-edit"></i>
							</button>
							<button type="button" class="btn btn-outline-info btn-copiar-actividad" data-id="${actividad.id}" title="Copiar actividad">
								<i class="fas fa-copy"></i>
							</button>
							<button type="button" class="btn btn-outline-danger btn-eliminar" data-id="${actividad.id}">
								<i class="fas fa-trash"></i>
							</button>
							
						</div>
					</td>
				`;
				cuerpoActividades.appendChild(fila);
			});

			// Mostrar advertencia si hay choques
			this.mostrarAdvertenciaChoques(actividadesConChoques);
			
			// ACTUALIZAR: Mostrar horarios por día
			this.mostrarHorariosPorDia();

			// Agregar event listeners a los botones
			this.agregarEventListenersBotones();
		}
			detectarTodosLosChoques() {
				const choques = [];
				
				for (let i = 0; i < this.actividades.length; i++) {
					for (let j = i + 1; j < this.actividades.length; j++) {
						const act1 = this.actividades[i];
						const act2 = this.actividades[j];
						
						if (this.hayChoqueEntreActividades(act1, act2)) {
							choques.push({ actividad1: act1, actividad2: act2 });
						}
					}
				}
				
				return choques;
			}

			hayChoqueEntreActividades(act1, act2) {
				if (act1.dia_semana !== act2.dia_semana) {
					return false;
				}

				const convertirAMinutos = (hora) => {
					const [horas, minutos] = hora.split(':').map(Number);
					return horas * 60 + minutos;
				};

				const inicio1 = convertirAMinutos(act1.hora_inicio);
				const fin1 = convertirAMinutos(act1.hora_fin);
				const inicio2 = convertirAMinutos(act2.hora_inicio);
				const fin2 = convertirAMinutos(act2.hora_fin);

				return (inicio1 < fin2 && fin1 > inicio2);
			}
        agregarEventListenersBotones() {
            // Botones editar
            document.querySelectorAll('.btn-editar').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const id = parseInt(e.target.closest('.btn-editar').getAttribute('data-id'));
                    this.editarActividad(id);
                });
            });
			// Botones copiar 
			document.querySelectorAll('.btn-copiar-actividad').forEach(btn => {
				btn.addEventListener('click', (e) => {
					const id = parseInt(e.target.closest('.btn-copiar-actividad').getAttribute('data-id'));
					this.gestorCopiaActividad.abrirModalCopiaActividad(id);
				});
			});

            // Botones eliminar
            document.querySelectorAll('.btn-eliminar').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const id = parseInt(e.target.closest('.btn-eliminar').getAttribute('data-id'));
                    this.eliminarActividad(id);
                });
            });
        }
		mostrarAdvertenciaChoques(choques) {
			// Eliminar advertencias anteriores
			const advertenciasAnteriores = document.querySelectorAll('.choque-horario-warning');
			advertenciasAnteriores.forEach(adv => adv.remove());

			if (choques.length > 0) {
				const contadorDetalles = document.getElementById('contadorResultadosDetalles');
				if (contadorDetalles) {
					const advertencia = document.createElement('div');
					advertencia.className = 'choque-horario-warning mt-2';
					advertencia.innerHTML = `
						<i class="fas fa-exclamation-triangle text-warning me-2"></i>
						<strong>Advertencia:</strong> Se detectaron ${choques.length} choque(s) de horario. 
						Las actividades en conflicto están marcadas en rojo.
					`;
					contadorDetalles.parentNode.insertBefore(advertencia, contadorDetalles.nextSibling);
				}
			}
		}
		editarActividad(id) {
			const actividad = this.actividades.find(a => a.id === id);
			if (!actividad) return;

			// Guardar la actividad original para validaciones
			this.actividadOriginal = { ...actividad };

			// Llenar formulario con datos de la actividad
			document.getElementById('diaSemana').value = actividad.dia_semana;
			document.getElementById('actividadId').value = actividad.actividad_id;
			document.getElementById('detalle').value = actividad.detalle || '';
			document.getElementById('horaInicio').value = actividad.hora_inicio;
			document.getElementById('horaFin').value = actividad.hora_fin;
			document.getElementById('rendimiento').value = actividad.rendimiento;
			document.getElementById('agendamiento').value = actividad.agendamiento;
			document.getElementById('ubicacion').value = actividad.ubicacion;
			document.getElementById('especialidadRemId').value = actividad.especialidad_rem_id || '';

			// Cambiar a modo edición
			this.editandoActividadId = id;
			document.getElementById('actividadEditId').value = id;
			document.getElementById('btnSubmitActividad').innerHTML = '<i class="fas fa-save me-2"></i>Actualizar Actividad';
			document.getElementById('btnCancelarEdicion').style.display = 'block';

			// Scroll al formulario
			document.querySelector('#formNuevaActividad').scrollIntoView({ behavior: 'smooth' });
		}

		actualizarActividad() {
			const calculo = this.calcularHorasYCupos();
			const formData = new FormData(document.getElementById('formNuevaActividad'));
			
			// Obtener ubicación (puede ser vacío)
			let ubicacion = formData.get('ubicacion');
			if (!ubicacion) {
				ubicacion = '';
			}
			
			const actividadActualizada = {
				id: this.editandoActividadId,
				dia_semana: formData.get('dia_semana'),
				actividad_id: formData.get('actividad_id'),
				actividad_texto: document.getElementById('actividadId').selectedOptions[0]?.text || '',
				detalle: formData.get('detalle'),
				hora_inicio: formData.get('hora_inicio'),
				hora_fin: formData.get('hora_fin'),
				horas_calculadas: calculo.horas,
				rendimiento: parseFloat(formData.get('rendimiento')) || 0.00,
				especialidad_rem_id: formData.get('especialidad_rem_id') || null,
				especialidad_rem_texto: document.getElementById('especialidadRemId').selectedOptions[0]?.text || '',
				agendamiento: formData.get('agendamiento'),
				ubicacion: ubicacion,//formData.get('ubicacion'),
				cupos_calculados: calculo.cupos
			};

			if (!this.validarActividad(actividadActualizada)) {
				return;
			}

			// Validar choque de horarios (excluyendo la actividad que se está editando)
			const actividadesSinLaActual = this.actividades.filter(a => a.id !== this.editandoActividadId);
			const gestorTemporal = new GestorDetallesAgenda();
			gestorTemporal.actividades = actividadesSinLaActual;
			
			if (gestorTemporal.tieneChoqueHorario(actividadActualizada)) {
				this.mostrarError('Choque de horario: Ya existe otra actividad en este horario y día.');
				return;
			}

			// Validar horas contratadas
			const horasActualesSinEsta = actividadesSinLaActual.reduce((total, act) => total + act.horas_calculadas, 0);
			const totalHorasDespues = horasActualesSinEsta + actividadActualizada.horas_calculadas;
			
			if (totalHorasDespues > this.horasContrato) {
				const horasExcedidas = totalHorasDespues - this.horasContrato;
				if (!confirm(`⚠️ Advertencia: Al actualizar esta actividad se excederán las horas contratadas por ${horasExcedidas.toFixed(2)} horas.\n\n¿Desea continuar de todos modos?`)) {
					return;
				}
			}

			// Actualizar en el array
			const index = this.actividades.findIndex(a => a.id === this.editandoActividadId);
			if (index !== -1) {
				this.actividades[index] = actividadActualizada;
			}

			this.actualizarListaActividades();
			this.actualizarContadorHoras();
			this.cancelarEdicion();
			
			this.mostrarExito('Actividad actualizada correctamente | ' + totalHorasDespues + ' horas programadas');
		}

        eliminarActividad(id) {
            if (confirm('¿Está seguro de eliminar esta actividad?')) {
                this.actividades = this.actividades.filter(a => a.id !== id);
                this.actualizarListaActividades();
                this.actualizarContadorHoras();
                this.mostrarExito('Actividad eliminada correctamente.');
            }
        }

        cancelarEdicion() {
            this.editandoActividadId = null;
            document.getElementById('actividadEditId').value = '';
            document.getElementById('btnSubmitActividad').innerHTML = '<i class="fas fa-plus me-2"></i>Agregar Actividad';
            document.getElementById('btnCancelarEdicion').style.display = 'none';
            this.limpiarFormulario();
        }

		actualizarContadorHoras() {
			const totalHorasAsignadas = this.calcularTotalHoras();
			const horasRestantes = this.horasContrato - totalHorasAsignadas;
			const horasExcedidas = Math.max(0, -horasRestantes);
			const porcentaje = this.horasContrato > 0 ? (totalHorasAsignadas / this.horasContrato) * 100 : 0;
			const porcentajeMostrar = Math.min(100, Math.round(porcentaje));
			
			// Actualizar elementos existentes
			const totalHorasAsignadasElem = document.getElementById('totalHorasAsignadas');
			const horasRestantesElem = document.getElementById('horasRestantes');
			const estadoAsignacionElem = document.getElementById('estadoAsignacion');
			const contadorResultadosDetalles = document.getElementById('contadorResultadosDetalles');
			
			if (totalHorasAsignadasElem) totalHorasAsignadasElem.textContent = totalHorasAsignadas.toFixed(2);
			
			if (horasRestantesElem) {
				if (horasRestantes >= 0) {
					horasRestantesElem.textContent = horasRestantes.toFixed(2);
					horasRestantesElem.className = '';
				} else {
					horasRestantesElem.textContent = horasExcedidas.toFixed(2);
					horasRestantesElem.className = 'text-danger';
				}
			}
			
			if (estadoAsignacionElem) {
				if (Math.abs(horasRestantes) < 0.01) {
					estadoAsignacionElem.textContent = 'Completo';
					estadoAsignacionElem.className = 'badge bg-success';
				} else if (horasRestantes > 0) {
					estadoAsignacionElem.textContent = 'Incompleto';
					estadoAsignacionElem.className = 'badge bg-warning';
				} else {
					estadoAsignacionElem.textContent = `Excedido (+${horasExcedidas.toFixed(2)}h)`;
					estadoAsignacionElem.className = 'badge bg-danger';
				}
			}

			// ACTUALIZAR BARRA DE PROGRESO
			this.actualizarBarraProgreso(totalHorasAsignadas, porcentajeMostrar, horasRestantes, horasExcedidas);
			
			// Actualizar el contador principal con más información
			if (contadorResultadosDetalles) {
				let estadoHTML = '';
				if (horasRestantes > 0) {
					estadoHTML = `<span class="text-warning"><strong>Faltan:</strong> ${horasRestantes.toFixed(2)}h</span>`;
				} else if (horasRestantes < 0) {
					estadoHTML = `<span class="text-danger"><strong>Exceso:</strong> ${horasExcedidas.toFixed(2)}h</span>`;
				} else {
					estadoHTML = `<span class="text-success"><strong>Completo</strong></span>`;
				}

				contadorResultadosDetalles.innerHTML = `
					<div class="row">
						<div class="col-md-3">
							<strong>Horas Contratadas:</strong> ${this.horasContrato}h
						</div>
						<div class="col-md-3">
							<strong>Horas Asignadas:</strong> ${totalHorasAsignadas.toFixed(2)}h
						</div>
						<div class="col-md-3">
							<strong>Horas Restantes/Exceso:</strong> ${estadoHTML}
						</div>
						<div class="col-md-3">
							<strong>Estado:</strong> <span id="estadoAsignacion">${estadoAsignacionElem ? estadoAsignacionElem.textContent : ''}</span>
						</div>
					</div>
				`;
			}
			
			// Actualizar badge de total de actividades
			const totalActividadesBadge = document.getElementById('totalActividadesBadge');
			if (totalActividadesBadge) {
				const cantidadActividades = this.actividades.length;
				totalActividadesBadge.textContent = `${cantidadActividades} actividad${cantidadActividades !== 1 ? 'es' : ''}`;
				
				if (cantidadActividades === 0) {
					totalActividadesBadge.className = 'badge bg-secondary ms-2';
				} else if (cantidadActividades < 3) {
					totalActividadesBadge.className = 'badge bg-info ms-2';
				} else {
					totalActividadesBadge.className = 'badge bg-success ms-2';
				}
			}
		}

		// NUEVO MÉTODO: Actualizar la barra de progreso
		actualizarBarraProgreso(totalHorasAsignadas, porcentaje, horasRestantes, horasExcedidas) {
			// Elementos de la barra de progreso
			const barraProgreso = document.getElementById('barraProgresoHoras');
			const barraTextoInterno = document.getElementById('barraTextoInterno');
			const progresoTextoHoras = document.getElementById('progresoTextoHoras');
			const progresoHorasContrato = document.getElementById('progresoHorasContrato');
			const progresoHorasAsignadas = document.getElementById('progresoHorasAsignadas');
			const progresoEstadoHoras = document.getElementById('progresoEstadoHoras');
			const excesoAdvertencia = document.getElementById('excesoHorasAdvertencia');
			const excesoHorasCantidad = document.getElementById('excesoHorasCantidad');
			const cardProgreso = document.getElementById('cardProgresoHoras');
			
			if (!barraProgreso) return;
			
			// Mostrar card de progreso solo si hay actividades o si el modal está abierto
			if (cardProgreso) {
				cardProgreso.style.display = 'block';
			}
			
			// Actualizar valores mostrados
			if (progresoHorasContrato) progresoHorasContrato.textContent = this.horasContrato;
			if (progresoHorasAsignadas) progresoHorasAsignadas.textContent = totalHorasAsignadas.toFixed(2);
			
			// Configurar color de la barra según el estado
			let barraColor = '';
			let estadoTexto = '';
			let estadoIcono = '';
			let estadoClase = '';
			
			if (horasRestantes > 0) {
				// Incompleto - Barra amarilla/naranja
				barraColor = 'bg-warning';
				estadoTexto = 'Incompleto';
				estadoIcono = 'fa-hourglass-half';
				estadoClase = 'text-warning';
				if (progresoEstadoHoras) {
					progresoEstadoHoras.className = estadoClase;
					progresoEstadoHoras.innerHTML = `<i class="fas ${estadoIcono} me-1"></i><strong>Estado:</strong> ${estadoTexto} (faltan ${horasRestantes.toFixed(2)}h)`;
				}
				if (excesoAdvertencia) excesoAdvertencia.style.display = 'none';
				
			} else if (horasRestantes < 0) {
				// Excedido - Barra roja
				barraColor = 'bg-danger';
				estadoTexto = `Excedido (+${horasExcedidas.toFixed(2)}h)`;
				estadoIcono = 'fa-exclamation-triangle';
				estadoClase = 'text-danger';
				if (progresoEstadoHoras) {
					progresoEstadoHoras.className = estadoClase;
					progresoEstadoHoras.innerHTML = `<i class="fas ${estadoIcono} me-1"></i><strong>Estado:</strong> ${estadoTexto}`;
				}
				if (excesoAdvertencia) {
					excesoAdvertencia.style.display = 'block';
					if (excesoHorasCantidad) excesoHorasCantidad.textContent = horasExcedidas.toFixed(2);
				}
				
			} else {
				// Completo - Barra verde
				barraColor = 'bg-success';
				estadoTexto = 'Completo';
				estadoIcono = 'fa-check-circle';
				estadoClase = 'text-success';
				if (progresoEstadoHoras) {
					progresoEstadoHoras.className = estadoClase;
					progresoEstadoHoras.innerHTML = `<i class="fas ${estadoIcono} me-1"></i><strong>Estado:</strong> ${estadoTexto}`;
				}
				if (excesoAdvertencia) excesoAdvertencia.style.display = 'none';
			}
			
			// Aplicar color a la barra (remover clases anteriores y agregar nueva)
			barraProgreso.className = `progress-bar progress-bar-striped progress-bar-animated ${barraColor}`;
			
			// Configurar el ancho y texto de la barra
			const anchoMostrar = Math.min(100, porcentaje);
			barraProgreso.style.width = `${anchoMostrar}%`;
			barraProgreso.setAttribute('aria-valuenow', anchoMostrar);
			
			// Texto dentro de la barra
			if (barraTextoInterno) {
				if (porcentaje >= 25) {
					barraTextoInterno.textContent = `${totalHorasAsignadas.toFixed(1)} / ${this.horasContrato} h (${anchoMostrar}%)`;
				} else {
					barraTextoInterno.textContent = `${anchoMostrar}%`;
				}
			}
			
			// Texto fuera de la barra (badge)
			if (progresoTextoHoras) {
				progresoTextoHoras.textContent = `${anchoMostrar}%`;
				
				// Cambiar color del badge según porcentaje
				if (anchoMostrar >= 100) {
					progresoTextoHoras.className = 'badge bg-danger';
				} else if (anchoMostrar >= 80) {
					progresoTextoHoras.className = 'badge bg-warning text-dark';
				} else if (anchoMostrar >= 50) {
					progresoTextoHoras.className = 'badge bg-info text-dark';
				} else {
					progresoTextoHoras.className = 'badge bg-primary';
				}
			}
			
			// Efecto de animación adicional si se completa exactamente
			if (Math.abs(horasRestantes) < 0.01 && this.horasContrato > 0) {
				barraProgreso.classList.add('progress-bar-striped');
				// Pequeña animación de "pulso" al completar
				setTimeout(() => {
					barraProgreso.classList.remove('progress-bar-animated');
				}, 1000);
				setTimeout(() => {
					barraProgreso.classList.add('progress-bar-animated');
				}, 2000);
			}
		}

		async guardarTodo() {
			try {
				if (this.actividades.length === 0) {
					this.mostrarError('No hay actividades para guardar');
					return;
				}

				console.log('Enviando actividades:', this.actividades);

				// Mostrar loading
				const btnGuardar = document.getElementById('btnGuardarTodo');
				const originalText = btnGuardar.innerHTML;
				btnGuardar.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Guardando...';
				btnGuardar.disabled = true;

				const response = await fetch('api/guardar_detalles_agenda.php', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
					},
					body: JSON.stringify({
						agenda_id: this.agendaActual,
						actividades: this.actividades
					})
				});

				// Obtener la respuesta como texto primero
				const responseText = await response.text();
				console.log('Respuesta del servidor:', responseText);

				// Intentar parsear como JSON
				let data;
				try {
					data = JSON.parse(responseText);
				} catch (parseError) {
					console.error('Error parseando JSON:', parseError);
					console.error('Respuesta cruda:', responseText);
					
					// Verificar si es un error HTML de PHP
					if (responseText.includes('<b>Warning</b>') || responseText.includes('<b>Fatal error</b>') || responseText.includes('<br />')) {
						throw new Error('El servidor devolvió un error PHP. Verifique la configuración del servidor.');
					} else {
						throw new Error('Respuesta del servidor no es JSON válido: ' + responseText.substring(0, 100));
					}
				}

				if (!response.ok) {
					throw new Error(data.error || `Error HTTP: ${response.status}`);
				}
				
				if (data.success) {
					this.mostrarExito(data.message || 'Todas las actividades han sido guardadas correctamente');
					if (this.modal) {
						setTimeout(() => {
							this.modal.hide();
							location.reload();
						}, 3000);
					}
				} else {
					throw new Error(data.error || 'Error desconocido al guardar');
				}
				
			} catch (error) {
				console.error('Error completo al guardar actividades:', error);
				
				let mensajeError = 'Error al guardar las actividades: ' + error.message;
				this.mostrarError(mensajeError);
			} finally {
				// Restaurar botón
				const btnGuardar = document.getElementById('btnGuardarTodo');
				if (btnGuardar) {
					btnGuardar.innerHTML = originalText;
					btnGuardar.disabled = false;
				}
			}
		}

        limpiarFormulario() {
            const form = document.getElementById('formNuevaActividad');
            if (form) {
                form.reset();
            }
            
            const rendimiento = document.getElementById('rendimiento');
            if (rendimiento) {
                rendimiento.value = '0.00';
            }
            
            const especialidadRemId = document.getElementById('especialidadRemId');
            if (especialidadRemId) {
                especialidadRemId.value = '';
            }
            
            const infoCalculo = document.getElementById('infoCalculo');
            if (infoCalculo) {
                infoCalculo.innerHTML = '';
            }
        }

        mostrarError(mensaje) {
            this.mostrarAlerta(mensaje, 'danger');
        }

        mostrarExito(mensaje) {
            this.mostrarAlerta(mensaje, 'success');
        }

        mostrarAlerta(mensaje, tipo) {
            const modalBody = document.querySelector('#modalDetallesAgenda .modal-body');
            if (!modalBody) return;

            const alertasAnteriores = modalBody.querySelectorAll('.alert');
            alertasAnteriores.forEach(alerta => alerta.remove());

            const alerta = document.createElement('div');
            alerta.className = `alert alert-${tipo} alert-dismissible fade show`;
            alerta.innerHTML = `
                ${mensaje}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            modalBody.insertBefore(alerta, modalBody.firstChild);
            
            setTimeout(() => {
                if (alerta.parentNode) {
                    alerta.remove();
                }
            }, 4000);
        }

        capitalizeFirst(string) {
            return string.charAt(0).toUpperCase() + string.slice(1);
        }
		// Calcular horarios por día y espacios libres
			calcularHorariosPorDia() {
				const dias = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'];
				const horariosPorDia = {};
				
				// Inicializar estructura para cada día
				dias.forEach(dia => {
					horariosPorDia[dia] = {
						actividades: [],
						horaInicioMin: null,
						horaFinMax: null,
						espaciosLibres: []
					};
				});

				// Agrupar actividades por día y encontrar horarios mínimos/máximos
				this.actividades.forEach(actividad => {
					const dia = actividad.dia_semana;
					if (horariosPorDia[dia]) {
						horariosPorDia[dia].actividades.push(actividad);
						
						// Convertir horas a minutos para comparación
						const minutosInicio = this.horaAMinutos(actividad.hora_inicio);
						const minutosFin = this.horaAMinutos(actividad.hora_fin);
						
						// Actualizar hora mínima de inicio
						if (horariosPorDia[dia].horaInicioMin === null || minutosInicio < horariosPorDia[dia].horaInicioMin) {
							horariosPorDia[dia].horaInicioMin = minutosInicio;
						}
						
						// Actualizar hora máxima de fin
						if (horariosPorDia[dia].horaFinMax === null || minutosFin > horariosPorDia[dia].horaFinMax) {
							horariosPorDia[dia].horaFinMax = minutosFin;
						}
					}
				});

				// Calcular espacios libres para cada día
				dias.forEach(dia => {
					const datosDia = horariosPorDia[dia];
					if (datosDia.actividades.length > 0 && datosDia.horaInicioMin !== null && datosDia.horaFinMax !== null) {
						datosDia.espaciosLibres = this.calcularEspaciosLibres(datosDia.actividades, datosDia.horaInicioMin, datosDia.horaFinMax);
					}
				});

				return horariosPorDia;
			}

			// Convertir hora string a minutos
			horaAMinutos(hora) {
				const [horas, minutos] = hora.split(':').map(Number);
				return horas * 60 + minutos;
			}

			// Convertir minutos a hora string
			minutosAHora(minutos) {
				const horas = Math.floor(minutos / 60);
				const mins = minutos % 60;
				return `${horas.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}`;
			}

			// Calcular espacios libres entre actividades
			calcularEspaciosLibres(actividades, horaInicioMin, horaFinMax) {
				const espacios = [];
				
				// Ordenar actividades por hora de inicio
				const actividadesOrdenadas = [...actividades].sort((a, b) => 
					this.horaAMinutos(a.hora_inicio) - this.horaAMinutos(b.hora_inicio)
				);

				// Espacio antes de la primera actividad
				const primeraActividad = actividadesOrdenadas[0];
				const inicioPrimera = this.horaAMinutos(primeraActividad.hora_inicio);
				if (inicioPrimera > horaInicioMin) {
					espacios.push({
						inicio: this.minutosAHora(horaInicioMin),
						fin: primeraActividad.hora_inicio,
						duracion: (inicioPrimera - horaInicioMin) / 60
					});
				}

				// Espacios entre actividades
				for (let i = 0; i < actividadesOrdenadas.length - 1; i++) {
					const actividadActual = actividadesOrdenadas[i];
					const actividadSiguiente = actividadesOrdenadas[i + 1];
					
					const finActual = this.horaAMinutos(actividadActual.hora_fin);
					const inicioSiguiente = this.horaAMinutos(actividadSiguiente.hora_inicio);
					
					if (finActual < inicioSiguiente) {
						espacios.push({
							inicio: actividadActual.hora_fin,
							fin: actividadSiguiente.hora_inicio,
							duracion: (inicioSiguiente - finActual) / 60
						});
					}
				}

				// Espacio después de la última actividad
				const ultimaActividad = actividadesOrdenadas[actividadesOrdenadas.length - 1];
				const finUltima = this.horaAMinutos(ultimaActividad.hora_fin);
				if (finUltima < horaFinMax) {
					espacios.push({
						inicio: ultimaActividad.hora_fin,
						fin: this.minutosAHora(horaFinMax),
						duracion: (horaFinMax - finUltima) / 60
					});
				}

				return espacios;
			}

					// Mostrar horarios en la card
					mostrarHorariosPorDia() {
						const cardHorarios = document.getElementById('cardHorarios');
						const cuerpoHorarios = document.getElementById('cuerpoHorarios');
						
						if (!cardHorarios || !cuerpoHorarios) return;

						const horarios = this.calcularHorariosPorDia();
						const diasConActividades = Object.keys(horarios).filter(dia => 
							horarios[dia].actividades.length > 0
						);

						if (diasConActividades.length === 0) {
							cardHorarios.style.display = 'none';
							return;
						}

						// Mostrar la card
						cardHorarios.style.display = 'block';

						let html = '';
						
						diasConActividades.forEach(dia => {
							const datosDia = horarios[dia];
							const horaInicioMin = datosDia.horaInicioMin !== null ? 
								this.minutosAHora(datosDia.horaInicioMin) : '--:--';
							const horaFinMax = datosDia.horaFinMax !== null ? 
								this.minutosAHora(datosDia.horaFinMax) : '--:--';
							
							html += `
								<div class="horario-dia">
									<div class="d-flex justify-content-between align-items-start mb-2">
										<div>
											<strong class="text-capitalize">${dia}</strong>
											<span class="badge badge-horario bg-primary ms-2">
												${datosDia.actividades.length} actividad(es)
											</span>
										</div>
										<div class="text-end">
											<small class="text-muted">
												<i class="fas fa-play-circle text-success me-1"></i>${horaInicioMin}
												<i class="fas fa-stop-circle text-danger ms-2 me-1"></i>${horaFinMax}
											</small>
										</div>
									</div>
							`;

							// Mostrar espacios libres
							if (datosDia.espaciosLibres.length > 0) {
								html += `<div class="mt-2">`;
								html += `<small class="text-muted d-block mb-1">Espacios sin programar:</small>`;
								
								datosDia.espaciosLibres.forEach(espacio => {
									html += `
										<div class="espacio-libre">
											<i class="fas fa-clock me-1"></i>
											${espacio.inicio} - ${espacio.fin} 
											<span class="badge badge-horario bg-info ms-1">
												${espacio.duracion.toFixed(2)}h
											</span>
										</div>
									`;
								});
								
								html += `</div>`;
							} else {
								html += `
									<div class="sin-espacios">
										<small><i class="fas fa-check-circle text-success me-1"></i>Sin espacios libres entre actividades</small>
									</div>
								`;
							}

							html += `</div>`;
						});

						cuerpoHorarios.innerHTML = html;
					}
			
			// Mostrar loading en la barra de progreso
					mostrarLoadingEnBarraProgreso() {
						const barraProgreso = document.getElementById('barraProgresoHoras');
						const barraTextoInterno = document.getElementById('barraTextoInterno');
						const progresoTextoHoras = document.getElementById('progresoTextoHoras');
						
						if (barraProgreso) {
							barraProgreso.style.width = '100%';
							barraProgreso.className = 'progress-bar progress-bar-striped progress-bar-animated bg-secondary';
						}
						
						if (barraTextoInterno) {
							barraTextoInterno.textContent = 'Cargando...';
						}
						
						if (progresoTextoHoras) {
							progresoTextoHoras.textContent = 'Cargando...';
							progresoTextoHoras.className = 'badge bg-secondary';
						}
					}

					// Ocultar loading en la barra de progreso
					ocultarLoadingEnBarraProgreso() {
						// Este método puede estar vacío o simplemente reinicializar
						this.inicializarBarraProgreso();
					}
				// Método para inicializar la barra de progreso al abrir el modal
				inicializarBarraProgreso() {
					const totalHorasAsignadas = this.calcularTotalHoras();
					const horasRestantes = this.horasContrato - totalHorasAsignadas;
					const horasExcedidas = Math.max(0, -horasRestantes);
					const porcentaje = this.horasContrato > 0 ? (totalHorasAsignadas / this.horasContrato) * 100 : 0;
					const porcentajeMostrar = Math.min(100, Math.round(porcentaje));
					
					// Actualizar la barra de progreso
					this.actualizarBarraProgreso(totalHorasAsignadas, porcentajeMostrar, horasRestantes, horasExcedidas);
					
					// Actualizar badge de total de actividades
					const totalActividadesBadge = document.getElementById('totalActividadesBadge');
					if (totalActividadesBadge) {
						const cantidadActividades = this.actividades.length;
						totalActividadesBadge.textContent = `${cantidadActividades} actividad${cantidadActividades !== 1 ? 'es' : ''}`;
						
						if (cantidadActividades === 0) {
							totalActividadesBadge.className = 'badge bg-secondary ms-2';
						} else if (cantidadActividades < 3) {
							totalActividadesBadge.className = 'badge bg-info ms-2';
						} else {
							totalActividadesBadge.className = 'badge bg-success ms-2';
						}
					}
				}			
								
				
				
				
    }
			

		// Copia de detalles de agenda
		class GestorCopiaDetalles {
			constructor() {
				this.agendaOrigenId = null;
				this.actividadesOrigen = [];
			}

			init() {
				this.bindEvents();
			}

			bindEvents() {
				// Evento para abrir modal de copia
				document.addEventListener('click', (e) => {
					if (e.target.closest('.btn-copiar-detalles')) {
						const button = e.target.closest('.btn-copiar-detalles');
						this.abrirModalCopia(button);
					}
				});

				// Evento para cambiar agenda destino
				const agendaDestino = document.getElementById('agendaDestino');
				if (agendaDestino) {
					agendaDestino.addEventListener('change', () => this.actualizarResumenCopia());
				}

				// Evento para confirmar copia
				const btnConfirmarCopia = document.getElementById('btnConfirmarCopia');
				if (btnConfirmarCopia) {
					btnConfirmarCopia.addEventListener('click', () => this.confirmarCopia());
				}
			}

			async abrirModalCopia(button) {
				this.agendaOrigenId = button.getAttribute('data-agenda-id');
				const especialidad = button.getAttribute('data-especialidad');
				const profesional = button.getAttribute('data-profesional');
				const horasContrato = button.getAttribute('data-horas-contrato');

				// Actualizar información en el modal
				document.getElementById('copiarOrigenId').textContent = this.agendaOrigenId;
				document.getElementById('copiarOrigenEspecialidad').textContent = especialidad;
				document.getElementById('copiarOrigenProfesional').textContent = profesional;

				// Cargar actividades de la agenda origen
				await this.cargarActividadesOrigen();
	
				// Mostrar modal
				const modalElement = document.getElementById('modalCopiarDetalles');
				if (modalElement) {
					const modal = new bootstrap.Modal(modalElement);
					modal.show();
				}
			}

			async cargarActividadesOrigen() {
				try {
					const response = await fetch(`api/get_detalles_agenda.php?agenda_id=${this.agendaOrigenId}`);
					const data = await response.json();
					
					if (data.success) {
						this.actividadesOrigen = data.detalles;
						this.actualizarResumenCopia();
					} else {
						throw new Error(data.error || 'Error al cargar actividades');
					}
				} catch (error) {
					console.error('Error al cargar actividades origen:', error);
					document.getElementById('resumenActividadesCopiar').innerHTML = 
						'<div class="alert alert-danger">Error al cargar actividades: ' + error.message + '</div>';
				}
			}

			actualizarResumenCopia() {
				const resumenDiv = document.getElementById('resumenActividadesCopiar');
				const agendaDestinoId = document.getElementById('agendaDestino').value;

				if (this.actividadesOrigen.length === 0) {
					resumenDiv.innerHTML = '<p class="text-muted">No hay actividades para copiar</p>';
					return;
				}

				// Agrupar actividades por día
				const actividadesPorDia = {};
				this.actividadesOrigen.forEach(actividad => {
					if (!actividadesPorDia[actividad.dia_semana]) {
						actividadesPorDia[actividad.dia_semana] = [];
					}
					actividadesPorDia[actividad.dia_semana].push(actividad);
				});

				let html = '<div class="row">';
				
				Object.keys(actividadesPorDia).forEach(dia => {
					html += `
						<div class="col-md-6 mb-2">
							<strong class="text-capitalize">${dia}:</strong>
							<span class="badge bg-primary ms-1">${actividadesPorDia[dia].length} actividades</span>
							<br>
							<small class="text-muted">
								${actividadesPorDia[dia].map(a => 
									`${a.hora_inicio}-${a.hora_fin} (${a.horas_calculadas}h)`
								).join(', ')}
							</small>
						</div>
					`;
				});

				html += '</div>';

				// Mostrar total de horas
				const totalHoras = this.actividadesOrigen.reduce((total, act) => total + parseFloat(act.horas_calculadas), 0);
				html += `<div class="mt-2 p-2 bg-light rounded">
							<strong>Total a copiar:</strong> ${this.actividadesOrigen.length} actividades, 
							${totalHoras.toFixed(2)} horas
						 </div>`;

				// Advertencia si se seleccionó agenda destino
				if (agendaDestinoId) {
					html += `<div class="mt-2 alert alert-warning">
								<i class="fas fa-exclamation-triangle me-1"></i>
								<strong>Advertencia:</strong> Esta acción reemplazará todos los detalles existentes en la agenda destino.
							 </div>`;
				}

				resumenDiv.innerHTML = html;
			}

			async confirmarCopia() {
				const agendaDestinoId = document.getElementById('agendaDestino').value;
				
				if (!agendaDestinoId) {
					alert('Por favor, seleccione una agenda destino');
					return;
				}

				if (this.agendaOrigenId === agendaDestinoId) {
					alert('No puede copiar los detalles a la misma agenda de origen');
					return;
				}

				if (!confirm('¿Está seguro de copiar los detalles? Esta acción no se puede deshacer.')) {
					return;
				}

				// Mostrar loading
				const btnConfirmar = document.getElementById('btnConfirmarCopia');
				const originalText = btnConfirmar.innerHTML;
				btnConfirmar.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Copiando...';
				btnConfirmar.disabled = true;

				try {
					const formData = new FormData();
					formData.append('copiar_detalles', 'true');
					formData.append('agenda_origen_id', this.agendaOrigenId);
					formData.append('agenda_destino_id', agendaDestinoId);

					const response = await fetch('', { // Enviar al mismo archivo PHP
						method: 'POST',
						body: formData
					});

					if (response.ok) {
						// Cerrar modal
						const modal = bootstrap.Modal.getInstance(document.getElementById('modalCopiarDetalles'));
						modal.hide();
						
						// Recargar página para ver cambios
						setTimeout(() => {
							location.reload();
						}, 1000);
					} else {
						throw new Error('Error en la respuesta del servidor');
					}
				} catch (error) {
					console.error('Error al copiar detalles:', error);
					alert('Error al copiar detalles: ' + error.message);
				} finally {
					// Restaurar botón
					btnConfirmar.innerHTML = originalText;
					btnConfirmar.disabled = false;
				}
			}
		}
		
		



class GestorCopiaActividad {
    constructor(gestorDetalles) {
        this.gestorDetalles = gestorDetalles;
        this.actividadOrigen = null;
        this.ubicacionesData = {}; // Almacenar ubicaciones por sector
        this.eventListenersConfigurados = false;
        this.init();
    }

    init() {
        if (!this.eventListenersConfigurados) {
            this.cargarUbicacionesData();
            this.configurarEventListeners();
            this.eventListenersConfigurados = true;
        }
    }

    async cargarUbicacionesData() {
        try {
            const response = await fetch('api/get_ubicaciones_agrupadas.php');
            const data = await response.json();
            if (data.success) {
                this.ubicacionesData = data.ubicaciones;
                console.log('Ubicaciones cargadas:', this.ubicacionesData);
            }
        } catch (error) {
            console.error('Error al cargar ubicaciones:', error);
        }
    }

    configurarEventListeners() {
        console.log('Configurando event listeners para copiar actividad...');
        
        // Delegación de eventos para el botón de confirmar copia
        document.body.addEventListener('click', (e) => {
            const btn = e.target.closest('#btnConfirmarCopiaActividad');
            if (btn) {
                e.preventDefault();
                e.stopImmediatePropagation();
                console.log('Click en botón confirmar copia actividad');
                this.confirmarCopiaActividad();
                return false;
            }
        });

        // Eventos para actualizar preview
        document.body.addEventListener('change', (e) => {
            if (e.target.matches('#nuevoDiaSemana, #nuevaHoraInicio, #nuevaHoraFin, #nuevoSector, #nuevaUbicacion')) {
                this.actualizarPreview();
            }
        });
        
        document.body.addEventListener('input', (e) => {
            if (e.target.matches('#nuevaHoraInicio, #nuevaHoraFin')) {
                this.actualizarPreview();
            }
        });
        
        // Evento específico para cargar ubicaciones al seleccionar sector
        document.body.addEventListener('change', (e) => {
            if (e.target.id === 'nuevoSector') {
                this.cargarUbicacionesPorSector(e.target.value);
            }
        });
    }

    cargarUbicacionesPorSector(sector) {
        const selectUbicacion = document.getElementById('nuevaUbicacion');
        if (!selectUbicacion) return;
        
        if (!sector || !this.ubicacionesData[sector]) {
            selectUbicacion.innerHTML = '<option value="">Primero seleccione un sector</option>';
            selectUbicacion.disabled = true;
            return;
        }
        
        const ubicaciones = this.ubicacionesData[sector];
        selectUbicacion.innerHTML = '<option value="">Seleccione una ubicación</option>';
        
        ubicaciones.forEach(ubi => {
            const option = document.createElement('option');
            option.value = ubi.ubicacion;
            option.textContent = ubi.ubicacion;
            option.setAttribute('data-ubicacion-id', ubi.id);
            selectUbicacion.appendChild(option);
        });
        
        selectUbicacion.disabled = false;
    }

    abrirModalCopiaActividad(actividadId) {
        this.actividadOrigen = this.gestorDetalles.actividades.find(a => a.id === actividadId);
        
        if (!this.actividadOrigen) {
            alert('Error: No se encontró la actividad a copiar');
            return;
        }

        // Extraer sector de la ubicación original (formato: "Sector - Ubicación" o solo "Ubicación")
        let sectorOrigen = '';
        let ubicacionOrigen = this.actividadOrigen.ubicacion || '';
        
        // Si la ubicación contiene guión, separar sector y ubicación
        if (ubicacionOrigen.includes(' - ')) {
            const partes = ubicacionOrigen.split(' - ');
            sectorOrigen = partes[0];
            ubicacionOrigen = partes[1];
        } else {
            // Buscar el sector de esta ubicación en los datos
            for (const [sector, ubicaciones] of Object.entries(this.ubicacionesData)) {
                if (ubicaciones.some(ubi => ubi.ubicacion === ubicacionOrigen)) {
                    sectorOrigen = sector;
                    break;
                }
            }
        }

        // Llenar información de la actividad origen
        document.getElementById('copiarActividadDiaOrigen').textContent = this.capitalizeFirst(this.actividadOrigen.dia_semana);
        document.getElementById('copiarActividadHorarioOrigen').textContent = `${this.actividadOrigen.hora_inicio} - ${this.actividadOrigen.hora_fin}`;
        document.getElementById('copiarActividadNombreOrigen').textContent = this.actividadOrigen.actividad_texto;
        document.getElementById('copiarActividadSectorOrigen').textContent = sectorOrigen || 'No especificado';
        document.getElementById('copiarActividadUbicacionOrigen').textContent = ubicacionOrigen;
        document.getElementById('copiarActividadDuracionOrigen').textContent = `${this.actividadOrigen.horas_calculadas}h`;

        // Llenar formulario con valores por defecto (usando los valores originales)
        document.getElementById('actividadOrigenId').value = this.actividadOrigen.id;
        document.getElementById('ubicacionOrigenCompleta').value = this.actividadOrigen.ubicacion;
        document.getElementById('nuevoDiaSemana').value = this.actividadOrigen.dia_semana;
        document.getElementById('nuevaHoraInicio').value = this.actividadOrigen.hora_inicio;
        document.getElementById('nuevaHoraFin').value = this.actividadOrigen.hora_fin;
        
        // Establecer sector y cargar ubicaciones
        const selectSector = document.getElementById('nuevoSector');
        if (selectSector) {
            selectSector.value = sectorOrigen;
            this.cargarUbicacionesPorSector(sectorOrigen);
            
            // Establecer la ubicación después de cargar las opciones
            setTimeout(() => {
                const selectUbicacion = document.getElementById('nuevaUbicacion');
                if (selectUbicacion && ubicacionOrigen) {
                    selectUbicacion.value = ubicacionOrigen;
                }
                this.actualizarPreview();
            }, 100);
        }

        // Mostrar modal
        const modal = new bootstrap.Modal(document.getElementById('modalCopiarActividad'));
        modal.show();
    }

    confirmarCopiaActividad() {
        console.log('Confirmando copia de actividad...');
        
        const nuevoDia = document.getElementById('nuevoDiaSemana').value;
        const nuevaHoraInicio = document.getElementById('nuevaHoraInicio').value;
        const nuevaHoraFin = document.getElementById('nuevaHoraFin').value;
        const nuevoSector = document.getElementById('nuevoSector').value;
        const nuevaUbicacion = document.getElementById('nuevaUbicacion').value;

        // Validaciones
        if (!nuevoDia || !nuevaHoraInicio || !nuevaHoraFin ) {
            alert('Por favor, complete todos los campos (día, horario, sector y ubicación)');
            return;
        }

        if (nuevaHoraInicio >= nuevaHoraFin) {
            alert('La hora fin debe ser posterior a la hora inicio');
            return;
        }

        // Construir ubicación completa (Sector - Ubicación)
        const ubicacionCompleta = `${nuevoSector} - ${nuevaUbicacion}`;

        // Calcular duración y cupos
        const duracion = this.calcularDuracion(nuevaHoraInicio, nuevaHoraFin);
        let cuposCalculados = duracion * this.actividadOrigen.rendimiento;
        cuposCalculados = Math.round(cuposCalculados * 100) / 100;

        const nuevaActividad = {
            id: Date.now(),
            dia_semana: nuevoDia,
            actividad_id: this.actividadOrigen.actividad_id,
            actividad_texto: this.actividadOrigen.actividad_texto,
            detalle: this.actividadOrigen.detalle || '',
            hora_inicio: nuevaHoraInicio,
            hora_fin: nuevaHoraFin,
            horas_calculadas: duracion,
            rendimiento: this.actividadOrigen.rendimiento,
            especialidad_rem_id: this.actividadOrigen.especialidad_rem_id,
            especialidad_rem_texto: this.actividadOrigen.especialidad_rem_texto || '',
            agendamiento: this.actividadOrigen.agendamiento,
            ubicacion: ubicacionCompleta,
            cupos_calculados: cuposCalculados
        };

        // Verificar si es idéntica a la original
        const esIgual = this.actividadOrigen.dia_semana === nuevoDia &&
                        this.actividadOrigen.hora_inicio === nuevaHoraInicio &&
                        this.actividadOrigen.hora_fin === nuevaHoraFin &&
                        this.actividadOrigen.ubicacion === ubicacionCompleta;

        if (esIgual) {
            alert('La actividad copiada es idéntica a la original. No se realizará la copia.');
            return;
        }

        // Validar choque de horario
        const tieneChoque = this.gestorDetalles.tieneChoqueHorario(nuevaActividad);
        if (tieneChoque) {
            if (!confirm('⚠️ Advertencia: Existe un choque de horario con otra actividad existente.\n¿Desea continuar de todos modos?')) {
                return;
            }
        }

        // Agregar la nueva actividad
        this.gestorDetalles.actividades.push(nuevaActividad);
        
        // Actualizar interfaz
        this.gestorDetalles.actualizarListaActividades();
        this.gestorDetalles.actualizarContadorHoras();

        // Cerrar modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('modalCopiarActividad'));
        if (modal) modal.hide();

        // Limpiar formulario
        this.limpiarFormularioCopia();

        // Mensaje de éxito
        this.gestorDetalles.mostrarExito(`Actividad copiada a ${this.capitalizeFirst(nuevoDia)} ${nuevaHoraInicio}-${nuevaHoraFin} en ${ubicacionCompleta}`);
    }

    calcularDuracion(horaInicio, horaFin) {
        const inicio = new Date(`2000-01-01T${horaInicio}`);
        const fin = new Date(`2000-01-01T${horaFin}`);
        const diferenciaMs = fin - inicio;
        return Math.round((diferenciaMs / (1000 * 60 * 60)) * 100) / 100;
    }

    actualizarPreview() {
        const nuevoDia = document.getElementById('nuevoDiaSemana').value;
        const nuevaHoraInicio = document.getElementById('nuevaHoraInicio').value;
        const nuevaHoraFin = document.getElementById('nuevaHoraFin').value;
        const nuevoSector = document.getElementById('nuevoSector').value;
        const nuevaUbicacion = document.getElementById('nuevaUbicacion').value;

        // Validar campos requeridos
        if (!nuevoDia || !nuevaHoraInicio || !nuevaHoraFin || !nuevoSector || !nuevaUbicacion) {
            document.getElementById('previewNuevaActividad').style.display = 'none';
            return;
        }

        // Validar horario
        if (nuevaHoraInicio >= nuevaHoraFin) {
            this.mostrarValidacion('La hora de fin debe ser posterior a la hora de inicio', 'danger');
            document.getElementById('previewNuevaActividad').style.display = 'none';
            return;
        }

        // Calcular nueva duración
        const duracion = this.calcularDuracion(nuevaHoraInicio, nuevaHoraFin);
        if (duracion <= 0) {
            this.mostrarValidacion('Horario inválido', 'danger');
            document.getElementById('previewNuevaActividad').style.display = 'none';
            return;
        }

        // Calcular cupos estimados
        let cuposEstimados = duracion * this.actividadOrigen.rendimiento;
        cuposEstimados = Math.round(cuposEstimados * 100) / 100;

        // Actualizar preview
        document.getElementById('previewDia').textContent = this.capitalizeFirst(nuevoDia);
        document.getElementById('previewHorario').textContent = `${nuevaHoraInicio} - ${nuevaHoraFin}`;
        document.getElementById('previewDuracion').textContent = `${duracion.toFixed(2)}h`;
        document.getElementById('previewSector').textContent = nuevoSector;
        document.getElementById('previewUbicacion').textContent = nuevaUbicacion;
        document.getElementById('previewCupos').textContent = cuposEstimados;

        // Mostrar preview
        document.getElementById('previewNuevaActividad').style.display = 'block';
        this.mostrarValidacion('✓ Datos válidos', 'success');
        
        // Validar choques de horario en tiempo real
        this.validarChoquesHorarioEnPreview(nuevoDia, nuevaHoraInicio, nuevaHoraFin);
    }

    validarChoquesHorarioEnPreview(nuevoDia, nuevaHoraInicio, nuevaHoraFin) {
        if (!this.gestorDetalles || !this.gestorDetalles.actividades) return;
        
        const actividadTemp = {
            dia_semana: nuevoDia,
            hora_inicio: nuevaHoraInicio,
            hora_fin: nuevaHoraFin,
            id: -1
        };
        
        const tieneChoque = this.gestorDetalles.tieneChoqueHorario(actividadTemp);
        
        if (tieneChoque) {
            this.mostrarValidacion('⚠️ Advertencia: Este horario podría generar choque con otra actividad existente', 'warning');
        }
    }

    mostrarValidacion(mensaje, tipo) {
        const infoDiv = document.getElementById('infoValidacionCopiar');
        const textoDiv = document.getElementById('textoValidacionCopiar');
        
        if (infoDiv && textoDiv) {
            infoDiv.style.display = 'block';
            textoDiv.textContent = mensaje;
            infoDiv.className = `alert alert-${tipo} mt-3`;
        }
    }

    limpiarFormularioCopia() {
        const form = document.getElementById('formCopiarActividad');
        if (form) form.reset();
        
        const selectUbicacion = document.getElementById('nuevaUbicacion');
        if (selectUbicacion) {
            selectUbicacion.innerHTML = '<option value="">Primero seleccione un sector</option>';
            selectUbicacion.disabled = true;
        }
        
        document.getElementById('infoValidacionCopiar').style.display = 'none';
        document.getElementById('previewNuevaActividad').style.display = 'none';
    }

    limpiarPreview() {
        document.getElementById('previewNuevaActividad').style.display = 'none';
        document.getElementById('infoValidacionCopiar').style.display = 'none';
    }

    capitalizeFirst(string) {
        if (!string) return '';
        return string.charAt(0).toUpperCase() + string.slice(1);
    }
}

		function actualizarEstadoFiltros(filtrosActivos) {
			const estadoFiltros = document.getElementById('estadoFiltros');
			if (!estadoFiltros) return;

			if (filtrosActivos > 0) {
				estadoFiltros.innerHTML = `<i class="fas fa-filter me-1"></i>${filtrosActivos} filtro(s) activo(s)`;
				estadoFiltros.className = 'badge bg-warning text-dark';
			} else {
				estadoFiltros.innerHTML = `<i class="fas fa-check me-1"></i>Sin filtros activos`;
				estadoFiltros.className = 'badge bg-light text-dark';
			}
		}
			// Función completa para inicializar filtros de agendas
			function inicializarFiltrosAgendas() {
				console.log('Inicializando filtros de agendas...');
				
				const tablaAgendas = document.getElementById('tablaAgendas');
				if (!tablaAgendas) {
					console.error('No se encontró la tabla de agendas');
					return;
				}

				// Elementos de filtro
				const filtroEspecialidad = document.getElementById('filtroEspecialidad');
				const filtroProfesional = document.getElementById('filtroProfesional');
				const filtroEstado = document.getElementById('filtroEstado');
				const filtroFecha = document.getElementById('filtroFecha');
				const filtroBusqueda = document.getElementById('filtroBusqueda');
				const btnLimpiarFiltros = document.getElementById('btnLimpiarFiltros');
				const btnExportarFiltros = document.getElementById('btnExportarFiltros');
				const totalFilas = document.getElementById('totalFilas');

				// Función para actualizar el estado visual de los filtros
				function actualizarEstadoFiltros(filtrosActivos) {
					const estadoFiltros = document.getElementById('estadoFiltros');
					if (!estadoFiltros) return;

					if (filtrosActivos > 0) {
						estadoFiltros.innerHTML = `<i class="fas fa-filter me-1"></i>${filtrosActivos} filtro(s) activo(s)`;
						estadoFiltros.className = 'badge bg-warning text-dark';
					} else {
						estadoFiltros.innerHTML = `<i class="fas fa-check me-1"></i>Sin filtros activos`;
						estadoFiltros.className = 'badge bg-light text-dark';
					}
				}

			// Función principal de filtrado 
			function aplicarFiltros() {
				const filas = tablaAgendas.querySelectorAll('tbody tr');
				let filasVisibles = 0;

				const filtroEspecialidadVal = filtroEspecialidad ? filtroEspecialidad.value.toLowerCase() : '';
				const filtroProfesionalVal = filtroProfesional ? filtroProfesional.value.toLowerCase() : '';
				const filtroEstadoVal = filtroEstado ? filtroEstado.value : '';
				const filtroFechaVal = filtroFecha ? filtroFecha.value : '';
				const filtroBusquedaVal = filtroBusqueda ? filtroBusqueda.value.toLowerCase() : '';

				// Mapeo de valores de filtro a textos visibles - CORREGIDO
				const mapeoEstados = {
					'pendiente': 'Pendiente',
					'revision': 'En Revisión', 
					'boxnodisponible': 'Box No Disponible',
					'autorizada': 'Autorizada',
					'implementada': 'Implementada',
					'anulada': 'Anulada'
				};

				filas.forEach(fila => {
					let mostrarFila = true;

					// Obtener datos de la fila
					const celdas = fila.querySelectorAll('td');
					if (celdas.length < 6) {
						fila.style.display = 'none';
						return;
					}

					const especialidad = celdas[1].textContent.toLowerCase();
					const profesional = celdas[2].textContent.toLowerCase();
					const estadoBadge = celdas[6].querySelector('.badge'); 
					const estadoTexto = estadoBadge ? estadoBadge.textContent.trim() : '';
					const fechaTexto = celdas[5].textContent; 
					const textoCompleto = fila.textContent.toLowerCase();

					console.log('Fila:', {
						especialidad,
						profesional,
						estadoTexto,
						fechaTexto,
						filtroEstadoVal,
						filtroFechaVal
					});

					// Aplicar filtros individuales
					if (filtroEspecialidadVal && !especialidad.includes(filtroEspecialidadVal)) {
						mostrarFila = false;
					}

					if (mostrarFila && filtroProfesionalVal && !profesional.includes(filtroProfesionalVal)) {
						mostrarFila = false;
					}

					if (mostrarFila && filtroEstadoVal) {
						// Obtener el texto que debería mostrar el estado según el filtro seleccionado
						const textoEstadoFiltro = mapeoEstados[filtroEstadoVal] || filtroEstadoVal;
						
						// Comparar con el texto real del badge
						if (estadoTexto !== textoEstadoFiltro) {
							mostrarFila = false;
						}
					}

					if (mostrarFila && filtroFechaVal) {
						// Convertir fecha de la tabla (dd/mm/yyyy) a formato input (yyyy-mm-dd) 
						const partesFecha = fechaTexto.split('/');
						if (partesFecha.length === 3) {
							const fechaTabla = `${partesFecha[2]}-${partesFecha[1]}-${partesFecha[0]}`;
							console.log('Comparando fechas:', {
								fechaTabla,
								filtroFechaVal,
								coincide: fechaTabla === filtroFechaVal
							});
							
							if (fechaTabla !== filtroFechaVal) {
								mostrarFila = false;
							}
						} else {
							mostrarFila = false;
						}
					}

					if (mostrarFila && filtroBusquedaVal && !textoCompleto.includes(filtroBusquedaVal)) {
						mostrarFila = false;
					}

					// Mostrar/ocultar fila según los filtros
					fila.style.display = mostrarFila ? '' : 'none';
					if (mostrarFila) filasVisibles++;
				});

				// Actualizar contador
				if (totalFilas) {
					totalFilas.textContent = filasVisibles;
				}

				// Contar filtros activos
				let filtrosActivos = 0;
				if (filtroEspecialidad && filtroEspecialidad.value) filtrosActivos++;
				if (filtroProfesional && filtroProfesional.value) filtrosActivos++;
				if (filtroEstado && filtroEstado.value) filtrosActivos++;
				if (filtroFecha && filtroFecha.value) filtrosActivos++;
				if (filtroBusqueda && filtroBusqueda.value) filtrosActivos++;

				// Actualizar estado de filtros
				actualizarEstadoFiltros(filtrosActivos);

				console.log(`Filtros aplicados: ${filasVisibles} filas visibles, ${filtrosActivos} filtros activos`);
			}

				// Event listeners para todos los filtros
				if (filtroEspecialidad) {
					filtroEspecialidad.addEventListener('change', aplicarFiltros);
				}

				if (filtroProfesional) {
					filtroProfesional.addEventListener('change', aplicarFiltros);
				}

				if (filtroEstado) {
					filtroEstado.addEventListener('change', aplicarFiltros);
				}

				if (filtroFecha) {
					filtroFecha.addEventListener('change', aplicarFiltros);
				}

				if (filtroBusqueda) {
					filtroBusqueda.addEventListener('input', aplicarFiltros);
				}

				// Botón limpiar filtros
				if (btnLimpiarFiltros) {
					btnLimpiarFiltros.addEventListener('click', function() {
						// Limpiar todos los filtros
						if (filtroEspecialidad) filtroEspecialidad.value = '';
						if (filtroProfesional) filtroProfesional.value = '';
						if (filtroEstado) filtroEstado.value = '';
						if (filtroFecha) filtroFecha.value = '';
						if (filtroBusqueda) filtroBusqueda.value = '';

						// Aplicar filtros (que mostrará todas las filas)
						aplicarFiltros();

						// Mostrar mensaje de confirmación
						const contadorResultados = document.getElementById('contadorResultados');
						if (contadorResultados) {
							const alerta = document.createElement('div');
							alerta.className = 'alert alert-info alert-dismissible fade show mt-2';
							alerta.innerHTML = `
								<i class="fas fa-info-circle me-2"></i>Filtros limpiados correctamente
								<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
							`;
							contadorResultados.parentNode.insertBefore(alerta, contadorResultados.nextSibling);
							
							// Auto-eliminar la alerta después de 3 segundos
							setTimeout(() => {
								if (alerta.parentNode) {
									alerta.remove();
								}
							}, 3000);
						}
					});
				}

				// Botón exportar resultados
				if (btnExportarFiltros) {
					btnExportarFiltros.addEventListener('click', function() {
						const filasVisibles = tablaAgendas.querySelectorAll('tbody tr[style=""]').length;
						const totalFilas = tablaAgendas.querySelectorAll('tbody tr').length;
						
						if (filasVisibles === 0) {
							alert('No hay datos para exportar. Ajuste los filtros para mostrar resultados.');
							return;
						}

						// Crear datos para exportar
						const datosExportar = [];
						const headers = [];
						
						// Obtener headers
						tablaAgendas.querySelectorAll('thead th').forEach(th => {
							if (th.textContent.trim() !== 'Acciones') {
								headers.push(th.textContent.trim());
							}
						});

						// Obtener datos de filas visibles
						tablaAgendas.querySelectorAll('tbody tr').forEach(fila => {
							if (fila.style.display !== 'none') {
								const filaDatos = [];
								fila.querySelectorAll('td').forEach((td, index) => {
									// Excluir columna de acciones (última columna)
									if (index < fila.cells.length - 1) {
										let texto = td.textContent.trim();
										
										// Limpiar texto de badges en la columna de estado
										if (index === 5) {
											const badge = td.querySelector('.badge');
											if (badge) {
												texto = badge.textContent.trim();
											}
										}
										
										filaDatos.push(texto);
									}
								});
								datosExportar.push(filaDatos);
							}
						});

						// Crear CSV
						let csvContent = headers.join(',') + '\n';
						datosExportar.forEach(fila => {
							csvContent += fila.map(dato => `"${dato}"`).join(',') + '\n';
						});

						// Descargar archivo
						const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
						const link = document.createElement('a');
						const url = URL.createObjectURL(blob);
						link.setAttribute('href', url);
						link.setAttribute('download', `agendas_exportadas_${new Date().toISOString().split('T')[0]}.csv`);
						link.style.visibility = 'hidden';
						document.body.appendChild(link);
						link.click();
						document.body.removeChild(link);

						// Mostrar mensaje de éxito
						const contadorResultados = document.getElementById('contadorResultados');
						if (contadorResultados) {
							const alerta = document.createElement('div');
							alerta.className = 'alert alert-success alert-dismissible fade show mt-2';
							alerta.innerHTML = `
								<i class="fas fa-check-circle me-2"></i>Se exportaron ${filasVisibles} de ${totalFilas} agendas correctamente
								<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
							`;
							contadorResultados.parentNode.insertBefore(alerta, contadorResultados.nextSibling);
							
							setTimeout(() => {
								if (alerta.parentNode) {
									alerta.remove();
								}
							}, 3000);
						}
					});
				}


				
				console.log('Filtros de agendas inicializados correctamente');
			}


    // Inicializar cuando el DOM esté listo
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM cargado, inicializando componentes...');
        
        // Inicializar gestor de detalles
        const modalDetalles = document.getElementById('modalDetallesAgenda');
        const botonesGestionar = document.querySelectorAll('.btn-gestionar-detalles');
        
        if (modalDetalles && botonesGestionar.length > 0) {

            try {
                window.gestorDetalles = new GestorDetallesAgenda();
                window.gestorDetalles.init();
                console.log('Gestor de detalles de agenda inicializado correctamente');
            } catch (error) {
                console.error('Error al inicializar gestor de detalles:', error);
            }
        } else {
            console.log('Modal de detalles o botones no encontrados, omitiendo inicialización del gestor');
        }

        // Inicializar filtros
        try {
            inicializarFiltrosAgendas();
            console.log('Filtros de agendas inicializados correctamente');
        } catch (error) {
            console.error('Error al inicializar filtros:', error);
        }


		// Configurar botón limpiar del formulario principal
		const btnLimpiar = document.getElementById('btnLimpiar');
		if (btnLimpiar) {
			btnLimpiar.addEventListener('click', function() {
				document.getElementById('agendaForm').reset();
				// También limpiar el select de profesionales
				const selectProfesional = document.getElementById('profesional_id');
				if (selectProfesional) {
					selectProfesional.innerHTML = '<option value="">Primero seleccione una unidad</option>';
					selectProfesional.disabled = true;
				}
				const helpText = document.getElementById('profesional-help');
				if (helpText) {
					helpText.textContent = 'Seleccione una unidad para ver los profesionales disponibles';
					helpText.className = 'form-text text-muted';
				}
				// Limpiar estamento
				const estamentoInput = document.getElementById('estamento');
				if (estamentoInput) {
					estamentoInput.value = '';
					estamentoInput.nextElementSibling.textContent = 'Seleccione un profesional para ver su estamento';
				}
			});
		}
			// Event listener para cambio de especialidad
			const selectEspecialidad = document.getElementById('especialidad_id');
			if (selectEspecialidad) {
				selectEspecialidad.addEventListener('change', function() {
					const especialidadId = this.value;
					cargarProfesionalesPorEspecialidad(especialidadId);
				});
			}
			
			const selectProfesional = document.getElementById('profesional_id');
			if (selectProfesional) {
				selectProfesional.addEventListener('change', function() {
					const profesionalId = this.value;
					cargarEstamentoProfesional(profesionalId);
				});
			}
				// Validación del formulario principal
			const agendaForm = document.getElementById('agendaForm');
			if (agendaForm) {
				agendaForm.addEventListener('submit', function(e) {
					const especialidad = document.getElementById('especialidad_id');
					const profesional = document.getElementById('profesional_id');
					const horasContrato = document.getElementById('horas_contrato');
					const fechaInicio = document.getElementById('fecha_inicio');
					const estado = document.getElementById('estado');
					
					if (!especialidad?.value || !profesional?.value || !horasContrato?.value || !fechaInicio?.value || !estado?.value) {
						e.preventDefault();
						alert('Por favor, complete todos los campos del formulario.');
						return;
					}
				});
			}
			
					// Inicializar gestor de copia
			try {
				window.gestorCopia = new GestorCopiaDetalles();
				window.gestorCopia.init();
				console.log('Gestor de copia de detalles inicializado correctamente');
			} catch (error) {
				console.error('Error al inicializar gestor de copia:', error);
			}
			
			console.log('Todos los componentes inicializados');
			
			
		});
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
<script>
// Función para depurar - muestra qué elementos existen
function verificarElementos() {
    console.log('=== VERIFICANDO ELEMENTOS DEL MODAL ===');
    
    const ids = [
        'detalleAgendaId',
        'detalleEspecialidad', 
        'detalleProfesional',
        'detalleHorasContrato',
        'detalleEstamento',
        'detalleDescripcion',
		'detalleEstado',
        'totalHorasAsignadas',
        'horasRestantes',
        'estadoAsignacion',
        'tablaActividades'
    ];
    
    ids.forEach(id => {
        const elemento = document.getElementById(id);
        console.log(`${id}:`, elemento ? 'EXISTE' : 'NO EXISTE', elemento?.textContent?.substring(0, 50) || '');
    });
    
    // Verificar también por clases
    console.log('Elementos con clase "modal-title":', document.querySelectorAll('.modal-title').length);
    console.log('Tablas en el modal:', document.querySelectorAll('#modalDetallesAgenda table').length);
}

// Función corregida con manejo seguro de elementos
function exportarDetallesAgendaExcel() {
    try {
        // Primero verificamos qué elementos existen
        verificarElementos();
        
        // Obtener datos de forma segura
        const getElementText = (id) => {
            const element = document.getElementById(id);
            return element ? element.textContent.trim() : 'N/A';
        };
        
        const agendaId = getElementText('detalleAgendaId');
        const especialidad = getElementText('detalleEspecialidad');
        const profesional = getElementText('detalleProfesional');
        const horasContrato = getElementText('detalleHorasContrato');
        const estamento = getElementText('detalleEstamento');
        const descripcion = getElementText('detalleDescripcion');
        const horasAsignadas = getElementText('totalHorasAsignadas');
        const horasRestantes = getElementText('horasRestantes');
        const estadoAsignacion = getElementText('estadoAsignacion');
        
        console.log('Datos obtenidos:', {
            agendaId, especialidad, profesional, horasContrato, 
            estamento, descripcion, horasAsignadas, horasRestantes, estadoAsignacion
        });
        
        // Buscar la tabla de actividades de forma flexible
        let tabla = document.getElementById('tablaActividades');
        
        // Si no existe por ID, buscar dentro del modal
        if (!tabla) {
            const modal = document.getElementById('modalDetallesAgenda');
            if (modal) {
                tabla = modal.querySelector('table');
            }
        }
        
        if (!tabla) {
            console.warn('No se encontró la tabla de actividades');
            alert('No hay actividades para exportar');
            return;
        }
        
        console.log('Tabla encontrada:', tabla);
        
        // Verificar si SheetJS está cargado
        if (typeof XLSX === 'undefined') {
            alert('Error: La librería de Excel no está cargada. Por favor, recarga la página.');
            return;
        }
        
        // Crear libro de Excel
        const wb = XLSX.utils.book_new();
        
        // Hoja 1: Información de la agenda
        const infoData = [
            ["INFORMACIÓN DE LA AGENDA"],
            [],
            ["ID Agenda:", agendaId],
            ["Unidad | Servicio:", especialidad],
            ["Profesional:", profesional],
            ["Contrato:", horasContrato],
            ["Estamento:", estamento],
            ["Descripción:", descripcion],
            /*["Horas Asignadas:", horasAsignadas],
            ["Horas Restantes:", horasRestantes],*/
            ["Estado:", estadoAsignacion],
            [],
            ["Fecha de exportación:", new Date().toLocaleDateString('es-CL')],
            ["Hora de exportación:", new Date().toLocaleTimeString('es-CL')]
        ];
        
        const infoWs = XLSX.utils.aoa_to_sheet(infoData);
        XLSX.utils.book_append_sheet(wb, infoWs, "Información");
        
        // Hoja 2: Lista de actividades
        const filas = tabla.querySelectorAll('tbody tr');
        
        if (filas.length > 0) {
            const actividadesData = [
                ["LISTA DE ACTIVIDADES AGREGADAS"],
                [],
                ["Día", "Actividad", "Detalle", "Horario", "Horas", "Rendimiento", "Cupos", "Agendamiento", "Ubicación", "Especialidad REM"]
            ];
            
            filas.forEach((fila, index) => {
                const celdas = fila.querySelectorAll('td');
                console.log(`Fila ${index + 1}: ${celdas.length} celdas`);
                
                // Solo procesar si tiene suficientes celdas (excluyendo la columna de acciones)
                if (celdas.length >= 10) {
                    const rowData = [
                        celdas[0]?.textContent?.trim() || '',
                        celdas[1]?.textContent?.trim() || '',
                        celdas[2]?.textContent?.trim() || '',
                        celdas[3]?.textContent?.trim() || '',
                        celdas[4]?.textContent?.trim() || '',
                        celdas[5]?.textContent?.trim() || '',
                        celdas[6]?.textContent?.trim() || '',
                        celdas[7]?.textContent?.trim() || '',
                        celdas[8]?.textContent?.trim() || '',
                        celdas[9]?.textContent?.trim() || ''
                    ];
                    actividadesData.push(rowData);
                } else if (celdas.length > 0) {
                    // Si tiene menos columnas, adaptarnos
                    const rowData = [];
                    for (let i = 0; i < 10; i++) {
                        rowData.push(celdas[i]?.textContent?.trim() || '');
                    }
                    actividadesData.push(rowData);
                }
            });
            
            const actWs = XLSX.utils.aoa_to_sheet(actividadesData);
            XLSX.utils.book_append_sheet(wb, actWs, "Actividades");
        }
        
        // Generar nombre de archivo
        const fileName = `Agenda_${agendaId || 'sin_id'}_${new Date().toISOString().slice(0,10)}.xlsx`;
        
        // Exportar
        XLSX.writeFile(wb, fileName);
        console.log('Archivo exportado correctamente:', fileName);
        
    } catch (error) {
        console.error('Error completo al exportar:', error);
        console.error('Stack trace:', error.stack);
        alert(`Error al exportar a Excel: ${error.message}\n\nRevisa la consola para más detalles.`);
    }
}

// Versión simplificada que busca datos de forma alternativa
function exportarDetallesAgendaExcelSimple() {
    try {
        // Buscar datos de forma alternativa
        const modal = document.getElementById('modalDetallesAgenda');
        if (!modal) {
            alert('El modal no está abierto o no existe');
            return;
        }
        
        // Buscar información en el modal usando selectores flexibles
        const buscarTexto = (selector) => {
            const element = modal.querySelector(selector);
            return element ? element.textContent.trim() : '';
        };
        
        // Intentar diferentes selectores
        const agendaId = buscarTexto('[id*="agenda"], [id*="Agenda"], .agenda-id, span:first-child') || 'N/A';
        
        // Buscar tabla
        const tabla = modal.querySelector('table');
        if (!tabla) {
            alert('No se encontró la tabla de actividades en el modal');
            return;
        }
        
        // Crear datos básicos
        const wb = XLSX.utils.book_new();
        const datos = [['Datos exportados del modal']];
        
        // Agregar información general del modal
        const titulo = modal.querySelector('.modal-title');
        if (titulo) {
            datos.push(['Título:', titulo.textContent.trim()]);
        }
        
        // Agregar todas las filas de la tabla
        const filas = tabla.querySelectorAll('tr');
        filas.forEach(fila => {
            const celdas = fila.querySelectorAll('td, th');
            const filaData = Array.from(celdas).map(celda => celda.textContent.trim());
            if (filaData.length > 0) {
                datos.push(filaData);
            }
        });
        
        datos.push(['', '']);
        datos.push(['Exportado:', new Date().toLocaleString('es-CL')]);
        
        const ws = XLSX.utils.aoa_to_sheet(datos);
        XLSX.utils.book_append_sheet(wb, ws, "Datos");
        
        XLSX.writeFile(wb, `exportacion_modal_${new Date().getTime()}.xlsx`);
        alert('Exportación completada (versión simple)');
        
    } catch (error) {
        console.error('Error en versión simple:', error);
        alert('Error: ' + error.message);
    }
}

// Función para agregar botones de exportación (versión mejorada)
function agregarBotonesExportacion() {
    const modalFooter = document.querySelector('#modalDetallesAgenda .modal-footer');
    if (!modalFooter) {
        console.warn('No se encontró el footer del modal');
        return;
    }
    
    // Verificar si ya existen
    if (modalFooter.querySelector('.export-buttons')) return;
    
    const exportDiv = document.createElement('div');
    exportDiv.className = 'export-buttons btn-group me-2';
    
    exportDiv.innerHTML = `
        <button type="button" class="btn btn-outline-success" onclick="exportarDetallesAgendaExcel()" title="Exportar a Excel">
            <i class="fas fa-file-excel me-1"></i>Exportar a Excel
        </button>
        <!--<button type="button" class="btn btn-outline-danger" onclick="alert(\'Función PDF en desarrollo\')" title="Exportar a PDF">
            <i class="fas fa-file-pdf me-1"></i>PDF
        </button>
        <button type="button" class="btn btn-outline-primary" onclick="exportarComoCSV()" title="Exportar a CSV">
            <i class="fas fa-file-csv me-1"></i>CSV
        </button>
        <button type="button" class="btn btn-outline-info" onclick="verificarElementos()" title="Depurar elementos">
            <i class="fas fa-bug me-1"></i>Debug
        </button>
        <button type="button" class="btn btn-outline-warning" onclick="exportarDetallesAgendaExcelSimple()" title="Exportación simple">
            <i class="fas fa-file-export me-1"></i>Simple
        </button>-->
    `;
    
    // Insertar al principio del footer
    modalFooter.prepend(exportDiv);
    console.log('Botones de exportación agregados');
}

// Función para exportar como CSV (simple y directa)
function exportarComoCSV() {
    try {
        const modal = document.getElementById('modalDetallesAgenda');
        if (!modal) {
            alert('Abre el modal primero');
            return;
        }
        
        const tabla = modal.querySelector('table');
        if (!tabla) {
            alert('No hay tabla en el modal');
            return;
        }
        
        let csvContent = '';
        const filas = tabla.querySelectorAll('tr');
        
        filas.forEach(fila => {
            const celdas = fila.querySelectorAll('td, th');
            const filaData = Array.from(celdas).map(celda => {
                let text = celda.textContent.trim();
                // Escapar para CSV
                if (text.includes(',') || text.includes('"') || text.includes('\n')) {
                    text = '"' + text.replace(/"/g, '""') + '"';
                }
                return text;
            });
            
            csvContent += filaData.join(',') + '\n';
        });
        
        // Descargar
        const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `exportacion_${new Date().getTime()}.csv`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        setTimeout(() => URL.revokeObjectURL(url), 100);
        
        console.log('CSV exportado correctamente');
        
    } catch (error) {
        console.error('Error exportando CSV:', error);
        alert('Error: ' + error.message);
    }
}

// Inicialización mejorada
document.addEventListener('DOMContentLoaded', function() {
    console.log('Script de exportación cargado');
    
    // Intentar cargar SheetJS si no está
    if (typeof XLSX === 'undefined') {
        console.log('Cargando SheetJS...');
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js';
        script.onload = () => console.log('SheetJS cargado');
        script.onerror = () => console.error('Error cargando SheetJS');
        document.head.appendChild(script);
    }
    
    // Observar cuando se abre el modal
    const modal = document.getElementById('modalDetallesAgenda');
    if (modal) {
        // Usar MutationObserver para detectar cuando el modal se muestra
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.attributeName === 'class') {
                    const isShown = modal.classList.contains('show');
                    if (isShown) {
                        console.log('Modal abierto, agregando botones...');
                        setTimeout(() => {
                            agregarBotonesExportacion();
                            verificarElementos(); // Para depuración
                        }, 500);
                    }
                }
            });
        });
        
        observer.observe(modal, { attributes: true });
        
        // También con el evento de Bootstrap
        modal.addEventListener('shown.bs.modal', function() {
            console.log('Evento shown.bs.modal disparado');
            setTimeout(() => {
                agregarBotonesExportacion();
                verificarElementos();
            }, 500);
        });
    } else {
        console.warn('Modal #modalDetallesAgenda no encontrado en el DOM');
    }
});

// Exportar funciones al scope global para que sean accesibles desde HTML
window.exportarDetallesAgendaExcel = exportarDetallesAgendaExcel;
window.exportarDetallesAgendaExcelSimple = exportarDetallesAgendaExcelSimple;
window.verificarElementos = verificarElementos;
window.exportarComoCSV = exportarComoCSV;
window.agregarBotonesExportacion = agregarBotonesExportacion;
</script>
<script>
// Script para manejar la edición de agenda
document.addEventListener('DOMContentLoaded', function() {
    // Manejar clic en botón de editar agenda
    document.querySelectorAll('.btn-editar-agenda').forEach(btn => {
        btn.addEventListener('click', function() {
            const agendaId = this.getAttribute('data-agenda-id');
            const especialidad = this.getAttribute('data-especialidad');
            const profesional = this.getAttribute('data-profesional');
            const horasContrato = this.getAttribute('data-horas-contrato');
            const descripcion = this.getAttribute('data-descripcion');
            const estado = this.getAttribute('data-estado');
            
            // Llenar el modal con los datos
            document.getElementById('edit_agenda_id').value = agendaId;
            document.getElementById('edit_agenda_id_display').textContent = agendaId;
            document.getElementById('edit_agenda_especialidad').textContent = especialidad;
            document.getElementById('edit_agenda_profesional').textContent = profesional;
            document.getElementById('edit_horas_contrato').value = horasContrato;
            document.getElementById('edit_descripcion').value = descripcion || '';
            
            // Configurar badge de estado
            const estadoBadge = document.getElementById('edit_agenda_estado');
            const estadoTexto = estado.charAt(0).toUpperCase() + estado.slice(1);
            let estadoClase = 'bg-secondary';
            
            switch(estado) {
                case 'pendiente': estadoClase = 'bg-warning text-dark'; break;
                case 'revision': estadoClase = 'bg-info text-dark'; break;
                case 'boxnodisponible': estadoClase = 'bg-warning text-dark'; break;
                case 'autorizada': estadoClase = 'bg-success'; break;
                case 'implementada': estadoClase = 'bg-success'; break;
                case 'anulada': estadoClase = 'bg-danger'; break;
            }
            
            estadoBadge.textContent = estadoTexto;
            estadoBadge.className = `badge ${estadoClase}`;
            
            // Mostrar modal
            const modal = new bootstrap.Modal(document.getElementById('modalEditarAgenda'));
            modal.show();
        });
    });
    
    // Manejar envío del formulario de edición
    const formEditarAgenda = document.getElementById('formEditarAgenda');
    if (formEditarAgenda) {
        formEditarAgenda.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const agendaId = document.getElementById('edit_agenda_id').value;
            const horasContrato = document.getElementById('edit_horas_contrato').value;
            const descripcion = document.getElementById('edit_descripcion').value;
            
            // Validaciones
            if (!horasContrato) {
                alert('Por favor, seleccione las horas de contrato');
                return;
            }
            
            // Mostrar loading
            const btnGuardar = document.getElementById('btnGuardarEdicionAgenda');
            const originalText = btnGuardar.innerHTML;
            btnGuardar.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Guardando...';
            btnGuardar.disabled = true;
            
            // Enviar datos mediante fetch
            fetch('api/editar_agenda.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    agenda_id: agendaId,
                    horas_contrato: horasContrato,
                    descripcion: descripcion
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✓ ' + data.message);
                    // Cerrar modal y recargar página
                    const modal = bootstrap.Modal.getInstance(document.getElementById('modalEditarAgenda'));
                    modal.hide();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    throw new Error(data.error || 'Error al guardar los cambios');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('✗ Error: ' + error.message);
            })
            .finally(() => {
                btnGuardar.innerHTML = originalText;
                btnGuardar.disabled = false;
            });
        });
    }
});
</script>
<script>
		// Cambio de registros por página
		document.getElementById('registrosPorPagina')?.addEventListener('change', function() {
			const registros = this.value;
			const url = new URL(window.location.href);
			url.searchParams.set('registros', registros);
			url.searchParams.set('pagina', 1); // Reset a primera página
			window.location.href = url.toString();
		});
</script>



</body>
<footer class="text-center py-4">Patricio Corrotea | Gestión de la Demanda</footer>
</html>