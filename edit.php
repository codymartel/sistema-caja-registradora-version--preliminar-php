<?php
// ============================================
// ARCHIVO: edit.php (versión mejorada)
// ============================================
require_once "db.php";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🔐 Clave de acceso para editar
$clave_admin = "1234";

// Si se envió contraseña por modal
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["clave_acceso"])) {
    if ($_POST["clave_acceso"] === $clave_admin) {
        $_SESSION["editar_autorizado"] = true;
        header("Location: edit.php?id=" . $_POST["id"]);
        exit;
    } else {
        $error = "Contraseña incorrecta.";
    }
}

// Si no está autorizado, mostrar modal
if (empty($_SESSION["editar_autorizado"])) {
    $id = $_GET['id'] ?? '';
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
      <meta charset="UTF-8">
      <title>Confirmar Acceso</title>
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
      <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    </head>
    <body class="bg-light">
    <div class="container mt-5">
      <div class="card p-4 shadow-sm col-md-6 mx-auto">
        <h5 class="card-title text-danger"><i class="bi bi-lock-fill"></i> Acceso Restringido</h5>
        <p>Ingrese la contraseña para editar la venta #<?php echo htmlspecialchars($id); ?>:</p>
        <?php if (!empty($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
        <form method="POST">
          <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
          <div class="mb-3">
            <input type="password" name="clave_acceso" class="form-control" placeholder="Contraseña" required>
          </div>
          <button type="submit" class="btn btn-primary"><i class="bi bi-unlock"></i> Entrar</button>
          <a href="index.php" class="btn btn-secondary">Cancelar</a>
        </form>
      </div>
    </div>
    </body>
    </html>
    <?php
    exit;
}

// ============================================
// Si ya está autorizado -> Guardar cambios
// ============================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST["clave_acceso"])) {
    $id             = $_POST['id'];
    $tipo_pago_id   = $_POST['tipo_pago_id'];
    $punto_venta_id = $_POST['punto_venta_id'];
    $descripcion    = $_POST['descripcion'];
    $cantidad       = $_POST['cantidad'];
    $precio_unitario = $_POST['precio_unitario'];
    $monto          = $precio_unitario * $cantidad;
    $tipo           = $_POST['tipo'];

    $entrada = ($tipo === 'entrada') ? $monto : 0;
    $salida  = ($tipo === 'salida') ? $monto : 0;

    // Si aún no existe columna precio_unitario, ignora ese campo
    $sql = "UPDATE ventas 
            SET tipo_pago_id=?, punto_venta_id=?, descripcion=?, cantidad=?, total_entrada=?, total_salida=?, precio_unitario=?
            WHERE id=?";
    $ok = query($sql, [$tipo_pago_id, $punto_venta_id, $descripcion, $cantidad, $entrada, $salida, $precio_unitario, $id]);

    unset($_SESSION["editar_autorizado"]);

    if ($ok) {
        header("Location: index.php?updated=1");
    } else {
        header("Location: index.php?error=1");
    }
    exit;
}

// ============================================
// Si es GET -> Mostrar formulario
// ============================================
if (!isset($_GET['id'])) {
    header("Location: index.php?error=notfound");
    exit;
}

$id = intval($_GET['id']);
$venta = fetchOne("SELECT * FROM ventas WHERE id = ?", [$id]);

if (!$venta) {
    header("Location: index.php?error=notfound");
    exit;
}

$tipos_pago   = fetchAll("SELECT * FROM tipos_pago ORDER BY nombre");
$puntos_venta = fetchAll("SELECT * FROM puntos_venta ORDER BY nombre");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Editar Venta</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4">
  <h3><i class="bi bi-pencil-square"></i> Editar Venta #<?php echo $venta['id']; ?></h3>

  <form method="POST" class="mt-3">
    <input type="hidden" name="id" value="<?php echo $venta['id']; ?>">

    <div class="mb-3">
      <label class="form-label">Descripción</label>
      <input type="text" name="descripcion" class="form-control" required
             value="<?php echo htmlspecialchars($venta['descripcion']); ?>">
    </div>

    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label">Tipo de Pago</label>
        <select name="tipo_pago_id" class="form-select" required>
          <?php foreach ($tipos_pago as $tp): ?>
            <option value="<?php echo $tp['id']; ?>" <?php if ($tp['id'] == $venta['tipo_pago_id']) echo "selected"; ?>>
              <?php echo $tp['nombre']; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label">Punto de Venta</label>
        <select name="punto_venta_id" class="form-select" required>
          <?php foreach ($puntos_venta as $pv): ?>
            <option value="<?php echo $pv['id']; ?>" <?php if ($pv['id'] == $venta['punto_venta_id']) echo "selected"; ?>>
              <?php echo $pv['nombre']; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="row">
      <div class="col-md-3 mb-3">
        <label class="form-label">Cantidad</label>
        <input type="number" name="cantidad" class="form-control" min="1" required
               value="<?php echo $venta['cantidad']; ?>">
      </div>

      <div class="col-md-3 mb-3">
        <label class="form-label">Precio Unitario</label>
        <input type="number" step="0.01" name="precio_unitario" class="form-control" required
               value="<?php echo $venta['precio_unitario'] ?? 0; ?>">
      </div>

      <div class="col-md-3 mb-3">
        <label class="form-label">Monto Total</label>
        <input type="number" step="0.01" name="monto" class="form-control bg-light" readonly
               value="<?php echo $venta['total_entrada'] > 0 ? $venta['total_entrada'] : $venta['total_salida']; ?>">
      </div>

      <div class="col-md-3 mb-3">
        <label class="form-label">Tipo</label>
        <select name="tipo" class="form-select" required>
          <option value="entrada" <?php if ($venta['total_entrada'] > 0) echo "selected"; ?>>Entrada</option>
          <option value="salida" <?php if ($venta['total_salida'] > 0) echo "selected"; ?>>Salida</option>
        </select>
      </div>
    </div>

    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Guardar Cambios</button>
    <a href="index.php" class="btn btn-secondary">Cancelar</a>
  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // 🧮 Actualiza monto automáticamente
  const cantidad = document.querySelector('input[name="cantidad"]');
  const precio = document.querySelector('input[name="precio_unitario"]');
  const monto = document.querySelector('input[name="monto"]');

  function actualizarMonto() {
    const total = (parseFloat(cantidad.value) || 0) * (parseFloat(precio.value) || 0);
    monto.value = total.toFixed(2);
  }

  cantidad.addEventListener('input', actualizarMonto);
  precio.addEventListener('input', actualizarMonto);
</script>
</body>
</html>
