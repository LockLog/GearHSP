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

// Procesar filtros
$filtro_usuario = $_GET['filtro_usuario'] ?? '';
$filtro_nombre = $_GET['filtro_nombre'] ?? '';
$filtro_rol = $_GET['filtro_rol'] ?? '';

// Procesar formularios
if ($_POST) {
    // Crear usuario
    if (isset($_POST['crear_usuario'])) {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $nombre_completo = trim($_POST['nombre_completo']);
        $email = trim($_POST['email']);
        $rol = $_POST['rol'];
        
        // Validaciones
        if (empty($username) || empty($password) || empty($nombre_completo)) {
            $error = "Todos los campos obligatorios deben ser completados";
        } elseif ($auth->usernameExists($username)) {
            $error = "El nombre de usuario ya existe";
        } elseif ($email && $auth->emailExists($email)) {
            $error = "El email ya está registrado";
        } elseif (strlen($password) < 6) {
            $error = "La contraseña debe tener al menos 6 caracteres";
        } else {
            if ($auth->createUser($username, $password, $nombre_completo, $email, $rol)) {
                $success = "Usuario creado correctamente";
            } else {
                $error = "Error al crear el usuario";
            }
        }
    }
    
    // Editar usuario
	if (isset($_POST['editar_usuario'])) {
		$user_id = $_POST['user_id'];
		$username = trim($_POST['username']);
		$nombre_completo = trim($_POST['nombre_completo']);
		$email = trim($_POST['email']);
		$rol = $_POST['rol'];
		$activo = isset($_POST['activo']) ? 1 : 0; // Valor por defecto si no existe
		
		// Validaciones
		if (empty($username) || empty($nombre_completo)) {
			$error = "Todos los campos obligatorios deben ser completados";
		} elseif ($auth->usernameExists($username, $user_id)) {
			$error = "El nombre de usuario ya existe";
		} elseif ($email && $auth->emailExists($email, $user_id)) {
			$error = "El email ya está registrado";
		} else {
			if ($auth->updateUser($user_id, $username, $nombre_completo, $email, $rol, $activo)) {
				$success = "Usuario actualizado correctamente";
			} else {
				$error = "Error al actualizar el usuario";
			}
		}
	}
    
    // Eliminar usuario
    if (isset($_POST['eliminar_usuario'])) {
        $user_id = $_POST['user_id'];
        
        // No permitir eliminar el propio usuario
        if ($user_id == $_SESSION['user_id']) {
            $error = "No puedes eliminar tu propio usuario";
        } else {
            if ($auth->deleteUser($user_id)) {
                $success = "Usuario eliminado correctamente";
            } else {
                $error = "Error al eliminar el usuario";
            }
        }
    }
    
    // Cambiar contraseña
    if (isset($_POST['cambiar_password'])) {
        $user_id = $_POST['user_id'];
        $nueva_password = $_POST['nueva_password'];
        $confirmar_password = $_POST['confirmar_password'];
        
        if (empty($nueva_password)) {
            $error = "La nueva contraseña no puede estar vacía";
        } elseif ($nueva_password !== $confirmar_password) {
            $error = "Las contraseñas no coinciden";
        } elseif (strlen($nueva_password) < 6) {
            $error = "La contraseña debe tener al menos 6 caracteres";
        } else {
            if ($auth->updateUserPassword($user_id, $nueva_password)) {
                $success = "Contraseña actualizada correctamente";
            } else {
                $error = "Error al actualizar la contraseña";
            }
        }
    }
		// Resetear contraseña (generar contraseña temporal)
	if (isset($_POST['resetear_password'])) {
		$user_id = $_POST['user_id'];
		$username = $_POST['username'];
		
		// No permitir resetear la propia contraseña (opcional)
		if ($user_id == $_SESSION['user_id']) {
			$error = "No puedes resetear tu propia contraseña. Usa la opción de cambiar contraseña.";
		} else {
			$temp_password = $auth->resetUserPassword($user_id);
			if ($temp_password) {
				$success = "Contraseña resetada para el usuario '" . htmlspecialchars($username) . "'.<br>
							<strong>Contraseña temporal: " . htmlspecialchars($temp_password) . "</strong><br>
							<small class='text-muted'>El usuario deberá cambiar esta contraseña en su próximo inicio de sesión.</small>";
			} else {
				$error = "Error al resetear la contraseña del usuario";
			}
		}
	}
	
	
	
}

