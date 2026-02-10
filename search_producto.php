<?php
require_once 'db.php';

$term = $_GET['term'] ?? '';
if (strlen($term) < 2) {
    echo json_encode([]);
    exit;
}

try {
    // AGREGADO: JOIN para obtener punto_venta_id
    $sql = "
        SELECT 
            p.id, 
            p.nombre, 
            p.precio, 
            p.stock,
            pv.id AS punto_venta_id
        FROM productos p
        LEFT JOIN puntos_venta pv ON pv.id = p.categoria
        WHERE p.activo = 1 AND p.nombre LIKE ? 
        ORDER BY p.nombre 
        LIMIT 10
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['%' . $term . '%']);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $resultados = [];
    foreach ($productos as $p) {
        $resultados[] = [
            'id' => $p['id'],
            'nombre' => $p['nombre'],
            'precio' => (float)$p['precio'],
            'stock' => (int)$p['stock'],
            'punto_venta_id' => $p['punto_venta_id'] ? (int)$p['punto_venta_id'] : 8, // 8 = Otros
            'label' => $p['nombre'] . ' (Stock: ' . $p['stock'] . ')',
            'value' => $p['nombre']
        ];
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($resultados, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log("Error en search_producto.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
}
?>