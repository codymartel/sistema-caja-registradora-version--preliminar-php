<?php
session_start();
require_once 'db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit(json_encode(['success' => false]));

$caja_id = $_POST['caja_id'] ?? 0;
$usuario = $_POST['usuario'] ?? '';
$password = $_POST['password'] ?? '';

if (!$caja_id || !$usuario || !$password) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos']);
    exit;
}

// Verificar credenciales (ajusta según tu tabla de usuarios)
$res = fetchOne("SELECT * FROM usuarios WHERE usuario = ? AND password = ?", [$usuario, $password]);
if (!$res) {
    echo json_encode(['success' => false, 'message' => 'Credenciales incorrectas']);
    exit;
}

$caja = fetchOne("SELECT * FROM cajas WHERE id = ? AND estado = 'cerrada'", [$caja_id]);
if (!$caja) {
    echo json_encode(['success' => false, 'message' => 'Caja no encontrada o ya abierta']);
    exit;
}

query("UPDATE cajas SET estado = 'abierta', closed_at = NULL WHERE id = ?", [$caja_id]);
$_SESSION['caja_id'] = $caja_id;

echo json_encode(['success' => true, 'message' => 'Caja abierta correctamente']);
?>