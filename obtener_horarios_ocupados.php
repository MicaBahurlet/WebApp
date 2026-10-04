<?php
// obtener_horarios_ocupados.php
require_once 'conexion.php';

header('Content-Type: application/json');

$id_negocio = intval($_GET['id_negocio'] ?? 0);
$fecha      = $_GET['fecha'] ?? '';

if (!$id_negocio || !$fecha) {
    echo json_encode([]);
    exit();
}

// Buscar turnos no cancelados para la fecha especificada
$stmt = $pdo->prepare("
    SELECT DATE_FORMAT(fecha_hora_inicio, '%H:%i') AS hora_ocupada 
    FROM turnos 
    WHERE id_negocio = :id_negocio 
      AND DATE(fecha_hora_inicio) = :fecha 
      AND estado != 'cancelado'
");
$stmt->execute([
    'id_negocio' => $id_negocio,
    'fecha'      => $fecha
]);

$turnosOcupados = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo json_encode($turnosOcupados);
?>