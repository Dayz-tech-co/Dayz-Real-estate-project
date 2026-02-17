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

header("content-type: application/json");
if (getenv('REQUEST_METHOD') === $api_method) {
    try {
        //Validate Agent Token
        $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1, 2);
        $agent_pubkey = $decodedToken->usertoken;

        // 🔍 Fetch Agent
        $getAgent = $db_call_class->selectRows(
            "agents",
            "id, agency_name, email, status, kyc_verified",
            [[
                ['column' => 'agentpubkey', 'operator' => '=', 'value' => $agent_pubkey]
            ]]
        );

        if ($utility_class_call->input_is_invalid($getAgent)) {
            $api_status_code_class_call->respondUnauthorized();
        }

        $agent_id = $getAgent[0]['id'];
        $agency_name = $getAgent[0]['agency_name'];

        //Ensure KYC Verified
        if (strtolower($getAgent[0]['kyc_verified']) !== 'verified') {
            $api_status_code_class_call->respondBadRequest(API_User_Response::$kycnotverified);
        }

        //Pagination Inputs
        $page  = isset($_POST['page']) ? (int)$_POST['page'] : 1;
        $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 10;
        if ($page < 1) $page = 1;
        if ($limit < 1) $limit = 10;

        //Optional Filter by Transaction Status
        $status = isset($_POST['status']) ? strtolower(trim($_POST['status'])) : null;
        $allowedStatuses = ['pending', 'completed', 'failed', 'refunded'];

        //Build Conditions
        $conditions = [[
            ['column' => 'agent_id', 'operator' => '=', 'value' => $agent_id]
        ]];

        if (!empty($status) && in_array($status, $allowedStatuses)) {
            $conditions[0][] = ['column' => 'status', 'operator' => '=', 'value' => $status];
        }

        // Count total unique buyers
        $allBuyerRows = $db_call_class->selectRows(
            "transactions",
            ["user_id", "MAX(id) AS last_id"],
            $conditions,
            [
                'groupBy' => 'user_id'
            ]
        );
        $total = is_array($allBuyerRows) ? count($allBuyerRows) : 0;

        // Fetch distinct buyer IDs for pagination
        $transactions = $db_call_class->selectRows(
            "transactions",
            ["user_id", "MAX(id) AS last_id"],
            $conditions,
            [
                'groupBy' => 'user_id',
                'orderBy' => 'last_id',
                'orderDirection' => 'DESC',
                'limit' => $limit,
                'pageno' => $page
            ]
        );

        if ($utility_class_call->input_is_invalid($transactions)) {
            $api_status_code_class_call->respondNotFound("No buyers found for this agent");
        }

        //Fetch Buyer Details
        $buyersList = [];

        if (!empty($transactions) && is_array($transactions)) {
            foreach ($transactions as $trans) {
                $user_id = $trans['user_id'];

                $buyer = $db_call_class->selectRows(
                    "users",
                    "id, fname, lname, email, phoneno, city, state, kyc_verified, profile_pic, created_at",
                    [[
                        ['column' => 'id', 'operator' => '=', 'value' => $user_id]
                    ]]
                );

                if (!$utility_class_call->input_is_invalid($buyer)) {
                    $buyersList[] = [
                        "buyer_id"      => $buyer[0]['id'],
                        "full_name"     => trim($buyer[0]['fname'] . " " . $buyer[0]['lname']),
                        "email"         => $buyer[0]['email'],
                        "phoneno"       => $buyer[0]['phoneno'],
                        "city"          => $buyer[0]['city'],
                        "state"         => $buyer[0]['state'],
                        "kyc_verified"  => $buyer[0]['kyc_verified'],
                        "profile_pic"   => $buyer[0]['profile_pic'] ?? null,
                        "joined_at"     => $buyer[0]['created_at']
                    ];
                }
            }
        }


        //Pagination Meta
        $pagination = [
            "current_page" => $page,
            "per_page"     => $limit,
            "total"        => $total,
            "total_pages"  => ceil($total / $limit)
        ];

        //Final Success Response
        $api_status_code_class_call->respondOK([
            "pagination" => $pagination,
            "buyers"     => $buyersList
        ], "Buyers retrieved successfully");
    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
