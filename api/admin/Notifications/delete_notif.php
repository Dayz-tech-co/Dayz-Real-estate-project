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

        $ids = $_POST['notification_ids'] ?? [];
        if (empty($ids)) $api_status_code->respondBadRequest(API_User_Response::$notification_not_found);

        // Ensure it's an array
        if (!is_array($ids)) $ids = [$ids];

        $deleted = 0;
        foreach ($ids as $id) {
            $result = $db_call->deleteRows(
                "notifications",
                [[
                    ['column' => 'admin_id', 'operator' => '=', 'value' => $admin_id],
                    ['column' => 'id', 'operator' => '=', 'value' => $id]
                ]]
            );
            if ($result) $deleted++;
        }

        $api_status_code->respondOK([
            "deleted_count" => $deleted
        ], API_User_Response::$notification_deleted_successfully);

    } catch (Exception $e) {
        $api_status_code->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code->respondMethodNotAlowed();
}
