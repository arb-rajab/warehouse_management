<?php

use Illuminate\Support\Facades\File;

/**
 * The legacy gateway and push-notification code used to switch off
 * CURLOPT_SSL_VERIFYPEER/VERIFYHOST (Semgrep's curl-ssl-verifypeer-off rule
 * fails CI on it). Nothing here may turn certificate checking off again.
 */
test('no app code disables curl certificate verification', function () {
    $offenders = [];

    foreach (File::allFiles(app_path()) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        if (preg_match('/CURLOPT_SSL_VERIFY(?:PEER|HOST)\s*,\s*(?:false|0)\b/i', $file->getContents()) === 1) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([]);
});
