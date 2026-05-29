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
	<style>
		.dashboard-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
			gap: 24px;
			padding: 20px;
			max-width: 1600px;
			margin: 0 auto;
		}

		.dashboard-card {
			background: white;
			border-radius: 20px;
			padding: 15px 10px;
			text-align: center;
			cursor: pointer;
			transition: all 0.3s ease;
			box-shadow: 0 4px 12px rgba(0,0,0,0.1);
			border: 1px solid #eef2f6;
		}

		.dashboard-card:hover {
			transform: translateY(-8px);
			box-shadow: 0 20px 30px -12px rgba(0,0,0,0.2);
			border-color: transparent;
		}

		.card-icon {
			font-size: 32px;
			color: #2c7da0;
			margin-bottom: 20px;
		}

		.dashboard-card h3 {
			font-size: 1.2rem;
			margin: 0 0 10px 0;
			color: #1e2a3e;
		}

		.dashboard-card p {
			color: #5a6e7c;
			margin: 0 0 20px 0;
			font-size: 0.9rem;
		}

		.card-btn {
			background: #2c7da0;
			color: white;
			border: none;
			padding: 5px 10px;
			border-radius: 30px;
			cursor: pointer;
			font-weight: 200;
			transition: background 0.3s ease;
		}

		.card-btn:hover {
			background: #1f5e7a;
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

                <!-- Dashboard Header -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-digital-tachograph me-2"></i>Administración | Gear-HSP
                    </h1>
                </div>

                <!-- Dashboard Stats -->
                <div class="row mt-4 border-bottom">
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

                <!-- Panel de control -->
                <div class="row mt-4">
                    <div class="col-12">
                      <div class="dashboard-grid">
							<div class="dashboard-card" onclick="location.href='gestion_usuarios.php'">
								<div class="card-icon">
									<i class="fas fa-users-cog"></i>
								</div>
								<h3>Gestión Usuarios</h3>
								<p>Administrar cuentas y permisos</p>
								<button class="card-btn">Acceder →</button>
							</div>

							<div class="dashboard-card" onclick="location.href='gestion_especialidades.php'">
								<div class="card-icon">
									<i class="fas fa-briefcase-medical"></i>
								</div>
								<h3>Gestión Unidades</h3>
								<p>Administrar Unidades y Servicios</p>
								<button class="card-btn">Acceder →</button>
							</div>
							
							<div class="dashboard-card" onclick="location.href='gestion_ubicaciones.php'">
								<div class="card-icon">
									<i class="fa solid fa-location-dot"></i>
								</div>
								<h3>Gestión Ubicaciones</h3>
								<p>Administrar Ubicaciones/Sectores y Box</p>
								<button class="card-btn">Acceder →</button>
							</div>
							
							<div class="dashboard-card" onclick="location.href='gestion_profesionales.php'">
								<div class="card-icon">
									<i class="fas fa-user-md"></i>
								</div>
								<h3>Gestión Profesionales</h3>
								<p>Administrar profesionales de salud</p>
								<button class="card-btn">Acceder →</button>
							</div>
							
							<div class="dashboard-card" onclick="location.href='gestion_especialidades_rem.php'">
								<div class="card-icon">
									<i class="fas fa-briefcase-medical"></i>
								</div>
								<h3>Gestión Especialidades REM</h3>
								<p>Administrar rendimiento y metas de Especialidades REM</p>
								<button class="card-btn">Acceder →</button>
							</div>
							<div class="dashboard-card" onclick="location.href='gestion_actividades.php'">
								<div class="card-icon">
									<i class="fas fa-tasks me-2"></i>
								</div>
								<h3>Gestión Actividades</h3>
								<p>Administrar actividades diponibles para confección de agenda</p>
								<button class="card-btn">Acceder →</button>
							</div>
							<div class="dashboard-card" onclick="location.href='gestion_motivos.php'">
								<div class="card-icon">
									<i class="fas fa-calendar-times me-2"></i>
								</div>
								<h3>Gestión Motivos de Bloqueo</h3>
								<p>Administrar los motivos para bloqueo de agenda</p>
								<button class="card-btn">Acceder →</button>
							</div>
						</div>

						
                    </div>
                </div>
            </main>
        </div>
    </div>



    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
	<!-- Font Awesome -->
	<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    <script src="js/script.js"></script>


</body>
<footer class="text-center py-4">Patricio Corrotea | Gestión de la Demanda</footer>
</html>