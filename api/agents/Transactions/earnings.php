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

        // Fetch earnings summary
        $transactions = $db_call_class->selectRows(
            "transactions",
            "id, amount, agent_amount, status",
            [[['column' => 'agent_id', 'operator' => '=', 'value' => $agent_id]]]
        );

        $total_earnings = 0.0;
        $total_pending = 0.0;
        $completed_count = 0;
        $pending_count = 0;

        foreach ($transactions as $txn) {
            if ($txn['status'] === 'completed') {
                $total_earnings += (float)$txn['agent_amount'];
                $completed_count++;
            } elseif ($txn['status'] === 'pending') {
                $total_pending += (float)$txn['agent_amount'];
                $pending_count++;
            }
        }

        $responseData = [
            "agent_id" => $agent_id,
            "total_earnings" => number_format($total_earnings, 2),
            "total_pending" => number_format($total_pending, 2),
            "completed_transactions" => $completed_count,
            "pending_transactions" => $pending_count
        ];

        $api_status_code_class_call->respondOK($responseData, "Earnings summary fetched successfully.");

    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
?>
