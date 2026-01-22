<?php


require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../bootstrap.php';

use Config\Notification_Queue;

// Prevent multiple instances from running simultaneously
$lockFile = __DIR__ . '/../logs/notification_worker.lock';
if (file_exists($lockFile)) {
    $lockTime = file_get_contents($lockFile);
    // If lock is older than 5 minutes, assume previous process crashed
    if (time() - $lockTime > 300) {
        unlink($lockFile);
    } else {
        echo "Worker already running\n";
        exit(0);
    }
}

// Create lock file
file_put_contents($lockFile, time());

try {
    $queue = new Notification_Queue();

    // Process up to 50 notifications per run
    $processed = $queue->processQueue(50);

    // Log processing stats
    $stats = $queue->getQueueStats();
    $logMessage = sprintf(
        "[%s] Processed %d notifications. Queue stats: %s\n",
        date('Y-m-d H:i:s'),
        $processed,
        json_encode($stats)
    );

    $logFile = __DIR__ . '/../logs/notification_worker.log';
    if (!is_dir(dirname($logFile))) {
        mkdir(dirname($logFile), 0777, true);
    }
    file_put_contents($logFile, $logMessage, FILE_APPEND);

    echo "Processed {$processed} notifications\n";

} catch (Exception $e) {
    $errorLog = __DIR__ . '/../logs/notification_worker_error.log';
    $errorMessage = sprintf(
        "[%s] Worker error: %s in %s:%d\n",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    );
    file_put_contents($errorLog, $errorMessage, FILE_APPEND);
    echo "Error: " . $e->getMessage() . "\n";
} finally {
    // Remove lock file
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
}
