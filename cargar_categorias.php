<?php
// cargar_categorias.php
require 'db.php';

$categorias = fetchAll("SELECT id, nombre FROM categorias WHERE activo = 1 ORDER BY id"); // Asumiendo tabla 'categorias'; ajusta si es diferente
$options = '<option value="">Seleccionar categoría</option>';

foreach ($categorias as $cat) {
    $options .= '<option value="' . $cat['id'] . '">' . $cat['id'] . ': ' . htmlspecialchars($cat['nombre']) . '</option>';
}

echo $options;
?>