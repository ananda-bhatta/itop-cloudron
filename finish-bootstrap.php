<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
if (PHP_SAPI !== 'cli') {
    exit(1);
}
$credentials = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
require '/app/data/public/approot.inc.php';
require APPROOT . 'application/startup.inc.php';
$admin = MetaModel::GetObjectFromOQL('SELECT UserLocal WHERE login = :login', ['login' => $credentials['admin_user']], true);
if (!$admin || !$admin->CheckCredentials($credentials['admin_password'])) {
    throw new RuntimeException('The initial administrator account could not be verified.');
}
$cron = MetaModel::GetObjectFromOQL('SELECT UserLocal WHERE login = :login', ['login' => $credentials['cron_user']], true);
if (!$cron) {
    $profile = MetaModel::GetObjectFromOQL('SELECT URP_Profiles WHERE name = :name', ['name' => ADMIN_PROFILE_NAME], true);
    if (!$profile) {
        throw new RuntimeException('The Administrator profile is missing.');
    }
    $cron = new UserLocal();
    $cron->Set('login', $credentials['cron_user']);
    $cron->Set('password', $credentials['cron_password']);
    $cron->Set('language', 'EN US');
    $cron->Set('expiration', UserLocal::EXPIRE_NEVER);
    $profiles = new ormLinkSet(UserLocal::class, 'profile_list', DBObjectSet::FromScratch(URP_UserProfile::class));
    $profiles->AddItem(MetaModel::NewObject('URP_UserProfile', [
        'profileid' => $profile->GetKey(), 'reason' => 'Cloudron background tasks',
    ]));
    $cron->Set('profile_list', $profiles);
    $cron->DBInsert();
} elseif (!$cron->CheckCredentials($credentials['cron_password'])) {
    throw new RuntimeException('An existing background task account has different credentials; refusing to overwrite it.');
}
$admin->Set('expiration', UserLocal::EXPIRE_FORCE);
$admin->DBUpdate();
echo "Initial administrator and background task account verified.\n";
