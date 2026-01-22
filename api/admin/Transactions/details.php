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

        // Validate transaction_id
        if (!isset($_GET['transaction_id']) || empty($_GET['transaction_id'])) {
            $api_status_code_class_call->respondBadRequest([], API_User_Response::$transactionidrequired);
        }

        $transaction_id = isset($GET['transaction_id'])? $utility_class_call->clean_user_data($_GET['transaction_id']): '';

        //JOIN with user, agent, and property
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

        //  Select Columns
        $selectColumns = [
            't.transaction_id',
            't.amount',
            't.commission',
            't.transaction_type',
            't.status',
            't.created_at',
            't.updated_at',
            'u.id AS user_id',
            'u.fullname AS user_name',
            'u.email AS user_email',
            'a.id AS agent_id',
            'a.agency_name AS agent_name',
            'a.email AS agent_email',
            'p.id AS property_id',
            'p.title AS property_title',
            'p.price AS property_price'
        ];

        // Conditions
        $conditions = [[
            ['column' => 't.transaction_id', 'operator' => '=', 'value' => $transaction_id]
        ]];

        // Query
        $options = ['joins' => $joins, 'limit' => 1];

        $transaction = $db_call_class->selectRows('transactions t', $selectColumns, $conditions, $options);

        if ($utility_class_call->input_is_invalid($transaction)) {
            $api_status_code_class_call->respondNotFound([], API_User_Response::$transactionnotfound);
        }

        //Success Response
        $api_status_code_class_call->respondOK(
            ['transaction' => $transaction[0]],
            API_User_Response::$transactionsuccessful
        );

    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
