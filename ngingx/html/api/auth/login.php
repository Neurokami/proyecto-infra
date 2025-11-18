<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

require_method('POST');

$body = get_request_body();
$documento = require_field($body, 'documento', 'El documento del vendedor es obligatorio.');

$pdo = get_pdo_or_fail();

$sql = 'SELECT id_vendedor, documento, nombre, telefono, email 
        FROM vendedores 
        WHERE documento = :documento 
        LIMIT 1';

$stmt = $pdo->prepare($sql);
$stmt->execute([':documento' => $documento]);

$vendedor = $stmt->fetch();

if (!$vendedor) {
    json_error('Vendedor no encontrado.', 404);
}

json_response([
    'success' => true,
    'vendedor' => $vendedor,
]);