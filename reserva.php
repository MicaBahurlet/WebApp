<?php
// reserva.php
require_once 'conexion.php';

$link_unico =$_GET['link'] ?? '';
$mensaje = '';$tipo_mensaje = '';

// 1. BUSCAR EL NEGOCIO
$stmtNegocio =$pdo->prepare("SELECT * FROM negocios WHERE link_unico = :link AND estado_cuenta = 'activa'");
$stmtNegocio->execute(['link' =>$link_unico]);
$negocio =$stmtNegocio->fetch();

if (!$negocio) {
    die("<h2 style='text-align:center;color:red;'>El negocio o servicio especificado no existe o no se encuentra activo.</h2>");
}

// 2. ENDPOINT AJAX PARA HORARIOS OCUPADOS
if (isset($_GET['action']) && $_GET['action'] === 'obtener_ocupados' && isset($_GET['fecha'])) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    
    $fechaSel =$_GET['fecha'];

    $stmtOcupados =$pdo->prepare("
        SELECT DATE_FORMAT(fecha_hora_inicio, '%H:%i') as hora 
        FROM turnos 
        WHERE id_negocio = :id_negocio 
        AND estado != 'cancelado' 
        AND DATE(fecha_hora_inicio) = :fecha
    ");
    $stmtOcupados->execute([
        'id_negocio' => $negocio['id_negocio'],
        'fecha' => $fechaSel
    ]);

    $horasOcupadas =$stmtOcupados->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode($horasOcupadas ? $horasOcupadas : []);
    exit();
}

// 3. CARGAR SERVICIOS DEL NEGOCIO (¡AQUÍ ESTABA EL DETALLE!)
$stmtServicios =$pdo->prepare("SELECT * FROM servicios WHERE id_negocio = :id_negocio AND estado = 1");
$stmtServicios->execute(['id_negocio' =>$negocio['id_negocio']]);
$servicios =$stmtServicios->fetchAll();

// 4. PROCESAR RESERVA
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_servicio = intval($_POST['id_servicio']);
    $nombre_cliente = trim($_POST['nombre_cliente_final']);
    $telefono_cliente = trim($_POST['telefono_cliente_final']);
    $fecha =$_POST['fecha'];
    $hora =$_POST['hora'];

    if (!empty($id_servicio) && !empty($nombre_cliente) && !empty($telefono_cliente) && !empty($fecha) && !empty($hora)) {
        
        $stmtServicioSel =$pdo->prepare("SELECT duracion_minutos FROM servicios WHERE id_servicio = :id_servicio AND id_negocio = :id_negocio");
        $stmtServicioSel->execute(['id_servicio' => $id_servicio, 'id_negocio' =>$negocio['id_negocio']]);
        $servicioSel =$stmtServicioSel->fetch();

        if ($servicioSel) {$fecha_hora_inicio = date('Y-m-d H:i:s', strtotime("$fecha$hora"));
            $duracion = $servicioSel['duracion_minutos'];$fecha_hora_fin = date('Y-m-d H:i:s', strtotime("$fecha_hora_inicio +$duracion minutes"));

            // Validar si justo alguien reservó al mismo tiempo
            $stmtCheck =$pdo->prepare("
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
            
            if ($stmtCheck->fetchColumn() > 0) {$mensaje = "El horario seleccionado ya fue tomado por otro cliente. Por favor elige otro.";
                $tipo_mensaje = "error";
            } else {
                $stmtInsert =$pdo->prepare("
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

                $mensaje = "¡Tu turno ha sido solicitado con éxito!";
                $tipo_mensaje = "exito";
            }
        }
    } else {
        $mensaje = "Por favor completa todos los campos.";
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&family=PT+Sans:wght@400;700&display=swap" rel="stylesheet">
    <!-- Carga del CSS global unificado -->
    <link rel="stylesheet" href="assets/css/styles.css?v=1.2">
</head>
<body class="reserva-body">

    <div class="reserva-card">
        <h1><?php echo htmlspecialchars($negocio['nombre_completo']); ?></h1>
        <p class="reserva-subtitle">Rubro: <?php echo htmlspecialchars($negocio['rubro']); ?></p>

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?>" style="margin-bottom: 20px; border-radius: 8px;">
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
                <select name="hora" id="hora" required disabled>
                    <option value="">-- Primero selecciona una fecha --</option>
                </select>
            </div>

            <button type="submit" class="btn-submit-reserva">Confirmar Reserva</button>
        </form>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const inputFecha = document.getElementById('fecha');
    const selectHora = document.getElementById('hora');

    function generarHorariosBase() {
        const horarios = [];
        let hora = 8;
        let minuto = 0;

        while (hora < 20 || (hora === 20 && minuto === 0)) {
            const hStr = hora.toString().padStart(2, '0');
            const mStr = minuto.toString().padStart(2, '0');
            horarios.push(`${hStr}:${mStr}`);

            minuto += 30;
            if (minuto >= 60) {
                minuto = 0;
                hora += 1;
            }
        }
        return horarios;
    }

    if (inputFecha && selectHora) {
        inputFecha.addEventListener('change', function () {
            const fechaSeleccionada = this.value;
            if (!fechaSeleccionada) return;

            selectHora.disabled = true;
            selectHora.innerHTML = '<option value="">Cargando horarios disponibles...</option>';

            const linkParam = new URLSearchParams(window.location.search).get('link') || '<?php echo urlencode($link_unico); ?>';
            const requestUrl = `reserva.php?link=${encodeURIComponent(linkParam)}&action=obtener_ocupados&fecha=${encodeURIComponent(fechaSeleccionada)}`;

            fetch(requestUrl)
                .then(response => {
                    if (!response.ok) throw new Error('Error en el servidor');
                    return response.json();
                })
                .then(horasOcupadas => {
                    selectHora.innerHTML = '';

                    const defaultOption = document.createElement('option');
                    defaultOption.value = '';
                    defaultOption.textContent = '-- Selecciona un Horario --';
                    selectHora.appendChild(defaultOption);

                    const todosLosHorarios = generarHorariosBase();
                    let horariosDisponibles = 0;

                    todosLosHorarios.forEach(hora => {
                        if (!horasOcupadas.includes(hora)) {
                            const opt = document.createElement('option');
                            opt.value = hora;
                            opt.textContent = `${hora} hs`;
                            selectHora.appendChild(opt);
                            horariosDisponibles++;
                        }
                    });

                    if (horariosDisponibles === 0) {
                        selectHora.innerHTML = '<option value="">No hay horarios disponibles para esta fecha</option>';
                        selectHora.disabled = true;
                    } else {
                        selectHora.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error al consultar horarios:', error);
                    selectHora.innerHTML = '<option value="">Error al cargar horarios</option>';
                    selectHora.disabled = false;
                });
        });
    }
});
</script>
</body>
</html>