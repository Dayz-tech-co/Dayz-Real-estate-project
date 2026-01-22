<?php

require __DIR__ . '/../../../vendor/autoload.php';

use Config\API_Status_Code;
use Config\API_User_Response;

require_once __DIR__ . "/../../../bootstrap.php";

// Init classes
$api_status_code_class_call = new Config\API_Status_Code;
$db_call_class = new Config\DB_Calls_Functions;
$utility_class_call = new Config\Utility_Functions;

$api_method = "POST";

if (getenv('REQUEST_METHOD') === $api_method) {
    try {
        // Verify admin token
        $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1,1);
        $admin_pubkey = $decodedToken->usertoken;

        $getAdmin = $db_call_class->selectRows("admins", "id", [[
            ['column' => 'adminpubkey', 'operator' => '=', 'value' => $admin_pubkey]
        ]]);

        if ($utility_class_call->input_is_invalid($getAdmin)) {
            $api_status_code_class_call->respondUnauthorized();
        }

        //  Sanitize inputs
        $transaction_id_input = isset($_POST['transaction_id']) ? $utility_class_call->clean_user_data($_POST['transaction_id'], 1) : null;
        $status_input = isset($_POST['status']) ? strtolower($utility_class_call->clean_user_data($_POST['status'])) : null;

        // Validate required fields
        if ($utility_class_call->input_is_invalid($transaction_id_input) || $utility_class_call->input_is_invalid($status_input)) {
            $api_status_code_class_call->respondBadRequest($transaction_id_input, API_User_Response::$transidandstatusneeded);
        }

        //  Validate status value
        $valid_statuses = ['pending', 'completed', 'failed', 'refunded'];
        if (!in_array($status_input, $valid_statuses)) {
            $api_status_code_class_call->respondBadRequest($status_input, API_User_Response::$commissionstatusnotallowed.$valid_statuses);
        }

        // Check if transaction exists
        $getTransaction = $db_call_class->selectRows("transactions", "*", [[
            ['column' => 'id', 'operator' => '=', 'value' => $transaction_id_input]
        ]]);

        if ($utility_class_call->input_is_invalid($getTransaction)) {
            $api_status_code_class_call->respondNotFound($getTransaction, API_User_Response::$transactionnotfound);
        }

        $transaction = $getTransaction[0];

        //  Update transaction status
        $updateTransaction = $db_call_class->updateRows("transactions", [
            "status" => $status_input,
            "updated_at" => date('Y-m-d H:i:s')
        ], [[
            "column" => "id",
            "operator" => "=",
            "value" => $transaction_id_input
        ]]);

        if ($updateTransaction) {
            // If completed, mark commission as settled (optional logic for later)
            if ($status_input === "completed") {
                // Placeholder for future commission updates if needed
            }

            $api_status_code_class_call->respondOK([
                "transaction_id" => $transaction_id_input,
                "status" => $status_input
            ], API_User_Response::$transactionupdated);
        } else {
            $api_status_code_class_call->respondInternalError($updateTransaction, API_User_Response::$transactionfailed);
        }

    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
