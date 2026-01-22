<?php

require __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . "/../../../bootstrap.php";

use Config\API_Status_Code;
use Config\API_User_Response;
use Config\DB_Calls_Functions;
use Config\Utility_Functions;
use Config\Mail_SMS_Responses;

$api_status_code_class_call = new API_Status_Code;
$db_call_class = new DB_Calls_Functions;
$utility_class_call = new Utility_Functions;
$mail_sms_call = new Mail_SMS_Responses;

$api_method = "POST";
header("Content-Type: application/json");

if (getenv('REQUEST_METHOD') === $api_method) {
    try {
        //Validate Agent Token
        $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1, 2);
        $agent_pubkey = $decodedToken->usertoken;

        //Fetch Agent
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

        //Ensure Agent's KYC is Verified
        if (strtolower($getAgent[0]['kyc_verified']) !== 'verified') {
            $api_status_code_class_call->respondBadRequest(API_User_Response::$kycnotverified);
        }

        //Buyer ID Input
        $buyer_id        = isset($_POST["buyer_id"]) ? $utility_class_call->clean_user_data($_POST['buyer_id'], 1) : "";
        if ($buyer_id <= 0) {
            $api_status_code_class_call->respondBadRequest(API_User_Response::$missingrequiredfields);
        }

        //Fetch Buyer Details
        $buyer = $db_call_class->selectRows(
            "users",
            "id, fname, lname, email, phoneno, city, state, streetname, kyc_verified, profile_pic, created_at",
            [[
                ['column' => 'id', 'operator' => '=', 'value' => $buyer_id]
            ]]
        );

        if ($utility_class_call->input_is_invalid($buyer)) {
            $api_status_code_class_call->respondNotFound("Buyer not found.");
        }

        $buyer = $buyer[0];

        //Fetch All Transactions Under This Agent and Buyer
        $transactions = $db_call_class->selectRows("
            SELECT 
                t.transaction_id,
                t.property_id,
                t.amount,
                t.commission,
                t.commission_percentage,
                t.agent_amount,
                t.transaction_type,
                t.status,
                t.created_at,
                p.title AS property_title,
                p.property_type,
                p.property_category,
                p.city AS property_city,
                p.state AS property_state,
                p.location AS property_location,
                p.thumbnail AS property_thumbnail
            FROM transactions t
            LEFT JOIN properties p ON t.property_id = p.id
            WHERE t.user_id = ? AND t.agent_id = ?
            ORDER BY t.created_at DESC
        ", [$buyer_id, $agent_id]);

        //Prepare Response Data
        $formattedTxns = [];
        $totalSpent = 0;
        $totalCommission = 0;

        if (!empty($transactions) && is_array($transactions)) {
            foreach ($transactions as $txn) {
                $formattedTxns[] = [
                    "transaction_id" => $txn['transaction_id'],
                    "amount"         => (float)$txn['amount'],
                    "commission"     => (float)$txn['commission'],
                    "commission_percentage" => (float)$txn['commission_percentage'],
                    "agent_amount"   => (float)$txn['agent_amount'],
                    "transaction_type" => ucfirst($txn['transaction_type']),
                    "status"         => ucfirst($txn['status']),
                    "created_at"     => $txn['created_at'],

                    // Property Info
                    "property" => [
                        "property_id"     => $txn['property_id'],
                        "title"           => $txn['property_title'],
                        "type"            => $txn['property_type'],
                        "category"        => $txn['property_category'],
                        "city"            => $txn['property_city'],
                        "state"           => $txn['property_state'],
                        "location"        => $txn['property_location'],
                        "thumbnail"       => $txn['property_thumbnail']
                    ]
                ];

                $totalSpent += (float)$txn['amount'];
                $totalCommission += (float)$txn['commission'];
            }
        }

        //Final API Response
        $api_status_code_class_call->respondOK([
            "buyer" => [
                "buyer_id"      => $buyer['id'],
                "full_name"     => trim($buyer['fname'] . " " . $buyer['lname']),
                "email"         => $buyer['email'],
                "phoneno"       => $buyer['phoneno'],
                "city"          => $buyer['city'],
                "state"         => $buyer['state'],
                "streetname"    => $buyer['streetname'],
                "kyc_verified"  => ucfirst($buyer['kyc_verified']),
                "profile_pic"   => $buyer['profile_pic'] ?? null,
                "joined_at"     => $buyer['created_at']
            ],
            "summary" => [
                "total_transactions" => count($transactions),
                "total_spent"        => $totalSpent,
                "total_commission"   => $totalCommission
            ],
            "transactions" => $formattedTxns
        ], "Buyer details and transactions retrieved successfully.");

    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
