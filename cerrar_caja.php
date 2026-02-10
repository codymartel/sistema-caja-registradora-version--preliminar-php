<?php
require_once "db.php";
require_once "helpers.php";
$id = $_GET['id'] ?? 0;
// Verificar si la caja existe y está abierta
$caja = fetchOne("SELECT * FROM cajas WHERE id = ?", [$id]);
if (!$caja) {
    die("Caja no encontrada");
}
if ($caja['estado'] === 'Cerrada') {
    die("Esta caja ya está cerrada");
}
// Calcular saldo final
try {
    $totalEntradas = fetchOne("
        SELECT SUM(total) as total
        FROM transacciones
        WHERE id_caja = ?",
        [$id]
    );
    $saldo_final = $caja['saldo_inicial'] + ($totalEntradas['total'] ?? 0);
} catch (PDOException $e) {
    $saldo_final = $caja['saldo_inicial'];
}
// Cerrar caja
query("UPDATE cajas SET estado = 'Cerrada', saldo_final = ? WHERE id = ?", [$saldo_final, $id]);
// ============================================
// BLOQUE QUE DEBES AÑADIR AQUÍ MISMO
// ============================================

// === AÑADIDO: Cálculo de los 3 tipos de ganancias (desde ventas) ===
$ganancias = fetchOne("
    SELECT
        COALESCE(SUM(saldo), 0) AS general,
        COALESCE(SUM(CASE WHEN tipo_pago_id = 1 THEN saldo ELSE 0 END), 0) AS efectivo,
        COALESCE(SUM(CASE WHEN punto_venta_id != 5 THEN saldo ELSE 0 END), 0) AS operacional
    FROM ventas
    WHERE caja_id = ?
", [$id]);

$ganancia_general     = $ganancias['general'];
$ganancia_efectivo    = $ganancias['efectivo'];
$ganancia_operacional = $ganancias['operacional'];

// === AÑADIDO: Guardar los 3 tipos en saldo_final_efectivo (sin romper nada) ===
query("
    INSERT INTO saldo_final_efectivo 
    (fecha_cierre, saldo_inicial, entrada_efectivo, ganancia_general, ganancia_operacional, saldo_final)
    VALUES (NOW(), ?, ?, ?, ?, ?)
", [
    $caja['saldo_inicial'],
    $ganancia_efectivo,
    $ganancia_general,
    $ganancia_operacional,
    $caja['saldo_inicial'] + $ganancia_efectivo
]);

// === FIN DE AÑADIDO ===

// Obtener solo el efectivo (tipo_pago_id = 1)
$efectivo = fetchOne("
    SELECT COALESCE(SUM(saldo), 0) AS total
    FROM ventas
    WHERE caja_id = ? AND tipo_pago_id = 1",
    [$id]
);
// Guardar saldo final para reporte histórico
query("
    INSERT INTO saldo_final_efectivo (fecha_cierre, saldo_inicial, entrada_efectivo, saldo_final)
    VALUES (NOW(), ?, ?, ?)", [
        $caja['saldo_inicial'],
        $efectivo['total'],
        $caja['saldo_inicial'] + $efectivo['total']
]);
// ============================================
header("Location: historial_cajas.php");
exit;
?>