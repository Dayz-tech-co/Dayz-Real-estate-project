<?php

require __DIR__ . '/../../../vendor/autoload.php';

use Config\API_Status_Code;
use Config\API_User_Response;

require_once __DIR__ . "/../../../bootstrap.php";

$api_method = "GET";
$api_status_code_class_call = new Config\API_Status_Code;
$db_call_class = new Config\DB_Calls_Functions;
$utility_class_call = new Config\Utility_Functions;


header("Content-type: application/json");

if (getenv('REQUEST_METHOD') === $api_method){
    try {

// Validate Token
$decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1,1);
$user_pubkey  = $decodedToken->usertoken;

//  Confirm user is an admin
$getuserattached = $db_call_class->selectRows(
    "admin",
    "id",
    [[
        ['column' => 'adminpubkey', 'operator' => '=', 'value' => $user_pubkey]
    ]]
);

if ($utility_class_call->input_is_invalid($getuserattached)) {
    $api_status_code_class_call->respondUnauthorized();
}

// Collect required POST data
$agent_id = isset($_POST['agentid']) ? $utility_class_call->clean_user_data($_POST['agentid'], 1) : '';

if (empty($agent_id)) {
    $api_status_code_class_call->respondBadRequest($agent_id, API_User_Response::$agentidrequired);
}

// Check if agent exists and is pending
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

if ($getAgent[0]['status'] !== "pending") {
    $api_status_code_class_call->respondBadRequest($getAgent, API_User_Response::$agentnotpending);
}

// Reject Agent
$updateAgent = $db_call_class->updateRows(
    "agents",
    [
        ['column' => 'status', 'value' => 'rejected']
    ],
    [
        ['column' => 'id', 'operator' => '=', 'value' => $agent_id]
    ]
);

if ($updateAgent) {
    $api_status_code_class_call->respondOK($updateAgent, API_User_Response::$agentrejected);
} else {
    $api_status_code_class_call->respondInternalError($updateAgent, API_User_Response::$failtorejectagent);
}

} catch (\Exception $e) {
        $api_status_code_class_call->respondInternalError(
            $utility_class_call->get_details_from_exception($e)
        );
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
