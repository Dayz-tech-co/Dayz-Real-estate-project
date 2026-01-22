<?php

require __DIR__ . '/../../../vendor/autoload.php';

use Config\API_Status_Code;
use Config\API_User_Response;

require_once __DIR__ . "/../../../bootstrap.php";

// Init classes (consistent with your other files)
$api_status_code_class_call = new Config\API_Status_Code;
$db_call_class               = new Config\DB_Calls_Functions;
$utility_class_call          = new Config\Utility_Functions;

$api_method = "POST";

if (getenv('REQUEST_METHOD') === $api_method) {
    try {
        // 1) Admin validation
        $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1,1);
        $user_pubkey  = $decodedToken->usertoken;

        $getAdmin = $db_call_class->selectRows("admins", "id", [[
            ['column' => 'adminpubkey', 'operator' => '=', 'value' => $user_pubkey]
        ]]);

        if ($utility_class_call->input_is_invalid($getAdmin)) {
            $api_status_code_class_call->respondUnauthorized();
        }

        // 2) Inputs & sanitization
        $transaction_id = isset($_POST['transaction_id']) ? $utility_class_call->clean_user_data($_POST['transaction_id'], 1) : '';

        // Optional override but default will be 10%
        $commission_percentage_input = isset($_POST['commission_percentage']) ? $utility_class_call->clean_user_data($_POST['commission_percentage'], 1) : null;

        // 2) parse & validate (allow "10", "10.5", "10%", "10,5")
        if ($commission_percentage_input !== null && $commission_percentage_input !== '') {
            $tmp = trim($commission_percentage_input);
            $tmp = str_replace('%', '', $tmp);    // allow "10%"
            $tmp = str_replace(',', '.', $tmp);   // allow "10,5"

            if (!is_numeric($tmp)) {
                $api_status_code_class_call->respondBadRequest($tmp, API_User_Response::$invalidcommissionpercentage);
            }

            $commission_percentage = (float) $tmp;

            // bounds check: 0..100
            if ($commission_percentage < 0 || $commission_percentage > 100) {
                $api_status_code_class_call->respondBadRequest($commission_percentage, API_User_Response::$validcommissionnumbers);
            }

            // normalize
            $commission_percentage = round($commission_percentage, 2);
        } else {
            // default if not provided
            $commission_percentage = 10.00;
        }

        if ($utility_class_call->input_is_invalid($transaction_id)) {
            $api_status_code_class_call->respondBadRequest(API_User_Response::$request_body_invalid);
        }

        // 3) Fetch transaction

        $transactionRows = $db_call_class->selectRows(
            "transactions",
            "*",
            [[
                ['column' => 'transaction_id', 'operator' => '=', 'value' => $transaction_id]
            ]]
        );

        if ($utility_class_call->input_is_invalid($transactionRows)) {
            $api_status_code_class_call->respondNotFound([], API_User_Response::$transactionnotfound);
        }

        $transaction = $transactionRows[0];

        // Normalize status exactly to your allowed values
        $status = strtolower($transaction['status']);
        $amount = (float)$transaction['amount'];

        // 4) Status checks

        // Allowed statuses: pending, completed, failed, refunded
        if (!in_array($status, ['pending', 'completed', 'failed', 'refunded'])) {
            $api_status_code_class_call->respondBadRequest($status, API_User_Response::$invalidtransactionstatus . $status);
        }

        // Commission settlement only allowed for completed transactions
        if ($status !== 'completed') {
            $api_status_code_class_call->respondBadRequest($status, API_User_Response::$commissionstatusnotallowed . $status);
        }

        // Check if commission already settled
        $alreadySettled = false;
        if (isset($transaction['commission_settled']) && (int)$transaction['commission_settled'] === 1) {
            $alreadySettled = true;
        } elseif (!empty($transaction['commission']) && $transaction['commission'] !== null) {
            $alreadySettled = true;
        }

        if ($alreadySettled) {
            $api_status_code_class_call->respondBadRequest($alreadySettled, API_User_Response::$commissionalreadysettled);
        }
        // 5) Commission calculation (default 10%)

        // If admin passed a commission_percentage, use it; otherwise use 10%
        if ($commission_percentage_input !== null && $commission_percentage_input !== '') {
            // sanitize and cast numeric (allow comma or dot decimals)
            $commission_percentage = (float) str_replace(',', '.', $commission_percentage_input);
        } else {
            $commission_percentage = 10.0; // default 10%
        }

        if ($commission_percentage < 0) {
            $api_status_code_class_call->respondBadRequest($commission_percentage, API_User_Response::$invalidcommissionpercentage);
        }

        $commission_amount = round(($commission_percentage / 100) * $amount, 2);
        $agent_amount      = round($amount - $commission_amount, 2);

        // 6) Persist commission using updateRows (your preferred pattern)

        $updateData = [
            "commission"            => $commission_amount,
            "commission_percentage" => $commission_percentage,
            "commission_settled"    => 1,
            "agent_amount"          => $agent_amount,
            "settled_at"            => date("Y-m-d H:i:s")
        ];

        $updateWhere = [
            ["column" => "transaction_id", "operator" => "=", "value" => $transaction_id]
        ];

        $updateResponse = $db_call_class->updateRows("transactions", $updateData, $updateWhere);

        if ($updateResponse <= 0) {
            $api_status_code_class_call->respondInternalError($updateResponse, API_User_Response::$commissionfailed . $updateResponse);
        }

        // Optionally insert into commissions audit table

        if (method_exists($db_call_class, 'insertRow')) {
            // use transaction internal id if available
            $tx_db_id = isset($transaction['id']) ? (int)$transaction['id'] : null;
            $db_call_class->insertRow("commissions", [
                "transaction_id" => $tx_db_id,
                "platform_share" => $commission_amount,
                "agent_share"    => $agent_amount,
                "commission_percentage" => $commission_percentage,
                "status"         => "settled",
                "created_at"     => date("Y-m-d H:i:s")
            ]);
        }
        //  Response

        $api_status_code_class_call->respondOK([
            "transaction_id"        => $transaction_id,
            "amount"                => number_format($amount, 2, '.', ''),
            "commission"            => number_format($commission_amount, 2, '.', ''),
            "commission_percentage" => $commission_percentage,
            "agent_amount"          => number_format($agent_amount, 2, '.', ''),
            "status"                => "settled"
        ], API_User_Response::$commissionsettled);
    } catch (\Exception $e) {
        $api_status_code_class_call->respondInternalError($utility_class_call->get_details_from_exception($e));
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
