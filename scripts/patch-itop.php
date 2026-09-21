<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Keep this narrow patch explicit: iTop evaluates its configuration before this
// point. Applying overrides here also handles configurations rewritten by setup.
$path = $argv[1] ?? '';
$source = file_get_contents($path);
$anchor = '\t\tforeach ($MySettings as $sPropCode => $rawvalue) {';
$anchor = str_replace('\\t', "\t", $anchor);
if (substr_count($source, $anchor) !== 1) {
    throw new RuntimeException('The upstream configuration loader changed; review the Cloudron patch.');
}
$insertion = "\t\t// Cloudron package: resolve managed credentials on every config load.\n"
    . '\t\t$MySettings = array_replace($MySettings, require \'/app/code/cloudron-settings.php\');' . "\n\n";
$insertion = str_replace('\\t', "\t", $insertion);
if (file_put_contents($path, str_replace($anchor, $insertion . $anchor, $source)) === false) {
    throw new RuntimeException('Could not apply Cloudron configuration patch.');
}
