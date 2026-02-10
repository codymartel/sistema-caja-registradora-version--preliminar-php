<?php
require_once 'db.php';
header('Content-Type: application/json');

$venta_id = intval($_POST['venta_id'] ?? 0);
$medio_pago_id = intval($_POST['medio_pago_id'] ?? 0);

if($venta_id<=0){
    echo json_encode(['success'=>false,'message'=>'ID inválido']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE ventas SET medio_pago_id = ? WHERE id = ?");
    $stmt->execute([$medio_pago_id,$venta_id]);
    echo json_encode(['success'=>true]);
} catch(Exception $e){
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
