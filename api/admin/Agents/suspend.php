<?php

require __DIR__ . '/../../../vendor/autoload.php';

use Config\API_Status_Code;
use Config\API_User_Response;

require_once __DIR__ . "/../../../bootstrap.php";

// Init
$api_status_code_class_call = new Config\API_Status_Code;
$db_call_class = new Config\DB_Calls_Functions;
$utility_class_call = new Config\Utility_Functions;

$api_method = "POST";

if (getenv('REQUEST_METHOD') === $api_method) {
    try {
        // Validate token
        $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1,1);
        $user_pubkey  = $decodedToken->usertoken;

        // Confirm it's an admin
        $getuserattached = $db_call_class->selectRows(
            "admins",
            "id",
            [[
                ['column' => 'adminpubkey', 'operator' => '=', 'value' => $user_pubkey]
            ]]
        );

        if ($utility_class_call->input_is_invalid($getuserattached)) {
            $api_status_code_class_call->respondUnauthorized();
        }

        // Agent ID from request
        $agent_id = isset($_POST['agent_id']) ? $utility_class_call->clean_user_data($_POST['agent_id'], 1) : '';

        if ($utility_class_call->input_is_invalid($agent_id)) {
            $api_status_code_class_call->respondBadRequest($agent_id, API_User_Response::$agentidrequired);
        }

        // Check if agent exists
        $getAgent = $db_call_class->selectRows(
            "agents",
            "id, status",
            [[
                ['column' => 'id', 'operator' => '=', 'value' => $agent_id]
            ]]
        );

        if ($utility_class_call->input_is_invalid($getAgent)) {
            $api_status_code_class_call->respondNotFound($getAgent, API_User_Response::$agentnotfound);
        }

        // Update agent status to "suspended"
        $update = $db_call_class->updateRows(
            "agents",
            ["status" => "suspended"],
            [[
                ['column' => 'id', 'operator' => '=', 'value' => $agent_id]
            ]]
        );

        if ($update > 0) {
            $api_status_code_class_call->respondOK(
                ["agent_id" => $agent_id, "new_status" => "suspended"],
                API_User_Response::$agentsuspended
            );
        } else {
            $api_status_code_class_call->respondInternalError($update, API_User_Response::$failtosuspendagent);
        }

    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}

