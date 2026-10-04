<?php
// dashboard_negocio.php
session_start();
require_once 'conexion.php';

if (!isset($_SESSION['id_negocio'])) {
    $_SESSION['id_negocio'] = 1; 
}

$id_negocio = $_SESSION['id_negocio'];

// Procesar actualización de estado del turno
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cambiar_estado') {
    $id_turno = intval($_POST['id_turno']);
    $nuevo_estado = $_POST['estado'];
    
    if (in_array($nuevo_estado, ['pendiente', 'confirmado', 'cancelado'])) {
        $stmt = $pdo->prepare("UPDATE turnos SET estado = :estado WHERE id_turno = :id_turno AND id_negocio = :id_negocio");
        $stmt->execute([
            'estado' => $nuevo_estado,
            'id_turno' => $id_turno,
            'id_negocio' => $id_negocio
        ]);
    }
    header("Location: dashboard_negocio.php");
    exit();
}

// Obtener datos del negocio
$stmtNegocio = $pdo->prepare("SELECT * FROM negocios WHERE id_negocio = :id_negocio");
$stmtNegocio->execute(['id_negocio' => $id_negocio]);
$negocio = $stmtNegocio->fetch();

// Obtener lista de turnos con información del servicio
$stmtTurnos = $pdo->prepare("
    SELECT t.*, s.nombre_servicio, s.duracion_minutos 
    FROM turnos t 
    JOIN servicios s ON t.id_servicio = s.id_servicio 
    WHERE t.id_negocio = :id_negocio 
    ORDER BY t.fecha_hora_inicio ASC
");
$stmtTurnos->execute(['id_negocio' => $id_negocio]);
$turnos = $stmtTurnos->fetchAll();

// Métricas para las tarjetas superiores (compatible con PHP 5.6+)
$totalTurnos = count($turnos);
$pendientes = 0;
$confirmados = 0;

foreach ($turnos as $t) {
    if ($t['estado'] === 'pendiente') {
        $pendientes++;
    } elseif ($t['estado'] === 'confirmado') {
        $confirmados++;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Gestión - <?php echo htmlspecialchars($negocio['nombre_completo'] ?? 'Mi Negocio'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&family=PT+Sans:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css?v=1.1">
</head>
<body class="dashboard-body">

    <!-- Navbar con diseño unificado -->
    <header class="navbar">
        <div class="container nav-container">
            <div class="logo-slot" aria-hidden="true">
                <span style="color:#ffffff; font-weight:700; font-family:'PT Sans', sans-serif; font-size:1.2rem;">Turnero App</span>
            </div>
            <nav class="nav-links">
                <span class="user-badge"><?php echo htmlspecialchars($negocio['nombre_completo'] ?? 'Mi Negocio'); ?></span>
                <a href="index.php" class="btn btn-outline-light btn-sm">Cerrar Sesión</a>
            </nav>
        </div>
    </header>

    <main class="container dashboard-main">
        <!-- Encabezado del Panel -->
        <div class="dashboard-header">
            <div>
                <h1 class="dash-title">Hola, <?php echo htmlspecialchars($negocio['nombre_completo'] ?? ''); ?> 👋</h1>
                <p class="dash-subtitle">Gestioná tus turnos y reservas en tiempo real.</p>
            </div>
            
            <!-- Caja destacada para el link de clientes -->
            <div class="link-share-card">
                <div class="link-info">
                    <span class="link-label">Tu enlace de reservas públicas:</span>
                    <a href="reserva.php?link=<?php echo urlencode($negocio['link_unico'] ?? ''); ?>" target="_blank" class="link-url">
                        reserva.php?link=<?php echo htmlspecialchars($negocio['link_unico'] ?? ''); ?>
                    </a>
                </div>
                <button class="btn btn-accent btn-sm" onclick="navigator.clipboard.writeText(window.location.origin + '/reserva.php?link=<?php echo urlencode($negocio['link_unico'] ?? ''); ?>'); alert('¡Link copiado!');">
                    Copiar Enlace
                </button>
            </div>
        </div>

        <!-- Tarjetas de Estadísticas -->
        <div class="metrics-grid">
            <div class="metric-card">
                <span class="metric-title">Total Turnos</span>
                <span class="metric-value"><?php echo $totalTurnos; ?></span>
            </div>
            <div class="metric-card border-orange">
                <span class="metric-title">Pendientes</span>
                <span class="metric-value text-orange"><?php echo $pendientes; ?></span>
            </div>
            <div class="metric-card border-green">
                <span class="metric-title">Confirmados</span>
                <span class="metric-value text-green"><?php echo $confirmados; ?></span>
            </div>
        </div>

        <!-- Listado de Turnos -->
        <section class="dash-section">
            <div class="section-header-flex">
                <h2>Agenda de Turnos</h2>
                <span class="turnos-count"><?php echo $totalTurnos; ?> registrados</span>
            </div>

            <?php if (empty($turnos)): ?>
                <div class="empty-state-card">
                    <p>No tenés turnos registrados por el momento.</p>
                    <small>Compartí tu link de reserva para empezar a recibir solicitudes.</small>
                </div>
            <?php else: ?>
                <div class="turnos-list">
                    <?php foreach ($turnos as $turno): ?>
                        <div class="turno-card">
                            <div class="turno-time-box">
                                <span class="time-date"><?php echo date('d/m/Y', strtotime($turno['fecha_hora_inicio'])); ?></span>
                                <span class="time-hour"><?php echo date('H:i', strtotime($turno['fecha_hora_inicio'])); ?> hs</span>
                            </div>

                            <div class="turno-details">
                                <h3 class="client-name"><?php echo htmlspecialchars($turno['nombre_cliente_final']); ?></h3>
                                <div class="client-meta">
                                    <span>📞 <?php echo htmlspecialchars($turno['telefono_cliente_final']); ?></span>
                                    <span class="meta-separator">•</span>
                                    <span>🛠️ <?php echo htmlspecialchars($turno['nombre_servicio']); ?> (<?php echo $turno['duracion_minutos']; ?> min)</span>
                                </div>
                            </div>

                            <div class="turno-actions">
                                <span class="badge tag-<?php echo $turno['estado']; ?>">
                                    <?php echo ucfirst($turno['estado']); ?>
                                </span>

                                <form method="POST" class="action-form">
                                    <input type="hidden" name="action" value="cambiar_estado">
                                    <input type="hidden" name="id_turno" value="<?php echo $turno['id_turno']; ?>">
                                    <select name="estado" class="form-select-subtle" onchange="this.form.submit()">
                                        <option value="pendiente" <?php echo $turno['estado'] === 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                                        <option value="confirmado" <?php echo $turno['estado'] === 'confirmado' ? 'selected' : ''; ?>>Confirmado</option>
                                        <option value="cancelado" <?php echo $turno['estado'] === 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                                    </select>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

</body>
</html>