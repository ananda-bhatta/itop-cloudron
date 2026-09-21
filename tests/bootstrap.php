<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Integration checks for the disposable CI instance only.
require '/app/data/public/approot.inc.php';
require APPROOT . 'application/startup.inc.php';
$mode = $argv[1] ?? '';
$newPassword = 'CI-only-changed-password-729!';
if ($mode === 'change') {
    $details = stream_get_contents(STDIN);
    if (!preg_match('/^Password: (.+)$/m', $details, $matches)
        || !UserRights::CheckCredentials('admin', trim($matches[1]))) {
        throw new RuntimeException('Generated initial admin credentials do not authenticate.');
    }
    UserRights::Login('admin');
    $admin = MetaModel::GetObjectFromOQL('SELECT UserLocal WHERE login = :login', ['login' => 'admin'], true);
    $admin->Set('password', $newPassword);
    $admin->Set('expiration', UserLocal::EXPIRE_NEVER);
    $admin->DBUpdate();
    $organization = MetaModel::NewObject('Organization', ['name' => 'CI persistence check', 'code' => 'CI-PERSIST']);
    $organization->DBInsert();
} elseif ($mode === 'verify') {
    if (!UserRights::CheckCredentials('admin', $newPassword)) {
        throw new RuntimeException('Restart reset the administrator password.');
    }
    UserRights::Login('admin');
    if (!MetaModel::GetObjectFromOQL('SELECT Organization WHERE code = :code', ['code' => 'CI-PERSIST'], true)) {
        throw new RuntimeException('Restart lost application data.');
    }
} else {
    throw new RuntimeException('Unknown integration test mode.');
}
echo "Bootstrap integration check passed.\n";
