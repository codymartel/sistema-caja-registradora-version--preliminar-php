<?php
// imprimir_nota.php?id=123
require_once 'db.php';
if (!isset($_GET['id'])) die('Falta ID');
header("Location: generar_nota.php?venta_id=" . intval($_GET['id']));
?>