// Obtener lista de usuarios
$usuarios = $auth->getUsers();

// Aplicar filtros si existen
if (!empty($filtro_usuario) || !empty($filtro_nombre) || !empty($filtro_rol)) {
    $usuarios = array_filter($usuarios, function($usuario) use ($filtro_usuario, $filtro_nombre, $filtro_rol) {
        $cumple_filtro_usuario = true;
        $cumple_filtro_nombre = true;
        $cumple_filtro_rol = true;
        
        // Filtrar por nombre de usuario
        if (!empty($filtro_usuario)) {
            $cumple_filtro_usuario = stripos($usuario['username'], $filtro_usuario) !== false;
        }
        
        // Filtrar por nombre completo
        if (!empty($filtro_nombre)) {
            $cumple_filtro_nombre = stripos($usuario['nombre_completo'], $filtro_nombre) !== false;
        }
        
        // Filtrar por rol
        if (!empty($filtro_rol) && $filtro_rol !== 'todos') {
            $cumple_filtro_rol = $usuario['rol'] === $filtro_rol;
        }
        
        return $cumple_filtro_usuario && $cumple_filtro_nombre && $cumple_filtro_rol;
    });
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="icon" href="img/favicon.png" type="image/png">
    <title>Gestión de Usuarios | Gear-HSP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .table-actions {
            white-space: nowrap;
        }
        .badge-activo {
            background-color: #28a745;
        }
        .badge-inactivo {
            background-color: #6c757d;
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #2c3e50;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        .filter-section {
            background-color: #f8f9fa;
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border: 1px solid #dee2e6;
        }
        .filter-header {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #495057;
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
                        <i class="fas fa-users-cog me-2"></i>Gestión de Usuarios
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#crearUsuarioModal">
                            <i class="fas fa-user-plus me-2"></i>Nuevo Usuario
                        </button>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="filter-section">
                    <h6 class="filter-header">
                        <i class="fas fa-filter me-2"></i>Filtrar Usuarios
                    </h6>
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="filtro_usuario" class="form-label">Buscar por Usuario</label>
                            <input type="text" class="form-control" id="filtro_usuario" name="filtro_usuario" 
                                   value="<?php echo htmlspecialchars($filtro_usuario); ?>" 
                                   placeholder="Ingrese nombre de usuario...">
                        </div>
                        <div class="col-md-3">
                            <label for="filtro_nombre" class="form-label">Buscar por Nombre</label>
                            <input type="text" class="form-control" id="filtro_nombre" name="filtro_nombre" 
                                   value="<?php echo htmlspecialchars($filtro_nombre); ?>" 
                                   placeholder="Ingrese nombre completo...">
                        </div>
                        <div class="col-md-3">
                            <label for="filtro_rol" class="form-label">Filtrar por Rol</label>
                            <select class="form-select" id="filtro_rol" name="filtro_rol">
                                <option value="todos">Todos los roles</option>
                                <option value="admin" <?php echo $filtro_rol === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                                <option value="gestor" <?php echo $filtro_rol === 'gestor' ? 'selected' : ''; ?>>Gestor</option>
                                <option value="user" <?php echo $filtro_rol === 'user' ? 'selected' : ''; ?>>Usuario Normal</option>
								<option value="ugd" <?php echo $filtro_rol === 'ugd' ? 'selected' : ''; ?>>Usuarios UGD</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="btn-group w-100">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i>Filtrar
                                </button>
                                <?php if (!empty($filtro_usuario) || !empty($filtro_nombre) || !empty($filtro_rol)): ?>
                                    <a href="gestion_usuarios.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-2"></i>Limpiar
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                    <?php if (!empty($filtro_usuario) || !empty($filtro_nombre) || !empty($filtro_rol)): ?>
                        <div class="mt-2">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Mostrando <?php echo count($usuarios); ?> usuario(s) 
                                <?php 
                                $filtros_activos = [];
                                if (!empty($filtro_usuario)) {
                                    $filtros_activos[] = 'usuario: "' . htmlspecialchars($filtro_usuario) . '"';
                                }
                                if (!empty($filtro_nombre)) {
                                    $filtros_activos[] = 'nombre: "' . htmlspecialchars($filtro_nombre) . '"';
                                }
                                if (!empty($filtro_rol) && $filtro_rol !== 'todos') {
                                    $filtros_activos[] = 'rol: "' . ucfirst($filtro_rol) . '"';
                                }
                                echo 'filtrado(s) por ' . implode(', ', $filtros_activos);
                                ?>
                            </small>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Estadísticas -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-primary"><?php echo count($usuarios); ?></h3>
                                <p class="mb-0">Usuarios <?php echo (!empty($filtro_usuario) || !empty($filtro_nombre) || !empty($filtro_rol)) ? 'Filtrados' : 'Totales'; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-success"><?php echo count(array_filter($usuarios, function($u) { return $u['rol'] === 'admin'; })); ?></h3>
                                <p class="mb-0">Administradores</p>
                            </div>
                        </div>
                    </div>
					<div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-warning"><?php echo count(array_filter($usuarios, function($u) { return $u['rol'] === 'gestor'; })); ?></h3>
                                <p class="mb-0">Gestores</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-info"><?php echo count(array_filter($usuarios, function($u) { return $u['rol'] === 'user'; })); ?></h3>
                                <p class="mb-0">Usuarios Normales</p>
                            </div>
                        </div>
                    </div>
					<div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-info"><?php echo count(array_filter($usuarios, function($u) { return $u['rol'] === 'ugd'; })); ?></h3>
                                <p class="mb-0">Usuarios UGD</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabla de Usuarios -->
				<div class="card dashboard-card">
					<div class="card-header">
						<h5 class="card-title mb-0">
							<i class="fas fa-list me-2"></i>Lista de Usuarios
                            <?php if (!empty($filtro_usuario) || !empty($filtro_nombre) || !empty($filtro_rol)): ?>
                                <span class="badge bg-primary ms-2">Filtrados</span>
                            <?php endif; ?>
						</h5>
					</div>
					<div class="card-body">
						<?php if (empty($usuarios)): ?>
							<div class="text-center py-4">
								<i class="fas fa-users fa-3x text-muted mb-3"></i>
								<p class="text-muted">
                                    <?php if (!empty($filtro_usuario) || !empty($filtro_nombre) || !empty($filtro_rol)): ?>
                                        No se encontraron usuarios que coincidan con los criterios de búsqueda
                                    <?php else: ?>
                                        No hay usuarios registrados
                                    <?php endif; ?>
                                </p>
                                <?php if (!empty($filtro_usuario) || !empty($filtro_nombre) || !empty($filtro_rol)): ?>
                                    <a href="gestion_usuarios.php" class="btn btn-outline-primary me-2">
                                        <i class="fas fa-times me-2"></i>Limpiar Filtros
                                    </a>
                                <?php endif; ?>
                                <?php if (empty($filtro_usuario) && empty($filtro_nombre) && empty($filtro_rol)): ?>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#crearUsuarioModal">
                                        <i class="fas fa-user-plus me-2"></i>Registrar Usuario
                                    </button>
                                <?php endif; ?>
							</div>
						<?php else: ?>
							<div class="table-responsive">
								<table class="table table-hover table-striped">
									<thead class="table-dark">
										<tr>
											<th>Usuario</th>
											<th>Nombre Completo</th>
											<th>Email</th>
											<th>Rol</th>
											<th>Estado</th>
											<th>Fecha Registro</th>
											<th>Acciones</th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($usuarios as $usuario): 
											// Manejar campos que pueden no existir
											$activo = isset($usuario['activo']) ? $usuario['activo'] : 1;
											$email = isset($usuario['email']) ? $usuario['email'] : '';
											$fecha_registro = isset($usuario['fecha_registro']) ? $usuario['fecha_registro'] : date('Y-m-d H:i:s');
										?>
										<tr>
											<td>
												<div class="d-flex align-items-center">
													<div class="user-avatar me-3">
														<?php echo strtoupper(substr($usuario['username'], 0, 2)); ?>
													</div>
													<div>
														<strong><?php echo htmlspecialchars($usuario['username']); ?></strong>
														<?php if ($usuario['id'] == $_SESSION['user_id']): ?>
															<span class="badge bg-info ms-1">Tú</span>
														<?php endif; ?>
													</div>
												</div>
											</td>
											<td><?php echo htmlspecialchars($usuario['nombre_completo']); ?></td>
											<td><?php echo htmlspecialchars($email); ?></td>
											<td>
												<span class="badge bg-<?php echo ($usuario['rol'] === 'admin') ? 'danger' : (($usuario['rol'] === 'gestor') ? 'warning' : 'primary'); ?>">
													<?php echo ucfirst($usuario['rol']); ?>
												</span>
											</td>
											<td>
												<span class="badge <?php echo $activo ? 'badge-activo' : 'badge-inactivo'; ?>">
													<?php echo $activo ? 'Activo' : 'Inactivo'; ?>
												</span>
											</td>
											<td><?php echo date('d/m/Y H:i', strtotime($fecha_registro)); ?></td>
											<td class="table-actions">
												<div class="btn-group btn-group-sm">
													<button class="btn btn-outline-primary" 
															onclick="editarUsuario(<?php echo $usuario['id']; ?>)"
															title="Editar usuario">
														<i class="fas fa-edit"></i>
													</button>
													<button class="btn btn-outline-warning" 
															onclick="cambiarPassword(<?php echo $usuario['id']; ?>, '<?php echo htmlspecialchars($usuario['username']); ?>')"
															title="Cambiar contraseña" hidden>
														<i class="fas fa-key"></i>
													</button>
													<button class="btn btn-outline-info" 
															onclick="resetearPassword(<?php echo $usuario['id']; ?>, '<?php echo htmlspecialchars($usuario['username']); ?>')"
															title="Resetear contraseña (generar temporal)">
														<i class="fas fa-key"></i>
													</button>
													<?php if ($usuario['id'] != $_SESSION['user_id']): ?>
													<button class="btn btn-outline-danger" 
															onclick="eliminarUsuario(<?php echo $usuario['id']; ?>, '<?php echo htmlspecialchars($usuario['username']); ?>')"
															title="Eliminar usuario">
														<i class="fas fa-trash"></i>
													</button>
													<?php endif; ?>
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

    <!-- Modales -->
   <!-- <?php include 'includes/modals_usuarios.php'; ?>-->
       <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script_usuarios.js"></script>
   <script>
// Script de diagnóstico - Verificar que todos los elementos existan
document.addEventListener('DOMContentLoaded', function() {
    console.log('=== DIAGNÓSTICO DE ELEMENTOS ===');
    
    const elementosRequeridos = [
        // Modal Editar Usuario
        'editar_user_id', 'editar_username', 'editar_nombre_completo', 
        'editar_email', 'editar_rol', 'editar_activo', 'editarUsuarioModal',
        
        // Modal Cambiar Password
        'password_user_id', 'password-username', 'cambiarPasswordModal',
        
        // Modal Eliminar Usuario
        'eliminar_user_id', 'eliminar-username', 'eliminarUsuarioModal',
        'confirmar_eliminacion', 'btnEliminarUsuario'
    ];
    
    let elementosFaltantes = [];
    
    elementosRequeridos.forEach(id => {
        const elemento = document.getElementById(id);
        if (elemento) {
            console.log('✅', id);
        } else {
            console.log('❌', id, 'NO ENCONTRADO');
            elementosFaltantes.push(id);
        }
    });
    
    if (elementosFaltantes.length > 0) {
        console.warn('Elementos faltantes:', elementosFaltantes);
    } else {
        console.log('🎉 Todos los elementos están presentes');
    }
    
    // Verificar que Bootstrap esté cargado
    if (typeof bootstrap === 'undefined') {
        console.error('❌ Bootstrap no está cargado');
    } else {
        console.log('✅ Bootstrap cargado correctamente');
    }
});
</script>
<script>
// Función para resetear contraseña
function resetearPassword(userId, username) {
    console.log('resetearPassword llamado con:', userId, username);
    
    // Crear el modal si no existe
    let modalElement = document.getElementById('resetearPasswordModal');
    
    // Si el modal no existe, crearlo dinámicamente
    if (!modalElement) {
        console.log('Creando modal dinámicamente...');
        modalElement = document.createElement('div');
        modalElement.className = 'modal fade';
        modalElement.id = 'resetearPasswordModal';
        modalElement.setAttribute('tabindex', '-1');
        modalElement.setAttribute('aria-labelledby', 'resetearPasswordModalLabel');
        modalElement.setAttribute('aria-hidden', 'true');
        modalElement.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title" id="resetearPasswordModalLabel">
                            <i class="fas fa-sync-alt me-2"></i>Resetear Contraseña
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="gestion_usuarios.php" id="resetearPasswordForm">
                        <div class="modal-body">
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>¡Atención!</strong> Esta acción generará una contraseña temporal de 8 caracteres.
                            </div>
                            <p>¿Estás seguro de que deseas resetear la contraseña del siguiente usuario?</p>
                            <div class="card bg-light mb-3">
                                <div class="card-body">
                                    <strong>Usuario:</strong> <span id="resetear-username"></span><br>
                                    <input type="hidden" id="resetear_user_id" name="user_id">
                                    <input type="hidden" name="username" id="resetear_username_input">
                                </div>
                            </div>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <small>Se generará una contraseña aleatoria que será mostrada después de confirmar la operación.</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-2"></i>Cancelar
                            </button>
                            <button type="submit" name="resetear_password" class="btn btn-info" id="confirmar_reseteo">
                                <i class="fas fa-sync-alt me-2"></i>Resetear Contraseña
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        `;
        document.body.appendChild(modalElement);
    }
    
    // Asignar valores a los campos del modal
    const resetearUserId = document.getElementById('resetear_user_id');
    const resetearUsername = document.getElementById('resetear-username');
    const resetearUsernameInput = document.getElementById('resetear_username_input');
    
    if (resetearUserId && resetearUsername && resetearUsernameInput) {
        resetearUserId.value = userId;
        resetearUsername.textContent = username || 'Usuario';
        resetearUsernameInput.value = username || '';
        console.log('Valores asignados correctamente');
    } else {
        console.error('No se pudieron encontrar los elementos del modal');
        // Crear los elementos si no existen
        if (!resetearUserId) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.id = 'resetear_user_id';
            input.name = 'user_id';
            input.value = userId;
            document.getElementById('resetearPasswordForm')?.appendChild(input);
        }
    }
    
    // Mostrar el modal usando Bootstrap
    if (typeof bootstrap !== 'undefined') {
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
    } else {
        console.error('Bootstrap no está cargado');
        // Fallback: mostrar confirmación nativa
        if (confirm(`¿Resetear contraseña para el usuario "${username}"?`)) {
            const form = document.getElementById('resetearPasswordForm');
            if (form) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'resetear_password';
                input.value = '1';
                form.appendChild(input);
                form.submit();
            }
        }
    }
}

// Función para verificar que todo esté funcionando
document.addEventListener('DOMContentLoaded', function() {
    console.log('Script de resetearPassword cargado correctamente');
    console.log('Función resetearPassword disponible:', typeof resetearPassword === 'function');
    
    // Verificar botones de resetear en la tabla
    const botonesReset = document.querySelectorAll('button[title="Resetear contraseña (generar temporal)"]');
    console.log('Botones de resetear encontrados:', botonesReset.length);
});
</script>



</body>
<footer class="text-center py-4">Patricio Corrotea | Gestión de la Demanda</footer>
</html>