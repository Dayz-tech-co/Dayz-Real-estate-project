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

header("Content-type: application/json");

$api_method = "POST";

if (getenv('REQUEST_METHOD') === $api_method) {
    try {

        // Validate Admin Token
        $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1, 1);
        $admin_pubkey = $decodedToken->usertoken;

        // Collect Inputs
        $user_id  = isset($_POST['user_id']) ? $utility_class_call->clean_user_data($_POST['user_id'], 1) : "";
        $decision = isset($_POST['decision']) ? strtolower($utility_class_call->clean_user_data($_POST['decision'], 1)) : "";
        $reason   = isset($_POST['reason']) ? $utility_class_call->clean_user_data($_POST['reason'], 1) : "";

        if (empty($user_id) || empty($decision) || empty($reason)) {
            $api_status_code_class_call->respondBadRequest(API_User_Response::$missingrequiredfields);
        }

        // Valid decision check
        if (!in_array($decision, ['approved', 'rejected'])) {
            $api_status_code_class_call->respondBadRequest([
                "message" => "Decision must be either 'approved' or 'rejected'."
            ]);
        }

        // Fetch User
        $getUser = $db_call_class->selectRows("users", 
            "id, fname, lname, email, kyc_verified, status",
            [[ ['column' => 'id', 'operator' => '=', 'value' => $user_id] ]]
        );

        if ($utility_class_call->input_is_invalid($getUser)) {
            $api_status_code_class_call->respondNotFound(["message" => "User not found."]);
        }

        $fname = $getUser[0]['fname'];
        $lname = $getUser[0]['lname'];
        $user_email = $getUser[0]['email'];

        // Fetch USER KYC record
        $getKYC = $db_call_class->selectRows("users_kyc_verifications",
            "id, government_id_number, status",
            [[ ['column' => 'user_id', 'operator' => '=', 'value' => $user_id] ]]
        );

        if ($utility_class_call->input_is_invalid($getKYC)) {
            $api_status_code_class_call->respondNotFound(["message" => "No KYC record found for this user."]);
        }

        $kyc_id = $getKYC[0]['id'];
        $gov_id = $getKYC[0]['government_id_number'];

        // Determine status
        $status   = $decision === 'approved' ? "approved" : "rejected";
        $verified = $decision === 'approved' ? 1 : 0;

        // Update USER KYC
        $updateKYC = $db_call_class->updateRows("users_kyc_verifications", [
            "status"        => $status,
            "verified"      => $verified,
            "admin_comment" => $reason,
            "updated_at"    => date("Y-m-d H:i:s")
        ], [
            ['column' => 'id', 'operator' => '=', 'value' => $kyc_id]
        ]);

        if ($updateKYC < 1) {
            $api_status_code_class_call->respondInternalError(["message" => "Failed to update User KYC record."]);
        }

        // Update User Table
        $updateUser = $db_call_class->updateRows("users", [
            "kyc_verified" => $status === "approved" ? "verified" : "pending",
            "status"       => $status
        ], [
            ['column' => 'id', 'operator' => '=', 'value' => $user_id]
        ]);

        if ($updateUser < 1) {
            $api_status_code_class_call->respondInternalError(["message" => "Failed to update User record."]);
        }

        // Send Email Notification
        $systemname = $_ENV['APP_NAME'];

        $greetingText = "Dear $fname $lname,<br><br>";
        $messagetitle = "KYC Verification Update";

        if ($decision === 'approved') {
            $subject = "Your KYC Has Been Approved - $systemname";

            $mailText = "
                Congratulations! 🎉<br><br>
                Your identity verification has been <strong>approved</strong>.<br><br>
                <strong>ID Number:</strong> $gov_id <br><br>
                <strong>Admin Remark:</strong> $reason <br><br>
                You can now fully access all features of <strong>$systemname</strong>.<br><br>
                Warm regards,<br><strong>$systemname Verification Team</strong>
            ";

        } else {
            $subject = "Your KYC Was Rejected - $systemname";

            $mailText = "
                We could not verify your identity documents.<br><br>
                <strong>Reason:</strong> $reason <br><br>
                Please log in and resubmit the correct documents.<br><br>
                Warm regards,<br><strong>$systemname Verification Team</strong>
            ";
        }

        $messageText = "Your KYC has been $status. Please check your email for details.";
        $messageHTML = $mail_sms_call->generalMailTemplate($messagetitle, $greetingText, $mailText, "");

        $mail_sms_call->sendUserMail($subject, $user_email, $messageText, $messageHTML);

        // Response
        $api_status_code_class_call->respondOK([
            "kyc_action_result" => [
                "user_id"   => $user_id,
                "kyc_status"=> $status,
                "reason"    => $reason
            ]
        ], "KYC $status successfully");

    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($utility_class_call->get_details_from_exception($e));
    }

} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
