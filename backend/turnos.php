<?php
// backend/turnos.php
// API JSON para la app Android: listado, detalle, servicios, negocio, alta y cambio de estado de turnos.
require_once __DIR__ . '/../config/conexion.php';

// Limpiar cualquier búfer previa (evita warnings o espacios en la respuesta JSON).
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');

function responder($datos, $codigo = 200) {
    http_response_code($codigo);
    echo json_encode($datos);
    exit();
}

function errorApi($mensaje, $codigo = 400) {
    responder(array('error' => $mensaje), $codigo);
}

function leerCuerpo() {
    $contenido = file_get_contents('php://input');
    if ($contenido === false || trim($contenido) === '') {
        return $_POST;
    }

    $json = json_decode($contenido, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
        return $json;
    }

    return $_POST;
}

function buscarNegocio($pdo, $link) {
    $stmt = $pdo->prepare("SELECT id_negocio, nombre_completo, rubro, link_unico FROM negocios WHERE link_unico = :link AND estado_cuenta = 'activa'");
    $stmt->execute(array('link' => $link));
    $negocio = $stmt->fetch();

    if (!$negocio) {
        errorApi('Negocio no encontrado o inactivo.', 404);
    }

    return $negocio;
}

function turnoPorId($pdo, $idNegocio, $idTurno) {
    $stmt = $pdo->prepare("
        SELECT t.id_turno, t.id_servicio, s.nombre_servicio, t.nombre_cliente_final,
               t.telefono_cliente_final, t.email_cliente_final, t.fecha_hora_inicio,
               t.fecha_hora_fin, t.estado, t.codigo_cancelacion
        FROM turnos t
        INNER JOIN servicios s ON s.id_servicio = t.id_servicio
        WHERE t.id_turno = :id_turno AND t.id_negocio = :id_negocio
    ");
    $stmt->execute(array('id_turno' => $idTurno, 'id_negocio' => $idNegocio));

    return $stmt->fetch();
}

$metodo = $_SERVER['REQUEST_METHOD'];
$link = isset($_GET['link']) ? trim($_GET['link']) : '';

if ($link === '') {
    errorApi('Falta el parámetro link.', 400);
}

$negocio = buscarNegocio($pdo, $link);
$idNegocio = intval($negocio['id_negocio']);

if ($metodo === 'GET') {
    if (!empty($_GET['servicios'])) {
        $stmt = $pdo->prepare("SELECT id_servicio, nombre_servicio, duracion_minutos FROM servicios WHERE id_negocio = :id_negocio AND estado = 1 ORDER BY nombre_servicio");
        $stmt->execute(array('id_negocio' => $idNegocio));
        responder($stmt->fetchAll());
    }

    if (!empty($_GET['negocio'])) {
        responder($negocio);
    }

    if (!empty($_GET['id'])) {
        $turno = turnoPorId($pdo, $idNegocio, intval($_GET['id']));
        if (!$turno) {
            errorApi('Turno no encontrado.', 404);
        }
        responder($turno);
    }

    $stmt = $pdo->prepare("
        SELECT t.id_turno, t.id_servicio, s.nombre_servicio, t.nombre_cliente_final,
               t.telefono_cliente_final, t.email_cliente_final, t.fecha_hora_inicio,
               t.fecha_hora_fin, t.estado, t.codigo_cancelacion
        FROM turnos t
        INNER JOIN servicios s ON s.id_servicio = t.id_servicio
        WHERE t.id_negocio = :id_negocio
        ORDER BY t.fecha_hora_inicio DESC
    ");
    $stmt->execute(array('id_negocio' => $idNegocio));
    responder($stmt->fetchAll());
}

if ($metodo === 'POST') {
    $datos = leerCuerpo();

    $idServicio = isset($datos['id_servicio']) ? intval($datos['id_servicio']) : 0;
    $nombre = trim($datos['nombre_cliente_final'] ?? '');
    $telefono = trim($datos['telefono_cliente_final'] ?? '');
    $email = trim($datos['email_cliente_final'] ?? '');
    $fecha = trim($datos['fecha'] ?? '');
    $hora = trim($datos['hora'] ?? '');

    if ($idServicio <= 0) {
        errorApi('Debe seleccionar un servicio.', 422);
    }
    if ($nombre === '') {
        errorApi('El nombre del cliente es obligatorio.', 422);
    }
    if ($telefono === '') {
        errorApi('El teléfono del cliente es obligatorio.', 422);
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        errorApi('El correo electrónico no es válido.', 422);
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        errorApi('La fecha debe tener formato YYYY-MM-DD.', 422);
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $hora)) {
        errorApi('La hora debe tener formato HH:MM.', 422);
    }

    list($anio, $mes, $dia) = array_map('intval', explode('-', $fecha));
    if (!checkdate($mes, $dia, $anio)) {
        errorApi('La fecha no es válida.', 422);
    }

    list($horas, $minutos) = array_map('intval', explode(':', $hora));
    if ($horas < 0 || $horas > 23 || $minutos < 0 || $minutos > 59) {
        errorApi('La hora no es válida.', 422);
    }

    $inicio = sprintf('%04d-%02d-%02d %02d:%02d:00', $anio, $mes, $dia, $horas, $minutos);
    $marca = strtotime($inicio);
    if ($marca === false) {
        errorApi('La fecha u hora no son válidas.', 422);
    }
    if ($marca <= time()) {
        errorApi('El turno debe ser en el futuro.', 422);
    }

    $stmtServicio = $pdo->prepare("SELECT duracion_minutos FROM servicios WHERE id_servicio = :id_servicio AND id_negocio = :id_negocio AND estado = 1");
    $stmtServicio->execute(array('id_servicio' => $idServicio, 'id_negocio' => $idNegocio));
    $servicio = $stmtServicio->fetch();

    if (!$servicio) {
        errorApi('El servicio no existe o está inactivo.', 422);
    }

    $duracion = max(15, intval($servicio['duracion_minutos']));
    $fin = date('Y-m-d H:i:s', strtotime($inicio . ' +' . $duracion . ' minutes'));

    $stmtChoque = $pdo->prepare("
        SELECT COUNT(*) FROM turnos
        WHERE id_negocio = :id_negocio
        AND estado != 'cancelado'
        AND fecha_hora_inicio < :fin
        AND fecha_hora_fin > :inicio
    ");
    $stmtChoque->execute(array('id_negocio' => $idNegocio, 'inicio' => $inicio, 'fin' => $fin));
    if (intval($stmtChoque->fetchColumn()) > 0) {
        errorApi('El horario seleccionado ya está ocupado.', 409);
    }

    $codigo = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));

    $stmtInsert = $pdo->prepare("
        INSERT INTO turnos (id_negocio, id_servicio, nombre_cliente_final, telefono_cliente_final,
                            email_cliente_final, fecha_hora_inicio, fecha_hora_fin, estado, codigo_cancelacion)
        VALUES (:id_negocio, :id_servicio, :nombre, :telefono, :email, :inicio, :fin, 'pendiente', :codigo)
    ");
    $stmtInsert->execute(array(
        'id_negocio' => $idNegocio,
        'id_servicio' => $idServicio,
        'nombre' => $nombre,
        'telefono' => $telefono,
        'email' => $email !== '' ? $email : null,
        'inicio' => $inicio,
        'fin' => $fin,
        'codigo' => $codigo
    ));

    $turno = turnoPorId($pdo, $idNegocio, intval($pdo->lastInsertId()));
    responder($turno, 201);
}

if ($metodo === 'PUT') {
    $datos = leerCuerpo();
    $idTurno = isset($_GET['id']) ? intval($_GET['id']) : intval($datos['id_turno'] ?? 0);
    $estado = trim($datos['estado'] ?? '');

    if ($idTurno <= 0) {
        errorApi('Falta el id del turno.', 422);
    }
    if (!in_array($estado, array('pendiente', 'confirmado', 'cancelado'), true)) {
        errorApi('Estado inválido. Use pendiente, confirmado o cancelado.', 422);
    }

    $turno = turnoPorId($pdo, $idNegocio, $idTurno);
    if (!$turno) {
        errorApi('Turno no encontrado.', 404);
    }

    $stmtUpdate = $pdo->prepare("UPDATE turnos SET estado = :estado WHERE id_turno = :id_turno AND id_negocio = :id_negocio");
    $stmtUpdate->execute(array('estado' => $estado, 'id_turno' => $idTurno, 'id_negocio' => $idNegocio));

    responder(turnoPorId($pdo, $idNegocio, $idTurno));
}

header('Allow: GET, POST, PUT');
errorApi('Método no permitido.', 405);
