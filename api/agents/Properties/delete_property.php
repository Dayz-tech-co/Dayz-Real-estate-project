<?php

require __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . "/../../../bootstrap.php";

use Config\API_Status_Code;
use Config\API_User_Response;
use Config\DB_Calls_Functions;
use Config\Utility_Functions;

$api_status_code_class_call = new API_Status_Code;
$db_call_class = new DB_Calls_Functions;
$utility_class_call = new Utility_Functions;

header("Content-type: application/json");

$api_method = "POST";

if (getenv('REQUEST_METHOD') === $api_method) {
    try {
        //Validate Agent Token
        $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1, 2);
        $agent_pubkey = $decodedToken->usertoken;

        //Fetch Agent Record
        $getAgent = $db_call_class->selectRows(
            "agents",
            "id, agency_name, kyc_verified",
            [[
                ['column' => 'agentpubkey', 'operator' => '=', 'value' => $agent_pubkey]
            ]]
        );

        if ($utility_class_call->input_is_invalid($getAgent)) {
            $api_status_code_class_call->respondUnauthorized();
        }

        $agent_id = $getAgent[0]['id'];
        $agency_name = trim(strtolower($getAgent[0]['agency_name'] ?? ''));

        // Ensure KYC Verified
        if (strtolower($getAgent[0]['kyc_verified']) !== 'verified') {
            $api_status_code_class_call->respondBadRequest(API_User_Response::$kycnotverified);
        }

        // Validate Property ID
        $property_id = $utility_class_call->clean_user_data($_POST['property_id'] ?? '', 1);
        if (empty($property_id)) {
            $api_status_code_class_call->respondBadRequest(API_User_Response::$propertyidrequired);
        }

        // Check if property exists and belongs to this agent
        $property = $db_call_class->selectRows(
            "properties",
            "id, title",
            [[
                ['column' => 'id', 'operator' => '=', 'value' => $property_id],
                ['column' => 'agent_id', 'operator' => '=', 'value' => $agent_id]
            ]],
            ['limit' => 1]
        );

        if ($utility_class_call->input_is_invalid($property)) {
            $api_status_code_class_call->respondNotFound(API_User_Response::$propertynotfound);
        }

        $property = $property[0];

        // Insert into deleted_properties
        $db_call_class->insertRow("deleted_properties", [
            "property_id" => $property['id'],
            "agent_id" => $agent_id,
            "title" => $property['title'],
            "deleted_at" => date("Y-m-d H:i:s")
        ]);

        //  Perform permanent delete
        $deleted = $db_call_class->deleteRows(
            "properties",
            [[
                ['column' => 'id', 'operator' => '=', 'value' => $property_id],
                ['column' => 'agent_id', 'operator' => '=', 'value' => $agent_id]
            ]]
        );

        if ($deleted) {
            $api_status_code_class_call->respondOK([
                "property_id" => $property_id,
                "message" => "Property '{$property['title']}' deleted permanently"
            ], API_User_Response::$propertydeleted);
        } else {
            $api_status_code_class_call->respondInternalError(API_User_Response::$failedtodeleteproperty);
        }
    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
