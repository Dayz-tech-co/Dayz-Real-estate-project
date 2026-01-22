<?php
require_once 'bootstrap.php';

use Config\CacheSystem;

// Test the caching system
echo "Testing Cache System\n";
echo "===================\n\n";

try {
    // Test with PHPFastCache (file-based)
    echo "1. Testing PHPFastCache (file-based):\n";
    $cache = new CacheSystem('phpfastcache');

    // Test set and get
    $testKey = 'test_key_' . time();
    $testValue = 'Hello from cache! Time: ' . date('Y-m-d H:i:s');

    echo "   Setting cache: $testKey => $testValue\n";
    $cache->setCache($testKey, 300, $testValue); // 5 minutes TTL

    echo "   Getting cache: ";
    $retrieved = $cache->getCache($testKey);
    echo $retrieved ? $retrieved : 'null';
    echo "\n";

    // Test delete
    echo "   Deleting cache...\n";
    $cache->deleteCache($testKey);
    $afterDelete = $cache->getCache($testKey);
    echo "   After delete: " . ($afterDelete ? $afterDelete : 'null') . "\n\n";

    // Test with Redis if available
    echo "2. Testing Redis (if available):\n";
    try {
        $redisCache = new CacheSystem('redis');

        $redisKey = 'redis_test_' . time();
        $redisValue = 'Redis cache test: ' . date('Y-m-d H:i:s');

        echo "   Setting Redis cache: $redisKey => $redisValue\n";
        $redisCache->setCache($redisKey, 300, $redisValue);

        echo "   Getting Redis cache: ";
        $redisRetrieved = $redisCache->getCache($redisKey);
        echo $redisRetrieved ? $redisRetrieved : 'null';
        echo "\n";

        echo "   Deleting Redis cache...\n";
        $redisCache->deleteCache($redisKey);

    } catch (Exception $e) {
        echo "   Redis test failed: " . $e->getMessage() . "\n";
        echo "   (This is normal if Redis server is not running)\n";
    }

    echo "\nCache testing completed successfully!\n";

} catch (Exception $e) {
    echo "Error during testing: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
