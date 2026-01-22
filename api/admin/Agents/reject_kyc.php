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
        $agent_id = isset($_POST['agent_id']) ? $util->clean_user_data($_POST['agent_id'], 1) : "";
        $reason   = isset($_POST['reason']) ? $util->clean_user_data($_POST['reason'], 1) : "";

        if (empty($agent_id) || empty($reason)) {
            $api_status->respondBadRequest(API_User_Response::$missingrequiredfields);
        }

        // Fetch Agent
        $agent = $db->selectRows("agents",
            "id, agency_name, email",
            [[ ['column' => 'id', 'operator' => '=', 'value' => $agent_id] ]]
        );

        if ($util->input_is_invalid($agent)) {
            $api_status->respondNotFound(["message" => "Agent not found"]);
        }

        $agency_name = $agent[0]['agency_name'];
        $agent_email = $agent[0]['email'];

        // Fetch Agent KYC
        $kyc = $db->selectRows("kyc_verifications",
            "id, business_reg_no AS cac_number",
            [[ ['column' => 'agent_id', 'operator' => '=', 'value' => $agent_id] ]]
        );

        if ($util->input_is_invalid($kyc)) {
            $api_status->respondNotFound(["message" => "Agent KYC not found"]);
        }

        $kyc_id = $kyc[0]['id'];
        $cac_number = $kyc[0]['cac_number'];

        // Update KYC table
        $updateKYC = $db->updateRows("kyc_verifications", [
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

        // Update Agent Table
        $updateAgent = $db->updateRows("agents", [
            "kyc_verified" => "pending",
            "status"       => "rejected"
        ], [
            ['column' => 'id', 'operator' => '=', 'value' => $agent_id]
        ]);

        if ($updateAgent < 1) {
            $api_status->respondInternalError(["message" => "Failed to update agent"]);
        }

        // Send Email Notification
        $systemname = $_ENV['APP_NAME'];
        $subject = "Your KYC Was Rejected - $systemname";

        $greeting = "Dear $agency_name,<br><br>";
        $title = "KYC Verification Result";

        $mailBody = "
            We could not verify your business documents.<br><br>
            <strong>Rejection Reason:</strong> $reason <br><br>
            <strong>CAC Number:</strong> $cac_number <br><br>
            Please log in and resubmit valid documents.<br><br>
            Regards,<br><strong>$systemname Verification Team</strong>
        ";

        $mailHTML = $mail->generalMailTemplate($title, $greeting, $mailBody, "");
        $mail->sendUserMail($subject, $agent_email, strip_tags($mailBody), $mailHTML);

        // Response
        $api_status->respondOK([
            "agent_id"   => $agent_id,
            "kyc_status" => "rejected",
            "reason"     => $reason
        ], "Agent KYC rejected successfully");

    } catch (Exception $e) {
        $api_status->respondInternalError($util->get_details_from_exception($e));
    }

} else {
    $api_status->respondMethodNotAlowed();
}
