<?php

$webPath = __DIR__ . '/../routes/web.php';
$file = file_get_contents($webPath);
$file = str_replace("'/hr/", "'", $file);
$file = str_replace('"hr/', '"', $file);
$file = str_replace("'hr/", "'", $file);

$legacyRedirect = <<<'PHP'

    // Legacy /hr/* URLs → direct paths (bookmarks, old links)
    Route::any('hr/{path}', function (string $path) {
        $query = request()->getQueryString();
        $target = '/' . $path . ($query ? '?' . $query : '');

        return redirect($target, 301);
    })->where('path', '.*');

PHP;

if (!str_contains($file, 'Legacy /hr/* URLs')) {
    $file = str_replace(
        "    Route::get('api/activity-logs/latest', [App\\Http\\Controllers\\ActivityLogController::class, 'latest'])->name('api.activity-logs.latest');\n});",
        "    Route::get('api/activity-logs/latest', [App\\Http\\Controllers\\ActivityLogController::class, 'latest'])->name('api.activity-logs.latest');" . $legacyRedirect . "\n});",
        $file
    );
}

file_put_contents($webPath, $file);
echo "Updated routes/web.php\n";
