<?php
// Habilitar errores temporalmente
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/auth.php';
$auth = new Auth();

if (!$auth->isLoggedIn()) {
    header("Location: index.php");
    exit;
}

if (!($auth->isAdmin() || $auth->isUGD())) {
    header("Location: dashboard.php");
    exit;
}

require_once 'config/database.php';
$database = new Database();
$conn = $database->getConnection();

$mensaje = '';
$error = '';
$registros_procesados = 0;
$registros_errores = 0;

// PROBAR: ¿Se está ejecutando el POST?
error_log("=== INICIO PROCESAMIENTO CSV ===");
error_log("Método: " . $_SERVER['REQUEST_METHOD']);
error_log("¿Hay archivo?: " . (isset($_FILES['archivo_csv']) ? 'SÍ' : 'NO'));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo_csv'])) {
    $archivo = $_FILES['archivo_csv'];
    error_log("Archivo recibido: " . print_r($archivo, true));
    
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        $error = "Error en subida: " . $archivo['error'];
        error_log("Error upload: " . $archivo['error']);
    } elseif (pathinfo($archivo['name'], PATHINFO_EXTENSION) !== 'csv') {
        $error = "Debe ser archivo CSV";
    } else {
        // LEER ARCHIVO
        $handle = fopen($archivo['tmp_name'], 'r');
        if ($handle === FALSE) {
            $error = "No se pudo abrir archivo";
        } else {
            // Saltar encabezados
            $encabezados = fgetcsv($handle, 1000, ',');
            error_log("Encabezados: " . print_r($encabezados, true));
            
            // Preparar SQL
            $sql = "INSERT INTO reportes (
                Fecha_Atencion, Tipo_Reporte, RUT, Agenda, Profesional, 
                Hora, Paciente, Tipo_Atencion, Estado_Cita, Num_Reporte, 
                Agendado_por, ID_agenda, Grupo, Usuario_Carga, Fecha_Carga
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            try {
                $stmt = $conn->prepare($sql);
                $usuario = $_SESSION['username'] ?? 'sistema';
                
                $linea_num = 1;
                $conn->beginTransaction();
                
                while (($datos = fgetcsv($handle, 1000, ',')) !== FALSE) {
                    $linea_num++;
                    
                    // Saltar líneas vacías
                    if (empty($datos) || count(array_filter($datos, 'strlen')) === 0) {
                        continue;
                    }
                    
                    error_log("Procesando línea $linea_num: " . print_r($datos, true));
                    
                    // Asegurar 13 elementos
                    while (count($datos) < 13) {
                        $datos[] = NULL;
                    }
                    
                    // Agregar usuario
                    $datos[] = $usuario;
                    
                    try {
                        $resultado = $stmt->execute($datos);
                        
                        if ($resultado) {
                            $registros_procesados++;
                            error_log("✅ Línea $linea_num insertada");
                        } else {
                            $registros_errores++;
                            $error_info = $stmt->errorInfo();
                            error_log("❌ Error línea $linea_num: " . print_r($error_info, true));
                        }
                        
                    } catch (PDOException $e) {
                        $registros_errores++;
                        error_log("❌ PDOException línea $linea_num: " . $e->getMessage());
                    }
                }
                
                fclose($handle);
                
                // COMMIT
                if ($conn->inTransaction()) {
                    $conn->commit();
                    error_log("✅ Commit exitoso");
                }
                
                if ($registros_procesados > 0) {
                    $mensaje = "✅ Archivo procesado. Insertados: $registros_procesados registros";
                    if ($registros_errores > 0) {
                        $mensaje .= " ($registros_errores errores)";
                    }
                } else {
                    $error = "⚠️ No se insertaron registros. Verifica el formato.";
                }
                
            } catch (PDOException $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                $error = "❌ Error en base de datos: " . $e->getMessage();
                error_log("❌ Error general BD: " . $e->getMessage());
            }
        }
    }
}

// Obtener reportes para mostrar
$reportes = [];
try {
    $stmt = $conn->query("SELECT * FROM reportes ORDER BY Fecha_Carga DESC LIMIT 25");
    $reportes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error .= " | Error al leer: " . $e->getMessage();
}

