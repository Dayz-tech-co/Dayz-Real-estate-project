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
        // Validate Admin Token
        $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1,1);
        $admin_pubkey = $decodedToken->usertoken;

        $getAdmin = $db_call_class->selectRows("admins", "id", [[
            ['column' => 'adminpubkey', 'operator' => '=', 'value' => $admin_pubkey]
        ]]);

        if ($utility_class_call->input_is_invalid($getAdmin)) {
            $api_status_code_class_call->respondUnauthorized();
        }

        // Filters
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

        //JOIN transactions with users, agents, and properties
        $joins = [
            [
                'type' => 'JOIN',
                'table' => 'users u',
                'condition' => 't.user_id = u.id'
            ],
            [
                'type' => 'JOIN',
                'table' => 'agents a',
                'condition' => 't.agent_id = a.id'
            ],
            [
                'type' => 'JOIN',
                'table' => 'properties p',
                'condition' => 't.property_id = p.id'
            ]
        ];

        $options = [
            'limit' => $limit,
            'pageno' => $page,
            'joins' => $joins,
            'orderBy' => 't.id',
            'orderDirection' => 'DESC'
        ];

        // Select columns
        $selectColumns = [
            't.transaction_id',
            't.amount',
            't.commission',
            't.transaction_type',
            't.status',
            't.created_at',
            'u.fullname AS user_name',
            'a.agency_name AS agent_name',
            'p.title AS property_title'
        ];

        // Fetch data
        $transactions = $db_call_class->selectRows('transactions t', $selectColumns, $conditions, $options);

        $api_status_code_class_call->respondOK($transactions, API_User_Response::$transactionsfetched);

    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
