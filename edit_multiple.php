<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['caja_id'])) {
    header("Location: index.php");
    exit;
}

$ids = $_GET['ids'] ?? '';
$ids_array = array_filter(explode(',', $ids));
if (empty($ids_array)) {
    header("Location: index.php");
    exit;
}

// Aquí puedes cargar los datos y mostrar formulario para editar en masa
// Ejemplo básico:
$ventas = fetchAll("SELECT * FROM ventas WHERE id IN (" . implode(',', array_fill(0, count($ids_array), '?')) . ") AND caja_id = ?", 
    array_merge($ids_array, [$_SESSION['caja_id']]));
?>
<!DOCTYPE html>
<html>
<head><title>Editar Múltiples</title></head>
<body>
  <h3>Editar <?= count($ventas) ?> transacciones</h3>
  <form method="POST" action="save_multiple.php">
    <?php foreach ($ventas as $v): ?>
      <input type="hidden" name="ids[]" value="<?= $v['id'] ?>">
      <p><strong><?= htmlspecialchars($v['descripcion']) ?></strong></p>
      <!-- Agrega campos que quieras editar -->
    <?php endforeach; ?>
    <button type="submit">Guardar Cambios</button>
  </form>
</body>
</html>