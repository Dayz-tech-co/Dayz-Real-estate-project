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
        // -------------------------
        // 1) Admin validation
        // -------------------------
        $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1,1);
        $admin_pubkey  = $decodedToken->usertoken;

        $getAdmin = $db_call_class->selectRows(
            "admins",
            "id, fname, lname, email",
            [[
                ['column' => 'adminpubkey', 'operator' => '=', 'value' => $admin_pubkey]
            ]]
        );

        if ($utility_class_call->input_is_invalid($getAdmin)) {
            $api_status_code_class_call->respondUnauthorized();
        }

        // -------------------------
        // 2) Input & sanitization
        // -------------------------
        $user_id = isset($_GET['user_id'])
            ? (int) $utility_class_call->clean_user_data($_GET['user_id'], 1)
            : null;

        if ($utility_class_call->input_is_invalid($user_id)) {
            $api_status_code_class_call->respondBadRequest($user_id, API_User_Response::$user_id_required);
        }

        // -------------------------
        // 3) Fetch user + KYC details
        // -------------------------
        $selectColumns = "id, fullname, fname, lname, email, phoneno, status, kyc_type, kyc_document, kyc_verified, created_at, updated_at";

        $userRows = $db_call_class->selectRows(
            "users",
            $selectColumns,
            [[
                ['column' => 'id', 'operator' => '=', 'value' => $user_id]
            ]]
        );

        if ($utility_class_call->input_is_invalid($userRows)) {
            $api_status_code_class_call->respondNotFound([], API_User_Response::$userNotFound);
        }

        $user = $userRows[0];

        // Normalize name field if fullname missing
        if (empty($user['fullname'])) {
            $user['fullname'] = trim(($user['fname'] ?? '') . ' ' . ($user['lname'] ?? ''));
        }

        // Prepare KYC document URL if stored as filename (adjust path if needed)
        if (!empty($user['kyc_document'])) {
            // you may want to change the base URL/path according to your upload setup
            $user['kyc_document_url'] = rtrim($_ENV['APP_BASE_URL'] ?? '', '/') . '/uploads/kyc/' . $user['kyc_document'];
        } else {
            $user['kyc_document_url'] = null;
        }

        // -------------------------
        // 4) Respond with user data
        // -------------------------
        $api_status_code_class_call->respondOK(
            $user,
            API_User_Response::$userfetched);
        

    } catch (\Exception $e) {
        // Use utility helper if available to extract exception details
        $details = method_exists($utility_class_call, 'get_details_from_exception')
            ? $utility_class_call->get_details_from_exception($e)
            : $e->getMessage();

        $api_status_code_class_call->respondInternalError($details);
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
?>