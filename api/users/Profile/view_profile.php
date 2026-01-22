<?php
require __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . "/../../../bootstrap.php";

use Config\API_User_Response;
use Config\Utility_Functions;

$apimethod = "POST";

$api_status_call = new Config\API_Status_Code;
$db_call = new Config\DB_Calls_Functions;
$utility_class_call = new Utility_Functions;

header("Content-Type: application/json");

if (getenv("REQUEST_METHOD") !== $apimethod) {
    $api_status_call->respondMethodNotAlowed();
    exit;
}

try {

    //Validate API Token for User
    $token = $api_status_call->ValidateAPITokenSentIN(1, 3);

    // Extract user public key
    $user_pubkey = isset($token->usertoken) ? $utility_class_call->clean_user_data($token->usertoken, 1) : '';

    if ($utility_class_call->input_is_invalid($user_pubkey)) {
        $api_status_call->respondBadRequest(API_User_Response::$invalidUserDetail);
    }

    // Fetch user data
    $user = $db_call->selectRows(
        "users",
        "id, fname, lname, email, phoneno, country, email_verified, phone_verified, kyc_verified, status, created_at, updated_at",
        [
            [
                ['column' => 'userpubkey', 'operator' => '=', 'value' => $user_pubkey]
            ]
        ]
    );

    if ($utility_class_call->input_is_invalid($user)) {
        $api_status_call->respondUnauthorized();
    } else {
        $maindata = $user[0];
        $text = API_User_Response::$data_found;
        $api_status_call->respondOK($maindata, $text);
    }

} catch (\Exception $e) {
    $api_status_call->respondInternalError($utility_class_call->get_details_from_exception($e));
}
?>
