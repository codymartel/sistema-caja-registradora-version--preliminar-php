<?php
// delete.php - VERSIÓN CORREGIDA
session_start();
require_once 'db.php';
header('Content-Type: application/json');

// Verificar que hay una caja activa
$caja_id = $_SESSION['caja_id'] ?? null;
if (!$caja_id) {
    echo json_encode(['success' => false, 'message' => 'No hay caja activa']);
    exit;
}

$action = $_POST['action'] ?? '';
$usuario = $_POST['usuario'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($usuario) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Faltan credenciales']);
    exit;
}

try {
    // VERIFICAR USUARIO - CORREGIDO (tu tabla se llama usuarios_sistema, no usuarios)
    $user = fetchOne("SELECT * FROM usuarios_sistema WHERE usuario = ? AND activo = 1", [$usuario]);
    
    if (!$user) {
        throw new Exception('Usuario no encontrado');
    }
    
    // CORREGIDO: el campo se llama 'contraseña', no 'password'
    if (!password_verify($password, $user['contraseña'])) {
        throw new Exception('Contraseña incorrecta');
    }

    // PROCESAR ACCIONES
    if ($action === 'selected' && !empty($_POST['ids'])) {
        $ids = array_map('intval', $_POST['ids']);
        
        // Verificar que existen y pertenecen a la caja actual
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $valid_ids = fetchAll(
            "SELECT id FROM ventas WHERE id IN ($placeholders) AND caja_id = ?", 
            array_merge($ids, [$caja_id])
        );
        
        if (count($valid_ids) === 0) {
            throw new Exception('No se encontraron transacciones válidas para eliminar');
        }
        
        // Eliminar transacciones
        $valid_ids = array_column($valid_ids, 'id');
        $placeholders = str_repeat('?,', count($valid_ids) - 1) . '?';
        query("DELETE FROM ventas WHERE id IN ($placeholders)", $valid_ids);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Se eliminaron ' . count($valid_ids) . ' transacciones correctamente'
        ]);

    } elseif ($action === 'all') {
        // Verificar cuántas transacciones hay
        $count = fetchOne("SELECT COUNT(*) as total FROM ventas WHERE caja_id = ?", [$caja_id]);
        $total = $count['total'] ?? 0;
        
        if ($total === 0) {
            throw new Exception('No hay transacciones para eliminar');
        }
        
        // Eliminar todas las transacciones de esta caja
        query("DELETE FROM ventas WHERE caja_id = ?", [$caja_id]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Se eliminaron todas las transacciones (' . $total . ') correctamente'
        ]);

    } else {
        throw new Exception('Acción no válida o datos incompletos');
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>