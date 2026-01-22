<?php

namespace Config;

/**
 * Observability Middleware
 * Include this at the top of API endpoints to automatically add logging and metrics
 */
class Observability_Middleware
{
    private static $observability;
    private static $context = [];

    /**
     * Initialize observability for the current request
     * Call this at the beginning of API endpoints
     */
    public static function init(array $additionalContext = []): Observability
    {
        self::$observability = new Observability();

        // Set default context
        self::$context = array_merge([
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ], $additionalContext);

        // Add request ID to response headers
        if (!headers_sent()) {
            header('X-Request-ID: ' . self::$observability->getRequestId());
        }

        return self::$observability;
    }

    /**
     * Set user context after authentication
     */
    public static function setUserContext(int $userId = null, string $userRole = 'anonymous'): void
    {
        self::$context['user_id'] = $userId;
        self::$context['user_role'] = $userRole;
    }

    /**
     * Log successful API response
     */
    public static function logSuccess(string $endpoint, int $statusCode = 200): void
    {
        if (self::$observability) {
            self::$observability->logRequestComplete($endpoint, $statusCode, self::$context);
        }
    }

    /**
     * Log API error
     */
    public static function logError(string $endpoint, string $error, int $statusCode = 500): void
    {
        if (self::$observability) {
            self::$observability->logApiError($endpoint, $error, $statusCode, self::$context);
        }
    }

    /**
     * Log security event
     */
    public static function logSecurityEvent(string $event, array $data = []): void
    {
        if (self::$observability) {
            self::$observability->logSecurityEvent($event, $data, self::$context);
        }
    }

    /**
     * Log business event
     */
    public static function logBusinessEvent(string $event, array $data = []): void
    {
        if (self::$observability) {
            self::$observability->logBusinessEvent($event, $data, self::$context);
        }
    }

    /**
     * Get current request ID
     */
    public static function getRequestId(): string
    {
        return self::$observability ? self::$observability->getRequestId() : 'unknown';
    }

    /**
     * Enhanced error handler that includes observability
     */
    public static function handleException(\Exception $e, string $endpoint): void
    {
        self::logError($endpoint, $e->getMessage(), 500);

        // Re-throw to let normal error handling continue
        throw $e;
    }

    /**
     * Wrapper for API functions with automatic observability
     */
    public static function observeApiCall(callable $apiFunction, string $endpoint): mixed
    {
        try {
            if (self::$observability) {
                self::$observability->logRequestStart($endpoint, self::$context);
            }

            $result = $apiFunction();

            self::logSuccess($endpoint, 200);

            return $result;
        } catch (\Exception $e) {
            self::logError($endpoint, $e->getMessage(), 500);
            throw $e;
        }
    }
}

/**
 * Helper function to quickly add observability to any API endpoint
 *
 * Usage in API endpoint:
 * $obs = initObservability(['user_role' => 'admin', 'user_id' => $adminId]);
 *
 * @param array $context
 * @return Observability
 */
function initObservability(array $context = []): Observability
{
    return Observability_Middleware::init($context);
}

/**
 * Helper function to set user context
 *
 * Usage:
 * setObservabilityUser($userId, 'admin');
 *
 * @param int|null $userId
 * @param string $userRole
 */
function setObservabilityUser(int $userId = null, string $userRole = 'anonymous'): void
{
    Observability_Middleware::setUserContext($userId, $userRole);
}

/**
 * Helper function to log successful API completion
 *
 * Usage:
 * logApiSuccess('/api/admin/bookings/approve');
 *
 * @param string $endpoint
 * @param int $statusCode
 */
function logApiSuccess(string $endpoint, int $statusCode = 200): void
{
    Observability_Middleware::logSuccess($endpoint, $statusCode);
}

/**
 * Helper function to log API errors
 *
 * Usage:
 * logApiError('/api/admin/bookings/approve', 'Database connection failed');
 *
 * @param string $endpoint
 * @param string $error
 * @param int $statusCode
 */
function logApiError(string $endpoint, string $error, int $statusCode = 500): void
{
    Observability_Middleware::logError($endpoint, $error, $statusCode);
}
