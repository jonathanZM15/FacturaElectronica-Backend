<?php
/**
 * Script to tail the latest logs - run this before testing the upload
 */

$logPath = storage_path('logs/laravel.log');

if (!file_exists($logPath)) {
    echo "Log file not found at: $logPath\n";
    exit(1);
}

// Get file size
$fileSize = filesize($logPath);
// Read last 5000 characters
$handle = fopen($logPath, 'r');
fseek($handle, max(0, $fileSize - 5000));
$content = fread($handle, 5000);
fclose($handle);

// Print with timestamp
echo "=== LAST 5000 CHARACTERS OF LARAVEL LOG ===\n";
echo "Read at: " . date('Y-m-d H:i:s') . "\n";
echo "File size: " . number_format($fileSize) . " bytes\n";
echo "---\n";
echo $content;
echo "\n---\n";
?>
