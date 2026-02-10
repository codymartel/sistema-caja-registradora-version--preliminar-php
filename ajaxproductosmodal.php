<?php
$(document).ready(function () {
  // Evento para el campo de búsqueda de producto
  $('#buscarProductoModal').on('input', function () {
    const searchTerm = $(this).val(); // Obtener el término de búsqueda

    // Si el término no está vacío
    if (searchTerm.trim() !== "") {
      $.ajax({
        url: 'search_producto.php',  // El archivo PHP que maneja la búsqueda de productos
        method: 'GET',
        data: { term: searchTerm },  // Pasamos el término de búsqueda
        success: function (response) {
          const productos = JSON.parse(response);  // Convertir la respuesta JSON
          
          if (productos.length > 0) {
            let resultsHTML = '';
            // Mostrar los productos encontrados en el contenedor
            productos.forEach(function (producto) {
              resultsHTML += `
                <div class="product-result" data-id="${producto.id}" data-nombre="${producto.nombre}" data-precio="${producto.precio}" data-punto-venta="${producto.punto_venta_id}">
                  ${producto.nombre} - S/. ${producto.precio}
                </div>
              `;
            });

            // Insertar los resultados en el contenedor de búsqueda
            $('#searchResults').html(resultsHTML);
          } else {
            $('#searchResults').html('<p>No se encontraron productos.</p>');
          }
        }
      });
    } else {
      $('#searchResults').empty(); // Limpiar resultados si el término de búsqueda está vacío
    }
  });

  // Cuando se hace clic en un producto del dropdown, completar los campos en la tabla
  $(document).on('click', '.product-result', function () {
    const productoId = $(this).data('id');
    const productoNombre = $(this).data('nombre');
    const productoPrecio = $(this).data('precio');
    const productoPuntoVenta = $(this).data('punto-venta');
    
    // Asignar los datos del producto seleccionado en la fila correspondiente
    const index = $('tr.filaVenta').length - 1;  // Última fila en la tabla
    $('#producto_id-' + index).val(productoId);
    $('#producto-' + index).val(productoNombre); // Nombre del producto
    $('#precio_unitario-' + index).val(productoPrecio);
    $('#punto_venta-' + index).val(productoPuntoVenta);

    // Calcular el total de la entrada y saldo
    const cantidad = $('#cantidad-' + index).val();
    $('#total_entrada-' + index).val(cantidad * productoPrecio);
    $('#saldo-' + index).text(cantidad * productoPrecio);
    
    // Limpiar los resultados después de seleccionar un producto
    $('#searchResults').empty();
  });

  // Calcular el saldo total de la venta
  $(document).on('input', '.entrada, .salida', function () {
    let saldoTotal = 0;
    $('.filaVenta').each(function () {
      const entrada = parseFloat($(this).find('.entrada').val()) || 0;
      const salida = parseFloat($(this).find('.salida').val()) || 0;
      saldoTotal += entrada - salida;
    });
    $('#saldoTotal').text(saldoTotal.toFixed(2));
  });
});

