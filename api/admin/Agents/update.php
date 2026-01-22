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

        //Params
        $agent_id = isset($_POST['agent_id']) ? $utility_class_call->clean_user_data($_POST['agent_id'], 1) : '';
        $new_status = isset($_POST['status']) ? strtolower($utility_class_call->clean_user_data($_POST['status'], 1)) : '';
         $reason     = isset($_POST['reason']) ? $utility_class_call->clean_user_data($_POST['reason'], 1) : null;

        if ($utility_class_call->input_is_invalid($agent_id) || $utility_class_call->input_is_invalid($new_status)) {
            $api_status_code_class_call->respondBadRequest($agent_id, $new_status, API_User_Response::$agentidandnewstatusrequired);
        }

        $validStatuses = ['pending', 'approved', 'rejected', 'suspended'];
        if (!in_array($new_status, $validStatuses)) {
            $api_status_code_class_call->respondBadRequest($new_status, $validStatuses, API_User_Response::$invalidstatusfilter);
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
             

         $old_status = $getAgent[0]['status'];
        // Update agent status
        $update = $db_call_class->updateRows(
            "agents",
            ["status" => $new_status],
            [[
                ['column' => 'id', 'operator' => '=', 'value' => $agent_id]
            ]]
        );

        if ($update > 0) {

              // Insert into history log
            $db_call_class->insertRow("agent_status_history", [
                "agent_id"   => $agent_id,
                "old_status" => $old_status,
                "new_status" => $new_status,
                "reason"     => $reason
            ]);

            $api_status_code_class_call->respondOK(
                ["agent_id" => $agent_id, "new_status" => $new_status],
                API_User_Response::$agentupdated
            );
        } else {
            $api_status_code_class_call->respondInternalError($update, API_User_Response::$failtoupdateagent);
        }

    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}

