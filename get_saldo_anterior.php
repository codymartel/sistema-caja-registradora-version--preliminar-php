<?php
require 'db.php';

// Obtener el ultimo saldo final efectivo registrado
$row = fetchOne("
    SELECT saldo_final 
    FROM saldo_final_efectivo
    ORDER BY id DESC
    LIMIT 1
");

echo json_encode([
    'saldo' => $row ? floatval($row['saldo_final']) : 0
]);
