<?php
// dashboard_negocio.php
session_start();
require_once 'conexion.php';

// Verificación de sesión simulada (reemplazar por tu flujo de login)
if (!isset($_SESSION['id_negocio'])) {
    // Para pruebas, asignamos un ID estático o redirigimos a login
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - <?php echo htmlspecialchars($negocio['nombre_completo'] ?? 'Mi Negocio'); ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f7f6; margin: 0; padding: 20px; }
        .container { max-width: 1100px; margin: 0 auto; background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1, h2 { color: #1E3A8A; }
        .link-box { background: #e0e7ff; padding: 12px; border-radius: 6px; margin-bottom: 20px; color: #1e40af; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #1E3A8A; color: white; }
        .badge { padding: 5px 10px; border-radius: 4px; font-weight: bold; font-size: 0.85em; text-transform: capitalize; }
        .badge-pendiente { background: #fef3c7; color: #92400e; }
        .badge-confirmado { background: #d1fae5; color: #065f46; }
        .badge-cancelado { background: #fee2e2; color: #991b1b; }
        .form-select { padding: 4px 8px; border-radius: 4px; }
        .btn-update { background: #FF6B00; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Bienvenido, <?php echo htmlspecialchars($negocio['nombre_completo'] ?? ''); ?></h1>
        <div class="link-box">
            <strong>Link de reserva para tus clientes:</strong> 
            <a href="reserva.php?link=<?php echo urlencode($negocio['link_unico'] ?? ''); ?>" target="_blank">
                reserva.php?link=<?php echo htmlspecialchars($negocio['link_unico'] ?? ''); ?>
            </a>
        </div>

        <h2>Lista de Clientes y Turnos Solicitados</h2>
        <table>
            <thead>
                <tr>
                    <th>Fecha y Hora</th>
                    <th>Cliente</th>
                    <th>Teléfono</th>
                    <th>Servicio</th>
                    <th>Estado</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($turnos)): ?>
                    <tr><td colspan="6" style="text-align:center;">No hay turnos registrados.</td></tr>
                <?php else: ?>
                    <?php foreach ($turnos as $turno): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i', strtotime($turno['fecha_hora_inicio'])); ?> hs</td>
                            <td><?php echo htmlspecialchars($turno['nombre_cliente_final']); ?></td>
                            <td><?php echo htmlspecialchars($turno['telefono_cliente_final']); ?></td>
                            <td><?php echo htmlspecialchars($turno['nombre_servicio']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $turno['estado']; ?>">
                                    <?php echo $turno['estado']; ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="cambiar_estado">
                                    <input type="hidden" name="id_turno" value="<?php echo $turno['id_turno']; ?>">
                                    <select name="estado" class="form-select">
                                        <option value="pendiente" <?php echo $turno['estado'] === 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                                        <option value="confirmado" <?php echo $turno['estado'] === 'confirmado' ? 'selected' : ''; ?>>Confirmado</option>
                                        <option value="cancelado" <?php echo $turno['estado'] === 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                                    </select>
                                    <button type="submit" class="btn-update">Guardar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>