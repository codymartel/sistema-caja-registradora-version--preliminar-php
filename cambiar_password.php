<?php
// cambiar_password.php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$usuario = $data['usuario'] ?? '';
$password_actual = $data['password_actual'] ?? '';
$password_nueva = $data['password_nueva'] ?? '';

if (!$usuario || !$password_actual || !$password_nueva) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos']);
    exit;
}

// Buscar usuario
$user = fetchOne("SELECT * FROM usuarios_sistema WHERE usuario = ? AND activo = 1", [$usuario]);

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
    exit;
}

// Verificar contraseña actual
if (!password_verify($password_actual, $user['contraseña'])) {
    echo json_encode(['success' => false, 'message' => 'Contraseña actual incorrecta']);
    exit;
}

// Validar nueva contraseña
if (strlen($password_nueva) < 6) {
    echo json_encode(['success' => false, 'message' => 'La nueva contraseña debe tener al menos 6 caracteres']);
    exit;
}

// Hashear nueva contraseña
$nuevo_hash = password_hash($password_nueva, PASSWORD_BCRYPT);

// Actualizar en la base de datos
query("UPDATE usuarios_sistema SET contraseña = ? WHERE id = ?", [$nuevo_hash, $user['id']]);

echo json_encode(['success' => true, 'message' => 'Contraseña cambiada correctamente']);
?>