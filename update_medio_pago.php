<?php
session_start();
require_once 'db.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? 0;
$medio_pago_id = $input['medio_pago_id'] ?? null;

if (!$id || !isset($_SESSION['caja_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

query("UPDATE ventas SET medio_pago_id = ? WHERE id = ? AND caja_id = ?", 
    [$medio_pago_id, $id, $_SESSION['caja_id']]);
echo json_encode(['success' => true]);
?>