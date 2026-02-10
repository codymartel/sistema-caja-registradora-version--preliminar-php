<?php
require_once 'db.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$caja_id = $input['caja_id'] ?? null;
$conteo = $input['conteo'] ?? null;

if (!$caja_id || !is_array($conteo)) {
  echo json_encode(['status' => 'error', 'msg' => 'Datos inválidos']);
  exit;
}

try {
  $pdo->beginTransaction();

  // 🧹 Limpia el conteo anterior de esa caja
  $stmt = $pdo->prepare("DELETE FROM conteo_monedas WHERE caja_id = ?");
  $stmt->execute([$caja_id]);

  // 🧮 Inserta el nuevo conteo
  $stmt = $pdo->prepare("INSERT INTO conteo_monedas (caja_id, denominacion, cantidad, total)
                         VALUES (?, ?, ?, ?)");

  foreach ($conteo as $den => $cant) {
    $denf = floatval($den);
    $cant = intval($cant);
    $total = $denf * $cant;
    $stmt->execute([$caja_id, $denf, $cant, $total]);
  }

  $pdo->commit();
  echo json_encode(['status' => 'ok']);
} catch (Exception $e) {
  $pdo->rollBack();
  echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
}
