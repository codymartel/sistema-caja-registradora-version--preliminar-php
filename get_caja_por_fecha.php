<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$fecha = $input['fecha'] ?? '';
$estado = $input['estado'] ?? 'cerrada';

if (!$fecha || !$estado) {
    echo json_encode(['existe' => false]);
    exit;
}

$caja = fetchOne("SELECT id FROM cajas WHERE fecha = ? AND estado = ?", [$fecha, $estado]);

echo json_encode([
    'existe' => $caja ? true : false,
    'caja_id' => $caja['id'] ?? null
]);
?>