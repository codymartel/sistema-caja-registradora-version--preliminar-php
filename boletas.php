<?php
session_start();
require_once "db.php"; // Asegúrate de que aquí tengas tu conexión $pdo

// =======================
// Guardar boleta
// =======================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['guardar_boleta'])) {
    $cliente   = $_POST['cliente'];
    $telefono  = $_POST['telefono'];
    $fecha     = date("Y-m-d H:i:s");
    $productos = $_POST['productos'];

    $subtotal = 0;
    foreach($productos as $p){
        $subtotal += $p['cantidad'] * $p['precio_unitario'];
    }
    $igv = $subtotal * 0.18;
    $total = $subtotal + $igv;

    // Insertar boleta
    $sql = "INSERT INTO boletas (cliente, telefono, fecha, subtotal, igv, total) VALUES (?,?,?,?,?,?)";
    $stmt = $pdo->prepare($sql);
    if($stmt->execute([$cliente, $telefono, $fecha, $subtotal, $igv, $total])){
        $boleta_id = $pdo->lastInsertId();

        // Insertar detalle de productos
        foreach($productos as $p){
            $total_prod = $p['cantidad'] * $p['precio_unitario'];
            $sql_detalle = "INSERT INTO detalle_boletas (boleta_id, cantidad, detalle, precio_unitario, total) VALUES (?,?,?,?,?)";
            $stmt_detalle = $pdo->prepare($sql_detalle);
            $stmt_detalle->execute([$boleta_id, $p['cantidad'], $p['detalle'], $p['precio_unitario'], $total_prod]);
        }

        $_SESSION['boleta_id'] = $boleta_id;
        header("Location: boletas.php");
        exit;
    } else {
        echo "Error al guardar la boleta.";
    }
}

// =======================
// Obtener todas las boletas
// =======================
$stmt = $pdo->query("SELECT * FROM boletas ORDER BY fecha DESC");
$boletas = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Registro de Boletas</title>
<style>
body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
form, table { background: #fff; padding: 15px; margin-bottom: 20px; border: 1px solid #ccc; }
table { width: 100%; border-collapse: collapse; }
table th, table td { border: 1px solid #000; padding: 8px; text-align: center; }
h2 { margin-top: 0; }
button { padding: 5px 10px; margin-top: 10px; }
</style>
</head>
<body>

<h2>Registrar Boleta</h2>
<form method="POST">
    Cliente: <input type="text" name="cliente" required>
    Teléfono: <input type="text" name="telefono"><br><br>

    <div id="productos">
        <div>
            Cantidad: <input type="number" name="productos[0][cantidad]" required>
            Detalle: <input type="text" name="productos[0][detalle]" required>
            Precio Unitario: <input type="number" step="0.01" name="productos[0][precio_unitario]" required>
        </div>
    </div>
    <button type="button" onclick="agregarProducto()">Agregar Producto</button><br><br>

    <button type="submit" name="guardar_boleta">Guardar Boleta</button>
</form>

<h2>Registro de Boletas</h2>
<table>
    <tr>
        <th>ID</th>
        <th>Cliente</th>
        <th>Teléfono</th>
        <th>Fecha</th>
        <th>Subtotal</th>
        <th>IGV</th>
        <th>Total</th>
        <th>Acciones</th>
    </tr>
    <?php foreach($boletas as $b): ?>
    <tr>
        <td><?= $b['id'] ?></td>
        <td><?= htmlspecialchars($b['cliente']) ?></td>
        <td><?= htmlspecialchars($b['telefono']) ?></td>
        <td><?= $b['fecha'] ?></td>
        <td>S/ <?= number_format($b['subtotal'],2) ?></td>
        <td>S/ <?= number_format($b['igv'],2) ?></td>
        <td>S/ <?= number_format($b['total'],2) ?></td>
        <td><a href="ver_boleta.php?id=<?= $b['id'] ?>" target="_blank">Ver/Imprimir</a></td>
    </tr>
    <?php endforeach; ?>
</table>

<script>
let index = 1;
function agregarProducto(){
    const div = document.createElement('div');
    div.innerHTML = `
        Cantidad: <input type="number" name="productos[${index}][cantidad]" required>
        Detalle: <input type="text" name="productos[${index}][detalle]" required>
        Precio Unitario: <input type="number" step="0.01" name="productos[${index}][precio_unitario]" required>
    `;
    document.getElementById('productos').appendChild(div);
    index++;
}
</script>

</body>
</html>
