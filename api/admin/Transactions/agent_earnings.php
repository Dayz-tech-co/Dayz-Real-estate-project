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
        // Validate admin token
        $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1,1);
        $admin_pubkey = $decodedToken->usertoken;

        $getAdmin = $db_call_class->selectRows("admins", "id", [[
            ['column' => 'adminpubkey', 'operator' => '=', 'value' => $admin_pubkey]
        ]]);

        if ($utility_class_call->input_is_invalid($getAdmin)) {
            $api_status_code_class_call->respondUnauthorized();
        }

        // Pagination & filter
        $status = isset($_GET['status']) ? $utility_class_call->clean_user_data(strtolower($_GET['status'])) : '';
        $page   = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $limit  = 10;
        $offset = ($page - 1) * $limit;

        $conditions = [];
        if (!empty($status)) {
            $conditions[] = [
                'column' => 't.status',
                'operator' => '=',
                'value' => $status
            ];
        }

        //  Join transactions with agents table
        $joins = [[
            'type' => 'INNER',
            'table' => 'agents a',
            'condition' => 't.agent_id = a.id'
        ]];

        $transactions = $db_call_class->selectRows(
            "transactions t",
            "t.id, t.property_id, t.amount, t.status, t.created_at, t.commission, a.agency_name AS agent_name, a.email AS agent_email",
            $conditions,
            [
                'joins' => $joins,
                'limit' => $limit,
                'pageno' => $page,
                'orderBy' => 't.created_at',
                'orderDirection' => 'DESC'
            ]
        );

        // Calculate totals
        $total_amount = 0;
        $total_commission = 0;
        foreach ($transactions as $tx) {
            $total_amount += (float) ($tx['amount'] ?? 0);
            $total_commission += (float) ($tx['commission'] ?? 0);
            $total_transactions = count($transactions);
        }

        // ===== DISPLAY TOTAL TRANSACTIONS =====
        echo "<div style='margin-top:20px; font-weight:bold; font-size:16px;'>
        Total Transactions: " . $total_transactions . "
      </div>";

        $response = [
            "transactions" => $transactions,
            "summary" => [
                "total_amount" => $total_amount,
                "total_commission" => $total_commission
            ]
        ];

        $api_status_code_class_call->respondOK($transactions, API_User_Response::$transactionsfetched);
    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
