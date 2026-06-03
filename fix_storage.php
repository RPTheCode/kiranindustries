<?php
/**
 * Laravel Storage Link Auto Fix Script
 * 
 * This script helps automatically recreate the public/storage symlink.
 * Useful after uploading a new ZIP or when images are not showing.
 */

try {
    echo "<h2>Laravel Storage Link Fixer</h2>";
    echo "<hr>";
    echo "Step 1: Checking paths...<br>";

    $rootPath = __DIR__;
    $publicStorage = $rootPath . '/public/storage';

    // Step 1: Remove existing link/folder
    if (file_exists($publicStorage)) {
        echo "Found existing path at $publicStorage. Deleting...<br>";
        
        if (is_link($publicStorage)) {
            // It's a symlink
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                exec('del "' . $publicStorage . '"');
            } else {
                unlink($publicStorage);
            }
            echo "✔ Existing symlink deleted.<br>";
        } else {
            // It's a real directory
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                exec('rmdir /s /q "' . $publicStorage . '"');
            } else {
                exec('rm -rf ' . escapeshellarg($publicStorage));
            }
            echo "✔ Existing storage folder deleted.<br>";
        }
    } else {
        echo "No existing storage link found. Proceeding...<br>";
    }

    echo "Step 2: Running 'php artisan storage:link'...<br>";

    // Step 2: Run the command
    // We use shell_exec to get the output
    $output = shell_exec('php artisan storage:link 2>&1');
    
    echo "<div style='background: #f4f4f4; padding: 15px; border: 1px solid #ddd; margin-top: 10px;'>";
    echo "<strong>Artisan Output:</strong><br>";
    echo "<pre>" . ($output ? htmlspecialchars($output) : "No output returned. Please check if PHP is in your system PATH.") . "</pre>";
    echo "</div>";

    echo "<br><h3 style='color: green;'>✔ Process Completed Successfully!</h3>";
    echo "<p>Your images and files should now be visible.</p>";

} catch (Exception $e) {
    echo "<h3 style='color: red;'>✘ Error Occurred:</h3>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
