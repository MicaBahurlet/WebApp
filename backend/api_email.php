<?php
// backend/api_email.php
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/email_helper.php';

while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');

// Leer payload JSON o parametros POST
$input = json_decode(file_get_contents('php://input'), true);

$emailDestino = $input['email'] ?? $_POST['email'] ?? '';
$nombreCliente = $input['nombre_cliente'] ?? $_POST['nombre_cliente'] ?? '';
$nombreNegocio = $input['nombre_negocio'] ?? $_POST['nombre_negocio'] ?? '';
$fechaTurno = $input['fecha'] ?? $_POST['fecha'] ?? '';
$horaTurno = $input['hora'] ?? $_POST['hora'] ?? '';
$codigoCancelacion = $input['codigo'] ?? $_POST['codigo'] ?? '';

if (empty($emailDestino) || empty($nombreCliente)) {
    echo json_encode(['status' => 'error', 'message' => 'Faltan parámetros requeridos']);
    exit();
}

$enviado = enviarMailConfirmacion(
    $emailDestino,
    $nombreCliente,
    $nombreNegocio,
    $fechaTurno,
    $horaTurno,
    $codigoCancelacion
);

if ($enviado) {
    echo json_encode(['status' => 'success', 'message' => 'Correo enviado exitosamente']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'No se pudo enviar el correo']);
}
exit();