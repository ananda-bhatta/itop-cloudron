<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
function check(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
$root = dirname(__DIR__);
foreach ([
    'CLOUDRON_MYSQL_HOST' => 'mysql', 'CLOUDRON_MYSQL_PORT' => '3306',
    'CLOUDRON_MYSQL_DATABASE' => 'itop', 'CLOUDRON_MYSQL_USERNAME' => 'itop',
    'CLOUDRON_MYSQL_PASSWORD' => 'first', 'CLOUDRON_APP_ORIGIN' => 'https://itop.example.com',
    'CLOUDRON_MAIL_SMTP_SERVER' => 'mail', 'CLOUDRON_MAIL_SMTP_PORT' => '2525',
    'CLOUDRON_MAIL_SMTP_USERNAME' => 'sender', 'CLOUDRON_MAIL_SMTP_PASSWORD' => 'mail-first',
    'CLOUDRON_MAIL_FROM' => 'itop@example.com',
] as $key => $value) {
    putenv($key . '=' . $value);
}
$first = require $root . '/cloudron-settings.php';
check($first['db_host'] === 'mysql:3306', 'Database port must be preserved.');
check($first['email_transport_smtp.port'] === 2525, 'SMTP port must be an integer.');
putenv('CLOUDRON_MYSQL_PASSWORD=rotated');
putenv('CLOUDRON_MAIL_SMTP_PASSWORD=mail-rotated');
putenv('CLOUDRON_APP_ORIGIN=https://restored.example.com/');
$restored = require $root . '/cloudron-settings.php';
check($restored['db_pwd'] === 'rotated', 'Restores must use new database credentials.');
check($restored['email_transport_smtp.password'] === 'mail-rotated', 'SMTP rotation failed.');
check($restored['app_root_url'] === 'https://restored.example.com/', 'Restored URL is incorrect.');
putenv('CLOUDRON_PROXY_IP=172.18.0.1');
$_SERVER['REMOTE_ADDR'] = '172.18.0.1';
$proxied = require $root . '/cloudron-settings.php';
check($proxied['behind_reverse_proxy'] === true, 'Cloudron proxy must be trusted.');
$_SERVER['REMOTE_ADDR'] = '172.18.0.99';
$direct = require $root . '/cloudron-settings.php';
check($direct['behind_reverse_proxy'] === false, 'Other clients must not supply trusted headers.');
putenv('CLOUDRON_MYSQL_PASSWORD');
try {
    require $root . '/cloudron-settings.php';
    throw new RuntimeException('Missing credentials were silently accepted.');
} catch (RuntimeException $error) {
    check(str_contains($error->getMessage(), 'Missing Cloudron environment variable'), 'Unexpected error.');
}
echo "Credential rotation and restore configuration checks passed.\n";
