<?php


// index.php

// Email: admin@admin.com

// Contraseña: 123456789aA@

session_start();
require_once 'conexion.php';

$mensaje_login = '';
$mensaje_registro = '';

// 1. PROCESAR INICIO DE SESIÓN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (!empty($email) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM negocios WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $negocio = $stmt->fetch();

        if ($negocio && password_verify($password, $negocio['password_hash'])) {
            if ($negocio['estado_cuenta'] === 'activa') {
                $_SESSION['id_negocio'] = $negocio['id_negocio'];
                $_SESSION['nombre_negocio'] = $negocio['nombre_completo'];
                header("Location: dashboard_negocio.php");
                exit();
            } else {
                $mensaje_login = "Tu cuenta se encuentra suspendida.";
            }
        } else {
            $mensaje_login = "Email o contraseña incorrectos.";
        }
    } else {
        $mensaje_login = "Por favor completa todos los campos.";
    }
}

// 2. PROCESAR REGISTRO DE NUEVO NEGOCIO/CLIENTE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $nombre = trim($_POST['nombre']);
    $dni = trim($_POST['dni']);
    $email = trim($_POST['email']);
    $rubro = trim($_POST['rubro']);
    $password = $_POST['password'];

    if (!empty($nombre) && !empty($dni) && !empty($email) && !empty($password) && !empty($rubro)) {
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM negocios WHERE email = :email");
        $stmtCheck->execute(['email' => $email]);
        if ($stmtCheck->fetchColumn() > 0) {
            $mensaje_registro = "El email ya se encuentra registrado.";
        } else {
            $link_unico = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $nombre)) . '-' . rand(100, 999);
            $password_hash = password_hash($password, PASSWORD_BCRYPT);

            $stmtInsert = $pdo->prepare("
                INSERT INTO negocios (nombre_completo, dni, email, password_hash, rubro, link_unico, estado_cuenta) 
                VALUES (:nombre, :dni, :email, :password_hash, :rubro, :link_unico, 'activa')
            ");
            $success = $stmtInsert->execute([
                'nombre' => $nombre,
                'dni' => $dni,
                'email' => $email,
                'password_hash' => $password_hash,
                'rubro' => $rubro,
                'link_unico' => $link_unico
            ]);

            if ($success) {
                $id_nuevo_negocio = $pdo->lastInsertId();

                $stmtServicio = $pdo->prepare("
                    INSERT INTO servicios (id_negocio, nombre_servicio, duracion_minutos, estado) 
                    VALUES (:id_negocio, 'Consulta General', 30, 1)
                ");
                $stmtServicio->execute(['id_negocio' => $id_nuevo_negocio]);

                $_SESSION['id_negocio'] = $id_nuevo_negocio;
                $_SESSION['nombre_negocio'] = $nombre;
                header("Location: dashboard_negocio.php");
                exit();
            } else {
                $mensaje_registro = "Ocurrió un error al registrar la cuenta.";
            }
        }
    } else {
        $mensaje_registro = "Por favor completa todos los campos del registro.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Turnero Web App - Simple, Práctico & Organizado</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&family=PT+Sans:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css?v=1.7">
</head>
<body<?php echo $mensaje_login ? ' data-open-modal="loginModal"' : ($mensaje_registro ? ' data-open-modal="registerModal"' : ''); ?>>

    <header class="navbar">
        <div class="container nav-container">
            <a href="index.php" class="logo-link" aria-label="Agendify">
                <img src="assets/img/LogoWhite.svg" alt="Logo" width="160" height="47">
            </a>
            <button class="menu-toggle" type="button" onclick="toggleMobileMenu()" aria-label="Abrir menú">☰</button>
            <nav class="nav-links" id="navLinks">
                <a href="#como-funciona">¿Cómo funciona?</a>
                <a href="#que-ofrecemos">Producto</a>
                <a href="#contacto">Contacto</a>
            </nav>
        </div>
    </header>

    <?php if (!empty($mensaje_login)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($mensaje_login); ?></div>
    <?php endif; ?>
    <?php if (!empty($mensaje_registro)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($mensaje_registro); ?></div>
    <?php endif; ?>

    <section class="hero-section">
        <div class="container hero-container">
            <div class="hero-visual">
                <img src="assets/img/Registrarte.svg" alt="Calendario de turnos" width="426" height="438">
            </div>
            <div class="hero-content">
                <h1 class="hero-title"><span>Simple,</span><span>Práctico &amp;</span><span>Organizado</span></h1>
                <p class="hero-lead">Dar turnos para tu negocio,<br>no debería ser un problema</p>
                <div class="hero-buttons">
                    <button class="btn btn-accent" type="button" onclick="openModal('registerModal')">Registrate</button>
                    <button class="btn btn-outline" type="button" onclick="openModal('loginModal')">Inicia sesión</button>
                </div>
            </div>
        </div>
    </section>

    <section id="que-ofrecemos" class="section">
        <div class="container text-center">
            <h2 class="section-title">¿Qué ofrecemos?</h2>
            <p class="section-subtitle">Gestiona la agenda de tu comercio o servicio en 3 simples pasos.</p>
            <div class="features-grid">
                <article class="feature-card">
                    <div class="step-badge">1</div>
                    <p>Te registras ingresando los datos de tu negocio en la web app.</p>
                </article>
                <article class="feature-card">
                    <div class="step-badge">2</div>
                    <p>Personalizás tu tablero, configurás tus servicios y tus horarios de atención.</p>
                </article>
                <article class="feature-card">
                    <div class="step-badge">3</div>
                    <p>Cuando un cliente te escribe, le enviás tu link único de reserva directa.</p>
                </article>
            </div>
        </div>
    </section>

    <section id="como-funciona" class="section section-alt">
        <div class="container showcase-container">
            <div class="showcase-content">
                <h2>Simple para vos y simple para tu cliente</h2>
                <p>Tu agenda queda disponible las 24 horas. El cliente elige día, hora y servicio, sin idas y vueltas por mensaje.</p>
                <button class="btn btn-accent" type="button" onclick="openModal('registerModal')">Registrate</button>
            </div>
            <div class="showcase-box">
                <div class="dashboard-preview-card">
                    <div class="db-header">
                        <span class="dot"></span><span class="dot"></span><span class="dot"></span>
                        <span class="db-title">Panel de turnos</span>
                    </div>
                    <div class="db-body">
                        <div class="db-row"><span>09:00</span><span class="tag tag-blue">Juan Pérez · Limpieza</span></div>
                        <div class="db-row"><span>10:30</span><span class="tag tag-soft">Carlos S. · Consulta</span></div>
                        <div class="db-row"><span>11:15</span><span class="tag tag-green">Disponible</span></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="contacto" class="section">
        <div class="container contact-container">
            <div class="contact-info">
                <h2>Contactanos</h2>
                <p>¿Tenés alguna duda o querés una demo? Escribinos y te respondemos a la brevedad.</p>
            </div>
            <div class="contact-form-card">
                <h3>Formulario de contacto</h3>
                <form id="contactForm" onsubmit="alert('¡Mensaje enviado!'); return false;">
                    <div class="form-group">
                        <input type="text" placeholder="Nombre" required>
                    </div>
                    <div class="form-group">
                        <input type="email" placeholder="Email" required>
                    </div>
                    <div class="form-group">
                        <textarea placeholder="Escribí tu mensaje..." rows="4" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Enviar mensaje</button>
                </form>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container footer-container">
            <a href="index.php" class="footer-logo" aria-label="Agendify">
                <img src="assets/img/LogoWhite.svg" alt="Logo" width="150" height="44">
            </a>
            <nav class="footer-links">
                <a href="#como-funciona">¿Cómo funciona?</a>
                <a href="#que-ofrecemos">Producto</a>
                <a href="#">Deslinde de responsabilidad</a>
                <a href="#">Políticas de privacidad</a>
            </nav>
        </div>
    </footer>

    <div id="loginModal" class="modal">
        <div class="modal-content">
            <button class="close-modal" type="button" onclick="closeModal('loginModal')" aria-label="Cerrar">&times;</button>
            <h2>Iniciar sesión</h2>
            <form method="POST" action="index.php">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label>Email del negocio</label>
                    <input type="email" name="email" required placeholder="tu@negocio.com">
                </div>
                <div class="form-group">
                    <label>Contraseña</label>
                    <input type="password" name="password" required placeholder="••••••••">
                </div>
                <button type="submit" class="btn btn-accent btn-block">Ingresar al panel</button>
            </form>
        </div>
    </div>

    <div id="registerModal" class="modal">
        <div class="modal-content">
            <button class="close-modal" type="button" onclick="closeModal('registerModal')" aria-label="Cerrar">&times;</button>
            <h2>Registrar mi negocio</h2>
            <form method="POST" action="index.php">
                <input type="hidden" name="action" value="register">
                <div class="form-group">
                    <label>Nombre del negocio / profesional</label>
                    <input type="text" name="nombre" required placeholder="Ej: Consultorio Odontológico Dr. López">
                </div>
                <div class="form-group">
                    <label>DNI / CUIT del titular</label>
                    <input type="text" name="dni" required placeholder="Ej: 30123456789">
                </div>
                <div class="form-group">
                    <label>Rubro</label>
                    <input type="text" name="rubro" required placeholder="Ej: Odontología">
                </div>
                <div class="form-group">
                    <label>Email de contacto</label>
                    <input type="email" name="email" required placeholder="contacto@minegocio.com">
                </div>
                <div class="form-group">
                    <label>Contraseña</label>
                    <input type="password" name="password" required placeholder="Creá una contraseña">
                </div>
                <button type="submit" class="btn btn-accent btn-block">Crear cuenta</button>
            </form>
        </div>
    </div>

    <script src="assets/js/landing.js"></script>
</body>
</html>
