<?php
// Conexión PDO configurada por variables de entorno (Railway) o .env local.
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line[0] === '#' || strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        if (getenv(trim($k)) === false) putenv(trim($k) . '=' . trim($v, " \t'\""));
    }
}

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_NAME') ?: 'sistema_turnos';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS');
if ($pass === false) $pass = '';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Base vacía (primer deploy): crear el esquema. Nunca borra ni pisa datos.
    if ($pdo->query("SHOW TABLES LIKE 'negocios'")->fetchColumn() === false) {
        $pdo->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
    }
} catch (PDOException $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Error de conexión a la base de datos.');
}
