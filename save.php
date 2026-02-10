<?php
// save.php 
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

// Obtener caja activa
$caja = fetchOne("SELECT id FROM cajas WHERE estado = 'abierta' ORDER BY id DESC LIMIT 1");
if (!$caja) {
    $_SESSION['mensaje'] = "No hay caja activa.";
    $_SESSION['tipoMensaje'] = 'warning';
    header("Location: index.php");
    exit;
}

// Validar datos del formulario
if (!isset($_POST['descripcion']) || !is_array($_POST['descripcion'])) {
    $_SESSION['mensaje'] = "No se enviaron datos válidos.";
    $_SESSION['tipoMensaje'] = 'warning';
    header("Location: index.php");
    exit;
}

$pdo->beginTransaction();
try {
    // === OBTENER ORDEN DE FILAS ===
    $ordenes = $_POST['orden'] ?? [];
    $indices_ordenados = [];

    foreach ($ordenes as $i => $orden) {
        $indices_ordenados[$orden] = $i;
    }
    ksort($indices_ordenados); // Ordenar por posición visual

    // === GUARDAR EN ORDEN VISUAL ===
    foreach ($indices_ordenados as $orden => $i) {
        $desc            = trim($_POST['descripcion'][$i] ?? '');
        $producto_id     = !empty($_POST['producto_id'][$i]) ? intval($_POST['producto_id'][$i]) : null;
        $tipo_pago_id    = intval($_POST['tipo_pago_id'][$i] ?? 0);
        $punto_venta_id  = intval($_POST['punto_venta_id'][$i] ?? 0);
        $cantidad        = intval($_POST['cantidad'][$i] ?? 0);
        $precio_unitario = floatval($_POST['precio_unitario'][$i] ?? 0);
        $total_entrada   = floatval($_POST['total_entrada'][$i] ?? 0);
        $total_salida    = floatval($_POST['total_salida'][$i] ?? 0);

        // Si hay salida, entrada = 0
        if ($total_salida > 0) $total_entrada = 0;

        $saldo = $total_entrada - $total_salida;

        // Validar campos obligatorios
        if ($desc === '' || $tipo_pago_id <= 0 || $punto_venta_id <= 0) continue;
        if ($total_entrada <= 0 && $total_salida <= 0) continue;

        // === ACTUALIZAR STOCK SI ES PRODUCTO ===
        if ($producto_id && $cantidad > 0) {
            // Obtener información del producto
            $producto = fetchOne("SELECT stock, stock_infinito FROM productos WHERE id = ? AND activo = 1", [$producto_id]);

            if (!$producto) {
                throw new Exception("Producto no encontrado o inactivo: $desc");
            }

            // Si el producto NO es infinito, validar stock y descontar
            if (empty($producto['stock_infinito']) || $producto['stock_infinito'] == 0) {
                if ($producto['stock'] < $cantidad) {
                    throw new Exception("Stock insuficiente para: $desc");
                }

                query("UPDATE productos SET stock = stock - ? WHERE id = ?", [$cantidad, $producto_id]);
            }
            // Si es infinito, no se descuenta ni se valida stock
        }

        // === INSERTAR VENTA ===
        query("
            INSERT INTO ventas 
            (caja_id, tipo_pago_id, punto_venta_id, descripcion, cantidad, total_entrada, total_salida, saldo, fecha)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ", [
            $caja['id'], $tipo_pago_id, $punto_venta_id, $desc,
            $cantidad, $total_entrada, $total_salida, $saldo
        ]);
    }

    $pdo->commit();
    $_SESSION['mensaje'] = "Transacciones guardadas correctamente. Stock actualizado (productos infinitos excluidos).";
    $_SESSION['tipoMensaje'] = 'success';

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['mensaje'] = "Error: " . $e->getMessage();
    $_SESSION['tipoMensaje'] = 'danger';
}

header("Location: index.php");
exit;
?>
