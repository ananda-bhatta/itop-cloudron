<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Run as root before Apache starts. Credentials never go to stdout or arguments.
if (PHP_SAPI !== 'cli') {
    exit(1);
}
umask(0077);
$root = '/app/data/public';
$statePath = '/app/data/.bootstrap-state';
$credentialsPath = '/app/data/.bootstrap-credentials.json';
$configPath = $root . '/conf/production/config-itop.php';
$state = is_file($statePath) ? trim(file_get_contents($statePath)) : '';

function savePrivate(string $path, string $contents): void {
    $temporary = $path . '.tmp';
    if (file_put_contents($temporary, $contents) === false || !chmod($temporary, 0600)
        || !rename($temporary, $path)) {
        throw new RuntimeException('Could not save bootstrap state.');
    }
}

function runInstaller(array $command): void {
    $process = proc_open($command, [
        0 => ['file', '/dev/null', 'r'],
        1 => ['file', '/app/data/bootstrap.log', 'a'],
        2 => ['file', '/app/data/bootstrap.log', 'a'],
    ], $pipes, '/app/data/public');
    if (!is_resource($process) || proc_close($process) !== 0) {
        throw new RuntimeException('iTop initialization failed. Inspect /app/data/bootstrap.log in Cloudron File Manager.');
    }
}

try {
    if ($state === 'complete') {
        if (!is_file($configPath)) {
            throw new RuntimeException('An initialized instance is missing its configuration. Restore its backup.');
        }
        exit(0);
    }
    // A manually installed or restored instance must never be initialized again.
    if ($state === '' && is_file($configPath)) {
        echo "Existing iTop configuration preserved.\n";
        exit(0);
    }
    if ($state === 'installing') {
        throw new RuntimeException('A previous initialization was interrupted. Inspect bootstrap.log and restore the pre-install backup; no database cleanup was attempted.');
    }
    if (!in_array($state, ['', 'installed'], true)) {
        throw new RuntimeException('Unrecognized bootstrap state; refusing to modify the database.');
    }
    $settings = require '/app/code/cloudron-settings.php';
    $db = new PDO('mysql:host=' . getenv('CLOUDRON_MYSQL_HOST') . ';port=' . getenv('CLOUDRON_MYSQL_PORT')
        . ';dbname=' . $settings['db_name'], $settings['db_user'], $settings['db_pwd'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 10]);

    if ($state === '') {
        $tables = $db->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
        if ((int) $tables !== 0) {
            throw new RuntimeException('The database already contains tables but has no iTop configuration. Refusing to initialize or erase existing data.');
        }
        $credentials = [
            'admin_user' => 'admin', 'admin_password' => bin2hex(random_bytes(24)),
            'cron_user' => 'cloudron-cron', 'cron_password' => bin2hex(random_bytes(24)),
        ];
        savePrivate($credentialsPath, json_encode($credentials, JSON_THROW_ON_ERROR));
        $xml = new DOMDocument();
        $xml->load($root . '/setup/unattended-install/xml_setup/fresh-install.xml');
        $xpath = new DOMXPath($xml);
        $values = [
            'database/server' => $settings['db_host'], 'database/user' => $settings['db_user'],
            'database/pwd' => $settings['db_pwd'], 'database/name' => $settings['db_name'],
            'database/db_tls_enabled' => '0', 'database/db_tls_ca' => '', 'database/prefix' => '',
            'url' => $settings['app_root_url'], 'admin_account/user' => $credentials['admin_user'],
            'admin_account/pwd' => $credentials['admin_password'], 'admin_account/language' => 'EN US',
            'language' => 'EN US', 'sample_data' => '0', 'graphviz_path' => '/usr/bin/dot',
        ];
        foreach ($values as $path => $value) {
            $nodes = $xpath->query('/installation/' . $path);
            if ($nodes->length !== 1) {
                throw new RuntimeException('Upstream installer response schema changed: ' . $path);
            }
            $node = $nodes->item(0);
            while ($node->firstChild) {
                $node->removeChild($node->firstChild);
            }
            $node->appendChild($xml->createTextNode($value));
        }
        if (!is_dir('/run/itop-bootstrap')) {
            mkdir('/run/itop-bootstrap', 0700);
        }
        chown('/run/itop-bootstrap', 'www-data');
        $responsePath = '/run/itop-bootstrap/response.xml';
        savePrivate($responsePath, $xml->saveXML());
        chown($responsePath, 'www-data');
        savePrivate($statePath, 'installing');
        echo "Initializing iTop with the standard modules and no demo data...\n";
        try {
            runInstaller(['/usr/local/bin/gosu', 'www-data:www-data', 'php8.4', '-d', 'memory_limit=1024M',
                'setup/unattended-install/unattended-install.php', '--param-file=' . $responsePath,
                '--installation_xml=' . $root . '/datamodels/2.x/installation.xml']);
        } finally {
            if (is_file($responsePath)) {
                unlink($responsePath);
            }
        }
        if (!is_file($configPath) || !is_file($root . '/env-production/autoload.php')) {
            throw new RuntimeException('Installer did not produce a complete configuration and compiled environment.');
        }
        savePrivate($statePath, 'installed');
    }
    $credentials = json_decode(file_get_contents($credentialsPath), true, 512, JSON_THROW_ON_ERROR);
    // Send bootstrap credentials via stdin; no credentials in process arguments.
    $process = proc_open(['/usr/local/bin/gosu', 'www-data:www-data', 'php8.4', '/app/code/finish-bootstrap.php'], [
        0 => ['pipe', 'r'], 1 => ['file', '/app/data/bootstrap.log', 'a'],
        2 => ['file', '/app/data/bootstrap.log', 'a'],
    ], $pipes, $root);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not initialize the background task account.');
    }
    fwrite($pipes[0], json_encode($credentials, JSON_THROW_ON_ERROR));
    fclose($pipes[0]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Background task account initialization failed; see bootstrap.log.');
    }
    savePrivate('/app/data/cron.params', 'auth_user = ' . $credentials['cron_user'] . "\n"
        . 'auth_pwd = ' . $credentials['cron_password'] . "\n");
    savePrivate('/app/data/initial-admin.txt', 'URL: ' . $settings['app_root_url'] . "\n"
        . 'Username: ' . $credentials['admin_user'] . "\nPassword: " . $credentials['admin_password'] . "\n\n"
        . "Change this initial password at first login. This file is not updated when your password changes.\n"
        . "Background tasks use a separate account and are already configured.\n");
    savePrivate($statePath, 'complete');
    unlink($credentialsPath);
    echo "iTop initialization complete. Initial login: /app/data/initial-admin.txt\n";
} catch (Throwable $error) {
    // Avoid dumping database exception text or stack arguments into shared logs.
    $message = $error instanceof PDOException ? 'Cannot inspect the Cloudron database for initialization.' : $error->getMessage();
    fwrite(STDERR, $message . "\n");
    exit(1);
}
