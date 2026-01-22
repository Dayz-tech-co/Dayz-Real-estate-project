<?php
require __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . "/../../../bootstrap.php";

use Config\API_Status_Code;
use Config\DB_Calls_Functions;
use Config\Utility_Functions;
use Config\API_User_Response;

$api_status_code_class_call = new API_Status_Code;
$db_call_class = new DB_Calls_Functions;
$utility_class_call = new Utility_Functions;

header("Content-Type: application/json");
$api_method = "POST";

if (getenv('REQUEST_METHOD') === $api_method) {
    try {
        // Validate Agent Token
        $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1, 2);
        $agent_pubkey = $decodedToken->usertoken;

        // Fetch Agent Info
        $getAgent = $db_call_class->selectRows(
            "agents",
            "id, agency_name",
            [[['column' => 'agentpubkey', 'operator' => '=', 'value' => $agent_pubkey]]]
        );

        if ($utility_class_call->input_is_invalid($getAgent)) {
            $api_status_code_class_call->respondUnauthorized();
        }

        $agent_id = $getAgent[0]['id'];

        // Fetch transactions for agent
        $transactions = $db_call_class->selectRows(
            "transactions",
            "id, transaction_id, property_id, user_id, amount, agent_amount, commission_percentage, status",
            [[['column' => 'agent_id', 'operator' => '=', 'value' => $agent_id]]]
        );

        $commissionData = [];

        foreach ($transactions as $txn) {
            // Get user info
            $userInfo = $db_call_class->selectRows(
                "users",
                "id, fullname, email, phoneno",
                [[['column' => 'id', 'operator' => '=', 'value' => $txn['user_id']]]]
            );
            $userInfo = $utility_class_call->input_is_invalid($userInfo) ? [] : $userInfo[0];

            // Get commission info
            $commissionInfo = $db_call_class->selectRows(
                "commissions",
                "platform_share, agent_share, commission_percentage, status",
                [[['column' => 'transaction_id', 'operator' => '=', 'value' => $txn['id']]]]
            );

            $commissionInfo = $utility_class_call->input_is_invalid($commissionInfo) ? [
                "platform_share" => 0,
                "agent_share" => $txn['agent_amount'],
                "commission_percentage" => $txn['commission_percentage'],
                "status" => "pending"
            ] : $commissionInfo[0];

            $commissionData[] = [
                "transaction_id" => $txn['transaction_id'],
                "property_id" => $txn['property_id'],
                "user" => $userInfo,
                "amount" => number_format((float)$txn['amount'], 2),
                "agent_share" => number_format((float)$commissionInfo['agent_share'], 2),
                "platform_share" => number_format((float)$commissionInfo['platform_share'], 2),
                "commission_percentage" => $commissionInfo['commission_percentage'],
                "status" => $commissionInfo['status']
            ];
        }

        $api_status_code_class_call->respondOK($commissionData, "Commission breakdown fetched successfully.");

    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
?>
