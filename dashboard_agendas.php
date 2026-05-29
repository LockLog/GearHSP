<?php
require_once 'includes/auth.php';
$auth = new Auth();

if (!$auth->isLoggedIn()) {
    header("Location: index.php");
    exit;
}

// Solo administradores y gestores pueden acceder
if (!($auth->isAdmin() || $auth->isGestor())) {
    header("Location: dashboard.php");
    exit;
}

require_once 'config/database.php';
$database = new Database();
$conn = $database->getConnection();

// Obtener datos para filtros
$especialidades = $auth->getEspecialidades();
$estados_agenda = [
    'pendiente' => 'Pendiente',
    'revision' => 'En Revisión',
    'boxnodisponible' => 'Box No Disponible',
    'autorizada' => 'Autorizada',
    'implementada' => 'Implementada',
    'anulada' => 'Anulada'
];

// Obtener datos para el gráfico de horas por actividad
$queryGrafico = "SELECT a.actividad, SUM(d.horas_calculadas) as total_horas
                 FROM detalles_agenda d
                 INNER JOIN actividades a ON d.actividad_id = a.id
                 GROUP BY d.actividad_id, a.actividad
                 ORDER BY total_horas DESC
                 LIMIT 10";
$stmtGrafico = $conn->prepare($queryGrafico);
$stmtGrafico->execute();
$datosGrafico = $stmtGrafico->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="img/favicon.png" type="image/png">
    <title>Dashboard Agendas | Gear-HSP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        .content-area {
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        .filter-card {
            background: #f8f9fa;
            border-radius: 12px;
            border: none;
            margin-bottom: 20px;
        }
        
        .table-container {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }
        
        .filter-group {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .filter-label {
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 5px;
            color: #495057;
        }
        
        .badge-estado {
            font-size: 0.75rem;
            padding: 0.3rem 0.6rem;
        }
        
        .estado-pendiente { background-color: #ffc107; color: #000; }
        .estado-revision { background-color: #0dcaf0; color: #000; }
        .estado-boxnodisponible { background-color: #fd7e14; color: #fff; }
        .estado-autorizada { background-color: #198754; color: #fff; }
        .estado-implementada { background-color: #20c997; color: #fff; }
        .estado-anulada { background-color: #dc3545; color: #fff; }
        
        .dataTables_wrapper .dataTables_filter input {
            margin-left: 0.5rem;
            border-radius: 20px;
            padding: 0.3rem 0.8rem;
            border: 1px solid #ddd;
        }
        
        .dataTables_wrapper .dataTables_length select {
            border-radius: 20px;
            padding: 0.3rem 0.8rem;
        }
        
        .btn-filter {
            border-radius: 20px;
            padding: 0.4rem 1.2rem;
        }
        
        .filter-badge {
            display: inline-block;
            background-color: #e9ecef;
            padding: 0.2rem 0.6rem;
            margin: 0.2rem;
            border-radius: 20px;
            font-size: 0.7rem;
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
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            display: none;
            justify-content: center;
            align-items: center;
        }
        
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .hierarchy-line {
            border-left: 3px solid #3498db;
            padding-left: 15px;
            margin-left: 5px;
        }
        
        .chart-card {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
			width: 50%;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
		.summary-stats {
			background: linear-gradient(135deg, rgba(52, 152, 219, 0.85) 0%, rgba(41, 128, 185, 0.85) 100%);
			backdrop-filter: blur(8px);
			border-radius: 12px;
			padding: 1rem;
			color: white;
			margin-bottom: 20px;
			border: 1px solid rgba(255, 255, 255, 0.25);
			box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
			transition: all 0.3s ease;
			height: 90px;
		}

		.summary-stats:hover {
			background: linear-gradient(135deg, rgba(52, 152, 219, 0.95) 0%, rgba(41, 128, 185, 0.95) 100%);
			transform: translateY(-2px);
			box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
		}
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .stat-label {
            font-size: 0.8rem;
            opacity: 0.9;
        }
		/* Estilos para el gráfico de barras */
		.bar-chart-card {
			background: white;
			border-radius: 12px;
			padding: 1rem;
			margin-bottom: 20px;
			box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
			width: 50%;
		}

		.bar-chart-container {
			position: relative;
			height: 350px;
			width: 100%;
		}

		.chart-legend {
			display: flex;
			justify-content: center;
			gap: 20px;
			margin-top: 15px;
		}

		.legend-item {
			display: flex;
			align-items: center;
			gap: 8px;
			font-size: 0.8rem;
		}

		.legend-color {
			width: 20px;
			height: 20px;
			border-radius: 4px;
		}

		.legend-color.profesionales {
			background-color: #4e73df;
		}

		.legend-color.horas {
			background-color: #1cc88a;
		}

		.summary-card {
			background: #f8f9fa;
			border-radius: 8px;
			padding: 10px;
			text-align: center;
			margin-bottom: 10px;
		}

		.summary-card .number {
			font-size: 1.2rem;
			font-weight: bold;
			color: #4e73df;
		}

		.summary-card .label {
			font-size: 0.7rem;
			color: #6c757d;
		}
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <!-- Navbar -->
    <?php include 'includes/header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>

            <!-- Content Area -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 content-area">
                <!-- Header -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-search me-2 text-primary"></i>Dashboard de Agendas
                    </h1>
                    <div>
                        <button type="button" class="btn btn-outline-success me-2" id="btnExportarExcel">
                            <i class="fas fa-file-excel me-1"></i>Exportar Excel
                        </button>
                        <button type="button" class="btn btn-outline-danger" id="btnExportarPDF">
                            <i class="fas fa-file-pdf me-1"></i>Exportar PDF
                        </button>
                    </div>
                </div>



                <!-- Filtros Jerárquicos -->
                <div class="card filter-card">
                    <div class="card-body">
                        <h6 class="card-title mb-3">
                            <i class="fas fa-filter me-2"></i>Filtros Jerárquicos
                        </h6>
                        
                        <div class="row g-3">
                            <!-- Nivel 1: Unidad -->
                            <div class="col-md-3">
                                <div class="filter-group">
                                    <label class="filter-label">
                                        <i class="fas fa-building me-1 text-primary"></i>1. Unidad | Servicio
                                    </label>
                                    <select class="form-select" id="filtroEspecialidad">
                                        <option value="">Todas las unidades</option>
                                        <?php foreach ($especialidades as $esp): ?>
                                            <option value="<?php echo $esp['id']; ?>"><?php echo htmlspecialchars($esp['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Nivel 2: Profesional (dependiente de Unidad) -->
                            <div class="col-md-3">
                                <div class="filter-group">
                                    <label class="filter-label">
                                        <i class="fas fa-user-md me-1 text-success"></i>2. Profesional
                                    </label>
                                    <select class="form-select" id="filtroProfesional" disabled>
                                        <option value="">Primero seleccione una unidad</option>
                                    </select>
                                    <small class="text-muted" id="profesionalHelp"></small>
                                </div>
                            </div>
                            
                            <!-- Nivel 3: ID Agenda (dependiente de Profesional) -->
                            <div class="col-md-2">
                                <div class="filter-group">
                                    <label class="filter-label">
                                        <i class="fas fa-hashtag me-1 text-info"></i>3. ID Agenda
                                    </label>
                                    <select class="form-select" id="filtroAgendaId" disabled>
                                        <option value="">Primero seleccione un profesional</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Nivel 4: Actividad (dependiente de Unidad) -->
                            <div class="col-md-4">
                                <div class="filter-group">
                                    <label class="filter-label">
                                        <i class="fas fa-tasks me-1 text-warning"></i>4. Actividad
                                    </label>
                                    <select class="form-select" id="filtroActividad">
                                        <option value="">Todas las actividades</option>
                                    </select>
                                    <small class="text-muted" id="actividadHelp">Seleccione una unidad para ver actividades disponibles</small>
                                </div>
                            </div>
                            
                            <!-- Nivel 5: Día de la Semana -->
                            <div class="col-md-2">
                                <div class="filter-group">
                                    <label class="filter-label">
                                        <i class="fas fa-calendar-day me-1 text-secondary"></i>5. Día de la Semana
                                    </label>
                                    <select class="form-select" id="filtroDia">
                                        <option value="">Todos los días</option>
                                        <option value="lunes">Lunes</option>
                                        <option value="martes">Martes</option>
                                        <option value="miercoles">Miércoles</option>
                                        <option value="jueves">Jueves</option>
                                        <option value="viernes">Viernes</option>
                                        <option value="sabado">Sábado</option>
                                        <option value="domingo">Domingo</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Nivel 6: Estado de Agenda -->
                            <div class="col-md-2">
                                <div class="filter-group">
                                    <label class="filter-label">
                                        <i class="fas fa-flag-checkered me-1 text-danger"></i>6. Estado Agenda
                                    </label>
                                    <select class="form-select" id="filtroEstado">
                                        <option value="">Todos los estados</option>
                                        <?php foreach ($estados_agenda as $key => $nombre): ?>
                                            <option value="<?php echo $key; ?>"><?php echo $nombre; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
							<!-- Botones de acción -->
							<div class="col-md-4">
								<button type="button" class="btn btn-primary" id="btnAplicarFiltros">
									<i class="fas fa-search me-1"></i>Aplicar Filtros
								</button>
								<button type="button" class="btn btn-outline-secondary" id="btnLimpiarFiltros">
									<i class="fas fa-eraser me-1"></i>Limpiar Filtros
								</button>
							</div>
							<!-- Resumen Estadístico -->
							<div class="col-md-2 ">
								<div class="summary-stats text-center " >
									<div class="stat-number" id="totalHoras">0</div>
									<div class="stat-label">Total Horas</div>
								</div>
							</div>	
							<div class="col-md-3">
								<div class="summary-stats text-center" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);" hidden>
									<div class="stat-number" id="metaCN">0</div>
									<div class="stat-label">Meta Consulta Nueva (CN)</div>
									<small class="d-block" style="font-size: 0.7rem; opacity: 0.8;" id="especialidadREM_CN">-</small>
								</div>
							</div>
							<div class="col-md-3">
								<div class="summary-stats text-center" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);"hidden>
									<div class="stat-number" id="metaAMB">0</div>
									<div class="stat-label">Meta Ambulatoria</div>
									<small class="d-block" style="font-size: 0.7rem; opacity: 0.8;" id="especialidadREM_AMB">-</small>
								</div>
							</div>							
							<div class="col-md-3">
								<div class="summary-stats text-center" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);" hidden >
									<div class="stat-number" id="totalCupos" hidden>0</div>
									<div class="stat-label" hidden>Total Cupos</div>
								</div>
							</div>
							<div class="col-md-3">
								<div class="summary-stats text-center" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);" hidden>
									<div class="stat-number" id="totalActividades" hidden>0</div>
									<div class="stat-label" hidden>Actividades</div>
								</div>
							</div>
							<div class="col-md-3">
								<div class="summary-stats text-center" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);" hidden>
									<div class="stat-number" id="totalProfesionales" hidden>0</div>
									<div class="stat-label" hidden>Profesionales</div>
								</div>
							</div>
                        </div>
                        <!-- Resumen de filtros activos -->
                        <div class="row mt-3" id="filtrosActivosResumen" style="display: none;">
                            <div class="col-12">
                                <div class="alert alert-secondary py-2 mb-0">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Jerarquía de filtros aplicados:</strong>
                                    <span id="listaFiltrosActivos"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
				<!-- Gráficos -->
				<div class="row">
					<!-- Gráfico de Anillo - Horas por Actividad -->
					<div class="chart-card">
						<h6 class="mb-3">
							<i class="fas fa-chart-pie me-2 text-primary"></i>Distribución de Horas por Actividad
							<button type="button" class="btn btn-sm btn-outline-secondary float-end" id="btnActualizarGrafico" hidden>
								<i class="fas fa-sync-alt"></i> Actualizar
							</button>
						</h6>
						<div class="row">
							<div class="col-md-5">
								<div class="chart-container" style="position: relative; height: 400px; width: 100%;">
									<canvas id="donutChart"></canvas>
								</div>
							</div>
							<div class="col-md-7">
								<div class="table-responsive" style="max-height: 380px; overflow-y: auto;">
									<table class="table table-sm table-hover" id="tablaResumenActividades">
										<thead class="sticky-top bg-white">
											<tr style="background-color: #f8f9fa;">
												<th style="width: 70%;">Actividad</th>
												<th style="width: 15%;" class="text-end">Horas</th>
												<th style="width: 15%;" class="text-end">%</th>
												<th style="width: 15%;">Clasificación</th>
											</tr>
										</thead>
										<tbody id="cuerpoResumenActividades">
											<tr><td colspan="4" class="text-muted text-center">Cargando...</td></tr>
										</tbody>
									</table>
								</div>
							</div>
						</div>
					</div>
					<!-- Gráfico de Barras - Profesionales por Día y Horas -->
					<div class="bar-chart-card">
						<h6 class="mb-3">
							<i class="fas fa-chart-bar me-2 text-success"></i>Distribución por Día - Profesionales y Horas
							<button type="button" class="btn btn-sm btn-outline-secondary float-end" id="btnActualizarBarChart" hidden>
								<i class="fas fa-sync-alt"></i> Actualizar
							</button>
						</h6>
						
						<div class="row">
							<div class="col-md-9">
								<div class="bar-chart-container">
									<canvas id="barChart"></canvas>
								</div>
							</div>
							<div class="col-md-3">
								<div class="row">
									<div class="col-12">
										<div class="summary-card">
											<div class="number" id="totalProfesionalesSemana">0</div>
											<div class="label">Total Profesionales (semana)</div>
										</div>
									</div>
									<div class="col-12 mt-2">
										<div class="summary-card">
											<div class="number" id="totalHorasSemana">0</div>
											<div class="label">Total Horas (semana)</div>
										</div>
									</div>
									<div class="col-12 mt-2">
										<div class="summary-card">
											<div class="number" id="promedioProfesionales">0</div>
											<div class="label">Promedio Profesionales/día</div>
										</div>
									</div>
								</div>
							</div>
						</div>
						
						<div class="chart-legend">
							<div class="legend-item">
								<div class="legend-color profesionales"></div>
								<span>N° de Profesionales</span>
							</div>
							<div class="legend-item">
								<div class="legend-color horas"></div>
								<span>Horas Calculadas</span>
							</div>
						</div>
					</div>
				</div>
                
                <!-- Tabla de resultados -->
                <div class="table-container">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">
                            <i class="fas fa-table me-2"></i>Detalle de Agenda
                        </h6>
                        <div>
                            <span class="badge bg-primary" id="totalRegistros">0</span>
                            <span class="text-muted ms-1">registros encontrados</span>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped" id="tablaResultados">
                            <thead>
                                <tr>
                                    <th>ID Agenda</th>
                                    <th>Unidad | Servicio</th>
                                    <th>Especialidad REM</th>
                                    <th>Profesional</th>
                                    <th>Actividad</th>
                                    <th>Día Semana</th>
                                    <th>Horario</th>
                                    <th>Horas</th>
                                    <th>Cupos</th>
                                    <th>Estado Agenda</th>
                                </tr>
                            </thead>
                            <tbody id="cuerpoTabla">
                                <!-- Los datos se cargarán dinámicamente con DataTables -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <!-- SheetJS para Excel -->
    <script src="https://cdn.sheetjs.com/xlsx-0.20.2/package/dist/xlsx.full.min.js"></script>
    <!-- jsPDF para PDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
    
    <script>
            let dataTable = null;
            let datosActuales = [];
            let donutChart = null;
			let barChart = null;

            // Función para obtener clase CSS del estado
            function getEstadoClass(estado) {
                const clases = {
                    'pendiente': 'estado-pendiente',
                    'revision': 'estado-revision',
                    'boxnodisponible': 'estado-boxnodisponible',
                    'autorizada': 'estado-autorizada',
                    'implementada': 'estado-implementada',
                    'anulada': 'estado-anulada'
                };
                return clases[estado] || 'bg-secondary';
            }

            // Función para obtener nombre del estado
            function getEstadoNombre(estado) {
                const nombres = {
                    'pendiente': 'Pendiente',
                    'revision': 'En Revisión',
                    'boxnodisponible': 'Box No Disponible',
                    'autorizada': 'Autorizada',
                    'implementada': 'Implementada',
                    'anulada': 'Anulada'
                };
                return nombres[estado] || estado;
            }

            // Función para mostrar/ocultar loading
            function showLoading(show) {
                document.getElementById('loadingOverlay').style.display = show ? 'flex' : 'none';
            }

            // Función para actualizar el resumen de filtros activos
            function actualizarResumenFiltros() {
                const especialidad = document.getElementById('filtroEspecialidad');
                const especialidadText = especialidad.options[especialidad.selectedIndex]?.text;
                const profesional = document.getElementById('filtroProfesional');
                const profesionalText = profesional.options[profesional.selectedIndex]?.text;
                const agendaId = document.getElementById('filtroAgendaId');
                const agendaIdText = agendaId.options[agendaId.selectedIndex]?.text;
                const actividad = document.getElementById('filtroActividad');
                const actividadText = actividad.options[actividad.selectedIndex]?.text;
                const dia = document.getElementById('filtroDia');
                const diaText = dia.options[dia.selectedIndex]?.text;
                const estado = document.getElementById('filtroEstado');
                const estadoText = estado.options[estado.selectedIndex]?.text;
                
                let jerarquia = [];
                if (especialidad.value && especialidadText !== 'Todas las unidades') jerarquia.push(`🏢 ${especialidadText}`);
                if (profesional.value && profesionalText && profesionalText !== 'Primero seleccione una unidad' && profesionalText !== 'Todos los profesionales') jerarquia.push(`👨‍⚕️ ${profesionalText}`);
                if (agendaId.value && agendaIdText && agendaIdText !== 'Primero seleccione un profesional') jerarquia.push(`📋 ${agendaIdText}`);
                
                let filtros = [];
                if (actividad.value && actividadText !== 'Todas las actividades') filtros.push(`📌 Actividad: ${actividadText}`);
                if (dia.value && diaText !== 'Todos los días') filtros.push(`📅 Día: ${diaText}`);
                if (estado.value && estadoText !== 'Todos los estados') filtros.push(`🏷️ Estado: ${estadoText}`);
                
                const resumenDiv = document.getElementById('filtrosActivosResumen');
                const listaSpan = document.getElementById('listaFiltrosActivos');
                
                if (jerarquia.length > 0 || filtros.length > 0) {
                    resumenDiv.style.display = 'block';
                    let html = '';
                    if (jerarquia.length > 0) {
                        html += `<div class="hierarchy-line mb-1">`;
                        html += jerarquia.map((f) => `<span class="filter-badge">${f}</span>`).join(' → ');
                        html += `</div>`;
                    }
                    if (filtros.length > 0) {
                        html += `<div class="mt-1">${filtros.map(f => `<span class="filter-badge">${f}</span>`).join(' ')}</div>`;
                    }
                    listaSpan.innerHTML = html;
                } else {
                    resumenDiv.style.display = 'none';
                }
            }

				 // Cargar datos para el gráfico y tabla resumen
				async function cargarDatosGrafico() {
					const especialidad_id = document.getElementById('filtroEspecialidad').value;
					const profesional_id = document.getElementById('filtroProfesional').value;
					const agenda_id = document.getElementById('filtroAgendaId').value;
					const actividad_id = document.getElementById('filtroActividad').value;
					const dia_semana = document.getElementById('filtroDia').value;
					const estado = document.getElementById('filtroEstado').value;
					
					const params = new URLSearchParams();
					if (especialidad_id) params.append('especialidad_id', especialidad_id);
					if (profesional_id && profesional_id !== '') params.append('profesional_id', profesional_id);
					if (agenda_id && agenda_id !== '') params.append('agenda_id', agenda_id);
					if (actividad_id) params.append('actividad_id', actividad_id);
					if (dia_semana) params.append('dia_semana', dia_semana);
					if (estado) params.append('estado', estado);
					
					try {
						const response = await fetch(`api/get_horas_por_actividad.php?${params.toString()}`);
						const data = await response.json();
						
						if (data.success) {
							// Actualizar gráfico con datos de clasificaciones
							actualizarGrafico(data.clasificaciones);
							// Actualizar tabla resumen con datos de actividades
							actualizarResumenActividades(data.actividades);
						} else {
							if (donutChart) {
								donutChart.data.datasets[0].data = [];
								donutChart.data.labels = [];
								donutChart.update();
							}
							document.getElementById('cuerpoResumenActividades').innerHTML = '<tr><td colspan="4" class="text-muted text-center">No hay datos para mostrar</td></tr>';
						}
					} catch (error) {
						console.error('Error cargando datos del gráfico:', error);
					}
				}

				// Actualizar el gráfico de anillo (por clasificación)
				function actualizarGrafico(datos) {
					const labels = datos.map(item => item.clasificacion);
					const horas = datos.map(item => parseFloat(item.total_horas));
					
					// Colores específicos para cada clasificación
					const coloresPorClasificacion = {
						'Clinica': '#4e73df',
						'Ambulatoria': '#1cc88a',
						'No Clinica': '#f6c23e',
						'Sin Clasificar': '#858796',
					};
					
					const colores = labels.map(label => coloresPorClasificacion[label] || '#858796');
					
					const ctx = document.getElementById('donutChart').getContext('2d');
					
					if (donutChart) {
						donutChart.destroy();
					}
					
					donutChart = new Chart(ctx, {
						type: 'doughnut',
						data: {
							labels: labels,
							datasets: [{
								data: horas,
								backgroundColor: colores,
								borderWidth: 2,
								borderColor: '#fff',
								hoverOffset: 15,
								spacing: 5
							}]
						},
						options: {
							responsive: true,
							maintainAspectRatio: false,
							layout: {
								padding: {
									left: 20,
									right: 20,
									top: 20,
									bottom: 20
								}
							},
							plugins: {
								legend: {
									position: 'bottom',
									align: 'center',
									labels: {
										font: { size: 11, weight: 'normal' },
										boxWidth: 12,
										boxHeight: 12,
										padding: 10,
										usePointStyle: true,
										pointStyle: 'circle'
									},
									title: {
										display: true,
										font: { size: 12, weight: 'bold' },
										padding: { bottom: 10 }
									}
								},
								tooltip: {
									bodyFont: { size: 11 },
									titleFont: { size: 12, weight: 'bold' },
									callbacks: {
										label: function(context) {
											const label = context.label || '';
											const value = context.raw || 0;
											const total = context.dataset.data.reduce((a, b) => a + b, 0);
											const percentage = ((value / total) * 100).toFixed(1);
											return `${label}: ${value.toFixed(1)}h (${percentage}%)`;
										}
									}
								}
							},
							cutout: '45%',
							radius: '85%',
							hoverOffset: 15,
							animation: {
								animateRotate: true,
								duration: 800
							}
						}
					});
				}

				// Actualizar tabla resumen de actividades (mantiene actividad, horas, %, clasificación)
				function actualizarResumenActividades(datos) {
					const tbody = document.getElementById('cuerpoResumenActividades');
					const totalHoras = datos.reduce((sum, item) => sum + parseFloat(item.total_horas), 0);
					
					if (datos.length === 0) {
						tbody.innerHTML = '<tr><td colspan="4" class="text-muted text-center">No hay datos</td></tr>';
						return;
					}
					
					// Función para obtener el color de la clasificación
					function getClasificacionColor(clasificacion) {
						const colores = {
							'Clinica': '#4e73df',
							'Ambulatoria': '#1cc88a',
							'No Clinica': '#f6c23e',
							'Sin Clasificar': '#858796',
						};
						return colores[clasificacion] || '#858796';
					}
					
					function getClasificacionBadge(clasificacion) {
						const badges = {
							'Clinica': '<span class="badge" style="background-color: #4e73df;">Clinica</span>',
							'Ambulatoria': '<span class="badge" style="background-color: #1cc88a;">Ambulatoria</span>',
							'No Clinica': '<span class="badge" style="background-color: #f6c23e; color:#000;">No Clinica</span>',
							'Sin Clasificar': '<span class="badge" style="background-color: #858796;">Sin Clasificar</span>'
						};
						return badges[clasificacion] || `<span class="badge bg-secondary">${clasificacion || 'General'}</span>`;
					}
					
					tbody.innerHTML = datos.map(item => {
						const porcentaje = ((parseFloat(item.total_horas) / totalHoras) * 100).toFixed(1);
						const color = getClasificacionColor(item.clasificacion);
						return `
							<tr>
								<td>
									<div class="d-flex align-items-center">
										<div style="width: 10px; height: 10px; background-color: ${color}; border-radius: 2px; margin-right: 8px;"></div>
										<small>${escapeHtml(item.actividad)}</small>
									</div>
								</td>
								<td class="text-end">${parseFloat(item.total_horas).toFixed(1)}h</td>
								<td class="text-end">
									<div class="d-flex align-items-center justify-content-end gap-2">
										<small class="text-muted" style="min-width: 45px;">${porcentaje}%</small>
									</div>
								</td>
								<td class="text-center">${getClasificacionBadge(item.clasificacion)}</td>
							</tr>
						`;
					}).join('');
				}
            // Generar colores para el gráfico
            function generarColores(cantidad) {
                const colores = [
                    '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b',
                    '#858796', '#5a5c69', '#2e59d9', '#17a673', '#2c9faf',
                    '#e0a800', '#c21a1a', '#6c757d', '#343a40', '#6610f2',
                    '#6f42c1', '#e83e8c', '#fd7e14', '#20c997', '#3b5998'
                ];
                
                const coloresGenerados = [];
                for (let i = 0; i < cantidad; i++) {
                    coloresGenerados.push(colores[i % colores.length]);
                }
                return coloresGenerados;
            }

            // Actualizar estadísticas resumen
            function actualizarEstadisticas(data) {
                const totalHoras = data.reduce((sum, item) => sum + parseFloat(item.horas_calculadas || 0), 0);
                const totalCupos = data.reduce((sum, item) => sum + parseFloat(item.cupos_calculados || 0), 0);
                const actividadesUnicas = new Set(data.map(item => item.actividad_id)).size;
                const profesionalesUnicos = new Set(data.map(item => item.profesional_id)).size;
                
                document.getElementById('totalHoras').textContent = totalHoras.toFixed(1);
                document.getElementById('totalCupos').textContent = Math.round(totalCupos);
                document.getElementById('totalActividades').textContent = actividadesUnicas;
                document.getElementById('totalProfesionales').textContent = profesionalesUnicos;
				    
				// Cargar metas de especialidad REM
				cargarMetasEspecialidadREM();
            }
			// Función para cargar las metas de especialidad REM según los filtros
			async function cargarMetasEspecialidadREM() {
				const especialidad_id = document.getElementById('filtroEspecialidad').value;
				const profesional_id = document.getElementById('filtroProfesional').value;
				const agenda_id = document.getElementById('filtroAgendaId').value;
				
				const params = new URLSearchParams();
				if (especialidad_id) params.append('especialidad_id', especialidad_id);
				if (profesional_id && profesional_id !== '') params.append('profesional_id', profesional_id);
				if (agenda_id && agenda_id !== '') params.append('agenda_id', agenda_id);
				
				try {
					const response = await fetch(`api/get_metas_especialidad.php?${params.toString()}`);
					const data = await response.json();
					
					if (data.success) {
						document.getElementById('metaCN').textContent = data.meta_cn.toFixed(1);
						document.getElementById('metaAMB').textContent = data.meta_amb.toFixed(1);
						
						if (data.especialidad_rem) {
							document.getElementById('especialidadREM_CN').textContent = data.especialidad_rem;
							document.getElementById('especialidadREM_AMB').textContent = data.especialidad_rem;
						} else {
							document.getElementById('especialidadREM_CN').textContent = 'Sin especialidad REM';
							document.getElementById('especialidadREM_AMB').textContent = 'Sin especialidad REM';
						}
					} else {
						document.getElementById('metaCN').textContent = '0';
						document.getElementById('metaAMB').textContent = '0';
						document.getElementById('especialidadREM_CN').textContent = 'No asignada';
						document.getElementById('especialidadREM_AMB').textContent = 'No asignada';
					}
				} catch (error) {
					console.error('Error cargando metas:', error);
					document.getElementById('metaCN').textContent = 'Error';
					document.getElementById('metaAMB').textContent = 'Error';
				}
			}

			// Función para calcular promedio de horas por día (de los datos actuales)
			function actualizarPromedioHorasDia(data) {
				if (!data || data.length === 0) {
					document.getElementById('promedioHorasDia').textContent = '0';
					return;
				}
				
				// Obtener días únicos
				const diasUnicos = new Set();
				const horasPorDia = {};
				
				data.forEach(item => {
					const dia = item.dia_semana;
					if (dia) {
						diasUnicos.add(dia);
						horasPorDia[dia] = (horasPorDia[dia] || 0) + parseFloat(item.horas_calculadas || 0);
					}
				});
				
				const totalHoras = Object.values(horasPorDia).reduce((a, b) => a + b, 0);
				const cantidadDias = diasUnicos.size;
				const promedio = cantidadDias > 0 ? (totalHoras / cantidadDias).toFixed(1) : 0;
				
				document.getElementById('promedioHorasDia').textContent = promedio;
			}

            // Función para formatear los datos para DataTable
            function formatearDatosParaTabla(data) {
                return data.map(item => [
                    `<span class="badge bg-secondary">${escapeHtml(String(item.agenda_id || '-'))}</span>`,
                    escapeHtml(String(item.especialidad || '-')),
                    escapeHtml(String(item.especialidad_rem || '-')),
                    escapeHtml(String(item.profesional || '-')),
                    escapeHtml(String(item.actividad || '-')),
                    `<span class="text-capitalize">${escapeHtml(String(item.dia_semana || '-'))}</span>`,
                    `${escapeHtml(String(item.hora_inicio || '-'))} - ${escapeHtml(String(item.hora_fin || '-'))}`,
                    `${parseFloat(item.horas_calculadas || 0).toFixed(1)}h`,
                    `${parseFloat(item.cupos_calculados || 0).toFixed(0)}`,
                    `<span class="badge ${getEstadoClass(item.estado)} badge-estado">${getEstadoNombre(item.estado)}</span>`
                ]);
            }

            // Cargar datos desde la API y actualizar DataTable
            async function cargarDatos() {
                showLoading(true);
                
                const especialidad_id = document.getElementById('filtroEspecialidad').value;
                const profesional_id = document.getElementById('filtroProfesional').value;
                const agenda_id = document.getElementById('filtroAgendaId').value;
                const actividad_id = document.getElementById('filtroActividad').value;
                const dia_semana = document.getElementById('filtroDia').value;
                const estado = document.getElementById('filtroEstado').value;
                
                const params = new URLSearchParams();
                if (especialidad_id) params.append('especialidad_id', especialidad_id);
                if (profesional_id && profesional_id !== '') params.append('profesional_id', profesional_id);
                if (agenda_id && agenda_id !== '') params.append('agenda_id', agenda_id);
                if (actividad_id) params.append('actividad_id', actividad_id);
                if (dia_semana) params.append('dia_semana', dia_semana);
                if (estado) params.append('estado', estado);
                
                try {
                    const response = await fetch(`api/consulta_avanzada.php?${params.toString()}`);
                    const data = await response.json();
                    
                    if (data.success) {
                        datosActuales = data.data;
                        document.getElementById('totalRegistros').textContent = data.data.length;
                        
                        // Actualizar estadísticas
                        actualizarEstadisticas(data.data);
                        
                        // Formatear datos para la tabla
                        const tableData = formatearDatosParaTabla(data.data);
                        
                        // Actualizar o crear DataTable
                        if (dataTable) {
                            dataTable.clear();
                            if (tableData.length > 0) {
                                dataTable.rows.add(tableData);
                            }
                            dataTable.draw();
                        } else {
                            dataTable = $('#tablaResultados').DataTable({
                                data: tableData,
                                columns: [
                                    { title: "ID Agenda" },
                                    { title: "Unidad | Servicio" },
                                    { title: "Especialidad REM" },
                                    { title: "Profesional" },
                                    { title: "Actividad" },
                                    { title: "Día Semana" },
                                    { title: "Horario" },
                                    { title: "Horas" },
                                    { title: "Cupos" },
                                    { title: "Estado Agenda" }
                                ],
                                language: {
                                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
                                },
                                order: [[0, 'desc']],
                                pageLength: 25,
                                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]]
                            });
                        }
                        
                        // Cargar datos del gráfico después de cargar la tabla
                        await cargarDatosGrafico();
						await cargarDatosBarChart();
                        
                    } else {
                        console.error('Error:', data.error);
                        mostrarError(data.error || 'Error al cargar los datos');
                    }
                } catch (error) {
                    console.error('Error al cargar datos:', error);
                    mostrarError('Error al cargar los datos: ' + error.message);
                } finally {
                    showLoading(false);
                }
            }

            function escapeHtml(text) {
                if (!text || text === 'null' || text === 'undefined') return '-';
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            function mostrarError(mensaje) {
                document.getElementById('totalRegistros').textContent = '0';
                if (dataTable) {
                    dataTable.clear();
                    dataTable.draw();
                }
            }

            // Exportar a Excel
            function exportarExcel() {
                if (!datosActuales || datosActuales.length === 0) {
                    alert('No hay datos para exportar');
                    return;
                }
                
                const exportData = datosActuales.map(item => ({
                    'ID Agenda': item.agenda_id,
                    'Unidad | Servicio': item.especialidad,
                    'Especialidad REM': item.especialidad_rem,
                    'Profesional': item.profesional,
                    'Actividad': item.actividad,
                    'Día Semana': item.dia_semana,
                    'Horario': `${item.hora_inicio} - ${item.hora_fin}`,
                    'Horas': parseFloat(item.horas_calculadas || 0).toFixed(1),
                    'Cupos': parseFloat(item.cupos_calculados || 0).toFixed(0),
                    'Estado': getEstadoNombre(item.estado)
                }));
                
                const ws = XLSX.utils.json_to_sheet(exportData);
                const wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, 'Consulta Avanzada');
                XLSX.writeFile(wb, `consulta_avanzada_${new Date().toISOString().slice(0,19).replace(/:/g, '-')}.xlsx`);
            }

            // Exportar a PDF
            function exportarPDF() {
                if (!datosActuales || datosActuales.length === 0) {
                    alert('No hay datos para exportar');
                    return;
                }
                
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF({ orientation: 'landscape' });
                
                const tableData = datosActuales.map(item => [
                    item.agenda_id || '-',
                    item.especialidad || '-',
                    item.especialidad_rem || '-',
                    item.profesional || '-',
                    item.actividad || '-',
                    item.dia_semana || '-',
                    `${item.hora_inicio || ''}-${item.hora_fin || ''}`,
                    parseFloat(item.horas_calculadas || 0).toFixed(1),
                    parseFloat(item.cupos_calculados || 0).toFixed(0),
                    getEstadoNombre(item.estado)
                ]);
                
                doc.autoTable({
                    head: [['ID Agenda', 'Unidad', 'REM', 'Profesional', 'Actividad', 'Día', 'Horario', 'Horas', 'Cupos', 'Estado']],
                    body: tableData,
                    theme: 'striped',
                    styles: { fontSize: 8, cellPadding: 2 },
                    headStyles: { fillColor: [52, 152, 219], textColor: 255 },
                    margin: { top: 20 }
                });
                
                doc.setFontSize(16);
                doc.text('Consulta Avanzada de Actividades', 14, 15);
                doc.setFontSize(10);
                doc.text(`Fecha: ${new Date().toLocaleDateString()}`, 14, 22);
                doc.text(`Total registros: ${datosActuales.length}`, 14, 28);
                
                doc.save(`consulta_avanzada_${new Date().toISOString().slice(0,19).replace(/:/g, '-')}.pdf`);
            }

            // Event Listeners
            document.getElementById('btnAplicarFiltros').addEventListener('click', () => {
                actualizarResumenFiltros();
                cargarDatos();
            });

            document.getElementById('btnActualizarGrafico').addEventListener('click', () => {
                cargarDatosGrafico();
            });
	
			document.getElementById('btnActualizarBarChart').addEventListener('click', () => {
				cargarDatosBarChart();
			});

            document.getElementById('btnLimpiarFiltros').addEventListener('click', () => {
                document.getElementById('filtroEspecialidad').value = '';
                document.getElementById('filtroProfesional').innerHTML = '<option value="">Primero seleccione una unidad</option>';
                document.getElementById('filtroProfesional').disabled = true;
                document.getElementById('filtroAgendaId').innerHTML = '<option value="">Primero seleccione un profesional</option>';
                document.getElementById('filtroAgendaId').disabled = true;
                document.getElementById('filtroDia').value = '';
                document.getElementById('filtroEstado').value = '';
                
                // Recargar todas las actividades
                fetch('api/get_actividades.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const selectAct = document.getElementById('filtroActividad');
                            selectAct.innerHTML = '<option value="">Todas las actividades</option>';
                            data.actividades.forEach(act => {
                                const option = document.createElement('option');
                                option.value = act.id;
                                option.textContent = `${act.actividad} (${act.clasificacion || 'General'})`;
                                selectAct.appendChild(option);
                            });
                        }
                    });
                
                actualizarResumenFiltros();
                cargarDatos();
				cargarMetasEspecialidadREM();
            });

            document.getElementById('btnExportarExcel').addEventListener('click', exportarExcel);
            document.getElementById('btnExportarPDF').addEventListener('click', exportarPDF);

            // Filtro jerárquico
            document.getElementById('filtroEspecialidad').addEventListener('change', function() {
                const especialidadId = this.value;
                cargarProfesionalesPorEspecialidad(especialidadId);
                cargarActividadesPorEspecialidad(especialidadId);
            });

            document.getElementById('filtroProfesional').addEventListener('change', function() {
                const profesionalId = this.value;
                if (profesionalId && profesionalId !== '') {
                    cargarAgendasPorProfesional(profesionalId);
                } else {
                    document.getElementById('filtroAgendaId').innerHTML = '<option value="">Primero seleccione un profesional</option>';
                    document.getElementById('filtroAgendaId').disabled = true;
                }
                actualizarResumenFiltros();
            });

            document.getElementById('filtroAgendaId').addEventListener('change', actualizarResumenFiltros);
            document.getElementById('filtroActividad').addEventListener('change', actualizarResumenFiltros);
            document.getElementById('filtroDia').addEventListener('change', actualizarResumenFiltros);
            document.getElementById('filtroEstado').addEventListener('change', actualizarResumenFiltros);

            // Cargar agendas por profesional
            async function cargarAgendasPorProfesional(profesionalId) {
                const selectAgendaId = document.getElementById('filtroAgendaId');
                
                if (!profesionalId) {
                    selectAgendaId.innerHTML = '<option value="">Primero seleccione un profesional</option>';
                    selectAgendaId.disabled = true;
                    return;
                }
                
                selectAgendaId.innerHTML = '<option value="">Cargando agendas...</option>';
                selectAgendaId.disabled = true;
                
                try {
                    const response = await fetch(`api/get_agendas_por_profesional.php?profesional_id=${profesionalId}`);
                    const data = await response.json();
                    
                    if (data.success && data.agendas.length > 0) {
                        selectAgendaId.innerHTML = '<option value="">Todas las agendas</option>';
                        data.agendas.forEach(agenda => {
                            const option = document.createElement('option');
                            option.value = agenda.id;
                            option.textContent = `ID: ${agenda.id} - ${agenda.especialidad} - ${agenda.estado}`;
                            selectAgendaId.appendChild(option);
                        });
                        selectAgendaId.disabled = false;
                    } else {
                        selectAgendaId.innerHTML = '<option value="">No hay agendas para este profesional</option>';
                        selectAgendaId.disabled = true;
                    }
                } catch (error) {
                    console.error('Error cargando agendas:', error);
                    selectAgendaId.innerHTML = '<option value="">Error al cargar agendas</option>';
                    selectAgendaId.disabled = true;
                }
            }

            // Cargar profesionales por especialidad
            async function cargarProfesionalesPorEspecialidad(especialidadId) {
                const selectProfesional = document.getElementById('filtroProfesional');
                const selectAgendaId = document.getElementById('filtroAgendaId');
                const helpText = document.getElementById('profesionalHelp');
                
                selectAgendaId.innerHTML = '<option value="">Primero seleccione un profesional</option>';
                selectAgendaId.disabled = true;
                
                if (!especialidadId) {
                    selectProfesional.innerHTML = '<option value="">Primero seleccione una unidad</option>';
                    selectProfesional.disabled = true;
                    helpText.textContent = '';
                    return;
                }
                
                selectProfesional.innerHTML = '<option value="">Cargando...</option>';
                selectProfesional.disabled = true;
                
                try {
                    const response = await fetch(`api/get_profesionales.php?especialidad_id=${especialidadId}`);
                    const data = await response.json();
                    
                    if (data.success && data.profesionales.length > 0) {
                        selectProfesional.innerHTML = '<option value="">Todos los profesionales</option>';
                        data.profesionales.forEach(prof => {
                            const option = document.createElement('option');
                            option.value = prof.id;
                            option.textContent = prof.nombre;
                            selectProfesional.appendChild(option);
                        });
                        selectProfesional.disabled = false;
                        helpText.textContent = `${data.profesionales.length} profesional(es) disponible(s)`;
                    } else {
                        selectProfesional.innerHTML = '<option value="">No hay profesionales</option>';
                        selectProfesional.disabled = true;
                        helpText.textContent = 'No hay profesionales asignados a esta unidad';
                    }
                } catch (error) {
                    console.error('Error cargando profesionales:', error);
                    selectProfesional.innerHTML = '<option value="">Error al cargar</option>';
                    selectProfesional.disabled = true;
                    helpText.textContent = 'Error al cargar profesionales';
                }
            }

            // Cargar actividades por especialidad
            async function cargarActividadesPorEspecialidad(especialidadId) {
                const selectActividad = document.getElementById('filtroActividad');
                const helpText = document.getElementById('actividadHelp');
                
                if (!especialidadId) {
                    selectActividad.innerHTML = '<option value="">Todas las actividades</option>';
                    try {
                        const response = await fetch('api/get_actividades.php');
                        const data = await response.json();
                        if (data.success) {
                            data.actividades.forEach(act => {
                                const option = document.createElement('option');
                                option.value = act.id;
                                option.textContent = `${act.actividad} (${act.clasificacion || 'General'})`;
                                selectActividad.appendChild(option);
                            });
                        }
                    } catch (error) {
                        console.error('Error cargando actividades:', error);
                    }
                    helpText.textContent = 'Seleccione una unidad para filtrar actividades';
                    return;
                }
                
                selectActividad.innerHTML = '<option value="">Cargando...</option>';
                
                try {
                    const response = await fetch(`api/get_actividades_por_especialidad.php?especialidad_id=${especialidadId}`);
                    const data = await response.json();
                    
                    if (data.success && data.actividades.length > 0) {
                        selectActividad.innerHTML = '<option value="">Todas las actividades</option>';
                        data.actividades.forEach(act => {
                            const option = document.createElement('option');
                            option.value = act.id;
                            option.textContent = `${act.actividad} (${act.clasificacion || 'General'})`;
                            selectActividad.appendChild(option);
                        });
                        helpText.textContent = `${data.actividades.length} actividad(es) disponible(s)`;
                    } else {
                        selectActividad.innerHTML = '<option value="">No hay actividades</option>';
                        helpText.textContent = 'No hay actividades para esta unidad';
                    }
                } catch (error) {
                    console.error('Error cargando actividades:', error);
                    selectActividad.innerHTML = '<option value="">Error al cargar</option>';
                    helpText.textContent = 'Error al cargar actividades';
                }
            }

            // Cargar actividades iniciales
            async function cargarActividadesIniciales() {
                try {
                    const response = await fetch('api/get_actividades.php');
                    const data = await response.json();
                    if (data.success) {
                        const selectAct = document.getElementById('filtroActividad');
                        selectAct.innerHTML = '<option value="">Todas las actividades</option>';
                        data.actividades.forEach(act => {
                            const option = document.createElement('option');
                            option.value = act.id;
                            option.textContent = `${act.actividad} (${act.clasificacion || 'General'})`;
                            selectAct.appendChild(option);
                        });
                    }
                } catch (error) {
                    console.error('Error cargando actividades iniciales:', error);
                }
            }
            
            // Inicializar
            cargarActividadesIniciales();
            cargarDatos();
			
// Cargar datos para el gráfico de barras
async function cargarDatosBarChart() {
    const especialidad_id = document.getElementById('filtroEspecialidad').value;
    const profesional_id = document.getElementById('filtroProfesional').value;
    const agenda_id = document.getElementById('filtroAgendaId').value;
    const actividad_id = document.getElementById('filtroActividad').value;
    const dia_semana = document.getElementById('filtroDia').value;
    const estado = document.getElementById('filtroEstado').value;
    
    const params = new URLSearchParams();
    if (especialidad_id) params.append('especialidad_id', especialidad_id);
    if (profesional_id && profesional_id !== '') params.append('profesional_id', profesional_id);
    if (agenda_id && agenda_id !== '') params.append('agenda_id', agenda_id);
    if (actividad_id) params.append('actividad_id', actividad_id);
    if (dia_semana) params.append('dia_semana', dia_semana);
    if (estado) params.append('estado', estado);
    
    try {
        const response = await fetch(`api/get_profesionales_por_dia.php?${params.toString()}`);
        const data = await response.json();
        
        if (data.success && data.data.length > 0) {
            actualizarBarChart(data.data);
            actualizarResumenSemanal(data.data);
        } else {
            if (barChart) {
                barChart.data.datasets.forEach(dataset => {
                    dataset.data = Array(7).fill(0);
                });
                barChart.update();
            }
            document.getElementById('totalProfesionalesSemana').textContent = '0';
            document.getElementById('totalHorasSemana').textContent = '0';
            document.getElementById('promedioProfesionales').textContent = '0';
        }
    } catch (error) {
        console.error('Error cargando datos del gráfico de barras:', error);
    }
}

// Actualizar el gráfico de barras
function actualizarBarChart(datos) {
    const dias = datos.map(item => item.dia);
    const profesionales = datos.map(item => item.total_profesionales);
    const horas = datos.map(item => item.total_horas);
    
    const ctx = document.getElementById('barChart').getContext('2d');
    
    if (barChart) {
        barChart.destroy();
    }
    
    barChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: dias,
            datasets: [
                {
                    label: 'N° de Profesionales',
                    data: profesionales,
                    backgroundColor: '#4e73df',
                    borderColor: '#2e59d9',
                    borderWidth: 1,
                    yAxisID: 'y',
                    borderRadius: 6,
                    barPercentage: 0.6,
                    categoryPercentage: 0.7
                },
                {
                    label: 'Horas Calculadas',
                    data: horas,
                    backgroundColor: '#1cc88a',
                    borderColor: '#169b6b',
                    borderWidth: 1,
                    yAxisID: 'y1',
                    borderRadius: 6,
                    barPercentage: 0.6,
                    categoryPercentage: 0.7,
                    type: 'line',
                    fill: false,
                    tension: 0.3,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    pointBackgroundColor: '#1cc88a'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            let value = context.raw;
                            if (context.dataset.label === 'Horas Calculadas') {
                                return `${label}: ${value.toFixed(1)} horas`;
                            }
                            return `${label}: ${value} profesionales`;
                        }
                    }
                },
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        boxWidth: 10
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'N° de Profesionales',
                        color: '#4e73df'
                    },
                    grid: {
                        drawBorder: true,
                        color: '#e3e6f0'
                    }
                },
                y1: {
                    position: 'right',
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Horas Calculadas',
                        color: '#1cc88a'
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Día de la Semana'
                    },
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
}

// Actualizar resumen semanal
function actualizarResumenSemanal(datos) {
    const totalProfesionales = datos.reduce((sum, item) => sum + item.total_profesionales, 0);
    const totalHoras = datos.reduce((sum, item) => sum + item.total_horas, 0);
    const diasConDatos = datos.filter(item => item.total_profesionales > 0 || item.total_horas > 0).length;
    const promedioProfesionales = diasConDatos > 0 ? (totalProfesionales / diasConDatos).toFixed(1) : 0;
    
    document.getElementById('totalProfesionalesSemana').textContent = totalProfesionales;
    document.getElementById('totalHorasSemana').textContent = totalHoras.toFixed(1);
    document.getElementById('promedioProfesionales').textContent = promedioProfesionales;
}
    </script>
</body>
<footer class="text-center py-4">Patricio Corrotea | Gestión de la Demanda</footer>
</html>