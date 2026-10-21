<?php
// dashboard_negocio.php
session_start();
require_once __DIR__ . '/../config/conexion.php';

if (!isset($_SESSION['id_negocio'])) {
    header("Location: index.php");
    exit();
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

$diasSemana = [
    1 => 'Lunes',
    2 => 'Martes',
    3 => 'Miércoles',
    4 => 'Jueves',
    5 => 'Viernes',
    6 => 'Sábado',
    7 => 'Domingo',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'agregar_servicio') {
    $nombreServicio = trim($_POST['nombre_servicio'] ?? '');
    $duracion = intval($_POST['duracion_minutos'] ?? 0);

    if ($nombreServicio !== '' && strlen($nombreServicio) <= 100 && $duracion >= 15 && $duracion <= 240) {
        $stmt = $pdo->prepare("INSERT INTO servicios (id_negocio, nombre_servicio, duracion_minutos, estado) VALUES (:id_negocio, :nombre, :duracion, 1)");
        $stmt->execute([
            'id_negocio' => $id_negocio,
            'nombre' => $nombreServicio,
            'duracion' => $duracion,
        ]);
        $_SESSION['flash_dashboard'] = 'Servicio agregado.';
        $_SESSION['flash_dashboard_tipo'] = 'exito';
    } else {
        $_SESSION['flash_dashboard'] = 'Completá el nombre y una duración entre 15 y 240 minutos.';
        $_SESSION['flash_dashboard_tipo'] = 'error';
    }
    header("Location: dashboard_negocio.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'quitar_servicio') {
    $idServicio = intval($_POST['id_servicio'] ?? 0);
    $stmtServicio = $pdo->prepare("SELECT id_servicio FROM servicios WHERE id_servicio = :id_servicio AND id_negocio = :id_negocio");
    $stmtServicio->execute(['id_servicio' => $idServicio, 'id_negocio' => $id_negocio]);

    if ($stmtServicio->fetch()) {
        $stmtUso = $pdo->prepare("SELECT COUNT(*) FROM turnos WHERE id_servicio = :id_servicio");
        $stmtUso->execute(['id_servicio' => $idServicio]);
        if ($stmtUso->fetchColumn() > 0) {
            $stmt = $pdo->prepare("UPDATE servicios SET estado = 0 WHERE id_servicio = :id_servicio AND id_negocio = :id_negocio");
            $stmt->execute(['id_servicio' => $idServicio, 'id_negocio' => $id_negocio]);
            $_SESSION['flash_dashboard'] = 'El servicio quedó pausado porque ya tiene turnos.';
        } else {
            $stmt = $pdo->prepare("DELETE FROM servicios WHERE id_servicio = :id_servicio AND id_negocio = :id_negocio");
            $stmt->execute(['id_servicio' => $idServicio, 'id_negocio' => $id_negocio]);
            $_SESSION['flash_dashboard'] = 'Servicio eliminado.';
        }
        $_SESSION['flash_dashboard_tipo'] = 'exito';
    }
    header("Location: dashboard_negocio.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'activar_servicio') {
    $idServicio = intval($_POST['id_servicio'] ?? 0);
    $stmt = $pdo->prepare("UPDATE servicios SET estado = 1 WHERE id_servicio = :id_servicio AND id_negocio = :id_negocio");
    $stmt->execute(['id_servicio' => $idServicio, 'id_negocio' => $id_negocio]);
    $_SESSION['flash_dashboard'] = 'Servicio activado.';
    $_SESSION['flash_dashboard_tipo'] = 'exito';
    header("Location: dashboard_negocio.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'guardar_horarios') {
    $activos = $_POST['activo'] ?? [];
    $inicios = $_POST['inicio'] ?? [];
    $fines = $_POST['fin'] ?? [];
    $filas = [];
    $horarioInvalido = false;

    foreach ($activos as $dia) {
        $dia = intval($dia);
        if ($dia < 1 || $dia > 7) {
            continue;
        }
        $inicio = $inicios[$dia] ?? '';
        $fin = $fines[$dia] ?? '';
        if (!preg_match('/^\d{2}:\d{2}$/', $inicio) || !preg_match('/^\d{2}:\d{2}$/', $fin) || $inicio >= $fin) {
            $horarioInvalido = true;
            break;
        }
        $filas[] = [$dia, $inicio . ':00', $fin . ':00'];
    }

    if ($horarioInvalido) {
        $_SESSION['flash_dashboard'] = 'Revisá los horarios: la hora de fin tiene que ser posterior a la de inicio.';
        $_SESSION['flash_dashboard_tipo'] = 'error';
    } else {
        $pdo->beginTransaction();
        $stmtDelete = $pdo->prepare("DELETE FROM horarios_trabajo WHERE id_negocio = :id_negocio");
        $stmtDelete->execute(['id_negocio' => $id_negocio]);
        $stmtInsert = $pdo->prepare("INSERT INTO horarios_trabajo (id_negocio, dia_semana, hora_inicio, hora_fin) VALUES (:id_negocio, :dia, :inicio, :fin)");
        foreach ($filas as $fila) {
            $stmtInsert->execute([
                'id_negocio' => $id_negocio,
                'dia' => $fila[0],
                'inicio' => $fila[1],
                'fin' => $fila[2],
            ]);
        }
        $pdo->commit();
        $_SESSION['flash_dashboard'] = 'Días y horarios de atención guardados.';
        $_SESSION['flash_dashboard_tipo'] = 'exito';
    }
    header("Location: dashboard_negocio.php");
    exit();
}

$flash = $_SESSION['flash_dashboard'] ?? '';
$flashTipo = $_SESSION['flash_dashboard_tipo'] ?? 'exito';
unset($_SESSION['flash_dashboard'], $_SESSION['flash_dashboard_tipo']);

// Obtener datos del negocio
$stmtNegocio = $pdo->prepare("SELECT * FROM negocios WHERE id_negocio = :id_negocio");
$stmtNegocio->execute(['id_negocio' => $id_negocio]);
$negocio = $stmtNegocio->fetch();

// Obtener lista de turnos
$stmtTurnos = $pdo->prepare("
    SELECT t.*, s.nombre_servicio, s.duracion_minutos 
    FROM turnos t 
    JOIN servicios s ON t.id_servicio = s.id_servicio 
    WHERE t.id_negocio = :id_negocio 
    ORDER BY t.fecha_hora_inicio ASC
");
$stmtTurnos->execute(['id_negocio' => $id_negocio]);
$turnos = $stmtTurnos->fetchAll();

$stmtServicios = $pdo->prepare("SELECT * FROM servicios WHERE id_negocio = :id_negocio ORDER BY estado DESC, nombre_servicio ASC");
$stmtServicios->execute(['id_negocio' => $id_negocio]);
$servicios = $stmtServicios->fetchAll();

$stmtHorarios = $pdo->prepare("SELECT * FROM horarios_trabajo WHERE id_negocio = :id_negocio");
$stmtHorarios->execute(['id_negocio' => $id_negocio]);
$horariosPorDia = [];
foreach ($stmtHorarios->fetchAll() as $horario) {
    $horariosPorDia[intval($horario['dia_semana'])] = $horario;
}

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
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body class="dashboard-body">

    <header class="navbar">
        <div class="container nav-container">
            <a href="index.php" class="logo-link" aria-label="Agendify">
                <img src="../assets/img/LogoWhite.svg" alt="Logo" width="100" height="35">
            </a>
            <nav class="nav-links">
                <span class="user-badge"><?php echo htmlspecialchars($negocio['nombre_completo'] ?? 'Mi Negocio'); ?></span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Cerrar Sesión</a>
            </nav>
        </div>
    </header>

    <main class="container dashboard-main">
        <div class="dashboard-header">
            <div>
                <h1 class="dash-title">Hola, <?php echo htmlspecialchars($negocio['nombre_completo'] ?? ''); ?> 👋</h1>
                <p class="dash-subtitle">Gestioná tus turnos y reservas en tiempo real.</p>
            </div>
            
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

        <?php if ($flash !== ''): ?>
            <div class="dash-flash alert-<?php echo $flashTipo === 'error' ? 'error' : 'exito'; ?>">
                <?php echo htmlspecialchars($flash); ?>
            </div>
        <?php endif; ?>

        <?php
            $abrirServicios = $flash !== '' && stripos($flash, 'servicio') !== false;
            $abrirHorarios = $flash !== '' && stripos($flash, 'horario') !== false;
        ?>
        <div class="config-grid">
            <section class="dash-section panel-plegable">
                <details<?php echo $abrirServicios ? ' open' : ''; ?>>
                <summary class="section-header-flex">
                    <h2>Servicios</h2>
                    <span class="turnos-count"><?php echo count($servicios); ?></span>
                </summary>
                <p class="config-help">El servicio del registro ya está cargado. Sumá los demás que ofrece el negocio.</p>

                <?php if (empty($servicios)): ?>
                    <div class="empty-state-card">
                        <p>Todavía no hay servicios.</p>
                    </div>
                <?php else: ?>
                    <ul class="servicio-list">
                        <?php foreach ($servicios as $servicio): ?>
                            <li class="servicio-item">
                                <div>
                                    <strong><?php echo htmlspecialchars($servicio['nombre_servicio']); ?></strong>
                                    <span><?php echo intval($servicio['duracion_minutos']); ?> min</span>
                                    <?php if (intval($servicio['estado']) !== 1): ?>
                                        <span class="badge tag-cancelado">Pausado</span>
                                    <?php endif; ?>
                                </div>
                                <form method="POST">
                                    <input type="hidden" name="id_servicio" value="<?php echo intval($servicio['id_servicio']); ?>">
                                    <?php if (intval($servicio['estado']) === 1): ?>
                                        <input type="hidden" name="action" value="quitar_servicio">
                                        <button type="submit" class="btn btn-outline btn-sm">Quitar</button>
                                    <?php else: ?>
                                        <input type="hidden" name="action" value="activar_servicio">
                                        <button type="submit" class="btn btn-accent btn-sm">Activar</button>
                                    <?php endif; ?>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <form method="POST" class="config-form">
                    <input type="hidden" name="action" value="agregar_servicio">
                    <div class="form-group">
                        <label for="nombre_servicio">Nuevo servicio</label>
                        <input type="text" name="nombre_servicio" id="nombre_servicio" maxlength="100" required placeholder="Ej. Limpieza dental">
                    </div>
                    <div class="form-group">
                        <label for="duracion_minutos">Duración</label>
                        <select name="duracion_minutos" id="duracion_minutos" required>
                            <option value="15">15 min</option>
                            <option value="30" selected>30 min</option>
                            <option value="45">45 min</option>
                            <option value="60">60 min</option>
                            <option value="90">90 min</option>
                            <option value="120">120 min</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-accent">Agregar servicio</button>
                </form>
                </details>
            </section>

            <section class="dash-section panel-plegable">
                <details<?php echo $abrirHorarios ? ' open' : ''; ?>>
                <summary class="section-header-flex">
                    <h2>Días y horarios</h2>
                </summary>
                <p class="config-help">Marcá los días en los que se puede pedir turno y el rango de cada uno.</p>

                <form method="POST" class="config-form">
                    <input type="hidden" name="action" value="guardar_horarios">
                    <?php foreach ($diasSemana as $numero => $nombreDia): ?>
                        <?php
                            $horarioDia = $horariosPorDia[$numero] ?? null;
                            $inicioDia = $horarioDia ? substr($horarioDia['hora_inicio'], 0, 5) : '09:00';
                            $finDia = $horarioDia ? substr($horarioDia['hora_fin'], 0, 5) : '18:00';
                        ?>
                        <div class="horario-row">
                            <label class="horario-dia">
                                <input type="checkbox" name="activo[]" value="<?php echo $numero; ?>" <?php echo $horarioDia ? 'checked' : ''; ?>>
                                <?php echo $nombreDia; ?>
                            </label>
                            <input type="time" name="inicio[<?php echo $numero; ?>]" value="<?php echo htmlspecialchars($inicioDia); ?>" aria-label="Inicio <?php echo $nombreDia; ?>">
                            <input type="time" name="fin[<?php echo $numero; ?>]" value="<?php echo htmlspecialchars($finDia); ?>" aria-label="Fin <?php echo $nombreDia; ?>">
                        </div>
                    <?php endforeach; ?>
                    <button type="submit" class="btn btn-accent">Guardar horarios</button>
                </form>
                </details>
            </section>
        </div>

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