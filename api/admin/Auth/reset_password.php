<?php
require __DIR__ . '/../../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

require_once __DIR__ . "/../../../bootstrap.php";

use Config\API_User_Response;
use Config\Utility_Functions;

$apimethod = "POST";
$api_status_code_class_call = new Config\API_Status_Code;
$db_call_class = new Config\DB_Calls_Functions;

if (getenv('REQUEST_METHOD') == $apimethod) {
    try {
        $token = isset($_POST['token']) ? $utility_class_call->clean_user_data($_POST['token']) : '';
        $otp = isset($_POST['otp']) ? $utility_class_call->clean_user_data($_POST['otp']) : 0;
        $newpassword = isset($_POST['newpassword']) ? $utility_class_call->clean_user_data($_POST['newpassword'], 1) : '';

        // validate inputs
        if ($utility_class_call->input_is_invalid($token) || $utility_class_call->input_is_invalid($otp)) {
            $api_status_code_class_call->respondBadRequest(API_User_Response::$request_body_invalid);
            exit;
        }

        if ($utility_class_call->input_is_invalid($newpassword)) {
            $api_status_code_class_call->respondBadRequest(API_User_Response::$request_body_invalid);
            exit;
        }

        // check OTP + token validity within 5 mins
        $getoldotpsent = $db_call_class->selectRows("system_otps", "user_id", [
            [
                ['column' => 'TIMESTAMPDIFF(MINUTE, created_at, NOW())', 'operator' => '<', 'value' => 5],
                ['column' => 'forwho', 'operator' => '=', 'value' => 1],
                ['column' => 'verification_type', 'operator' => '=', 'value' => 3],
                ['column' => 'token', 'operator' => '=', 'value' => $token],
                ['column' => 'otp', 'operator' => '=', 'value' => $otp],
                'operator' => 'AND'
            ]
        ]);

        if ($utility_class_call->input_is_invalid($getoldotpsent)) {
            $api_status_code_class_call->respondBadRequest(API_User_Response::$invalidOtporExpired);
            exit;
        }

        $otpdata = $getoldotpsent[0];
        $user_id = $otpdata['user_id'];

        // get user record
        $getuserattached = $db_call_class->selectRows("admins", "id,email", [
            [
                ['column' => 'id', 'operator' => '=', 'value' => $user_id]
            ]
        ]);

        if ($utility_class_call->input_is_invalid($getuserattached)) {
            $api_status_code_class_call->respondUnauthorized();
            exit;
        }

        $password = $utility_class_call->Password_encrypt($newpassword);

        $updateddate = $db_call_class->updateRows("admins", ["password" => $password], [
            [
                ['column' => 'id', 'operator' => '=', 'value' => $user_id]
            ]
        ]);

        if ($updateddate) {
            $maindata = [];
            $text = "Password reset successful.";
            $api_status_code_class_call->respondOK($maindata, $text);
        } else {
            $api_status_code_class_call->respondInternalError(API_User_Response::$error_updating_record);
        }
    } catch (\Exception $e) {
        $api_status_code_class_call->respondInternalError($utility_class_call->get_details_from_exception($e));
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
