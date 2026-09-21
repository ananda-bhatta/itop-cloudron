<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
header('Content-Type: text/plain');
try {
    $db = new PDO(
        'mysql:host=' . getenv('CLOUDRON_MYSQL_HOST') . ';port=' . getenv('CLOUDRON_MYSQL_PORT')
            . ';dbname=' . getenv('CLOUDRON_MYSQL_DATABASE'),
        getenv('CLOUDRON_MYSQL_USERNAME'),
        getenv('CLOUDRON_MYSQL_PASSWORD'),
        [PDO::ATTR_TIMEOUT => 3, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $db->query('SELECT 1');
    echo "OK\n";
} catch (Throwable $error) {
    http_response_code(503);
    echo "Database unavailable\n";
}
