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
        //Validate API token for user (1)
        $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1, 3);
        $user_pubkey = $decodedToken->usertoken;

        //Fetch user record
        $getUser = $db_call_class->selectRows("users", "id, fname, lname, email, kyc_verified", [[
            ['column' => 'userpubkey', 'operator' => '=', 'value' => $user_pubkey]
        ]]);

        if ($utility_class_call->input_is_invalid($getUser)) {
            $api_status_code_class_call->respondUnauthorized();
        }

        $user_id = $getUser[0]['id'];
        $fullname = $getUser[0]['fname'] . " " . $getUser[0]['lname'];
        $user_email = $getUser[0]['email'];

        //Fetch User's KYC Record
        $getKYC = $db_call_class->selectRows(
            "users_kyc_verifications",
            "government_id_type, government_id_number, document_front, document_back, proof_of_address_type, proof_of_address_document, proof_of_address_status, proof_of_address_admin_comment, address, city, state, country, status, verified, admin_comment, created_at, updated_at",
            [[
                ['column' => 'user_id', 'operator' => '=', 'value' => $user_id]
            ]]
        );

        if ($utility_class_call->input_is_invalid($getKYC)) {
            $api_status_code_class_call->respondNotFound([], API_User_Response::$kycnotfound);
        }

        //Extract details
        $status = strtolower($getKYC[0]['status'] ?? '');
        $admin_comment = $getKYC[0]['admin_comment'] ?? null;

        //Generate a status summary message
        $status_summary = match ($status) {
            "pending" => "Your KYC verification is pending. Please allow 2–3 working days for review.",
            "approved" => "Your KYC verification has been approved. You now have full access to your account features.",
            "rejected" => "Your KYC verification was rejected. Review the admin's comment and re-submit your documents.",
            default => "Your KYC status is being processed. Please check back later."
        };

        //Prepare response data
        $kycData = [
            "government_id_type"   => $getKYC[0]['government_id_type'],
            "government_id_number" => $getKYC[0]['government_id_number'],
            "document_front"       => $getKYC[0]['document_front'],
            "document_back"        => $getKYC[0]['document_back'],
            "proof_of_address"     => $getKYC[0]['proof_of_address_document'] ?? null,
            "proof_of_address_type"=> $getKYC[0]['proof_of_address_type'] ?? null,
            "proof_of_address_status" => $getKYC[0]['proof_of_address_status'] ?? null,
            "proof_of_address_admin_comment" => $getKYC[0]['proof_of_address_admin_comment'] ?? null,
            "address"              => $getKYC[0]['address'],
            "city"                 => $getKYC[0]['city'],
            "state"                => $getKYC[0]['state'],
            "country"              => $getKYC[0]['country'],
            "status"               => ucfirst($status),
            "verified"             => (int)$getKYC[0]['verified'],
            "admin_comment"        => $admin_comment,
            "created_at"           => $getKYC[0]['created_at'],
            "updated_at"           => $getKYC[0]['updated_at'],
            "summary"              => $status_summary
        ];

        //Send success response
        $api_status_code_class_call->respondOK($kycData, API_User_Response::$kycStatusFetched);

    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
?>
