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

     // Validate API Token for Agent (2)
    $token = $api_status_call->ValidateAPITokenSentIN(1,2);

    // Extract agent public key
    $agent_pubkey = isset($token->usertoken) ? $utility_class_call->clean_user_data($token->usertoken, 1) : '';

    if ($utility_class_call->input_is_invalid($agent_pubkey)) {
        $api_status_call->respondBadRequest(API_User_Response::$invalidUserDetail);
    }

    $agency_name = isset($_POST['agency_name']) ? $utility_class_call->clean_user_data($_POST['agency_name']) : '';

    $conditions = [];
    if (!$utility_class_call->input_is_invalid($agency_name)) {
        $conditions = [[
            ['column' => 'agency_name', 'operator' => '=', 'value' => $agency_name]
        ]];
    } else {
        $conditions = [[
            ['column' => 'Agentpubkey', 'operator' => '=', 'value' => $agent_pubkey]
        ]];
    }

    $agent = $db_call->selectRows("agents", "id,full_name,agency_name,email,phoneno,business_address,state,city,kyc_verified,status,emailverified,phoneverified,created_at,updated_at", $conditions);

    if ($utility_class_call->input_is_invalid($agent)) {
        $api_status_call->respondUnauthorized();
    } else {
        $maindata = $agent[0];
        $kyc = $db_call->selectRows("kyc_verifications", "business_reg_no", [[
            ['column' => 'agent_id', 'operator' => '=', 'value' => $maindata['id']]
        ]]);
        $maindata['cac_number'] = !empty($kyc[0]['business_reg_no']) ? $kyc[0]['business_reg_no'] : '';
        $text = API_User_Response::$data_found;
        $api_status_call->respondOK($maindata, $text);
    }
} catch (\Exception $e) {
    $api_status_call->respondInternalError($utility_class_call->get_details_from_exception($e));
}
?>
