<?php
// C:\xampp\htdocs\cajafinal2\cajafinal\guardar_observacion_venta.php
ob_start();
header('Content-Type: application/json');

// RUTA CORRECTA: MISMA CARPETA, NO /config/
require_once __DIR__ . '/db.php';  // ← AQUÍ ESTÁ EL CAMBIO

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$id = $_POST['id'] ?? null;
$observacion = $_POST['observacion'] ?? '';

if (!is_numeric($id) || $id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID inválido']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE ventas SET observacion = ? WHERE id = ?");
    $stmt->execute([$observacion, $id]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'BD: ' . $e->getMessage()]);
}

ob_end_flush();
?>