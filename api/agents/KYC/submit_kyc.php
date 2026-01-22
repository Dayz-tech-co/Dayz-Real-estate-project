<?php

require __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . "/../../../bootstrap.php";

use Config\API_Status_Code;
use Config\API_User_Response;
use Config\DB_Calls_Functions;
use Config\Utility_Functions;
use Config\Mail_SMS_Responses;

$api_status_code_class_call = new API_Status_Code;
$db_call_class = new DB_Calls_Functions;
$utility_class_call = new Utility_Functions;
$mail_sms_call = new Mail_SMS_Responses;

header("Content-Type: application/json");

$api_method = "POST";

if (getenv('REQUEST_METHOD') === $api_method) {
    try {
        // Validate Agent Token
        $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1, 2);
        $agent_pubkey = $decodedToken->usertoken;

        // Fetch Agent Info
        $getAgent = $db_call_class->selectRows("agents", "id, agency_name, email, kyc_verified", [[
            ['column' => 'agentpubkey', 'operator' => '=', 'value' => $agent_pubkey]
        ]]);

        if ($utility_class_call->input_is_invalid($getAgent)) {
            $api_status_code_class_call->respondUnauthorized();
        }

        $agent_id = $getAgent[0]['id'];
        $agency_name = $getAgent[0]['agency_name'];
        $agent_email = $getAgent[0]['email'];
        $verified_kyc = (int)$getAgent[0]['kyc_verified'];

        // Prevent re-submission if already verified
        if ($verified_kyc === 1) {
            $api_status_code_class_call->respondBadRequest(API_User_Response::$kycAlreadyVerified);
        }

        // Collect and sanitize inputs
        $business_name      = $utility_class_call->clean_user_data($_POST['business_name'] ?? '', 1);
        $cac_number         = $utility_class_call->clean_user_data($_POST['cac_number'] ?? '', 1);
        $business_address   = $utility_class_call->clean_user_data($_POST['business_address'] ?? '', 1);
        $document_front     = $utility_class_call->clean_user_data($_POST['document_front'] ?? '', 1);
        $document_back      = $utility_class_call->clean_user_data($_POST['document_back'] ?? '', 1);
        $city               = $utility_class_call->clean_user_data($_POST['city'] ?? '', 1);
        $state              = $utility_class_call->clean_user_data($_POST['state'] ?? '', 1);
        $country            = $utility_class_call->clean_user_data($_POST['country'] ?? '', 1);

        if (
            empty($business_name) || empty($cac_number) ||
            empty($business_address) || empty($document_front) ||
            empty($document_back) || empty($city) ||
            empty($state) || empty($country)
        ) {
            $api_status_code_class_call->respondBadRequest(API_User_Response::$missingrequiredfields);
        }
        // Check if Agent already has a KYC record
        $existingKYC = $db_call_class->selectRows("kyc_verifications", "id, status, verified", [[
            ['column' => 'agent_id', 'operator' => '=', 'value' => $agent_id]
        ]]);

        // Prevent duplicate submission if already pending or verified
        if (!empty($existingKYC)) {
            $kycStatus = strtolower($existingKYC[0]['status'] ?? '');
            $kycVerified = (int)($existingKYC[0]['verified'] ?? 0);

            if ($kycStatus === 'pending') {
                $api_status_code_class_call->respondBadRequest(API_User_Response::$kycpendingreview);
            }

            if ($kycVerified === 1 || $kycStatus === 'verified') {
                $api_status_code_class_call->respondBadRequest(API_User_Response::$kycAlreadyVerified);
            }
        }

        // Prepare KYC data (aligned with your table)
        $kycData = [
            "agent_id"              => $agent_id,
            "agency_name"           => $business_name,
            "business_reg_no"       => $cac_number,
            "government_id_type"    => "CAC",
            "government_id_number"  => $cac_number,
            "document_front"        => $document_front,
            "document_back"         => $document_back,
            "address"               => $business_address,
            "city"                  => $city,
            "state"                 => $state,
            "country"               => $country,
            "status"                => "pending",
            "verified"              => 0,
            "admin_comment"         => null,
            "created_at"            => date("Y-m-d H:i:s"),
            "updated_at"            => date("Y-m-d H:i:s")
        ];

        // Insert or update depending on record existence
        if (empty($existingKYC)) {
            $insert = $db_call_class->insertRow("kyc_verifications", $kycData);
        } else {
            $insert = $db_call_class->updateRows("kyc_verifications", $kycData, [
                ['column' => 'agent_id', 'operator' => '=', 'value' => $agent_id]
            ]);
        }


        //Prevent duplicate submission if already pending or verified
        if (!empty($existingKYC)) {
            $kycStatus = strtolower($existingKYC[0]['status'] ?? '');
            $kycVerified = (int)($existingKYC[0]['verified'] ?? 0);

            if ($kycStatus === 'pending') {
                $api_status_code_class_call->respondBadRequest(API_User_Response::$kycpendingreview);
            }

            if ($kycVerified === 1 || $kycStatus === 'verified') {
                $api_status_code_class_call->respondBadRequest(API_User_Response::$kycAlreadyVerified);
            }
        }


        if ($insert > 0) {
            // Reflect submission in agents table
            $db_call_class->updateRows("agents", ["kyc_verified" => 0], [
                ['column' => 'id', 'operator' => '=', 'value' => $agent_id]
            ]);

            // Send confirmation email
            $systemname = $_ENV['APP_NAME'];
            $subject = "KYC Submission Received Pending Review";
            $messagetitle = "KYC Under Review";
            $greetingText = "Dear $agency_name,<br><br>";

            $mailText = "
                Your KYC submission with CAC Number <strong>$cac_number</strong> has been successfully received.<br><br>
                Our verification team will carefully review your business registration, and you’ll receive a response within <strong>3–4 working days</strong> regarding the status of your verification.<br><br>
                Thank you for your patience, cooperation, and trust in <strong>$systemname</strong>.<br><br>
                Warm regards,<br>
                <strong>$systemname Verification Team</strong>
            ";

            $messageText = "Your KYC submission for $systemname has been received and is currently under review.";
            $messageHTML = $mail_sms_call->generalMailTemplate($messagetitle, $greetingText, $mailText, "");

            $mail_sms_call->sendUserMail($subject, $agent_email, $messageText, $messageHTML);

            // Success response
            $api_status_code_class_call->respondOK([
                "kyc_status" => "pending",
                "message" => "KYC successfully submitted and is under review"
            ], API_User_Response::$kycSubmittedSuccessfully);
        } else {
            $api_status_code_class_call->respondInternalError(API_User_Response::$kycsubmittedfailed);
        }
    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
