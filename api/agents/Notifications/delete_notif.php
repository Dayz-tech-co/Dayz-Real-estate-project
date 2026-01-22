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
        $decoded = $api_status_code->ValidateAPITokenSentIN(1, 2);
        $agent_pubkey = $decoded->usertoken;

        // Fetch agent
        $agent = $db->selectRows("agents", "id", [[
            ['column' => 'agentpubkey', 'operator' => '=', 'value' => $agent_pubkey]
        ]]);
        if ($util->input_is_invalid($agent)) $api_status_code->respondUnauthorized();
        $agent_id = $agent[0]['id'];

        $ids = $_POST['notification_ids'] ?? [];
        if (empty($ids)) $api_status_code->respondBadRequest(API_User_Response::$notification_not_found);

        if (!is_array($ids)) $ids = [$ids];
        $ids_str = implode(',', array_map('intval', $ids));
        foreach ($ids as $id) {
            $db_call->deleteRows(
                "notifications",
                [[
                    ["column" => "agent_id", "operator" => "=", "value" => $agent_id],
                    ["column" => "id", "operator" => "=", "value" => (int)$id]
                ]]
            );
        }


        $api_status_code->respondOK([], API_User_Response::$notification_deleted_successfully);
    } catch (Exception $e) {
        $api_status_code->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code->respondMethodNotAlowed();
}
