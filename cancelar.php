<?php
// cancelar.php
require_once 'conexion.php';

$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = strtoupper(trim($_POST['codigo_cancelacion'] ?? ''));

    if (!empty($codigo)) {
        // Buscar el turno correspondiente al código
        $stmt = $pdo->prepare("
            SELECT t.*, n.nombre_completo AS negocio 
            FROM turnos t 
            JOIN negocios n ON t.id_negocio = n.id_negocio 
            WHERE t.codigo_cancelacion = :codigo AND t.estado != 'cancelado'
        ");
        $stmt->execute(['codigo' => $codigo]);
        $turno = $stmt->fetch();

        if ($turno) {
            $ahora = new DateTime();
            $fechaTurno = new DateTime($turno['fecha_hora_inicio']);
            $diferenciaHoras = ($fechaTurno->getTimestamp() - $ahora->getTimestamp()) / 3600;

            // Regla de negocio: Al menos 24 hs de anticipación
            if ($diferenciaHoras >= 24) {
                $stmtCancel = $pdo->prepare("UPDATE turnos SET estado = 'cancelado' WHERE id_turno = :id");
                $stmtCancel->execute(['id' => $turno['id_turno']]);

                $mensaje = "Tu turno para " . htmlspecialchars($turno['negocio']) . " ha sido cancelado con éxito.";
                $tipo_mensaje = "exito";
            } else {
                $mensaje = "Las cancelaciones deben realizarse con al menos 24 horas de anticipación.";
                $tipo_mensaje = "error";
            }
        } else {
            $mensaje = "Código de cancelación inválido o el turno ya se encuentra cancelado.";
            $tipo_mensaje = "error";
        }
    } else {
        $mensaje = "Por favor ingresá un código válido.";
        $tipo_mensaje = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancelar Turno - Agendify</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&family=PT+Sans:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css?v=1.8">
</head>
<body class="reserva-body">

    <div class="reserva-card">
        <a href="index.php" style="display: inline-block; margin-bottom: 15px; color: #1E3A8A; font-weight: 600; text-decoration: none;">← Volver al inicio</a>

        <h1>Cancelar un Turno</h1>
        <p class="reserva-subtitle">Ingresá tu código de cancelación de 6 caracteres.</p>

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?>" style="margin-bottom: 20px; border-radius: 8px;">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="codigo_cancelacion">Código de Cancelación:</label>
                <input type="text" name="codigo_cancelacion" id="codigo_cancelacion" maxlength="6" required placeholder="Ej. A8X912" style="text-transform: uppercase; text-align: center; font-size: 1.2rem; letter-spacing: 2px;">
            </div>

            <button type="submit" class="btn-submit-reserva" style="background-color: #dc2626;">Confirmar Cancelación</button>
        </form>
    </div>

</body>
</html>