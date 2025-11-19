<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

require_method('GET');

$pdo = get_pdo_or_fail();

$vendedorId = isset($_GET['vendedor_id']) ? (int) $_GET['vendedor_id'] : 0;
if ($vendedorId <= 0) {
    json_error('El parámetro vendedor_id es obligatorio y debe ser numérico.', 422);
}

/**
 * Listar ventas asociadas a los productos de un vendedor.
 *
 * Se obtienen las ventas (tabla ventas) donde al menos un ítem del carrito
 * pertenece a un producto cuyo vendedor_id coincide con el vendedor autenticado.
 *
 * Para cada venta se retorna:
 *  - id_venta
 *  - fecha
 *  - total (desde tabla ventas)
 *  - cliente_nombre
 *  - items (cantidad de líneas de carrito de ese vendedor en esa venta)
 */
$sql = '
    SELECT
        v.id_venta,
        v.fecha,
        v.total,
        c.nombre AS cliente_nombre,
        COUNT(DISTINCT ca.id_carrito) AS items
    FROM ventas v
    INNER JOIN clientes c
        ON v.cliente_id = c.id_cliente
    INNER JOIN carritos ca
        ON ca.venta_id = v.id_venta
    INNER JOIN productos p
        ON ca.producto_id = p.id_producto
    WHERE p.vendedor_id = :vendedor_id
    GROUP BY
        v.id_venta,
        v.fecha,
        v.total,
        c.nombre
    ORDER BY v.fecha DESC, v.id_venta DESC
';

$stmt = $pdo->prepare($sql);
$stmt->execute([':vendedor_id' => $vendedorId]);
$ventas = $stmt->fetchAll();

json_response([
    'success' => true,
    'ventas'  => $ventas,
]);