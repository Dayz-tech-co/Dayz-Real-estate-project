<?php
require __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . "/../../../bootstrap.php";

use Config\API_Status_Code;
use Config\API_User_Response;
use Config\DB_Calls_Functions;
use Config\Utility_Functions;

$api_status_code = new API_Status_Code;
$db = new DB_Calls_Functions;
$util = new Utility_Functions;

$api_method = "POST";
header("Content-Type: application/json");

if (getenv('REQUEST_METHOD') === $api_method) {
    try {
        $decoded = $api_status_code->ValidateAPITokenSentIN(1, 3); // User token
        $user_pubkey = $decoded->usertoken;

        // Fetch user
        $user = $db->selectRows("users", "id", [[
            ['column' => 'userpubkey', 'operator' => '=', 'value' => $user_pubkey]
        ]]);
        if ($util->input_is_invalid($user)) $api_status_code->respondUnauthorized();
        $user_id = $user[0]['id'];

        // Filters and pagination
        $page  = isset($_POST['page']) ? (int)$_POST['page'] : 1;
        $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 10;
        $offset = ($page - 1) * $limit;
        $status = isset($_POST['status']) ? strtolower(trim($_POST['status'])) : '';
        $type   = isset($_POST['type']) ? strtolower(trim($_POST['type'])) : '';

        $conditions = [[['column' => 'user_id', 'operator' => '=', 'value' => $user_id]]];

        // Optional filters
        if (in_array($status, ['read','unread'])) {
            $conditions[0][] = ['column' => 'is_read', 'operator' => '=', 'value' => $status === 'read' ? 1 : 0];
        }
        if (!empty($type)) {
            $conditions[0][] = ['column' => 'type', 'operator' => '=', 'value' => $type];
        }

        // Count total
        $totalRow = $db->selectRows("notifications", "COUNT(*) AS total", $conditions);
        $total = isset($totalRow[0]['total']) ? (int)$totalRow[0]['total'] : 0;

        // Fetch notifications
        $rows = $db->selectRows(
            "notifications",
            "id, title, message, type, is_read, created_at",
            $conditions,
            $limit,
            $offset,
            "ORDER BY created_at DESC"
        );

        $formatted = array_map(fn($n) => [
            "id" => (int)$n['id'],
            "title" => $n['title'],
            "message" => $n['message'],
            "type" => $n['type'],
            "is_read" => (bool)$n['is_read'],
            "created_at" => $n['created_at']
        ], $rows ?? []);

        $api_status_code->respondOK([
            "pagination" => [
                "current_page" => $page,
                "per_page" => $limit,
                "total" => $total,
                "total_pages" => ceil($total / $limit)
            ],
            "notifications" => $formatted
        ], API_User_Response::$notification_retrieved);

    } catch (Exception $e) {
        $api_status_code->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code->respondMethodNotAlowed();
}
