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
        // Validate token
        $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1,1);
        $user_pubkey  = $decodedToken->usertoken;

        // Confirm admin
        $getAdmin = $db_call_class->selectRows("admins", "id", [[
            ['column' => 'adminpubkey', 'operator' => '=', 'value' => $user_pubkey]
        ]]);

        if ($utility_class_call->input_is_invalid($getAdmin)) {
            $api_status_code_class_call->respondUnauthorized();
        }

        
        $status = isset($_GET['status']) ? $utility_class_call->clean_user_data(strtolower( $_GET['status'])) : '';
        $page   = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $limit  = 10;
        $offset = ($page - 1) * $limit;

        $conditions = [];
        if (!empty($status)) {
            $conditions[] = [
                'column' => 'status',
                'operator' => '=',
                'value' => $status
            ];
        }

        $users = $db_call_class->selectRows(
            "users",
            "id, fname, lname, email, phoneno, status, created_at",
            $conditions,
            $limit,
            $offset
        );

        $api_status_code_class_call->respondOK($users, API_User_Response::$usersfetchedsuccessfully);
    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
