<?php
/**
 * Script to CLEAR the log file before testing
 * Run this, then test the upload, then run tail_logs.php
 */

$logPath = storage_path('logs/laravel.log');

if (file_exists($logPath)) {
    file_put_contents($logPath, "=== LOG CLEARED AT " . date('Y-m-d H:i:s') . " ===\n\n");
    echo "✓ Log file cleared successfully\n";
    echo "  Location: $logPath\n";
    echo "\nNow test the logo upload and then run: php tail_logs.php\n";
} else {
    echo "✗ Log file not found at: $logPath\n";
}
?>
