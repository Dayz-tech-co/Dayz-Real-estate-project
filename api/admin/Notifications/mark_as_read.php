<?php
require __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . "/../../../bootstrap.php";

use Config\API_Status_Code;
use Config\API_User_Response;
use Config\DB_Calls_Functions;
use Config\Utility_Functions;

$api_status_code = new API_Status_Code;
$db_call = new DB_Calls_Functions;
$util = new Utility_Functions;

$api_method = "POST";
header("Content-Type: application/json");

if (getenv('REQUEST_METHOD') === $api_method) {
    try {
        $decoded = $api_status_code->ValidateAPITokenSentIN(1, 1); // Admin token
        $admin_pubkey = $decoded->usertoken;

        // Fetch admin
        $admin = $db_call->selectRows("admins", "id", [[
            ['column' => 'adminpubkey', 'operator' => '=', 'value' => $admin_pubkey]
        ]]);
        if ($util->input_is_invalid($admin)) $api_status_code->respondUnauthorized();
        $admin_id = $admin[0]['id'];

        // Get notification IDs
        $ids = $_POST['notification_ids'] ?? [];
        if (empty($ids)) $api_status_code->respondBadRequest(API_User_Response::$notification_not_found);
        if (!is_array($ids)) $ids = [$ids];

        // Validate notifications belong to admin
        $valid = $db_call->selectRows(
            "notifications",
            "COUNT(*) AS count",
            [[
                ['column' => 'admin_id', 'operator' => '=', 'value' => $admin_id],
                ['column' => 'id', 'operator' => 'IN', 'value' => $ids]
            ]]
        );
        $validCount = (int)($valid[0]['count'] ?? 0);

        if ($validCount === 0) {
            $api_status_code->respondNotFound([], "No valid notifications found");
        }

        // Update to read
        $updated = 0;
        foreach ($ids as $id) {
            $result = $db_call->updateRows(
                "notifications",
                ["is_read" => 1],
                [[
                    ['column' => 'admin_id', 'operator' => '=', 'value' => $admin_id],
                    ['column' => 'id', 'operator' => '=', 'value' => $id]
                ]]
            );
            if ($result) $updated++;
        }

        $api_status_code->respondOK([
            "updated_count" => $updated
        ], API_User_Response::$notification_read);

    } catch (Exception $e) {
        $api_status_code->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code->respondMethodNotAlowed();
}
