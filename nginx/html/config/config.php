<?php
declare(strict_types=1);

/**
 * Database connection configuration.
 *
 * Intended to run inside the Nginx/PHP-FPM container
 * talking to the "db" service from the MariaDB docker-compose.
 */

const DB_HOST = 'host.docker.internal';
const DB_PORT = 3306;
const DB_NAME = 'market';

/**
 * Importante:
 * El contenedor de MariaDB definido en docker-compose crea un usuario
 * "user" con contraseña "userpass" y le da permisos sobre la base.
 *
 * Conectarse como root desde fuera del contenedor suele estar restringido
 * y provoca errores como:
 * "Host 'jeronimo-virtualbox.local' is not allowed to connect to this MariaDB server"
 *
 * Por eso usamos aquí el usuario "user".
 */
const DB_USER = 'user';
const DB_PASS = 'userpass';

/**
 * Create and return a configured PDO instance.
 */
function getPDO(): PDO
{
    $host = getenv('DB_HOST') ?: DB_HOST;
    $port = getenv('DB_PORT') ?: (string) DB_PORT;
    $db   = getenv('DB_NAME') ?: DB_NAME;
    $user = getenv('DB_USER') ?: DB_USER;
    $pass = getenv('DB_PASS') ?: DB_PASS;

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $db);

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    return new PDO($dsn, $user, $pass, $options);
}

/**
 * Placeholder hook to ensure the marketplace schema is initialized.
 *
 * Right now we assume the SQL you provided is executed on the DB container
 * (e.g. via an init script or manual import).
 */
function ensureDatabaseInitialized(PDO $pdo): void
{
    // Here you could run migrations or checks if needed.
}
