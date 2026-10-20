<?php
// reserva.php
require_once __DIR__ . '/../config/conexion.php';

$link_unico = isset($_GET['link']) ? $_GET['link'] : '';
$mensaje = '';
$tipo_mensaje = '';

// 1. BUSCAR EL NEGOCIO
$stmtNegocio = $pdo->prepare("SELECT * FROM negocios WHERE link_unico = :link AND estado_cuenta = 'activa'");
$stmtNegocio->execute(array('link' => $link_unico));
$negocio = $stmtNegocio->fetch();

if (!$negocio) {
    die("
        <div style='max-width:500px; margin:50px auto; text-align:center; font-family:sans-serif;'>
            <h2 style='color:#dc2626;'>El negocio o servicio especificado no existe o no se encuentra activo.</h2>
            <p><a href='index.php' style='color:#1E3A8A; font-weight:bold; text-decoration:none;'>← Volver al inicio</a></p>
        </div>
    ");
}

function minutosDelDia($hora) {
    $partes = explode(':', $hora);
    return intval($partes[0]) * 60 + intval($partes[1]);
}

function horaDesdeMinutos($minutos) {
    $horas = intval($minutos / 60);
    $resto = $minutos % 60;
    return sprintf('%02d:%02d', $horas, $resto);
}

function slotsDisponibles($pdo, $idNegocio, $fecha, $duracion) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        return array();
    }
    $marca = strtotime($fecha . ' 12:00:00');
    if ($marca === false || $fecha < date('Y-m-d')) {
        return array();
    }

    $dia = intval(date('N', $marca));
    $stmtRangos = $pdo->prepare("SELECT hora_inicio, hora_fin FROM horarios_trabajo WHERE id_negocio = :id_negocio AND dia_semana = :dia ORDER BY hora_inicio");
    $stmtRangos->execute(array('id_negocio' => $idNegocio, 'dia' => $dia));
    $rangos = $stmtRangos->fetchAll();
    if (!$rangos) {
        return array();
    }

    $stmtOcupados = $pdo->prepare("
        SELECT fecha_hora_inicio, fecha_hora_fin
        FROM turnos
        WHERE id_negocio = :id_negocio
        AND estado != 'cancelado'
        AND DATE(fecha_hora_inicio) = :fecha
    ");
    $stmtOcupados->execute(array('id_negocio' => $idNegocio, 'fecha' => $fecha));
    $ocupados = $stmtOcupados->fetchAll();

    $duracion = max(15, intval($duracion));
    $ahora = date('Y-m-d H:i:s');
    $slots = array();

    foreach ($rangos as $rango) {
        $desde = minutosDelDia($rango['hora_inicio']);
        $hasta = minutosDelDia($rango['hora_fin']);
        for ($minuto = $desde; $minuto + $duracion <= $hasta; $minuto += 30) {
            $hora = horaDesdeMinutos($minuto);
            $inicio = $fecha . ' ' . $hora . ':00';
            $fin = date('Y-m-d H:i:s', strtotime($inicio . ' +' . $duracion . ' minutes'));
            if ($inicio <= $ahora) {
                continue;
            }
            $choca = false;
            foreach ($ocupados as $ocupado) {
                if ($ocupado['fecha_hora_inicio'] < $fin && $ocupado['fecha_hora_fin'] > $inicio) {
                    $choca = true;
                    break;
                }
            }
            if (!$choca) {
                $slots[] = $hora;
            }
        }
    }

    return $slots;
}



// 2. CARGAR SERVICIOS DEL NEGOCIO
$stmtServicios = $pdo->prepare("SELECT * FROM servicios WHERE id_negocio = :id_negocio AND estado = 1");
$stmtServicios->execute(array('id_negocio' => $negocio['id_negocio']));
$servicios = $stmtServicios->fetchAll();

