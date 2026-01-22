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
        $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1,1);
        $user_pubkey  = $decodedToken->usertoken;

        $getAdmin = $db_call_class->selectRows("admins", "id", [[
            ['column' => 'adminpubkey', 'operator' => '=', 'value' => $user_pubkey]
        ]]);

        if ($utility_class_call->input_is_invalid($getAdmin)) {
            $api_status_code_class_call->respondUnauthorized();
        }

        $admin_id = $getAdmin[0]['id'];

        $user_id = isset($_POST['user_id']) ? $utility_class_call->clean_user_data($_POST['user_id'], 1) : '';

        $getUser = $db_call_class->selectRows("users", "id, status", [[
            ['column' => 'id', 'operator' => '=', 'value' => $user_id]
        ]]);

        if ($utility_class_call->input_is_invalid($getUser)) {
            $api_status_code_class_call->respondNotFound($getUser, API_User_Response::$userNotFound);
        }

        $old_status = $getUser[0]['status'];
        $update = $db_call_class->updateRows("users", ["status" => "rejected"], [[
            ['column' => 'id', 'operator' => '=', 'value' => $user_id]
        ]]);

        if ($update > 0) {
            $db_call_class->insertRow("user_status_history", [
                "user_id" => $user_id,
                "old_status" => $old_status,
                "new_status" => "rejected",
               
            ]);

            $api_status_code_class_call->respondOK(
                ["user_id" => $user_id, "old_status" => $old_status, "new_status" => "rejected"],
                API_User_Response::$userrejected
            );
        } else {
            $api_status_code_class_call->respondInternalError($update, API_User_Response::$failtorejectuser);
        }

    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
?>