<?php

require __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . "/../../../bootstrap.php";

use Config\API_Status_Code;
use Config\API_User_Response;
use Config\DB_Calls_Functions;
use Config\Utility_Functions;

$api_status_code_class_call = new API_Status_Code;
$db_call_class = new DB_Calls_Functions;
$utility_class_call = new Utility_Functions;

header("Content-Type: application/json");

$api_method = "POST";

if (getenv('REQUEST_METHOD') === $api_method) {
    try {
        // Validate Agent
        $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1, 2);
        $agent_pubkey = $decodedToken->usertoken;

        // Fetch Agent
        $getAgent = $db_call_class->selectRows("agents", "id, agency_name, email, kyc_verified", [[
            ['column' => 'agentpubkey', 'operator' => '=', 'value' => $agent_pubkey]
        ]]);

        if ($utility_class_call->input_is_invalid($getAgent)) {
            $api_status_code_class_call->respondUnauthorized();
        }

        $agent_id = $getAgent[0]['id'];
        $agency_name = $getAgent[0]['agency_name'];
        $agent_email = $getAgent[0]['email'];

        // Fetch Agent's KYC Record
        $getKYC = $db_call_class->selectRows(
            "kyc_verifications",
            "business_reg_no, government_id_type, government_id_number, document_front, document_back, address, city, state, country, status, verified, admin_comment, created_at, updated_at",
            [[
                ['column' => 'agent_id', 'operator' => '=', 'value' => $agent_id]
            ]]
        );

        if ($utility_class_call->input_is_invalid($getKYC)) {
            $api_status_code_class_call->respondNotFound([], API_User_Response::$kycnotfound);
        }

        // Extract details
        $status = strtolower($getKYC[0]['status'] ?? '');
        $admin_comment = $getKYC[0]['admin_comment'] ?? null;

        // Prepare summary message
        $status_summary = "";
        if ($status === "pending") {
            $status_summary = "Your KYC verification is still pending. Please allow 3–4 working days for our team to review your documents.";
        } elseif ($status === "verified") {
            $status_summary = "Your KYC verification has been approved. You now have full access to all agent features.";
        } elseif ($status === "rejected") {
            $status_summary = "Your KYC verification was rejected. Please review the admin's comment and re-submit your documents.";
        } else {
            $status_summary = "KYC status is currently being processed. Please check back later.";
        }

        // Prepare final response data
        $kycData = [
            "business_reg_no"       => $getKYC[0]['business_reg_no'],
            "government_id_type"    => $getKYC[0]['government_id_type'],
            "government_id_number"  => $getKYC[0]['government_id_number'],
            "document_front"        => $getKYC[0]['document_front'],
            "document_back"         => $getKYC[0]['document_back'],
            "address"               => $getKYC[0]['address'],
            "city"                  => $getKYC[0]['city'],
            "state"                 => $getKYC[0]['state'],
            "country"               => $getKYC[0]['country'],
            "status"                => ucfirst($status),
            "verified"              => (int)$getKYC[0]['verified'],
            "admin_comment"         => $admin_comment,
            "created_at"            => $getKYC[0]['created_at'],
            "updated_at"            => $getKYC[0]['updated_at'],
            "summary"               => $status_summary
        ];

        // Respond success
        $api_status_code_class_call->respondOK($kycData, API_User_Response::$kycStatusFetched);

    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
