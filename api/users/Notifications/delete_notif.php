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
        $decoded = $api_status_code->ValidateAPITokenSentIN(1, 3); // User token
        $user_pubkey = $decoded->usertoken;

        // Fetch user
        $user = $db_call->selectRows("users", "id", [[
            ['column' => 'userpubkey', 'operator' => '=', 'value' => $user_pubkey]
        ]]);
        if ($util->input_is_invalid($user)) $api_status_code->respondUnauthorized();
        $user_id = $user[0]['id'];

        $ids = $_POST['notification_ids'] ?? [];
        if (empty($ids)) $api_status_code->respondBadRequest(API_User_Response::$notification_not_found);

        // Ensure it's an array
        if (!is_array($ids)) $ids = [$ids];

        $deleted = 0;
        foreach ($ids as $id) {
            $result = $db_call->deleteRows(
                "notifications",
                [[
                    ['column' => 'user_id', 'operator' => '=', 'value' => $user_id],
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
