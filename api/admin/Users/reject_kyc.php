<?php
require __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . "/../../../bootstrap.php";

use Config\API_Status_Code;
use Config\API_User_Response;
use Config\DB_Calls_Functions;
use Config\Utility_Functions;
use Config\Mail_SMS_Responses;

$api_status = new API_Status_Code;
$db = new DB_Calls_Functions;
$util = new Utility_Functions;
$mail = new Mail_SMS_Responses;

header("Content-type: application/json");

$api_method = "POST";

if ($_SERVER['REQUEST_METHOD'] === $api_method) {
    try {

        // Validate Admin Token
        $decodedToken = $api_status->ValidateAPITokenSentIN(1, 1);
        $admin_pubkey = $decodedToken->usertoken;

        // Inputs
        $user_id = isset($_POST['user_id']) ? $util->clean_user_data($_POST['user_id'], 1) : "";
        $reason  = isset($_POST['reason']) ? $util->clean_user_data($_POST['reason'], 1) : "";

        if (empty($user_id) || empty($reason)) {
            $api_status->respondBadRequest(API_User_Response::$missingrequiredfields);
        }

        // Fetch User
        $user = $db->selectRows("users",
            "id, fname, lname, email",
            [[ ['column' => 'id', 'operator' => '=', 'value' => $user_id] ]]
        );

        if ($util->input_is_invalid($user)) {
            $api_status->respondNotFound(["message" => "User not found"]);
        }

        $fname = $user[0]['fname'];
        $lname = $user[0]['lname'];
        $email = $user[0]['email'];

        // Fetch User KYC
        $kyc = $db->selectRows("users_kyc_verifications",
            "id, government_id_number",
            [[ ['column' => 'user_id', 'operator' => '=', 'value' => $user_id] ]]
        );

        if ($util->input_is_invalid($kyc)) {
            $api_status->respondNotFound(["message" => "User KYC not found"]);
        }

        $kyc_id = $kyc[0]['id'];
        $gov_id = $kyc[0]['government_id_number'];

        // Update KYC table
        $updateKYC = $db->updateRows("users_kyc_verifications", [
            "status"        => "rejected",
            "verified"      => 0,
            "admin_comment" => $reason,
            "updated_at"    => date("Y-m-d H:i:s")
        ], [
            ['column' => 'id', 'operator' => '=', 'value' => $kyc_id]
        ]);

        if ($updateKYC < 1) {
            $api_status->respondInternalError(["message" => "Failed to update KYC"]);
        }

        // Update user table
        $updateUser = $db->updateRows("users", [
            "kyc_verified" => "pending",
            "status"       => "rejected"
        ], [
            ['column' => 'id', 'operator' => '=', 'value' => $user_id]
        ]);

        if ($updateUser < 1) {
            $api_status->respondInternalError(["message" => "Failed to update user"]);
        }

        // Email notification
        $systemname = $_ENV['APP_NAME'];
        $subject = "Your KYC Was Rejected - $systemname";

        $greeting = "Dear $fname $lname,<br><br>";
        $title = "KYC Verification Result";

        $mailBody = "
            We were unable to verify your identity documents.<br><br>
            <strong>Rejection Reason:</strong> $reason <br><br>
            <strong>ID Provided:</strong> $gov_id <br><br>
            Please log in and upload correct documents.<br><br>
            Regards,<br><strong>$systemname Verification Team</strong>
        ";

        $mailHTML = $mail->generalMailTemplate($title, $greeting, $mailBody, "");
        $mail->sendUserMail($subject, $email, strip_tags($mailBody), $mailHTML);

        // API Response
        $api_status->respondOK([
            "user_id" => $user_id,
            "kyc_status" => "rejected",
            "reason" => $reason
        ], "User KYC rejected successfully");

    } catch (Exception $e) {
        $api_status->respondInternalError($util->get_details_from_exception($e));
    }

} else {
    $api_status->respondMethodNotAlowed();
}
