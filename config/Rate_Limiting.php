<?php

namespace Config;

class Rate_Limiting
{
    private $db;
    private $cache;

    public function __construct()
    {
        $this->db = new DB_Calls_Functions();
        $this->cache = new CacheSystem();
    }

    /**
     * Check if request is within rate limits
     * @param string $identifier Unique identifier (IP + user_id or just IP)
     * @param string $action Action type (login, booking, upload, etc.)
     * @param int $maxRequests Maximum requests allowed
     * @param int $timeWindow Time window in seconds
     * @return bool True if allowed, false if rate limited
     */
    public function checkRateLimit(string $identifier, string $action, int $maxRequests, int $timeWindow): bool
    {
        $key = "rate_limit:{$action}:{$identifier}";
        $now = time();

        // Get existing requests from cache
        $requests = $this->cache->getCache($key);
        if (!$requests) {
            $requests = [];
        } else {
            $requests = json_decode($requests, true);
        }

        // Remove old requests outside the time window
        $requests = array_filter($requests, function($timestamp) use ($now, $timeWindow) {
            return ($now - $timestamp) < $timeWindow;
        });

        // Check if under limit
        if (count($requests) < $maxRequests) {
            // Add current request
            $requests[] = $now;

            // Store back in cache (expire after time window + buffer)
            $this->cache->setCache($key, $timeWindow + 60, json_encode($requests));

            return true;
        }

        return false;
    }

    /**
     * Get remaining requests and reset time for a rate limit
     * @param string $identifier
     * @param string $action
     * @param int $maxRequests
     * @param int $timeWindow
     * @return array [remaining, reset_time]
     */
    public function getRateLimitInfo(string $identifier, string $action, int $maxRequests, int $timeWindow): array
    {
        $key = "rate_limit:{$action}:{$identifier}";
        $now = time();

        $requests = $this->cache->getCache($key);
        if (!$requests) {
            return ['remaining' => $maxRequests, 'reset_time' => $now + $timeWindow];
        }

        $requests = json_decode($requests, true);

        // Remove old requests
        $requests = array_filter($requests, function($timestamp) use ($now, $timeWindow) {
            return ($now - $timestamp) < $timeWindow;
        });

        $used = count($requests);
        $remaining = max(0, $maxRequests - $used);

        // Find oldest request to calculate reset time
        if (!empty($requests)) {
            $oldest = min($requests);
            $reset_time = $oldest + $timeWindow;
        } else {
            $reset_time = $now + $timeWindow;
        }

        return ['remaining' => $remaining, 'reset_time' => $reset_time];
    }

    /**
     * Check login rate limit (5 per minute)
     */
    public function checkLoginRateLimit(string $identifier): bool
    {
        return $this->checkRateLimit($identifier, 'login', 5, 60);
    }

    /**
     * Check password reset rate limit (5 per minute)
     */
    public function checkPasswordResetRateLimit(string $identifier): bool
    {
        return $this->checkRateLimit($identifier, 'password_reset', 5, 60);
    }

    /**
     * Check booking creation rate limit (10 per hour per user)
     */
    public function checkBookingRateLimit(int $userId): bool
    {
        return $this->checkRateLimit("user_{$userId}", 'booking', 10, 3600);
    }

    /**
     * Check file upload rate limit (reasonable limits for uploads)
     */
    public function checkUploadRateLimit(string $identifier): bool
    {
        return $this->checkRateLimit($identifier, 'upload', 20, 3600); // 20 uploads per hour
    }

    /**
     * Get rate limit headers for API responses
     */
    public function getRateLimitHeaders(string $identifier, string $action, int $maxRequests, int $timeWindow): array
    {
        $info = $this->getRateLimitInfo($identifier, $action, $maxRequests, $timeWindow);

        return [
            'X-RateLimit-Limit' => $maxRequests,
            'X-RateLimit-Remaining' => $info['remaining'],
            'X-RateLimit-Reset' => $info['reset_time'],
            'X-RateLimit-Retry-After' => $info['remaining'] === 0 ? ($info['reset_time'] - time()) : 0
        ];
    }
}
