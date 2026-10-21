<?php
// backend/email_helper.php

function enviarMailConfirmacion($emailDestino, $nombreCliente, $nombreNegocio, $fechaTurno, $horaTurno, $codigoCancelacion) {
    // 1. Obtener la clave API de Brevo (prioriza constante global de conexion.php)
    $apiKey = defined('EMAIL_API_KEY') ? EMAIL_API_KEY : '';
    // Quitar comillas o espacios accidentales
    $apiKey = trim(str_replace(array("'", '"'), '', $apiKey));

    if (empty($apiKey) || strpos($apiKey, 'xkeysib-') === false) {
        echo "<div style='color:red;'><strong>Error:</strong> La API Key de Brevo no es válida o está vacía.</div>";
        return false;
    }

    $url = 'https://api.brevo.com/v3/smtp/email';

    // Generar la URL absoluta a cancelar.php
    $protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Adaptar ruta según sea local o InfinityFree
    $scriptPath = (strpos($_SERVER['REQUEST_URI'] ?? '', '/WebApp') !== false) ? '/WebApp/frontend/cancelar.php' : '/frontend/cancelar.php';
    $urlCancelar = $protocolo . "://" . $host . $scriptPath . "?codigo=" . urlencode($codigoCancelacion);

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

            <p style='font-size: 0.85rem; color: #6b7280; margin-top: 25px; text-align: center;'>
                Si no podés asistir, podés cancelar tu reserva ingresando a <a href='{$urlCancelar}' style='color: #1E3A8A;'>este enlace</a>.
            </p>
        </div>
    ";

    // ESTRUCTURA DE PAYLOAD EXIGIDA POR BREVO API V3
    $payload = array(
        'sender' => array(
            'name'  => 'Agendify',
            'email' => 'charly.cronomax@gmail.com' // IMPORTANTE: Debe ser la casilla que verificaste en Brevo
        ),
        'to' => array(
            array(
                'email' => $emailDestino,
                'name'  => $nombreCliente
            )
        ),
        'subject'     => "Confirmación de Turno - {$nombreNegocio}",
        'htmlContent' => $htmlContent
    );

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'api-key: ' . $apiKey,
        'Content-Type: application/json',
        'Accept: application/json'
    ));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Si Brevo no devuelve HTTP 201 (Created), imprimimos el error para corregirlo
    if ($httpCode !== 201 && $httpCode !== 200) {
        echo "<div style='background:#fee2e2; border:1px solid #ef4444; color:#b91c1c; padding:15px; margin:15px; border-radius:6px;'>";
        echo "<strong>Error API Brevo (HTTP $httpCode):</strong><br>";
        echo htmlspecialchars($response);
        if (!empty($curlError)) {
            echo "<br><strong>cURL Error:</strong> " . htmlspecialchars($curlError);
        }
        echo "</div>";
        return false;
    }

    return true;
}