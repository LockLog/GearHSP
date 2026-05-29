<?php
require_once 'includes/auth.php';
$auth = new Auth();

if ($auth->isLoggedIn()) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
if ($_POST) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($auth->login($username, $password)) {
        header("Location: dashboard.php");
        exit;
    } else {
        $error = 'Usuario o contraseña incorrectos';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="img/favicon.png" type="image/png">
    <title>Gestión de Agenda y Registros | Gear-HSP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body, html {
            height: 100%;
            margin: 0;
            overflow: hidden;
        }
        
        .login-body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .split-container {
            display: flex;
            height: 100vh;
            width: 100vw;
        }
        
        .login-section {
            flex: 0 0 30%;
            background: rgba(255, 255, 255, 0.95);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            overflow-y: auto;
        }
        
        .carousel-section {
            flex: 0 0 70%;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 2rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .login-logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-logo img {
            max-width: 280px;
            margin-bottom: 1rem;
        }
        
        .login-logo h2 {
            color: #333;
            margin: 0.5rem 0;
        }
        
        .login-logo p {
            color: #666;
        }
        
        .carousel-container {
            width: 100%;
            max-width: 1200px;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .carousel-item img {
            width: 100%;
            height: 700px;
            object-fit: cover;
        }
        
        .carousel-caption {
            background: rgba(0, 0, 0, 0.6);
            padding: 1rem;
            border-radius: 10px;
            bottom: 20px;
            left: 20px;
            right: 20px;
        }
        
        .carousel-indicators {
            bottom: 10px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 0.75rem;
            font-weight: 600;
            transition: transform 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
        }
        
        @media (max-width: 992px) {
            .split-container {
                flex-direction: column;
            }
            
            .login-section, .carousel-section {
                flex: 0 0 100%;
                height: 50vh;
            }
            
            .carousel-container {
                max-width: 90%;
            }
            
            .carousel-item img {
                height: 300px;
            }
        }
    </style>
</head>
<body class="login-body">
    <div class="split-container">
        <!-- Sección Izquierda: Logo y Login -->
        <div class="login-section">
            <div class="login-container">
                <div class="login-logo">
                    <img src="img/logo.png" alt="Logo Gear" class="img-fluid">
                    <h2 class="fw-bold">Gear-HSP</h2>
                    <p class="text-muted">Ingrese sus credenciales</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST">
					<!--<div class="mb-3">
                            <i class="fas fa-exclamation-triangle" style="color: red;"></i>
							<span style="color: red;">Debido al corte de luz, Gear no estará disponible este miercoles 25-03-2026 entre 10 y 15 horas.</span>
                    </div>-->
                    <div class="mb-3">
                        <label for="username" class="form-label">
                            <i class="fas fa-user me-2"></i>Usuario
                        </label>
                        <input type="text" class="form-control" id="username" name="username" required 
                               placeholder="Ingrese su usuario">
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock me-2"></i>Contraseña
                        </label>
                        <input type="password" class="form-control" id="password" name="password" required 
                               placeholder="Ingrese su contraseña">
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                        <label class="form-check-label" for="remember">Recordarme</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión
                    </button>
                </form>
            </div>
        </div>

        <!-- Sección Derecha: Carrusel de Imágenes -->
        <div class="carousel-section">
            <div class="carousel-container">
                <div id="loginCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="3500">
                    <div class="carousel-indicators">
                        <button type="button" data-bs-target="#loginCarousel" data-bs-slide-to="0" class="active"></button>
                        <button type="button" data-bs-target="#loginCarousel" data-bs-slide-to="1"></button>
                        <button type="button" data-bs-target="#loginCarousel" data-bs-slide-to="2"></button>
                        <button type="button" data-bs-target="#loginCarousel" data-bs-slide-to="3"></button>
                    </div>
                    
                    <div class="carousel-inner">
                        <div class="carousel-item active">
                            <img src="img/conceptos.png" 
                                 alt="Gestión eficiente">
                            <div class="carousel-caption">
                                <h5>Gestión Eficiente</h5>
                                <p>Administra tu agenda y registros de manera sencilla</p>
                            </div>
                        </div>
                        <div class="carousel-item">
                            <img src="img/proceso.png"
                                 alt="Visualización Intuitiva">
                            <div class="carousel-caption">
                                <h5>Visualización Intuitiva</h5>
                                <p>Proceso de Confección</p>
                            </div>
                        </div>
                        <div class="carousel-item">
                            <img src="img/confeccion.png"
                                 alt="Registro ordenado">
                            <div class="carousel-caption">
                                <h5>Registro de Actividades</h5>
                                <p>Registro de ordenado por día y horario</p>
                            </div>
                        </div>
                        <div class="carousel-item">
                            <img src="img/dashboard.png"
                                 alt="Visualización Global del Establecimiento">
                            <div class="carousel-caption">
                                <h5>Detalle de Agendas</h5>
                                <p>Trabaja junto a tu equipo de manera coordinada</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Controles del carrusel (opcional) -->
                    <button class="carousel-control-prev" type="button" data-bs-target="#loginCarousel" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Anterior</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#loginCarousel" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Siguiente</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Focus en el campo de usuario al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
            
            // Iniciar el carrusel automáticamente
            const carousel = new bootstrap.Carousel(document.getElementById('loginCarousel'), {
                interval: 3000,
                wrap: true,
                ride: 'carousel'
            });
            
            // Pausar el carrusel cuando el usuario está escribiendo
            const inputs = document.querySelectorAll('#username, #password');
            inputs.forEach(input => {
                input.addEventListener('focus', () => {
                    carousel.pause();
                });
                
                input.addEventListener('blur', () => {
                    if (!document.activeElement.matches('#username, #password')) {
                        carousel.cycle();
                    }
                });
            });
        });
        
        // Enviar formulario con Enter
        document.getElementById('password').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.querySelector('form').submit();
            }
        });
        
        // Pausar/reanudar carrusel al interactuar con el formulario
        document.querySelector('form').addEventListener('mouseenter', function() {
            const carousel = bootstrap.Carousel.getInstance(document.getElementById('loginCarousel'));
            if (carousel) carousel.pause();
        });
        
        document.querySelector('form').addEventListener('mouseleave', function() {
            const carousel = bootstrap.Carousel.getInstance(document.getElementById('loginCarousel'));
            if (carousel && !document.querySelector('#username:focus, #password:focus')) {
                carousel.cycle();
            }
        });
    </script>
</body>
</html>

