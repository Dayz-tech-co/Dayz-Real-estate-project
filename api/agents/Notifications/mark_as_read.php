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
        // Validate token and get agent info
        $decoded = $api_status_code->ValidateAPITokenSentIN(1, 2);
        $agent_pubkey = $decoded->usertoken;

        $agent = $db_call->selectRows("agents", "id", [[
            ['column' => 'agentpubkey', 'operator' => '=', 'value' => $agent_pubkey]
        ]]);
        if ($util->input_is_invalid($agent)) $api_status_code->respondUnauthorized();

        $agent_id = $agent[0]['id'];

        // Get notification IDs
        $ids = $_POST['notification_ids'] ?? [];
        if (empty($ids)) $api_status_code->respondBadRequest(API_User_Response::$notification_not_found);
        if (!is_array($ids)) $ids = [$ids];

        $updated = 0;
        foreach ($ids as $id) {
            $result = $db_call->updateRows(
                "notifications",
                ["is_read" => 1],
                [[
                    ["column" => "agent_id", "operator" => "=", "value" => $agent_id],
                    ["column" => "id", "operator" => "=", "value" => (int)$id]
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
