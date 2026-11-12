<?php
// backend/api_turnos.php
// API REST JSON para consumo desde Android
require_once __DIR__ . '/../config/conexion.php';

while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

function responder($codigo, $payload) {
    http_response_code($codigo);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}

function obtenerTurnoPorId($pdo, $idTurno) {
    $stmt = $pdo->prepare("
        SELECT t.*, s.nombre_servicio
        FROM turnos t
        LEFT JOIN servicios s ON s.id_servicio = t.id_servicio
        WHERE t.id_turno = :id
        LIMIT 1
    ");
    $stmt->execute(['id' => $idTurno]);
    return $stmt->fetch();
}

function parseIdQuery() {
    if (!isset($_GET['id'])) {
        return null;
    }
    if (!is_numeric($_GET['id'])) {
        return false;
    }
    $id = intval($_GET['id']);
    if ($id <= 0) {
        return false;
    }
    return $id;
}

function leerBody() {
    $raw = file_get_contents('php://input');
    $input = null;

    if ($raw !== false && trim($raw) !== '') {
        $input = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
    }

    if (!is_array($input) || empty($input)) {
        $input = $_POST;
    }

    return is_array($input) ? $input : [];
}

function horarioOcupado($pdo, $idNegocio, $inicio, $fin, $excluirId = null) {
    $sql = "
        SELECT COUNT(*) FROM turnos
        WHERE id_negocio = :id_negocio
          AND estado != 'cancelado'
          AND fecha_hora_inicio < :fin
          AND fecha_hora_fin > :inicio
    ";
    $params = [
        'id_negocio' => $idNegocio,
        'inicio' => $inicio,
        'fin' => $fin,
    ];

    if ($excluirId !== null) {
        $sql .= " AND id_turno != :excluir_id";
        $params['excluir_id'] = $excluirId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn() > 0;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET': {
            $id = parseIdQuery();

            if ($id === false) {
                responder(400, ['error' => 'El parámetro id debe ser un número entero mayor a 0']);
            }

            if ($id === null) {
                $stmt = $pdo->query("
                    SELECT t.*, s.nombre_servicio
                    FROM turnos t
                    LEFT JOIN servicios s ON s.id_servicio = t.id_servicio
                    ORDER BY t.fecha_hora_inicio DESC
                ");
                responder(200, $stmt->fetchAll());
            }

            $turno = obtenerTurnoPorId($pdo, $id);
            if (!$turno) {
                responder(404, ['error' => 'Turno no encontrado']);
            }
            responder(200, $turno);
        }

        case 'POST': {
            $input = leerBody();
            if ($input === false) {
                responder(400, ['error' => 'JSON malformado']);
            }

            $nombre = trim($input['nombre_cliente_final'] ?? '');
            $telefono = trim($input['telefono_cliente_final'] ?? '');
            $email = trim($input['email_cliente_final'] ?? '');
            $fecha = trim($input['fecha'] ?? '');
            $hora = trim($input['hora'] ?? '');
            $idNegocio = isset($input['id_negocio']) ? intval($input['id_negocio']) : 0;
            $idServicio = isset($input['id_servicio']) ? intval($input['id_servicio']) : 0;

            if ($nombre === '') {
                responder(400, ['error' => 'Falta el campo nombre_cliente_final']);
            }
            if ($telefono === '') {
                responder(400, ['error' => 'Falta el campo telefono_cliente_final']);
            }
            if ($email === '') {
                responder(400, ['error' => 'Falta el campo email_cliente_final']);
            }
            if ($fecha === '') {
                responder(400, ['error' => 'Falta el campo fecha']);
            }
            if ($hora === '') {
                responder(400, ['error' => 'Falta el campo hora']);
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                responder(422, ['error' => 'El email_cliente_final no tiene un formato válido']);
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
                responder(422, ['error' => 'La fecha debe tener formato YYYY-MM-DD']);
            }
            if (!preg_match('/^\d{2}:\d{2}$/', $hora)) {
                responder(422, ['error' => 'La hora debe tener formato HH:MM']);
            }

            $marcaFecha = strtotime($fecha . ' 12:00:00');
            if ($marcaFecha === false) {
                responder(422, ['error' => 'La fecha no es válida']);
            }

            // Fallback: primer negocio activo + su primer servicio activo
            if ($idNegocio <= 0) {
                $stmtNegocio = $pdo->query("
                    SELECT id_negocio FROM negocios
                    WHERE estado_cuenta = 'activa'
                    ORDER BY id_negocio ASC
                    LIMIT 1
                ");
                $negocioAuto = $stmtNegocio->fetch();
                if (!$negocioAuto) {
                    responder(422, ['error' => 'No hay un negocio activo disponible para asignar el turno']);
                }
                $idNegocio = intval($negocioAuto['id_negocio']);
            } else {
                $stmtNegocio = $pdo->prepare("
                    SELECT id_negocio FROM negocios
                    WHERE id_negocio = :id AND estado_cuenta = 'activa'
                    LIMIT 1
                ");
                $stmtNegocio->execute(['id' => $idNegocio]);
                if (!$stmtNegocio->fetch()) {
                    responder(422, ['error' => 'El id_negocio no existe o no está activo']);
                }
            }

            if ($idServicio <= 0) {
                $stmtServicio = $pdo->prepare("
                    SELECT id_servicio, duracion_minutos FROM servicios
                    WHERE id_negocio = :id_negocio AND estado = 1
                    ORDER BY id_servicio ASC
                    LIMIT 1
                ");
                $stmtServicio->execute(['id_negocio' => $idNegocio]);
                $servicio = $stmtServicio->fetch();
                if (!$servicio) {
                    responder(422, ['error' => 'No hay un servicio activo disponible para el negocio']);
                }
                $idServicio = intval($servicio['id_servicio']);
                $duracion = intval($servicio['duracion_minutos']);
            } else {
                $stmtServicio = $pdo->prepare("
                    SELECT id_servicio, duracion_minutos FROM servicios
                    WHERE id_servicio = :id_servicio AND id_negocio = :id_negocio AND estado = 1
                    LIMIT 1
                ");
                $stmtServicio->execute([
                    'id_servicio' => $idServicio,
                    'id_negocio' => $idNegocio,
                ]);
                $servicio = $stmtServicio->fetch();
                if (!$servicio) {
                    responder(422, ['error' => 'El id_servicio no existe, no está activo o no pertenece al negocio']);
                }
                $duracion = intval($servicio['duracion_minutos']);
            }

            $fechaHoraInicio = $fecha . ' ' . $hora . ':00';
            $fechaHoraFin = date('Y-m-d H:i:s', strtotime($fechaHoraInicio . ' +' . $duracion . ' minutes'));

            if (horarioOcupado($pdo, $idNegocio, $fechaHoraInicio, $fechaHoraFin)) {
                responder(422, ['error' => 'El horario seleccionado ya está ocupado']);
            }

            $codigo = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));

            $stmtInsert = $pdo->prepare("
                INSERT INTO turnos (
                    id_negocio, id_servicio, nombre_cliente_final, telefono_cliente_final,
                    email_cliente_final, fecha_hora_inicio, fecha_hora_fin, estado, codigo_cancelacion
                ) VALUES (
                    :id_negocio, :id_servicio, :nombre, :telefono,
                    :email, :inicio, :fin, 'pendiente', :codigo
                )
            ");
            $stmtInsert->execute([
                'id_negocio' => $idNegocio,
                'id_servicio' => $idServicio,
                'nombre' => $nombre,
                'telefono' => $telefono,
                'email' => $email,
                'inicio' => $fechaHoraInicio,
                'fin' => $fechaHoraFin,
                'codigo' => $codigo,
            ]);

            $nuevoId = intval($pdo->lastInsertId());
            $turnoCreado = obtenerTurnoPorId($pdo, $nuevoId);
            responder(201, $turnoCreado);
        }

        case 'PUT': {
            $id = parseIdQuery();
            if ($id === null || $id === false) {
                responder(400, ['error' => 'Falta un id válido en la query (?id=)']);
            }

            $turno = obtenerTurnoPorId($pdo, $id);
            if (!$turno) {
                responder(404, ['error' => 'Turno no encontrado']);
            }

            $input = leerBody();
            if ($input === false) {
                responder(400, ['error' => 'JSON malformado']);
            }

            $estado = isset($input['estado']) ? trim($input['estado']) : null;
            $fecha = isset($input['fecha']) ? trim($input['fecha']) : null;
            $hora = isset($input['hora']) ? trim($input['hora']) : null;

            if ($estado === null && $fecha === null && $hora === null) {
                responder(400, ['error' => 'Debes enviar estado y/o fecha y hora para actualizar']);
            }

            if (($fecha !== null && $hora === null) || ($hora !== null && $fecha === null)) {
                responder(400, ['error' => 'Para cambiar fecha/hora debés enviar ambos campos: fecha y hora']);
            }

            $campos = [];
            $params = ['id' => $id];

            if ($estado !== null) {
                if (!in_array($estado, ['pendiente', 'confirmado', 'cancelado'], true)) {
                    responder(422, ['error' => 'estado inválido (use pendiente, confirmado o cancelado)']);
                }
                $campos[] = 'estado = :estado';
                $params['estado'] = $estado;
            }

            if ($fecha !== null && $hora !== null) {
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
                    responder(422, ['error' => 'La fecha debe tener formato YYYY-MM-DD']);
                }
                if (!preg_match('/^\d{2}:\d{2}$/', $hora)) {
                    responder(422, ['error' => 'La hora debe tener formato HH:MM']);
                }

                $stmtDuracion = $pdo->prepare("
                    SELECT duracion_minutos FROM servicios
                    WHERE id_servicio = :id_servicio
                    LIMIT 1
                ");
                $stmtDuracion->execute(['id_servicio' => $turno['id_servicio']]);
                $servicio = $stmtDuracion->fetch();
                $duracion = $servicio ? intval($servicio['duracion_minutos']) : 30;

                $fechaHoraInicio = $fecha . ' ' . $hora . ':00';
                $fechaHoraFin = date('Y-m-d H:i:s', strtotime($fechaHoraInicio . ' +' . $duracion . ' minutes'));

                if (horarioOcupado($pdo, $turno['id_negocio'], $fechaHoraInicio, $fechaHoraFin, $id)) {
                    responder(422, ['error' => 'El horario seleccionado ya está ocupado']);
                }

                $campos[] = 'fecha_hora_inicio = :inicio';
                $campos[] = 'fecha_hora_fin = :fin';
                $params['inicio'] = $fechaHoraInicio;
                $params['fin'] = $fechaHoraFin;
            }

            $sql = 'UPDATE turnos SET ' . implode(', ', $campos) . ' WHERE id_turno = :id';
            $stmtUpdate = $pdo->prepare($sql);
            $stmtUpdate->execute($params);

            $turnoActualizado = obtenerTurnoPorId($pdo, $id);
            responder(200, $turnoActualizado);
        }

        case 'DELETE': {
            $id = parseIdQuery();
            if ($id === null || $id === false) {
                responder(400, ['error' => 'Falta un id válido en la query (?id=)']);
            }

            $turno = obtenerTurnoPorId($pdo, $id);
            if (!$turno) {
                responder(404, ['error' => 'Turno no encontrado']);
            }

            // Soft-delete alineado con cancelar.php: marca estado cancelado
            $stmtCancel = $pdo->prepare("UPDATE turnos SET estado = 'cancelado' WHERE id_turno = :id");
            $stmtCancel->execute(['id' => $id]);

            responder(200, ['mensaje' => 'Turno eliminado/cancelado correctamente']);
        }

        default:
            responder(405, ['error' => 'Método no permitido']);
    }
} catch (PDOException $e) {
    error_log('api_turnos.php: ' . $e->getMessage());
    responder(500, ['error' => 'Error interno de base de datos']);
}
