<?php

$dir = __DIR__ . '/app/Http/Controllers';
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

$removedCount = 0;

foreach ($files as $file) {
    if ($file->getExtension() === 'php') {
        $path = $file->getRealPath();
        
        // Skip Auth controllers which log logins/logouts
        if (strpos($path, 'AuthenticatedSessionController') !== false) {
            continue;
        }

        $content = file_get_contents($path);
        
        // Match $this->logActivity(...) calls and remove them
        $pattern = '/\$this->logActivity\([^;]+;\s*/i';
        
        if (preg_match($pattern, $content)) {
            $newContent = preg_replace($pattern, '', $content);
            file_put_contents($path, $newContent);
            $removedCount++;
            echo "Cleaned: " . $file->getFilename() . "\n";
        }
    }
}

echo "\nSuccessfully removed manual logActivity calls from $removedCount controllers.\n";
