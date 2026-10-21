<?php
// backend/email_helper.php

// Cargar variables del .env si no fueron cargadas previamente
if (!getenv('EMAIL_API_KEY') && file_exists(__DIR__ . '/../.env')) {
    $lineas = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lineas as $linea) {
        $linea = trim($linea);
        if (!empty($linea) && strpos($linea, '#') !== 0 && strpos($linea, '=') !== false) {
            list($clave, $valor) = explode('=', $linea, 2);
            putenv(trim($clave) . '=' . trim($valor, " \t\n\r\0\x0B\"'"));
        }
    }
}

function enviarMailConfirmacion($emailDestino, $nombreCliente, $nombreNegocio, $fechaTurno, $horaTurno, $codigoCancelacion) {
    // Ahora getenv() sí podrá leer el valor guardado en el .env
    $apiKey = getenv('EMAIL_API_KEY');

    if (empty($apiKey)) {
        error_log("Error Email: API Key no encontrada en el .env");
        return false;
    }

    $url = 'https://api.resend.com/emails';

    $protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $urlCancelar = $protocolo . "://" . $host . "/WebApp/frontend/cancelar.php?codigo=" . urlencode($codigoCancelacion);

    $htmlContent = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 8px;'>
            <h2 style='color: #1E3A8A; text-align: center;'>¡Reserva Confirmada en {$nombreNegocio}!</h2>
            <p>Hola <strong>{$nombreCliente}</strong>,</p>
            <p>Tu turno ha sido solicitado y registrado con éxito. A continuación los detalles:</p>
            
            <div style='background-color: #f3f4f6; padding: 15px; border-radius: 6px; margin: 20px 0;'>
                <p style='margin: 5px 0;'><strong>Comercio:</strong> {$nombreNegocio}</p>
                <p style='margin: 5px 0;'><strong>Fecha:</strong> {$fechaTurno}</p>
                <p style='margin: 5px 0;'><strong>Hora:</strong> {$horaTurno} hs</p>
            </div>

            <div style='background-color: #ecfdf5; border: 1px dashed #10b981; padding: 15px; border-radius: 6px; text-align: center; margin-bottom: 20px;'>
                <span style='font-size: 0.9rem; color: #065f46;'>Tu Código de Cancelación es:</span><br>
                <strong style='font-size: 1.8rem; letter-spacing: 2px; color: #1E3A8A;'>{$codigoCancelacion}</strong>
            </div>

            <div style='text-align: center; margin-top: 25px;'>
                <a href='{$urlCancelar}' style='background-color: #dc2626; color: #ffffff; padding: 10px 20px; border-radius: 5px; text-decoration: none; font-weight: bold; display: inline-block;'>
                    Cancelar mi Turno
                </a>
            </div>
        </div>
    ";

    $payload = array(
        'from'    => 'Agendify <onboarding@resend.dev>',
        'to'      => array($emailDestino),
        'subject' => "Confirmación de Turno - {$nombreNegocio}",
        'html'    => $htmlContent
    );

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Bearer ' . trim($apiKey),
        'Content-Type: application/json'
    ));

    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($httpCode === 200 || $httpCode === 201);
}