// Obtener estadísticas
$total_registros = 0;
try {
    $stmt = $conn->query("SELECT COUNT(*) FROM reportes");
    $total_registros = $stmt->fetchColumn();
} catch (PDOException $e) {
    // Ignorar error en estadísticas
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cargar CSV - Depuración</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .status-box {
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .status-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .status-error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .status-info {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
    </style>
</head>
<body>
	<!-- Header -->
	<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
		<h1 class="h2">
			<i class="fas fa-calendar-times me-2"></i>📁 Cargar Reporte CSV
		</h1>
		<div class="btn-toolbar mb-2 mb-md-0">
			<a href="dashboard.php" class="btn btn-sm btn-outline-secondary">
				<i class="fas fa-arrow-left me-1"></i>Volver al Dashboard
			</a>
		</div>
	</div>
        <?php if ($mensaje): ?>
        <div class="status-box status-success">
            <h4>✅ Éxito</h4>
            <?php echo $mensaje; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="status-box status-error">
            <h4>❌ Error</h4>
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <div class="status-box status-info">
            <h4>ℹ️ Información</h4>
            <p>Total registros en base de datos: <strong><?php echo $total_registros; ?></strong>
            <span> | Usuario actual: <strong><?php echo $_SESSION['username'] ?? 'No identificado'; ?></strong></span></p>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h3>Cargar Archivo CSV</h3>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" id="formCarga">
                    <div class="mb-3">
                        <label class="form-label">Seleccionar archivo CSV:</label>
                        <input type="file" class="form-control" name="archivo_csv" accept=".csv" required>
                        <!--<div class="form-text">
                            El archivo debe tener 13 columnas en este orden:<br>
                            Fecha_Atencion, Tipo_Reporte, RUT, Agenda, Profesional, Hora, Paciente, 
                            Tipo_Atencion, Estado_Cita, Num_Reporte, Agendado_por, ID_agenda, Grupo
                        </div>-->
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Procesar CSV
                    </button>
                    <!--
                    <button type="button" class="btn btn-outline-secondary" onclick="mostrarDebug()">
                        <i class="fas fa-bug"></i> Ver Debug
                    </button>-->
                </form>
                <!--
                <div id="debugInfo" style="display: none; margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                    <h5>Información de Depuración:</h5>
                    <pre id="debugContent"></pre>
                </div>-->
            </div>
        </div>
        
        <?php if (!empty($reportes)): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h3>Últimos Registros (<?php echo count($reportes); ?>)</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                             
                                <th>Fecha</th>
                                <th>Reporte</th>
                                <th>Profesional</th>
                                <th>Estado</th>
                                <th>Usuario</th>
                                <th>Carga</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportes as $reporte): ?>
                            <tr>
                                
                                <td><?php echo htmlspecialchars($reporte['Fecha_Atencion'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars(substr($reporte['Num_Reporte'] ?? '', 0, 20)); ?></td>
                                <td><?php echo htmlspecialchars(substr($reporte['Profesional'] ?? '', 0, 20)); ?></td>
                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($reporte['Estado_Cita'] ?? ''); ?></span></td>
                                <td><small><?php echo htmlspecialchars($reporte['Usuario_Carga'] ?? ''); ?></small></td>
                                <td><small><?php echo htmlspecialchars($reporte['Fecha_Carga'] ?? ''); ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
	
 
    <script>
        function mostrarDebug() {
            const debugDiv = document.getElementById('debugInfo');
            debugDiv.style.display = debugDiv.style.display === 'none' ? 'block' : 'none';
            
            if (debugDiv.style.display === 'block') {
                // Obtener info del formulario
                const form = document.getElementById('formCarga');
                const fileInput = form.querySelector('input[type="file"]');
                let debugText = "Formulario listo\n";
                debugText += "File input: " + (fileInput.files.length > 0 ? fileInput.files[0].name : "Sin archivo") + "\n";
                debugText += "Método: POST\n";
                debugText += "Enctype: multipart/form-data\n";
                
                document.getElementById('debugContent').textContent = debugText;
            }
        }
        
        // Validación simple
        document.getElementById('formCarga').addEventListener('submit', function(e) {
            const fileInput = this.querySelector('input[type="file"]');
            if (fileInput.files.length === 0) {
                e.preventDefault();
                alert('Selecciona un archivo CSV');
                return;
            }
            
            const file = fileInput.files[0];
            if (!file.name.toLowerCase().endsWith('.csv')) {
                e.preventDefault();
                alert('El archivo debe ser CSV');
                return;
            }
            
            // Cambiar texto del botón
            const btn = this.querySelector('button[type="submit"]');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
            btn.disabled = true;
        });
    </script>
</body>
<footer class="text-center py-4">Patricio Corrotea | Gestión de la Demanda</footer>
</html>