$stmtDias = $pdo->prepare("SELECT DISTINCT dia_semana FROM horarios_trabajo WHERE id_negocio = :id_negocio");
$stmtDias->execute(array('id_negocio' => $negocio['id_negocio']));
$diasAbiertos = array_map('intval', $stmtDias->fetchAll(PDO::FETCH_COLUMN));

// 3. PROCESAR RESERVA
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_servicio = intval($_POST['id_servicio']);
    $nombre_cliente = trim($_POST['nombre_cliente_final']);
    $telefono_cliente = trim($_POST['telefono_cliente_final']);
    $fecha = $_POST['fecha'];
    $hora = $_POST['hora'];

    if (!empty($id_servicio) && !empty($nombre_cliente) && !empty($telefono_cliente) && !empty($fecha) && !empty($hora)) {
        
        $stmtServicioSel = $pdo->prepare("SELECT duracion_minutos FROM servicios WHERE id_servicio = :id_servicio AND id_negocio = :id_negocio");
        $stmtServicioSel->execute(array('id_servicio' => $id_servicio, 'id_negocio' => $negocio['id_negocio']));
        $servicioSel = $stmtServicioSel->fetch();

        if ($servicioSel) {
            $duracion = intval($servicioSel['duracion_minutos']);
            $horasValidas = slotsDisponibles($pdo, $negocio['id_negocio'], $fecha, $duracion);
            if (!in_array($hora, $horasValidas, true)) {
                $mensaje = "Ese día u horario no está dentro de la atención del negocio.";
                $tipo_mensaje = "error";
            } else {
                $fecha_hora_inicio = $fecha . ' ' . $hora . ':00';
                $fecha_hora_fin = date('Y-m-d H:i:s', strtotime($fecha_hora_inicio . ' +' . $duracion . ' minutes'));

                $stmtCheck = $pdo->prepare("
                    SELECT COUNT(*) FROM turnos 
                    WHERE id_negocio = :id_negocio 
                    AND estado != 'cancelado' 
                    AND ((fecha_hora_inicio < :fin AND fecha_hora_fin > :inicio))
                ");
                $stmtCheck->execute(array(
                    'id_negocio' => $negocio['id_negocio'],
                    'inicio' => $fecha_hora_inicio,
                    'fin' => $fecha_hora_fin
                ));
                
                if ($stmtCheck->fetchColumn() > 0) {
                    $mensaje = "El horario seleccionado ya fue tomado por otro cliente. Por favor elige otro.";
                    $tipo_mensaje = "error";
                } else {
                    // GENERAR CÓDIGO ÚNICO DE CANCELACIÓN (6 caracteres)
                    $codigo_generado = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));

                    $stmtInsert = $pdo->prepare("
                        INSERT INTO turnos (id_negocio, id_servicio, nombre_cliente_final, telefono_cliente_final, fecha_hora_inicio, fecha_hora_fin, estado, codigo_cancelacion) 
                        VALUES (:id_negocio, :id_servicio, :nombre, :telefono, :inicio, :fin, 'pendiente', :codigo)
                    ");
                    $stmtInsert->execute(array(
                        'id_negocio' => $negocio['id_negocio'],
                        'id_servicio' => $id_servicio,
                        'nombre' => $nombre_cliente,
                        'telefono' => $telefono_cliente,
                        'inicio' => $fecha_hora_inicio,
                        'fin' => $fecha_hora_fin,
                        'codigo' => $codigo_generado
                    ));

                    $mensaje = "¡Tu turno ha sido solicitado con éxito!";
                    $tipo_mensaje = "exito";
                }
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
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body class="reserva-body">

    <div class="reserva-card">
        <a href="index.php" style="display: inline-block; margin-bottom: 15px; color: #1E3A8A; font-weight: 600; text-decoration: none;">← Volver al inicio</a>

        <h1><?php echo htmlspecialchars($negocio['nombre_completo']); ?></h1>
        <p class="reserva-subtitle">Rubro: <?php echo htmlspecialchars($negocio['rubro']); ?></p>

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?>" style="margin-bottom: 20px; border-radius: 8px; padding: 15px;">
                <p style="font-size: 1.1rem; margin: 0; font-weight: bold;"><?php echo htmlspecialchars($mensaje); ?></p>
                
                <?php if ($tipo_mensaje === 'exito' && !empty($codigo_generado)): ?>
                    <div style="margin-top: 15px; padding: 12px; background-color: #ffffff; border: 2px dashed #10b981; border-radius: 6px; text-align: center;">
                        <span style="display: block; font-size: 0.9rem; color: #374151;">Tu código de cancelación es:</span>
                        <strong style="font-size: 1.8rem; letter-spacing: 3px; color: #1E3A8A; display: block; margin: 5px 0;"><?php echo $codigo_generado; ?></strong>
                        <small style="color: #6b7280;">Guardá este código por si necesitás cancelar o modificar tu turno en <a href="cancelar.php" style="color: #1E3A8A; font-weight: bold;">cancelar.php</a>.</small>
                    </div>
                <?php endif; ?>
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
                <label for="fecha">Día del turno:</label>
                <?php if (empty($diasAbiertos)): ?>
                    <p class="calendario-vacio">El negocio todavía no publicó días de atención.</p>
                    <input type="hidden" name="fecha" id="fecha" value="">
                <?php else: ?>
                    <div id="calendario" class="calendario" data-dias="<?php echo htmlspecialchars(implode(',', $diasAbiertos)); ?>"></div>
                    <input type="hidden" name="fecha" id="fecha" value="" required>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="hora">Horario:</label>
                <select name="hora" id="hora" required disabled>
                    <option value="">-- Primero seleccioná un día --</option>
                </select>
            </div>

            <button type="submit" class="btn-submit-reserva">Confirmar Reserva</button>
        </form>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectServicio = document.getElementById('id_servicio');
    const selectFecha = document.getElementById('fecha');
    const selectHora = document.getElementById('hora');
    const calendario = document.getElementById('calendario');

    if (calendario && selectFecha) {
        const diasAbiertos = calendario.dataset.dias.split(',').map(Number);
        const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        const hoy = new Date();
        hoy.setHours(12, 0, 0, 0);
        const limite = new Date(hoy);
        limite.setDate(limite.getDate() + 27);
        let anio = hoy.getFullYear();
        let mes = hoy.getMonth();
        let elegido = '';

        function clave(fecha) {
            const mesTexto = String(fecha.getMonth() + 1).padStart(2, '0');
            const diaTexto = String(fecha.getDate()).padStart(2, '0');
            return fecha.getFullYear() + '-' + mesTexto + '-' + diaTexto;
        }

        function diaSemana(fecha) {
            const dia = fecha.getDay();
            return dia === 0 ? 7 : dia;
        }

        function pintar() {
            calendario.innerHTML = '';
            const barra = document.createElement('div');
            barra.className = 'calendario-barra';

            const anterior = document.createElement('button');
            anterior.type = 'button';
            anterior.className = 'calendario-nav';
            anterior.textContent = '‹';
            anterior.setAttribute('aria-label', 'Mes anterior');
            const primerDiaMes = new Date(anio, mes, 1, 12);
            anterior.disabled = primerDiaMes <= new Date(hoy.getFullYear(), hoy.getMonth(), 1, 12);

            const titulo = document.createElement('strong');
            titulo.textContent = meses[mes] + ' ' + anio;

            const siguiente = document.createElement('button');
            siguiente.type = 'button';
            siguiente.className = 'calendario-nav';
            siguiente.textContent = '›';
            siguiente.setAttribute('aria-label', 'Mes siguiente');
            const ultimoVisible = new Date(anio, mes + 1, 0, 12);
            siguiente.disabled = ultimoVisible >= limite;

            anterior.addEventListener('click', function () {
                mes -= 1;
                if (mes < 0) {
                    mes = 11;
                    anio -= 1;
                }
                pintar();
            });
            siguiente.addEventListener('click', function () {
                mes += 1;
                if (mes > 11) {
                    mes = 0;
                    anio += 1;
                }
                pintar();
            });

            barra.append(anterior, titulo, siguiente);
            calendario.appendChild(barra);

            const grilla = document.createElement('div');
            grilla.className = 'calendario-grilla';
            ['L', 'M', 'M', 'J', 'V', 'S', 'D'].forEach(function (letra) {
                const cabeza = document.createElement('span');
                cabeza.className = 'calendario-cabeza';
                cabeza.textContent = letra;
                grilla.appendChild(cabeza);
            });

            const inicio = new Date(anio, mes, 1, 12);
            const desplazamiento = diaSemana(inicio) - 1;
            for (let i = 0; i < desplazamiento; i++) {
                const vacio = document.createElement('span');
                grilla.appendChild(vacio);
            }

            const cantidad = new Date(anio, mes + 1, 0).getDate();
            for (let dia = 1; dia <= cantidad; dia++) {
                const fecha = new Date(anio, mes, dia, 12);
                const valor = clave(fecha);
                const abierto = diasAbiertos.indexOf(diaSemana(fecha)) !== -1 && fecha >= hoy && fecha <= limite;
                const celda = document.createElement('button');
                celda.type = 'button';
                celda.className = 'calendario-dia';
                celda.textContent = String(dia);
                if (!abierto) {
                    celda.disabled = true;
                }
                if (valor === elegido) {
                    celda.classList.add('is-elegido');
                }
                celda.addEventListener('click', function () {
                    elegido = valor;
                    selectFecha.value = valor;
                    selectFecha.dispatchEvent(new Event('change'));
                    pintar();
                });
                grilla.appendChild(celda);
            }

            calendario.appendChild(grilla);
        }

        pintar();
    }

    function cargarHorarios() {
        if (!selectFecha || !selectHora || selectFecha.disabled) return;

        const fechaSeleccionada = selectFecha.value;
        selectHora.innerHTML = '';

        if (!fechaSeleccionada) {
            selectHora.disabled = true;
            selectHora.innerHTML = '<option value="">-- Primero seleccioná un día --</option>';
            return;
        }

        selectHora.disabled = true;
        selectHora.innerHTML = '<option value="">Cargando horarios...</option>';

        const linkParam = new URLSearchParams(window.location.search).get('link') || '<?php echo htmlspecialchars($link_unico, ENT_QUOTES); ?>';
        const servicio = selectServicio ? selectServicio.value : '';
        const requestUrl = `../backend/obtener_horarios.php?link=${encodeURIComponent(linkParam)}&fecha=${encodeURIComponent(fechaSeleccionada)}&id_servicio=${encodeURIComponent(servicio)}`;        fetch(requestUrl)
            .then(response => {
                if (!response.ok) throw new Error('Error en el servidor');
                return response.json();
            })
            .then(horarios => {
                selectHora.innerHTML = '';
                const vacio = document.createElement('option');
                vacio.value = '';
                vacio.textContent = '-- Seleccioná un horario --';
                selectHora.appendChild(vacio);

                horarios.forEach(hora => {
                    const opcion = document.createElement('option');
                    opcion.value = hora;
                    opcion.textContent = hora + ' hs';
                    selectHora.appendChild(opcion);
                });

                if (horarios.length === 0) {
                    selectHora.innerHTML = '<option value="">No hay horarios libres ese día</option>';
                    selectHora.disabled = true;
                } else {
                    selectHora.disabled = false;
                }
            })
            .catch(() => {
                selectHora.innerHTML = '<option value="">Error al cargar horarios</option>';
                selectHora.disabled = true;
            });
    }

    if (selectFecha) {
        selectFecha.addEventListener('change', cargarHorarios);
    }
    if (selectServicio) {
        selectServicio.addEventListener('change', cargarHorarios);
    }
});
</script>
</body>
</html>