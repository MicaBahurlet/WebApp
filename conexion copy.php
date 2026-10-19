<?php
// conexion.php

$host = 'sql206.infinityfree.com';
$dbname = 'if0_43220249_sistema_turnos';
$username = 'if0_43220249';
$password = 'Agendify1234aA'; // Ajustar según tu entorno local (XAMPP/WAMP)

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("Error al conectar con la base de datos: " . $e->getMessage());
}
?>