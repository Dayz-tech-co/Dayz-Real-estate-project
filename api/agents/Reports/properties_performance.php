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

$api_method = "POST";
header("Content-Type: application/json");

if (getenv('REQUEST_METHOD') === $api_method) {
    try {
        // Validate Agent Token
        $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1, 2);
        $agent_pubkey = $decodedToken->usertoken;

        // Fetch Agent
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

        // Ensure KYC Verified
        if (strtolower($getAgent[0]['kyc_verified']) !== 'verified') {
            $api_status_code_class_call->respondBadRequest(API_User_Response::$kycnotverified);
        }

        // Fetch properties for this agent
        $properties = $db_call_class->selectRows(
            "properties",
            "id, title, property_type, property_category, price, city, state, status, created_at, verified, sold_status",
            [[
                ['column' => 'agent_id', 'operator' => '=', 'value' => $agent_id],
                ['column' => 'is_deleted', 'operator' => '=', 'value' => 0]
            ]]
        );

        if ($utility_class_call->input_is_invalid($properties)) {
            $api_status_code_class_call->respondNotFound(API_User_Response::$propertynotfound);
        }

        $report = [];

        foreach ($properties as $property) {
            // Aggregate transactions for this property
            $txnStats = $db_call_class->selectRows("
                SELECT 
                    COUNT(*) AS total_transactions,
                    SUM(amount) AS total_sales,
                    SUM(agent_amount) AS total_agent_earning,
                    COUNT(DISTINCT user_id) AS unique_buyers
                FROM transactions
                WHERE property_id = ? AND status = 'completed'
            ", [$property['id']]);

            $txnStats = $txnStats[0] ?? [
                "total_transactions" => 0,
                "total_sales" => 0,
                "total_agent_earning" => 0,
                "unique_buyers" => 0
            ];

            $report[] = [
                "property_id" => $property['id'],
                "title" => $property['title'],
                "property_type" => $property['property_type'],
                "property_category" => $property['property_category'],
                "price" => (float)$property['price'],
                "city" => $property['city'],
                "state" => $property['state'],
                "status" => $property['status'],
                "verified" => (int)$property['verified'],
                "sold_status" => $property['sold_status'],
                "created_at" => $property['created_at'],
                "total_transactions" => (int)$txnStats['total_transactions'],
                "total_sales" => (float)$txnStats['total_sales'],
                "total_agent_earning" => (float)$txnStats['total_agent_earning'],
                "unique_buyers" => (int)$txnStats['unique_buyers']
            ];
        }

        // Return report
        $api_status_code_class_call->respondOK([
            "properties_report" => $report
        ], "Properties report retrieved successfully.");

    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
