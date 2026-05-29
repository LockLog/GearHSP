<?php
require_once 'includes/auth.php';
$auth = new Auth();

if (!$auth->isLoggedIn()) {
    header("Location: index.php");
    exit;
}

// Obtener ID de la agenda
$agenda_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($agenda_id <= 0) {
    die("ID de agenda no válido");
}

require_once 'config/database.php';
$database = new Database();
$conn = $database->getConnection();

// Obtener información de la agenda
$stmt = $conn->prepare("
    SELECT a.*, 
           e.nombre as especialidad_nombre, 
           p.nombre as profesional_nombre,
		   p.rut as profesional_rut,
           p.estamento as profesional_estamento
    FROM agendas a
    LEFT JOIN especialidades e ON a.especialidad_id = e.id
    LEFT JOIN profesionales p ON a.profesional_id = p.id
    WHERE a.id = ?
");
$stmt->execute([$agenda_id]);
$agenda = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$agenda) {
    die("Agenda no encontrada");
}

// Obtener detalles de la agenda con información de actividades
$stmt = $conn->prepare("
    SELECT d.*, 
           act.actividad as actividad_nombre,
           act.clasificacion,
           rem.especialidad_rem as especialidad_rem_nombre
    FROM detalles_agenda d
    LEFT JOIN actividades act ON d.actividad_id = act.id
    LEFT JOIN especialidades_rem rem ON d.especialidad_rem_id = rem.id
    WHERE d.agenda_id = ?
    ORDER BY FIELD(d.dia_semana, 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'),
             d.hora_inicio
");
$stmt->execute([$agenda_id]);
$detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organizar datos por actividad y día
$dias_semana = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
$dias_mostrar = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado']; // excluir domingo
$nombres_dias = [
    'lunes' => 'Lunes',
    'martes' => 'Martes',
    'miercoles' => 'Miércoles',
    'jueves' => 'Jueves',
    'viernes' => 'Viernes',
    'sabado' => 'Sábado'
];

// Agrupar por actividad (usando actividad_id como identificador único)
$actividades_agrupadas = [];
foreach ($detalles as $detalle) {
    $key = $detalle['actividad_id'];
    if (!isset($actividades_agrupadas[$key])) {
        $actividades_agrupadas[$key] = [
            'actividad_id' => $detalle['actividad_id'],
            'actividad_nombre' => $detalle['actividad_nombre'],
            'detalle' => $detalle['detalle'],
            'rendimiento' => $detalle['rendimiento'],
            'clasificacion' => $detalle['clasificacion'],
            'horarios_por_dia' => []
        ];
    }
    // Guardar horario por día (puede haber múltiples horarios por día)
    $dia = $detalle['dia_semana'];
    if (!isset($actividades_agrupadas[$key]['horarios_por_dia'][$dia])) {
        $actividades_agrupadas[$key]['horarios_por_dia'][$dia] = [];
    }
    $actividades_agrupadas[$key]['horarios_por_dia'][$dia][] = [
        'hora_inicio' => substr($detalle['hora_inicio'], 0, 5),
        'hora_fin' => substr($detalle['hora_fin'], 0, 5),
        'horas_calculadas' => $detalle['horas_calculadas'],
        'ubicacion' => $detalle['ubicacion'],
        'agendamiento' => $detalle['agendamiento']
    ];
}

// Calcular totales por día
$totales_por_dia = [];
foreach ($dias_mostrar as $dia) {
    $totales_por_dia[$dia] = 0;
}
foreach ($detalles as $detalle) {
    $dia = $detalle['dia_semana'];
    if (in_array($dia, $dias_mostrar)) {
        $totales_por_dia[$dia] += floatval($detalle['horas_calculadas']);
    }
}
$total_horas_generales = array_sum($totales_por_dia);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="img/favicon.png" type="image/png">
    <title>Resumen de Agenda | Gear-HSP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                padding: 0;
                margin: 0;
            }
            .container {
                max-width: 100%;
                padding: 0;
            }
            .table-responsive {
                overflow: visible !important;
            }
            .card {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
            }
            .badge {
                border: 1px solid #ddd;
            }
        }
        
        body {
            background-color: #f5f7fb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .header-resumen {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 20px 0;
            margin-bottom: 30px;
            border-radius: 0 0 15px 15px;
        }
        
        .info-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-left: 4px solid #3498db;
        }
        
        .info-card h5 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .info-item {
            margin-bottom: 8px;
        }
        
        .info-label {
            font-weight: 600;
            color: #7f8c8d;
            width: 120px;
            display: inline-block;
        }
        
        .info-value {
            color: #2c3e50;
        }
        
        .tabla-resumen {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        
        .tabla-resumen table {
            margin-bottom: 0;
        }
        
        .tabla-resumen th {
            background-color: #2c3e50;
            color: white;
            text-align: center;
            vertical-align: middle;
            padding: 12px 8px;
            font-weight: 500;
        }
        
        .tabla-resumen td {
            vertical-align: middle;
            padding: 10px 8px;
        }
        
        .tabla-resumen .actividad-nombre {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .tabla-resumen .detalle-actividad {
            font-size: 0.85em;
            color: #7f8c8d;
        }
        
        .horario-item {
            background-color: #e8f4fd;
            border-radius: 15px;
            padding: 4px 10px;
            margin: 3px 0;
            font-size: 0.85em;
            display: inline-block;
            white-space: nowrap;
        }
        
        .horario-multiple {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            justify-content: center;
        }
        
        .sin-horario {
            color: #bdc3c7;
            font-style: italic;
            font-size: 0.85em;
        }
        
        .badge-ubicacion {
            font-size: 0.7em;
            background-color: #ecf0f1;
            color: #7f8c8d;
            margin-left: 5px;
        }
        
        .total-row {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        
        .total-row td {
            background-color: #f8f9fa;
            border-top: 2px solid #dee2e6;
        }
        
        .footer-resumen {
            margin-top: 30px;
            padding: 20px;
            text-align: center;
            color: #7f8c8d;
            font-size: 0.85em;
            border-top: 1px solid #dee2e6;
        }
        
        .btn-imprimir {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            border-radius: 50px;
            padding: 12px 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .badge-estado {
            font-size: 0.85em;
            padding: 5px 12px;
            border-radius: 20px;
        }
        
        .estado-pendiente { background-color: #ffc107; color: #000; }
        .estado-revision { background-color: #0dcaf0; color: #000; }
        .estado-boxnodisponible { background-color: #fd7e14; color: #fff; }
        .estado-autorizada { background-color: #198754; color: #fff; }
        .estado-implementada { background-color: #20c997; color: #fff; }
        .estado-anulada { background-color: #dc3545; color: #fff; }
        
        @media (max-width: 768px) {
            .tabla-resumen {
                overflow-x: auto;
            }
            .info-label {
                width: 100%;
                display: block;
            }
            .horario-item {
                font-size: 0.75em;
                white-space: normal;
            }
        }
    </style>
</head>
<body>
    <div class="header-resumen no-print">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-chalkboard-user fa-2x me-3"></i>
                    <h1 class="d-inline-block h3 mb-0">Resumen de Agenda</h1>
                </div>
                <div>
                    <span class="badge estado-<?php echo $agenda['estado']; ?> badge-estado">
                        <?php echo $auth->getNombreEstadoAgenda($agenda['estado']); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container">
        <!-- Información de la agenda -->
        <div class="info-card">
            <div class="row">
                <div class="col-md-5">
                    <h5><i class="fas fa-info-circle me-2"></i>Datos Generales</h5>
                    <div class="info-item">
                        <span class="info-label">ID Agenda:</span>
                        <span class="info-value"><?php echo $agenda['id']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Unidad/Servicio:</span>
                        <span class="info-value"><?php echo htmlspecialchars($agenda['especialidad_nombre']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Profesional:</span>
                        <span class="info-value"><?php echo htmlspecialchars($agenda['profesional_nombre']); ?></span>
                    </div>
					<!-- RUT del profesional - AGREGADO -->
					<div class="info-item">
						<span class="info-label">RUT:</span>
						<span class="info-value rut-value">
							<?php 
							$rut = $agenda['profesional_rut'] ?? '';
							if (!empty($rut)) {
								// Formatear RUT si es necesario (ej: 12345678-5)
								if (preg_match('/^(\d{1,8})(\d{1})$/', $rut, $matches)) {
									echo number_format($matches[1], 0, '', '.') . '-' . $matches[2];
								} else {
									echo htmlspecialchars($rut);
								}
							} else {
								echo '<span class="text-muted">No registrado</span>';
							}
							?>
						</span>
					</div>
                    <div class="info-item">
                        <span class="info-label">Estamento:</span>
                        <span class="info-value"><?php echo htmlspecialchars($agenda['profesional_estamento'] ?? $agenda['estamento']); ?></span>
                    </div>
                </div>
                <div class="col-md-3">
                    <h5><i class="fas fa-chart-line me-2"></i>Datos Contractuales</h5>
                    <div class="info-item">
                        <span class="info-label">Horas Contrato:</span>
                        <span class="info-value"><?php echo $agenda['horas_contrato']; ?> horas</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Fecha Inicio:</span>
                        <span class="info-value"><?php echo date('d/m/Y', strtotime($agenda['fecha_inicio'])); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Total Horas Asignadas:</span>
                        <span class="info-value"><strong><?php echo number_format($total_horas_generales, 2); ?> horas</strong></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Diferencia:</span>
                        <span class="info-value <?php echo ($agenda['horas_contrato'] - $total_horas_generales) < 0 ? 'text-danger' : ''; ?>">
                            <?php 
                            $diferencia = $agenda['horas_contrato'] - $total_horas_generales;
                            echo number_format(abs($diferencia), 2) . ' horas ' . ($diferencia < 0 ? '(excedidas)' : ($diferencia > 0 ? '(pendientes)' : '(justo)'));
                            ?>
                        </span>
                    </div>
                </div>
				<!-- Indicadores de Gestión -->
				<?php
				// Calcular indicadores
				$total_cupos_cn = 0;
				$total_cupos_cr = 0;
				$total_horas_clinica = 0;

				// Obtener meta_cn y meta_amb de la especialidad_rem asociada a la agenda
				// Primero, obtener la especialidad_rem de alguna actividad de la agenda
				$especialidad_rem_data = null;
				if (count($detalles) > 0) {
					// Buscar la primera actividad que tenga especialidad_rem_id
					foreach ($detalles as $detalle) {
						if (!empty($detalle['especialidad_rem_id'])) {
							$stmt_rem = $conn->prepare("SELECT meta_cn, meta_amb FROM especialidades_rem WHERE id = ?");
							$stmt_rem->execute([$detalle['especialidad_rem_id']]);
							$especialidad_rem_data = $stmt_rem->fetch(PDO::FETCH_ASSOC);
							break;
						}
					}
				}

				// Calcular totales
				foreach ($detalles as $detalle) {
					// Sumar cupos para CN y CR según actividad
					if (stripos($detalle['actividad_nombre'] ?? '', 'Consulta Nueva de especialidad Presencial') !== false) {
						$total_cupos_cn += floatval($detalle['cupos_calculados'] ?? 0);
					}
					if (stripos($detalle['actividad_nombre'] ?? '', 'Consulta Control de especialidad Presencial') !== false) {
						$total_cupos_cr += floatval($detalle['cupos_calculados'] ?? 0);
					}
					
					// Sumar horas para actividades clínicas
					if (($detalle['clasificacion'] ?? '') === 'Ambulatoria') {
						$total_horas_clinica += floatval($detalle['horas_calculadas'] ?? 0);
					}
				}

				// Calcular indicadores
				$total_cupos_cn_cr = $total_cupos_cn + $total_cupos_cr;
				$ratio_cn = ($total_cupos_cn_cr > 0) ? ($total_cupos_cn / $total_cupos_cn_cr) * 100 : 0;
				$ratio_amb = ($agenda['horas_contrato'] > 0) ? ($total_horas_clinica / $agenda['horas_contrato']) * 100 : 0;

				$meta_cn = $especialidad_rem_data['meta_cn'] ?? 0;
				$meta_amb = $especialidad_rem_data['meta_amb'] ?? 0;

				// Determinar colores para las comparaciones
				$cn_color = ($ratio_cn >= $meta_cn) ? 'success' : (($ratio_cn >= $meta_cn * 0.7) ? 'warning' : 'danger');
				$amb_color = ($ratio_amb >= $meta_amb) ? 'success' : (($ratio_amb >= $meta_amb * 0.7) ? 'warning' : 'danger');

				$cn_icon = ($ratio_cn >= $meta_cn) ? 'fa-check-circle' : (($ratio_cn >= $meta_cn * 0.7) ? 'fa-chart-line' : 'fa-exclamation-triangle');
				$amb_icon = ($ratio_amb >= $meta_amb) ? 'fa-check-circle' : (($ratio_amb >= $meta_amb * 0.7) ? 'fa-chart-line' : 'fa-exclamation-triangle');
				?>
				<div class="col-md-4">
					<h5><i class="fas fa-chart-pie me-2"></i>Indicadores de Gestión</h5>
					
					<!-- Indicador CN -->
					<div class="info-item mb-3">
						<div class="d-flex justify-content-between align-items-center mb-1">
							<span class="info-label">Consulta Nueva:</span>
							<span class="badge bg-<?php echo $cn_color; ?>">
								<i class="fas <?php echo $cn_icon; ?> me-1"></i>
								<?php echo number_format($ratio_cn, 1); ?>% | Meta: <?php echo number_format($meta_cn, 1); ?>%
							</span>
						</div>
						<div class="progress" style="height: 8px;">
							<div class="progress-bar bg-<?php echo $cn_color; ?>" 
								 role="progressbar" 
								 style="width: <?php echo min(100, $ratio_cn); ?>%" 
								 aria-valuenow="<?php echo $ratio_cn; ?>" 
								 aria-valuemin="0" 
								 aria-valuemax="100">
							</div>
						</div>
						<small class="text-muted">
							CN: <?php echo number_format($total_cupos_cn, 0); ?> | 
							CR: <?php echo number_format($total_cupos_cr, 0); ?> | 
							Total: <?php echo number_format($total_cupos_cn_cr, 0); ?>
						</small>
					</div>
					
					<!-- Indicador Ambulatorio -->
					<div class="info-item mb-4">
						<div class="d-flex justify-content-between align-items-center mb-1">
							<span class="info-label">Ambulatoria:</span>
							<span class="badge bg-<?php echo $amb_color; ?>">
								<i class="fas <?php echo $amb_icon; ?> me-1"></i>
								<?php echo number_format($ratio_amb, 1); ?>% | Meta: <?php echo number_format($meta_amb, 1); ?>%
							</span>
						</div>
						<div class="progress" style="height: 8px;">
							<div class="progress-bar bg-<?php echo $amb_color; ?>" 
								 role="progressbar" 
								 style="width: <?php echo min(100, $ratio_amb); ?>%" 
								 aria-valuenow="<?php echo $ratio_amb; ?>" 
								 aria-valuemin="0" 
								 aria-valuemax="100">
							</div>
						</div>
						<small class="text-muted">
							Horas Ambulatorias: <?php echo number_format($total_horas_clinica, 2); ?>h | 
							Total Contrato: <?php echo $agenda['horas_contrato']; ?>h
						</small>
					</div>
				</div>
            </div>
            <?php if (!empty($agenda['descripcion'])): ?>
            <div class="row mt-3">
                <div class="col-12">
                    <hr>
                    <div class="info-item">
                        <span class="info-label">Descripción:</span>
                        <span class="info-value"><?php echo nl2br(htmlspecialchars($agenda['descripcion'])); ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Tabla de resumen de actividades -->
        <div class="tabla-resumen">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th style="width: 25%">Actividad / Detalle / Rendimiento</th>
                            <?php foreach ($dias_mostrar as $dia): ?>
                                <th style="width: 12.5%"><?php echo $nombres_dias[$dia]; ?></th>
                            <?php endforeach; ?>
                        </tr>
                        <tr class="text-center">
                            <td class="bg-light"><small>Horas por día</small></td>
                            <?php foreach ($dias_mostrar as $dia): ?>
                                <td class="bg-light">
                                    <small>
                                        <?php 
                                        $horas = $totales_por_dia[$dia] ?? 0;
                                        echo number_format($horas, 2) . 'h';
                                        ?>
                                    </small>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($actividades_agrupadas) > 0): ?>
                            <?php foreach ($actividades_agrupadas as $actividad): ?>
                                <tr>
                                    <td>
                                        <div class="actividad-nombre">
                                            <?php echo htmlspecialchars($actividad['actividad_nombre']); ?>
                                            <?php if (!empty($actividad['clasificacion'])): ?>
                                                <span class="badge bg-secondary ms-1"><?php echo htmlspecialchars($actividad['clasificacion']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($actividad['detalle'])): ?>
                                            <div class="detalle-actividad mt-1">
                                                <i class="fas fa-align-left fa-xs me-1"></i>
                                                <?php echo nl2br(htmlspecialchars(substr($actividad['detalle'], 0, 100))); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="mt-1">
                                            <small class="text-muted">
                                                <i class="fas fa-chart-line me-1"></i>Rend: <?php echo number_format($actividad['rendimiento'], 2); ?>
                                            </small>
                                        </div>
                                    </td>
                                    <?php foreach ($dias_mostrar as $dia): ?>
                                        <td class="text-center">
                                            <?php if (isset($actividad['horarios_por_dia'][$dia])): ?>
                                                <div class="horario-multiple">
                                                    <?php foreach ($actividad['horarios_por_dia'][$dia] as $horario): ?>
                                                        <div class="horario-item" title="<?php echo htmlspecialchars($horario['ubicacion']); ?>">
                                                            <i class="fas fa-clock fa-xs me-1"></i>
                                                            <?php echo $horario['hora_inicio']; ?> - <?php echo $horario['hora_fin']; ?>
                                                            <span class="badge-ubicacion">
                                                                <i class="fas fa-location-dot fa-xs"></i>
                                                                <?php 
                                                                $ubicacion_short = strlen($horario['ubicacion']) > 15 ? substr($horario['ubicacion'], 0, 12) . '...' : $horario['ubicacion'];
                                                                echo htmlspecialchars($ubicacion_short);
                                                                ?>
                                                            </span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                <?php 
                                                $total_dia = array_sum(array_column($actividad['horarios_por_dia'][$dia], 'horas_calculadas'));
                                                ?>
                                                <small class="text-muted d-block mt-1">
                                                    <?php echo number_format($total_dia, 2); ?>h
                                                </small>
                                            <?php else: ?>
                                                <span class="sin-horario">—</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <i class="fas fa-calendar-times fa-3x text-muted mb-3 d-block"></i>
                                    <p class="text-muted">No hay actividades registradas para esta agenda</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="total-row">
                        <tr>
                            <td class="text-end"><strong>TOTAL HORAS</strong></td>
                            <?php foreach ($dias_mostrar as $dia): ?>
                                <td class="text-center">
                                    <strong><?php echo number_format($totales_por_dia[$dia] ?? 0, 2); ?>h</strong>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        
        <!-- Tabla detallada (alternativa) -->
        <?php if (count($detalles) > 0): ?>
        <div class="tabla-resumen mt-4">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0"><i class="fas fa-list-ul me-2"></i>Detalle de Actividades por Horario</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Día</th>
                            <th>Actividad</th>
                            <th>Detalle</th>
                            <th>Horario</th>
                            <th>Horas</th>
                            <th>Ubicación</th>
                            <th>Rendimiento</th>
                            <th>Agendamiento</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detalles as $detalle): ?>
                        <tr>
                            <td><?php echo ucfirst($detalle['dia_semana']); ?></td>
                            <td><?php echo htmlspecialchars($detalle['actividad_nombre']); ?></td>
                            <td><?php echo htmlspecialchars(substr($detalle['detalle'] ?? '', 0, 50)); ?></td>
                            <td><?php echo substr($detalle['hora_inicio'], 0, 5); ?> - <?php echo substr($detalle['hora_fin'], 0, 5); ?></td>
                            <td><?php echo number_format($detalle['horas_calculadas'], 2); ?>h</td>
                            <td><?php echo htmlspecialchars($detalle['ubicacion']); ?></td>
                            <td><?php echo number_format($detalle['rendimiento'], 2); ?></td>
                            <td><?php echo htmlspecialchars($detalle['agendamiento']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="footer-resumen no-print">
            <p>
                <i class="fas fa-print me-2"></i> 
                Documento generado automáticamente por Gear-HSP<br>
                Fecha de emisión: <?php echo date('d/m/Y H:i:s'); ?>
            </p>
        </div>
    </div>
    
    <!-- Botón flotante para imprimir -->
    <button class="btn btn-primary btn-imprimir no-print" onclick="window.print()">
        <i class="fas fa-print me-2"></i>Imprimir / Guardar PDF
    </button>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>