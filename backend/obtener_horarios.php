<?php
// backend/obtener_horarios.php
require_once __DIR__ . '/../config/conexion.php';

// Limpiar cualquier búfer previa (evita espacios invisibles o warnings en la respuesta)
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');

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

// 1. Obtener datos de la consulta GET
$link_unico = $_GET['link'] ?? '';
$fecha = $_GET['fecha'] ?? '';
$idServicio = isset($_GET['id_servicio']) ? intval($_GET['id_servicio']) : 0;

// 2. Validar Negocio Activo
$stmtNegocio = $pdo->prepare("SELECT id_negocio FROM negocios WHERE link_unico = :link AND estado_cuenta = 'activa'");
$stmtNegocio->execute(array('link' => $link_unico));
$negocio = $stmtNegocio->fetch();

if (!$negocio) {
    echo json_encode(array());
    exit();
}

// 3. Obtener Duración del Servicio
$duracion = 30;
if ($idServicio > 0) {
    $stmtDuracion = $pdo->prepare("SELECT duracion_minutos FROM servicios WHERE id_servicio = :id_servicio AND id_negocio = :id_negocio AND estado = 1");
    $stmtDuracion->execute(array('id_servicio' => $idServicio, 'id_negocio' => $negocio['id_negocio']));
    $servicioDuracion = $stmtDuracion->fetch();
    if ($servicioDuracion) {
        $duracion = intval($servicioDuracion['duracion_minutos']);
    }
}

// 4. Retornar los horarios en formato JSON
$slots = slotsDisponibles($pdo, $negocio['id_negocio'], $fecha, $duracion);
echo json_encode($slots);
exit();