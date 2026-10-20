<?php
// conexion.php

function cargarEnv($ruta) {
    if (!file_exists($ruta)) {
        return;
    }

    $lineas = file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lineas as $linea) {
        if (strpos(trim($linea), '#') === 0) {
            continue;
        }

        list($clave, $valor) = explode('=', $linea, 2);
        $clave = trim($clave);
        $valor = trim($valor);
        $valor = trim($valor, '"\'');

        if (!array_key_exists($clave, $_SERVER) && !array_key_exists($clave, $_ENV)) {
            putenv("$clave=$valor");
            $_ENV[$clave] = $valor;
            $_SERVER[$clave] = $valor;
        }
    }
}

cargarEnv(__DIR__ . '/.env');

$host     = getenv('DB_HOST') ?: 'localhost';
$port     = getenv('DB_PORT') ?: '3306';
$dbname   = getenv('DB_NAME') ?: 'sistema_turnos';
$username = getenv('DB_USER') ?: 'admin';
$password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'admin';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("Error al conectar con la base de datos: " . $e->getMessage());
}