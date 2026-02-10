<?php
session_start();
require_once 'db.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$usuario = $input['usuario'] ?? '';
$password = $input['password'] ?? '';

$stmt = $pdo->prepare("SELECT * FROM usuarios_sistema WHERE usuario = ? AND activo = 1");
$stmt->execute([$usuario]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['contraseña'])) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Usuario o contraseña incorrectos']);
}
?>