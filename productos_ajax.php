<?php
require 'db.php';

// === CARGAR CATEGORÍAS DESDE puntos_venta (NUEVO) ===
$categorias = fetchAll("SELECT id, nombre FROM puntos_venta WHERE activo = 1 ORDER BY id");
$options_categorias = '<option value="">Seleccionar categoría</option>';
foreach ($categorias as $cat) {
    $nombre = trim(substr($cat['nombre'], strpos($cat['nombre'], ':') + 1));
    $options_categorias .= '<option value="' . $cat['id'] . '">' . $cat['id'] . ': ' . $nombre . '</option>';
}

// Guardar producto si viene POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'] ?? '';
    $categoria = $_POST['categoria'] ?? '';
    $precio = floatval($_POST['precio'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);

    // Manejo de imagen
    $imagen = '';
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['imagen'];
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array(strtolower($ext), $allowed)) {
            $imagen = uniqid('prod_') . '.' . $ext;
            move_uploaded_file($file['tmp_name'], 'uploads/' . $imagen);
        }
    }

    query("INSERT INTO productos (nombre, categoria, precio, stock, imagen) VALUES (?, ?, ?, ?, ?)", 
          [$nombre, $categoria, $precio, $stock, $imagen]);
    exit;
}

// Obtener productos
$buscar = $_GET['buscar'] ?? '';

if ($buscar) {
    $productos = fetchAll(
        "SELECT id, nombre, precio, categoria, stock, imagen 
         FROM productos 
         WHERE nombre LIKE ? OR categoria LIKE ?
         ORDER BY id ASC",
        ["%$buscar%", "%$buscar%"]
    );
} else {
    $productos = fetchAll("SELECT id, nombre, precio, categoria, stock, imagen FROM productos ORDER BY id ASC");
}

// === MAPEAR CATEGORÍA A NOMBRE (NUEVO) ===
$cat_map = [];
foreach ($categorias as $cat) {
    $nombre = trim(substr($cat['nombre'], strpos($cat['nombre'], ':') + 1));
    $cat_map[$cat['id']] = $cat['id'] . ': ' . $nombre;
}

// Generar filas HTML
foreach ($productos as $producto) {
    $id        = $producto['id'];
    $nombre    = htmlspecialchars($producto['nombre']);
    $categoria = $cat_map[$producto['categoria']] ?? htmlspecialchars($producto['categoria'] ?? 'Sin categoría');
    $precio    = number_format($producto['precio'], 2);
    $stock     = (int)$producto['stock'];
    $imagen    = htmlspecialchars($producto['imagen'] ?? '');

    echo "<tr>";

    echo "<td class='text-center align-middle'>$id</td>";

    // === IMAGEN + BOTÓN AMPLIAR ===
    echo "<td class='text-center align-middle'>";
    if (!empty($imagen)) {
        echo '<img src="uploads/' . $imagen . '" class="img-thumbnail mb-1" alt="' . $nombre . '" style="cursor:pointer; max-height:60px;" onclick="$(\'.btn-ampliar[data-imagen=&quot;' . $imagen . '&quot;]\').click();">';
        echo '<br>';
        echo '<button type="button" class="btn btn-info btn-sm btn-ampliar" data-imagen="' . $imagen . '">
                Ampliar
              </button>';
    } else {
        echo '<span class="text-muted">Sin imagen</span>';
    }
    echo "</td>";

    echo "<td class='align-middle'>$nombre</td>";
    echo "<td class='align-middle'>$categoria</td>";
    echo "<td class='text-end align-middle'>S/ $precio</td>";
    echo "<td class='text-center align-middle fw-bold text-success'>$stock</td>";

    // === BOTÓN EDITAR (CORREGIDO COMPLETO) ===
    echo "<td class='text-center align-middle'>
            <button class='btn btn-sm btn-warning btn-editar' 
                data-id='$id'
                data-nombre='$nombre'
                data-categoria='{$producto['categoria']}'
                data-precio='{$producto['precio']}'
                data-stock='$stock'
                data-imagen='$imagen'>
                Editar
            </button>
          </td>";

    echo "</tr>";
}
?>

<!-- === INYECTAR OPCIONES DE CATEGORÍAS EN MODALES (NUEVO) === -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const opciones = `<?= $options_categorias ?>`;
    document.querySelectorAll('select[name="categoria"], #edit_categoria').forEach(sel => {
        if (sel.innerHTML.trim() === '<option value="">Seleccionar categoría</option>' || sel.innerHTML === '') {
            sel.innerHTML = opciones;
        }
    });
});
</script>