<?php

require __DIR__ . '/../../../vendor/autoload.php';

use Config\API_Status_Code;
use Config\API_User_Response;

require_once __DIR__ . "/../../../bootstrap.php";

// Init classes
$api_status_code_class_call = new Config\API_Status_Code;
$db_call_class = new Config\DB_Calls_Functions;
$utility_class_call = new Config\Utility_Functions;

$api_method = "GET";

if (getenv('REQUEST_METHOD') === $api_method) {
    try {
        // Validate admin
        $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1,1);
        $admin_pubkey = $decodedToken->usertoken;

        $getAdmin = $db_call_class->selectRows("admins", "id", [[
            ['column' => 'adminpubkey', 'operator' => '=', 'value' => $admin_pubkey]
        ]]);

        if ($utility_class_call->input_is_invalid($getAdmin)) {
            $api_status_code_class_call->respondUnauthorized();
        }

        // Filters & pagination
        $status = isset($_GET['status']) ? $utility_class_call->clean_user_data(strtolower($_GET['status'])) : '';
        $page   = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $limit  = 10;

        $conditions = [];
        if (!empty($status)) {
            $conditions[] = [
                'column' => 't.status',
                'operator' => '=',
                'value' => $status
            ];
        }

        $joins = [
            [
                'type' => 'INNER',
                'table' => 'users u',
                'condition' => 't.user_id = u.id'
            ],
            [
                'type' => 'LEFT',
                'table' => 'properties p',
                'condition' => 't.property_id = p.id'
            ]
        ];

        // ✅ Fetch user transactions
        $transactions = $db_call_class->selectRows(
            "transactions t",
            "t.id, t.amount, t.status, t.transaction_type, t.created_at, 
             u.fullname AS user_name, u.email AS user_email, 
             p.title AS property_title, p.price AS property_price",
            $conditions,
            [
                'joins' => $joins,
                'limit' => $limit,
                'pageno' => $page,
                'orderBy' => 't.created_at',
                'orderDirection' => 'DESC'
            ]
        );

        //  Calculate total user payments
        $total_amount = 0;
        foreach ($transactions as $tx) {
            $total_amount += (float) ($tx['amount'] ?? 0);
            $total_transactions = count($transactions);
        }

        // ===== DISPLAY TOTAL TRANSACTIONS =====
        echo "<div style='margin-top:20px; font-weight:bold; font-size:16px;'>
        Total Transactions: " . $total_transactions . "
      </div>";

        $response = [
            "transactions" => $transactions,
            "summary" => [
                "total_user_payments" => $total_amount
            ]
        ];

        $api_status_code_class_call->respondOK($response, API_User_Response::$transactionsfetched);
    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
