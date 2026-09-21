<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
$required = static function (string $name): string {
    $value = getenv($name);
    if ($value === false || $value === '') {
        throw new RuntimeException('Missing Cloudron environment variable: ' . $name);
    }
    return $value;
};
$settings = [
    'db_host' => $required('CLOUDRON_MYSQL_HOST') . ':' . $required('CLOUDRON_MYSQL_PORT'),
    'db_name' => $required('CLOUDRON_MYSQL_DATABASE'),
    'db_user' => $required('CLOUDRON_MYSQL_USERNAME'),
    'db_pwd' => $required('CLOUDRON_MYSQL_PASSWORD'),
    'app_root_url' => rtrim($required('CLOUDRON_APP_ORIGIN'), '/') . '/',
    'behind_reverse_proxy' => getenv('CLOUDRON_PROXY_IP') !== false
        && ($_SERVER['REMOTE_ADDR'] ?? '') === getenv('CLOUDRON_PROXY_IP'),
    'graphviz_path' => '/usr/bin/dot',
];
if (getenv('CLOUDRON_MAIL_SMTP_SERVER')) {
    $settings += [
        'email_transport' => 'SMTP',
        'email_transport_smtp.host' => $required('CLOUDRON_MAIL_SMTP_SERVER'),
        'email_transport_smtp.port' => (int) $required('CLOUDRON_MAIL_SMTP_PORT'),
        'email_transport_smtp.username' => $required('CLOUDRON_MAIL_SMTP_USERNAME'),
        'email_transport_smtp.password' => $required('CLOUDRON_MAIL_SMTP_PASSWORD'),
        'email_transport_smtp.encryption' => '',
        'email_default_sender_address' => $required('CLOUDRON_MAIL_FROM'),
    ];
}
return $settings;
