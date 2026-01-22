<?php

namespace Config;

class Brute_Force_Protection
{
    private $db;
    private $cache;

    // Configuration constants
    const MAX_LOGIN_ATTEMPTS = 5;
    const LOCKOUT_DURATION = 7200; // 2 hours in seconds
    const RESET_WINDOW = 3600; // 1 hour window to track attempts

    public function __construct()
    {
        $this->db = new DB_Calls_Functions();
        $this->cache = new CacheSystem();
    }

    /**
     * Record a failed login attempt
     * @param string $identifier Usually email or username
     * @param string $ipAddress Client IP address
     * @return bool True if account should be locked
     */
    public function recordFailedAttempt(string $identifier, string $ipAddress): bool
    {
        $key = "failed_attempts:{$identifier}";
        $now = time();

        // Get existing attempts from cache
        $attempts = $this->cache->getCache($key);
        if (!$attempts) {
            $attempts = [];
        } else {
            $attempts = json_decode($attempts, true);
        }

        // Add new attempt
        $attempts[] = [
            'timestamp' => $now,
            'ip' => $ipAddress
        ];

        // Remove attempts outside the reset window
        $attempts = array_filter($attempts, function($attempt) use ($now) {
            return ($now - $attempt['timestamp']) < self::RESET_WINDOW;
        });

        // Store back in cache
        $this->cache->setCache($key, self::RESET_WINDOW + 60, json_encode($attempts));

        // Check if threshold exceeded
        if (count($attempts) >= self::MAX_LOGIN_ATTEMPTS) {
            $this->lockAccount($identifier);
            return true;
        }

        return false;
    }

    /**
     * Check if account is currently locked
     * @param string $identifier
     * @return bool True if locked
     */
    public function isAccountLocked(string $identifier): bool
    {
        $lockKey = "account_locked:{$identifier}";
        $lockTime = $this->cache->getCache($lockKey);

        if (!$lockTime) {
            return false;
        }

        $now = time();
        if ($now < $lockTime) {
            return true; // Still locked
        } else {
            // Lock expired, remove it
            $this->cache->deleteCache($lockKey);
            return false;
        }
    }

    /**
     * Lock an account after too many failed attempts
     * @param string $identifier
     */
    private function lockAccount(string $identifier): void
    {
        $lockKey = "account_locked:{$identifier}";
        $unlockTime = time() + self::LOCKOUT_DURATION;

        $this->cache->setCache($lockKey, self::LOCKOUT_DURATION + 60, $unlockTime);

        // Log the lockout
        $this->logSecurityEvent('account_locked', $identifier, [
            'reason' => 'too_many_failed_attempts',
            'unlock_time' => date('Y-m-d H:i:s', $unlockTime)
        ]);
    }

    /**
     * Clear failed attempts (on successful login)
     * @param string $identifier
     */
    public function clearFailedAttempts(string $identifier): void
    {
        $key = "failed_attempts:{$identifier}";
        $this->cache->deleteCache($key);
    }

    /**
     * Get remaining attempts before lockout
     * @param string $identifier
     * @return int Remaining attempts
     */
    public function getRemainingAttempts(string $identifier): int
    {
        $key = "failed_attempts:{$identifier}";
        $attempts = $this->cache->getCache($key);

        if (!$attempts) {
            return self::MAX_LOGIN_ATTEMPTS;
        }

        $attempts = json_decode($attempts, true);
        $now = time();

        // Filter recent attempts
        $recentAttempts = array_filter($attempts, function($attempt) use ($now) {
            return ($now - $attempt['timestamp']) < self::RESET_WINDOW;
        });

        return max(0, self::MAX_LOGIN_ATTEMPTS - count($recentAttempts));
    }

    /**
     * Get lockout status and remaining time
     * @param string $identifier
     * @return array|null [locked: bool, remaining_seconds: int] or null if not locked
     */
    public function getLockoutStatus(string $identifier): ?array
    {
        if (!$this->isAccountLocked($identifier)) {
            return null;
        }

        $lockKey = "account_locked:{$identifier}";
        $unlockTime = $this->cache->getCache($lockKey);
        $remaining = $unlockTime - time();

        return [
            'locked' => true,
            'remaining_seconds' => max(0, $remaining),
            'unlock_time' => date('Y-m-d H:i:s', $unlockTime)
        ];
    }

    /**
     * Check if login attempt is allowed
     * @param string $identifier
     * @param string $ipAddress
     * @return array [allowed: bool, reason: string, remaining_attempts: int, lockout_info: array|null]
     */
    public function checkLoginAttempt(string $identifier, string $ipAddress): array
    {
        if ($this->isAccountLocked($identifier)) {
            $lockoutInfo = $this->getLockoutStatus($identifier);
            return [
                'allowed' => false,
                'reason' => 'account_locked',
                'remaining_attempts' => 0,
                'lockout_info' => $lockoutInfo
            ];
        }

        $remaining = $this->getRemainingAttempts($identifier);

        return [
            'allowed' => true,
            'reason' => 'ok',
            'remaining_attempts' => $remaining,
            'lockout_info' => null
        ];
    }

    /**
     * Handle failed login attempt
     * @param string $identifier
     * @param string $ipAddress
     * @return array [locked: bool, remaining_attempts: int]
     */
    public function handleFailedLogin(string $identifier, string $ipAddress): array
    {
        $locked = $this->recordFailedAttempt($identifier, $ipAddress);
        $remaining = $this->getRemainingAttempts($identifier);

        return [
            'locked' => $locked,
            'remaining_attempts' => $remaining
        ];
    }

    /**
     * Handle successful login (clear attempts)
     * @param string $identifier
     */
    public function handleSuccessfulLogin(string $identifier): void
    {
        $this->clearFailedAttempts($identifier);
    }

    /**
     * Log security events
     * @param string $event
     * @param string $identifier
     * @param array $data
     */
    private function logSecurityEvent(string $event, string $identifier, array $data = []): void
    {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'identifier' => $identifier,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'data' => $data
        ];

        $logFile = __DIR__ . '/../logs/security_events_' . date('Y-m-d') . '.log';
        if (!is_dir(dirname($logFile))) {
            mkdir(dirname($logFile), 0777, true);
        }

        $logEntry = json_encode($logData) . PHP_EOL;
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }

    /**
     * Get security statistics
     * @return array
     */
    public function getSecurityStats(): array
    {
        // This would require scanning cache keys, which might be expensive
        // For now, return basic info
        return [
            'max_attempts' => self::MAX_LOGIN_ATTEMPTS,
            'lockout_duration_hours' => self::LOCKOUT_DURATION / 3600,
            'reset_window_hours' => self::RESET_WINDOW / 3600
        ];
    }
}
