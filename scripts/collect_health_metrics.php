<?php
// Background script to collect system health metrics
// This script should be run periodically via cron job
//
// Example cron job (run every 5 minutes):
// */5 * * * * php /path/to/project/scripts/collect_health_metrics.php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../bootstrap.php';

use Config\Observability;
use Config\DB_Calls_Functions;

// Prevent multiple instances from running simultaneously
$lockFile = __DIR__ . '/../logs/health_collector.lock';
if (file_exists($lockFile)) {
    $lockTime = file_get_contents($lockFile);
    // If lock is older than 10 minutes, assume previous process crashed
    if (time() - $lockTime > 600) {
        unlink($lockFile);
    } else {
        echo "Health collector already running\n";
        exit(0);
    }
}

// Create lock file
file_put_contents($lockFile, time());

try {
    $observability = new Observability();
    $db = new DB_Calls_Functions();

    // Get current system health
    $health = $observability->getSystemHealth();

    // Count active database connections (rough estimate)
    $activeConnections = null;
    try {
        // This is a simple way to estimate active connections
        // In production, you might want to use more sophisticated monitoring
        $connections = $db->selectRows("information_schema.processlist", "COUNT(*) as count", [
            [['column' => 'COMMAND', 'operator' => 'IN', 'value' => ['Query', 'Execute']]]
        ]);
        $activeConnections = $connections[0]['count'] ?? null;
    } catch (\Exception $e) {
        // Ignore errors in connection counting
    }

    // Store health snapshot
    $healthData = [
        'database_status' => $health['database'],
        'cache_status' => $health['cache'],
        'disk_usage_percent' => $health['disk_usage_percent'],
        'memory_current' => $health['memory_current'],
        'memory_peak' => $health['memory_peak'],
        'php_version' => $health['php_version'],
        'server_load' => $health['server_load'],
        'active_connections' => $activeConnections
    ];

    $db->insertRow("system_health_snapshots", $healthData);

    // Clean up old health snapshots (keep last 7 days)
    $db->deleteRows("system_health_snapshots", [[
        ['column' => 'timestamp', 'operator' => '<', 'value' => date('Y-m-d H:i:s', strtotime('-7 days'))]
    ]]);

    // Log collection
    $logFile = __DIR__ . '/../logs/health_collector.log';
    $logEntry = sprintf(
        "[%s] Health metrics collected - DB: %s, Cache: %s, Disk: %.2f%%, Memory: %s\n",
        date('Y-m-d H:i:s'),
        $health['database'],
        $health['cache'],
        $health['disk_usage_percent'],
        number_format($health['memory_current'] / 1024 / 1024, 2) . 'MB'
    );
    file_put_contents($logFile, $logEntry, FILE_APPEND);

    echo "Health metrics collected successfully\n";

} catch (Exception $e) {
    $errorLog = __DIR__ . '/../logs/health_collector_error.log';
    $errorMessage = sprintf(
        "[%s] Health collector error: %s in %s:%d\n",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    );
    file_put_contents($errorLog, $errorMessage, FILE_APPEND);
    echo "Error collecting health metrics: " . $e->getMessage() . "\n";
} finally {
    // Remove lock file
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
}
