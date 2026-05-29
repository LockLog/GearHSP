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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="img/favicon.png" type="image/png">
    <title>Consulta Avanzada | Gear-HSP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
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
                        <i class="fas fa-search me-2 text-primary"></i>Consulta Avanzada de Actividades
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
                        </div>
                        
                        <!-- Botones de acción -->
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="d-flex justify-content-end gap-2">
                                    <button type="button" class="btn btn-outline-secondary" id="btnLimpiarFiltros">
                                        <i class="fas fa-eraser me-1"></i>Limpiar Filtros
                                    </button>
                                    <button type="button" class="btn btn-primary" id="btnAplicarFiltros">
                                        <i class="fas fa-search me-1"></i>Aplicar Filtros
                                    </button>
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
                <!-- Tabla de resultados -->
                <div class="table-container">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">
                            <i class="fas fa-table me-2"></i>Resultados de la consulta
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
                                <tr>
                                    <td colspan="10" class="text-center text-muted">
                                        <i class="fas fa-spinner fa-spin me-2"></i>Cargando datos...
                                    </td>
                                </tr>
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
            let agendasDisponibles = []; // Cache de agendas disponibles por profesional

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
                const filtros = [];
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
                        html += jerarquia.map((f, idx) => `<span class="filter-badge"><i class="fas fa-arrow-right me-1"></i>${f}</span>`).join(' ');
                        html += `</div>`;
                    }
                    if (filtros.length > 0) {
                        html += `<div>${filtros.map(f => `<span class="filter-badge">${f}</span>`).join(' ')}</div>`;
                    }
                    listaSpan.innerHTML = html;
                } else {
                    resumenDiv.style.display = 'none';
                }
            }

            // Cargar agendas por profesional (para el filtro de ID Agenda)
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
                        agendasDisponibles = data.agendas;
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
                
                // Resetear agenda ID al cambiar profesional
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
                    // Cargar todas las actividades
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

            // Cargar datos desde la API
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
                        actualizarTabla(data.data);
                        document.getElementById('totalRegistros').textContent = data.data.length;
                    } else {
                        console.error('Error:', data.error);
                        mostrarError(data.error);
                    }
                } catch (error) {
                    console.error('Error al cargar datos:', error);
                    mostrarError('Error al cargar los datos');
                } finally {
                    showLoading(false);
                }
            }

            function actualizarTabla(data) {
                const tbody = document.getElementById('cuerpoTabla');
                
                if (!data || data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted">No se encontraron registros con los filtros seleccionados</td></tr>';
                    if (dataTable) {
                        dataTable.destroy();
                        dataTable = null;
                    }
                    return;
                }
                
                tbody.innerHTML = data.map(item => `
                    <tr>
                        <td><span class="badge bg-secondary">${item.agenda_id || '-'}</span></td>
                        <td>${escapeHtml(item.especialidad || '-')}</td>
                        <td>${escapeHtml(item.especialidad_rem || '-')}</td>
                        <td>${escapeHtml(item.profesional || '-')}</td>
                        <td>${escapeHtml(item.actividad || '-')}</td>
                        <td class="text-capitalize">${item.dia_semana || '-'}</td>
                        <td>${item.hora_inicio || '-'} - ${item.hora_fin || '-'}</td>
                        <td>${parseFloat(item.horas_calculadas || 0).toFixed(1)}h</td>
                        <td>${parseFloat(item.cupos_calculados || 0).toFixed(0)}</td>
                        <td><span class="badge ${getEstadoClass(item.estado)} badge-estado">${getEstadoNombre(item.estado)}</span></td>
                    </tr>
                `).join('');
                
                // Inicializar o actualizar DataTable
                if (dataTable) {
                    dataTable.destroy();
                }
                
                dataTable = $('#tablaResultados').DataTable({
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
                    },
                    order: [[0, 'desc']],
                    pageLength: 25,
                    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]]
                });
            }

            function escapeHtml(text) {
                if (!text) return '-';
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            function mostrarError(mensaje) {
                const tbody = document.getElementById('cuerpoTabla');
                tbody.innerHTML = `<tr><td colspan="10" class="text-center text-danger">${mensaje}</td></tr>`;
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
            });

            document.getElementById('btnExportarExcel').addEventListener('click', exportarExcel);
            document.getElementById('btnExportarPDF').addEventListener('click', exportarPDF);

            // Filtro jerárquico: al cambiar unidad, cargar profesionales y actividades
            document.getElementById('filtroEspecialidad').addEventListener('change', function() {
                const especialidadId = this.value;
                cargarProfesionalesPorEspecialidad(especialidadId);
                cargarActividadesPorEspecialidad(especialidadId);
            });

            // Filtro jerárquico: al cambiar profesional, cargar agendas disponibles
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

            // Evento para actualizar resumen al cambiar agenda ID
            document.getElementById('filtroAgendaId').addEventListener('change', actualizarResumenFiltros);
            document.getElementById('filtroActividad').addEventListener('change', actualizarResumenFiltros);
            document.getElementById('filtroDia').addEventListener('change', actualizarResumenFiltros);
            document.getElementById('filtroEstado').addEventListener('change', actualizarResumenFiltros);

            // Cargar datos iniciales
            cargarDatos();
    </script>
</body>
<footer class="text-center py-4">Patricio Corrotea | Gestión de la Demanda</footer>
</html>