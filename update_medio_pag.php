<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status"=>"error", "message"=>"Método no permitido"]);
    exit;
}

$venta_id = intval($_POST['venta_id'] ?? 0);
$medio_id = intval($_POST['medio_pago_id'] ?? 0);

if ($venta_id <= 0) {
    echo json_encode(["status"=>"error", "message"=>"ID inválido"]);
    exit;
}

query("UPDATE ventas SET medio_pago_id = ? WHERE id = ?", [$medio_id, $venta_id]);

echo json_encode(["status"=>"success", "message"=>"Medio de pago actualizado"]);
exit;
?>
