<?php

require __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . "/../../../bootstrap.php";

use Config\API_Status_Code;
use Config\API_User_Response;
use Config\DB_Calls_Functions;
use Config\Utility_Functions;
use Config\Observability;

header("Content-Type: application/json");

$api_status_code_class_call = new API_Status_Code;
$db_call_class = new DB_Calls_Functions;
$utility_class_call = new Utility_Functions;

$api_method = "GET";

if (getenv('REQUEST_METHOD') === $api_method) {
    try {
        // 1) Validate admin token
        $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1, 1);
        $admin_pubkey = $decodedToken->usertoken;

        // 2) Verify admin
        $getAdmin = $db_call_class->selectRows("admins", "id, fname, lname, email", [[
            ['column' => 'adminpubkey', 'operator' => '=', 'value' => $admin_pubkey]
        ]], ['limit' => 1]);

        if ($utility_class_call->input_is_invalid($getAdmin)) {
            $api_status_code_class_call->respondUnauthorized();
        }

        // 3) Get query parameters
        $action = $_GET['action'] ?? 'overview';
        $date = $_GET['date'] ?? date('Y-m-d');
        $endpoint = $_GET['endpoint'] ?? null;

        $observability = new Observability();

        switch ($action) {
            case 'performance':
                // Get performance metrics
                $metrics = $observability->getPerformanceMetrics($date, $endpoint);
                $api_status_code_class_call->respondOK($metrics, "Performance metrics retrieved successfully");
                break;

            case 'errors':
                // Get error metrics
                $metrics = $observability->getErrorMetrics($date);
                $api_status_code_class_call->respondOK($metrics, "Error metrics retrieved successfully");
                break;

            case 'health':
                // Get system health
                $health = $observability->getSystemHealth();
                $api_status_code_class_call->respondOK($health, "System health retrieved successfully");
                break;

            case 'overview':
            default:
                // Get comprehensive overview
                $performance = $observability->getPerformanceMetrics($date);
                $errors = $observability->getErrorMetrics($date);
                $health = $observability->getSystemHealth();

                $overview = [
                    'date' => $date,
                    'performance_summary' => [
                        'total_requests' => $performance['total_requests'] ?? 0,
                        'error_rate' => $performance['error_rate'] ?? 0,
                        'avg_response_time' => $performance['endpoint_metrics'][0]['avg_duration'] ?? 0
                    ],
                    'error_summary' => [
                        'total_errors' => $errors['total_errors'] ?? 0,
                        'error_types' => count($errors['error_groups'] ?? [])
                    ],
                    'system_health' => $health,
                    'top_endpoints' => array_slice($performance['endpoint_metrics'] ?? [], 0, 5),
                    'recent_errors' => $errors['recent_errors'] ?? []
                ];

                $api_status_code_class_call->respondOK($overview, "Dashboard overview retrieved successfully");
                break;
        }

    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
