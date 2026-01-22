<?php

namespace Config;

class Observability
{
    private $db;
    private $startTime;
    private $requestId;

    public function __construct()
    {
        $this->db = new DB_Calls_Functions();
        $this->startTime = microtime(true);
        $this->requestId = $this->generateRequestId();
    }

    /**
     * Generate unique request ID
     */
    private function generateRequestId(): string
    {
        return sprintf(
            '%s-%s-%s-%s-%s',
            bin2hex(random_bytes(4)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(6))
        );
    }

    /**
     * Get current request ID
     */
    public function getRequestId(): string
    {
        return $this->requestId;
    }

    /**
     * Log structured event
     */
    public function logEvent(string $level, string $event, array $data = [], array $context = []): void
    {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s.u'),
            'request_id' => $this->requestId,
            'level' => strtoupper($level),
            'event' => $event,
            'data' => $data,
            'context' => array_merge($context, [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
                'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                'user_id' => $context['user_id'] ?? null,
                'user_role' => $context['user_role'] ?? null
            ])
        ];

        $this->writeLogEntry($logEntry);

        // Store error metrics for admin dashboard
        if (in_array($level, ['error', 'critical', 'alert'])) {
            $this->recordErrorMetric($event, $data, $context);
        }
    }

    /**
     * Log API request start
     */
    public function logRequestStart(string $endpoint, array $context = []): void
    {
        $this->logEvent('info', 'request_start', [
            'endpoint' => $endpoint,
            'start_time' => $this->startTime
        ], $context);
    }

    /**
     * Log API request completion with performance metrics
     */
    public function logRequestComplete(string $endpoint, int $statusCode, array $context = []): void
    {
        $endTime = microtime(true);
        $duration = round(($endTime - $this->startTime) * 1000, 2); // milliseconds

        $this->logEvent('info', 'request_complete', [
            'endpoint' => $endpoint,
            'status_code' => $statusCode,
            'duration_ms' => $duration,
            'memory_peak' => memory_get_peak_usage(true)
        ], $context);

        // Record performance metric
        $this->recordPerformanceMetric($endpoint, $statusCode, $duration, $context);
    }

    /**
     * Log API error
     */
    public function logApiError(string $endpoint, string $error, int $statusCode = 500, array $context = []): void
    {
        $this->logEvent('error', 'api_error', [
            'endpoint' => $endpoint,
            'error' => $error,
            'status_code' => $statusCode,
            'duration_ms' => round((microtime(true) - $this->startTime) * 1000, 2)
        ], $context);
    }

    /**
     * Log security event
     */
    public function logSecurityEvent(string $event, array $data = [], array $context = []): void
    {
        $this->logEvent('warning', 'security_event', array_merge($data, [
            'security_event' => $event
        ]), $context);
    }

    /**
     * Log business event
     */
    public function logBusinessEvent(string $event, array $data = [], array $context = []): void
    {
        $this->logEvent('info', 'business_event', array_merge($data, [
            'business_event' => $event
        ]), $context);
    }

    /**
     * Record performance metric for dashboard
     */
    private function recordPerformanceMetric(string $endpoint, int $statusCode, float $duration, array $context): void
    {
        try {
            $metricData = [
                'endpoint' => $endpoint,
                'status_code' => $statusCode,
                'duration_ms' => $duration,
                'user_role' => $context['user_role'] ?? 'anonymous',
                'timestamp' => date('Y-m-d H:i:s'),
                'date' => date('Y-m-d'),
                'hour' => date('H')
            ];

            $this->db->insertRow("performance_metrics", $metricData);
        } catch (\Exception $e) {
            // Don't fail the request if metrics recording fails
            error_log("Failed to record performance metric: " . $e->getMessage());
        }
    }

    /**
     * Record error metric for dashboard
     */
    private function recordErrorMetric(string $event, array $data, array $context): void
    {
        try {
            $metricData = [
                'error_type' => $event,
                'endpoint' => $data['endpoint'] ?? 'unknown',
                'error_message' => $data['error'] ?? 'Unknown error',
                'user_role' => $context['user_role'] ?? 'anonymous',
                'status_code' => $data['status_code'] ?? 500,
                'timestamp' => date('Y-m-d H:i:s'),
                'date' => date('Y-m-d'),
                'hour' => date('H')
            ];

            $this->db->insertRow("error_metrics", $metricData);
        } catch (\Exception $e) {
            // Don't fail the request if error recording fails
            error_log("Failed to record error metric: " . $e->getMessage());
        }
    }

    /**
     * Write log entry to appropriate log file
     */
    private function writeLogEntry(array $logEntry): void
    {
        $level = strtolower($logEntry['level']);
        $date = date('Y-m-d');

        // Choose log file based on level
        $logFiles = [
            'debug' => "logs/debug_{$date}.log",
            'info' => "logs/info_{$date}.log",
            'warning' => "logs/warning_{$date}.log",
            'error' => "logs/error_{$date}.log",
            'critical' => "logs/critical_{$date}.log"
        ];

        $logFile = __DIR__ . '/../' . ($logFiles[$level] ?? $logFiles['info']);

        // Ensure log directory exists
        if (!is_dir(dirname($logFile))) {
            mkdir(dirname($logFile), 0777, true);
        }

        // Write structured JSON log entry
        $logLine = json_encode($logEntry) . PHP_EOL;
        file_put_contents($logFile, $logLine, FILE_APPEND);
    }

    /**
     * Get performance metrics for admin dashboard
     */
    public function getPerformanceMetrics(string $date = null, string $endpoint = null): array
    {
        $date = $date ?? date('Y-m-d');

        try {
            $conditions = [[['column' => 'date', 'operator' => '=', 'value' => $date]]];

            if ($endpoint) {
                $conditions[] = [['column' => 'endpoint', 'operator' => '=', 'value' => $endpoint]];
            }

            $metrics = $this->db->selectRows(
                "performance_metrics",
                "endpoint, status_code, COUNT(*) as request_count, AVG(duration_ms) as avg_duration, MAX(duration_ms) as max_duration, MIN(duration_ms) as min_duration",
                $conditions,
                [
                    'group_by' => 'endpoint, status_code',
                    'order_by' => 'request_count DESC'
                ]
            );

            // Calculate error rates
            $totalRequests = array_sum(array_column($metrics, 'request_count'));
            $errorRequests = array_sum(array_map(function($metric) {
                return $metric['status_code'] >= 400 ? $metric['request_count'] : 0;
            }, $metrics));

            return [
                'date' => $date,
                'total_requests' => $totalRequests,
                'error_requests' => $errorRequests,
                'error_rate' => $totalRequests > 0 ? round(($errorRequests / $totalRequests) * 100, 2) : 0,
                'endpoint_metrics' => $metrics
            ];
        } catch (\Exception $e) {
            return ['error' => 'Failed to retrieve performance metrics: ' . $e->getMessage()];
        }
    }

    /**
     * Get error metrics for admin dashboard
     */
    public function getErrorMetrics(string $date = null, int $limit = 50): array
    {
        $date = $date ?? date('Y-m-d');

        try {
            $errors = $this->db->selectRows(
                "error_metrics",
                "*",
                [[['column' => 'date', 'operator' => '=', 'value' => $date]]],
                [
                    'order_by' => 'timestamp DESC',
                    'limit' => $limit
                ]
            );

            // Group by error type
            $errorGroups = [];
            foreach ($errors as $error) {
                $type = $error['error_type'];
                if (!isset($errorGroups[$type])) {
                    $errorGroups[$type] = [
                        'type' => $type,
                        'count' => 0,
                        'last_occurred' => $error['timestamp'],
                        'endpoints' => []
                    ];
                }
                $errorGroups[$type]['count']++;
                $errorGroups[$type]['endpoints'][$error['endpoint']] = ($errorGroups[$type]['endpoints'][$error['endpoint']] ?? 0) + 1;
            }

            return [
                'date' => $date,
                'total_errors' => count($errors),
                'error_groups' => array_values($errorGroups),
                'recent_errors' => array_slice($errors, 0, 10)
            ];
        } catch (\Exception $e) {
            return ['error' => 'Failed to retrieve error metrics: ' . $e->getMessage()];
        }
    }

    /**
     * Get system health metrics
     */
    public function getSystemHealth(): array
    {
        try {
            // Database connection health
            $dbHealth = 'healthy';
            try {
                $this->db->selectRows("users", "COUNT(*) as count", [], ['limit' => 1]);
            } catch (\Exception $e) {
                $dbHealth = 'unhealthy: ' . $e->getMessage();
            }

            // Cache health
            $cacheHealth = 'healthy';
            try {
                $cache = new CacheSystem();
                $testKey = 'health_check_' . time();
                $cache->setCache($testKey, 10, 'test');
                $cache->getCache($testKey);
                $cache->deleteCache($testKey);
            } catch (\Exception $e) {
                $cacheHealth = 'unhealthy: ' . $e->getMessage();
            }

            // Disk space
            $diskFree = disk_free_space('/');
            $diskTotal = disk_total_space('/');
            $diskUsage = $diskTotal > 0 ? round((($diskTotal - $diskFree) / $diskTotal) * 100, 2) : 0;

            // Memory usage
            $memoryUsage = memory_get_usage(true);
            $memoryPeak = memory_get_peak_usage(true);

            return [
                'timestamp' => date('Y-m-d H:i:s'),
                'database' => $dbHealth,
                'cache' => $cacheHealth,
                'disk_usage_percent' => $diskUsage,
                'memory_current' => $memoryUsage,
                'memory_peak' => $memoryPeak,
                'php_version' => PHP_VERSION,
                'server_load' => function_exists('sys_getloadavg') ? sys_getloadavg()[0] : 'unknown'
            ];
        } catch (\Exception $e) {
            return ['error' => 'Failed to get system health: ' . $e->getMessage()];
        }
    }

    /**
     * Middleware function to wrap API endpoints with observability
     */
    public function observeApiCall(callable $apiFunction, string $endpoint, array $context = [])
    {
        try {
            // Log request start
            $this->logRequestStart($endpoint, $context);

            // Execute the API function
            $result = $apiFunction();

            // Log successful completion
            $this->logRequestComplete($endpoint, 200, $context);

            return $result;
        } catch (\Exception $e) {
            // Log error
            $this->logApiError($endpoint, $e->getMessage(), 500, $context);
            throw $e;
        }
    }
}
