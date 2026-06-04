<?php

$root = __DIR__ . '/../resources/js';
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
);

$pattern = '/\s*\{\s*title:\s*t\([\'"]HR Management[\'"]\)(?:\s*,\s*href:\s*route\([^)]+\))?\s*\},?\r?\n/';

$count = 0;
foreach ($iterator as $file) {
    if (!preg_match('/\.tsx$/', $file->getPathname())) {
        continue;
    }

    $content = file_get_contents($file->getPathname());
    $updated = preg_replace($pattern, "\n", $content);
    if ($updated !== $content) {
        file_put_contents($file->getPathname(), $updated);
        $count++;
        echo $file->getPathname() . PHP_EOL;
    }
}

echo "Updated {$count} files\n";
