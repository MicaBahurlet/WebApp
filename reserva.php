<?php
// reserva.php
require_once 'conexion.php';

$link_unico = $_GET['link'] ?? '';
$mensaje = '';
$tipo_mensaje = '';

// Buscar el negocio correspondiente al link único
$stmtNegocio = $pdo->prepare("SELECT * FROM negocios WHERE link_unico = :link AND estado_cuenta = 'activa'");
$stmtNegocio->execute(['link' => $link_unico]);
$negocio = $stmtNegocio->fetch();

if (!$negocio) {
    die("<h2 style='text-align:center;color:red;'>El negocio o servicio especificado no existe o no se encuentra activo.</h2>");
}

// Cargar servicios del negocio
$stmtServicios = $pdo->prepare("SELECT * FROM servicios WHERE id_negocio = :id_negocio AND estado = 1");
$stmtServicios->execute(['id_negocio' => $negocio['id_negocio']]);
$servicios = $stmtServicios->fetchAll();

// Procesar solicitud de reserva
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_servicio = intval($_POST['id_servicio']);
    $nombre_cliente = trim($_POST['nombre_cliente_final']);
    $telefono_cliente = trim($_POST['telefono_cliente_final']);
    $fecha = $_POST['fecha'];
    $hora = $_POST['hora'];

    if (!empty($id_servicio) && !empty($nombre_cliente) && !empty($telefono_cliente) && !empty($fecha) && !empty($hora)) {
        
        // Obtener la duración del servicio seleccionado
        $stmtServicioSel = $pdo->prepare("SELECT duracion_minutos FROM servicios WHERE id_servicio = :id_servicio AND id_negocio = :id_negocio");
        $stmtServicioSel->execute(['id_servicio' => $id_servicio, 'id_negocio' => $negocio['id_negocio']]);
        $servicioSel = $stmtServicioSel->fetch();

        if ($servicioSel) {
            $fecha_hora_inicio = date('Y-m-d H:i:s', strtotime("$fecha $hora"));
            $duracion = $servicioSel['duracion_minutos'];
            $fecha_hora_fin = date('Y-m-d H:i:s', strtotime("$fecha_hora_inicio + $duracion minutes"));

            // Validar superposición de turnos ocupados
            $stmtCheck = $pdo->prepare("
                SELECT COUNT(*) FROM turnos 
                WHERE id_negocio = :id_negocio 
                AND estado != 'cancelado' 
                AND ((fecha_hora_inicio < :fin AND fecha_hora_fin > :inicio))
            ");
            $stmtCheck->execute([
                'id_negocio' => $negocio['id_negocio'],
                'inicio' => $fecha_hora_inicio,
                'fin' => $fecha_hora_fin
            ]);
            
            if ($stmtCheck->fetchColumn() > 0) {
                $mensaje = "El horario seleccionado ya se encuentra reservado. Por favor, elige otro.";
                $tipo_mensaje = "error";
            } else {
                // Registrar el turno
                $stmtInsert = $pdo->prepare("
                    INSERT INTO turnos (id_negocio, id_servicio, nombre_cliente_final, telefono_cliente_final, fecha_hora_inicio, fecha_hora_fin, estado) 
                    VALUES (:id_negocio, :id_servicio, :nombre, :telefono, :inicio, :fin, 'pendiente')
                ");
                $stmtInsert->execute([
                    'id_negocio' => $negocio['id_negocio'],
                    'id_servicio' => $id_servicio,
                    'nombre' => $nombre_cliente,
                    'telefono' => $telefono_cliente,
                    'inicio' => $fecha_hora_inicio,
                    'fin' => $fecha_hora_fin
                ]);

                $mensaje = "¡Tu turno ha sido solicitado con éxito! Nos comunicaremos a la brevedad.";
                $tipo_mensaje = "exito";
            }
        }
    } else {
        $mensaje = "Por favor completa todos los campos del formulario.";
        $tipo_mensaje = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reserva tu Turno - <?php echo htmlspecialchars($negocio['nombre_completo']); ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #eef2f3; margin: 0; padding: 20px; }
        .card { max-width: 500px; margin: 30px auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .card h1 { color: #1E3A8A; text-align: center; margin-bottom: 5px; }
        .card p.subtitle { text-align: center; color: #666; margin-bottom: 25px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; color: #333; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; }
        .btn-submit { width: 100%; background: #FF6B00; color: white; padding: 12px; border: none; border-radius: 6px; font-size: 16px; font-weight: bold; cursor: pointer; margin-top: 10px; }
        .alert { padding: 12px; border-radius: 6px; margin-bottom: 20px; text-align: center; }
        .alert-exito { background: #d1fae5; color: #065f46; }
        .alert-error { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <div class="card">
        <h1><?php echo htmlspecialchars($negocio['nombre_completo']); ?></h1>
        <p class="subtitle">Rubro: <?php echo htmlspecialchars($negocio['rubro']); ?></p>

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="id_servicio">Servicio Solicitado:</label>
                <select name="id_servicio" id="id_servicio" required>
                    <option value="">-- Selecciona un Servicio --</option>
                    <?php foreach ($servicios as $servicio): ?>
                        <option value="<?php echo $servicio['id_servicio']; ?>">
                            <?php echo htmlspecialchars($servicio['nombre_servicio']); ?> (<?php echo $servicio['duracion_minutos']; ?> min)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="nombre_cliente_final">Tu Nombre Completo:</label>
                <input type="text" name="nombre_cliente_final" id="nombre_cliente_final" required placeholder="Ej. Juan Pérez">
            </div>

            <div class="form-group">
                <label for="telefono_cliente_final">Teléfono / WhatsApp:</label>
                <input type="text" name="telefono_cliente_final" id="telefono_cliente_final" required placeholder="Ej. 1122334455">
            </div>

            <div class="form-group">
                <label for="fecha">Fecha del Turno:</label>
                <input type="date" name="fecha" id="fecha" min="<?php echo date('Y-m-d'); ?>" required>
            </div>

            <div class="form-group">
                <label for="hora">Hora:</label>
                <input type="time" name="hora" id="hora" required>
            </div>

            <button type="submit" class="btn-submit">Confirmar Reserva</button>
        </form>
    </div>
</body>
</html>