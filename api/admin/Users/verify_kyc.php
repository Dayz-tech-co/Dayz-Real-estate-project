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
        $target   = isset($_POST['target']) ? strtolower($utility_class_call->clean_user_data($_POST['target'], 1)) : "kyc";

        if (empty($user_id) || empty($decision) || empty($reason)) {
            $api_status_code_class_call->respondBadRequest(API_User_Response::$missingrequiredfields);
        }

        if (!in_array($target, ['kyc', 'proof'], true)) {
            $target = 'kyc';
        }

        // Valid decision check
        if (!in_array($decision, ['approved', 'rejected'])) {
            $api_status_code_class_call->respondBadRequest([
                "message" => "Decision must be either 'approved' or 'rejected'."
            ]);
        }

        // Fetch User
        $getUser = $db_call_class->selectRows("users",
            "id, fname, lname, email, kyc_verified, status, proof_of_residence",
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
            "id, government_id_number, status, verified, proof_of_address_document, proof_of_address_status",
            [[ ['column' => 'user_id', 'operator' => '=', 'value' => $user_id] ]]
        );

        if ($utility_class_call->input_is_invalid($getKYC)) {
            $api_status_code_class_call->respondNotFound(["message" => "No KYC record found for this user."]);
        }

        $kyc_id = $getKYC[0]['id'];
        $gov_id = $getKYC[0]['government_id_number'];
        $existingKycStatus = strtolower($getKYC[0]['status'] ?? '');
        $existingKycVerified = (int)($getKYC[0]['verified'] ?? 0);
        $existingProofDoc = trim((string)($getKYC[0]['proof_of_address_document'] ?? ''));
        $existingProofStatus = strtolower($getKYC[0]['proof_of_address_status'] ?? '');

        // Block duplicate approvals.
        if ($decision === 'approved') {
            if ($target === 'proof') {
                if ($existingProofDoc === "") {
                    $api_status_code_class_call->respondBadRequest("No proof of residence submitted for this user.");
                }
                if ($existingProofStatus === 'approved') {
                    $api_status_code_class_call->respondBadRequest("Proof of residence is already approved for this user.");
                }
            } else {
                if ($existingKycStatus === 'approved' && $existingKycVerified === 1) {
                    $api_status_code_class_call->respondBadRequest("KYC/NIN is already approved for this user.");
                }
            }
        }

        // Determine status
        $status   = $decision === 'approved' ? "approved" : "rejected";
        $verified = $decision === 'approved' ? 1 : 0;

        if ($target === 'proof') {
            $updateKYC = $db_call_class->updateRows("users_kyc_verifications", [
                "proof_of_address_status"        => $status,
                "proof_of_address_admin_comment" => $reason,
                "updated_at"                     => date("Y-m-d H:i:s")
            ], [
                ['column' => 'id', 'operator' => '=', 'value' => $kyc_id]
            ]);
        } else {
            // Update USER KYC
            $updateKYC = $db_call_class->updateRows("users_kyc_verifications", [
                "status"        => $status,
                "verified"      => $verified,
                "admin_comment" => $reason,
                "updated_at"    => date("Y-m-d H:i:s")
            ], [
                ['column' => 'id', 'operator' => '=', 'value' => $kyc_id]
            ]);
        }

        if ($updateKYC < 1) {
            // updateRows() returns false when no values changed; treat already-matching state as success.
            $currentKYC = $db_call_class->selectRows("users_kyc_verifications",
                "status, verified, admin_comment, proof_of_address_status, proof_of_address_admin_comment",
                [[ ['column' => 'id', 'operator' => '=', 'value' => $kyc_id] ]]
            );

            if ($utility_class_call->input_is_invalid($currentKYC)) {
                $api_status_code_class_call->respondInternalError("Failed to update User KYC record.");
            }

            $currentKYCStatus = strtolower($currentKYC[0]['status'] ?? '');
            $currentVerified = (int)($currentKYC[0]['verified'] ?? 0);
            $currentAdminComment = trim((string)($currentKYC[0]['admin_comment'] ?? ''));
            $currentProofStatus = strtolower($currentKYC[0]['proof_of_address_status'] ?? '');
            $currentProofComment = trim((string)($currentKYC[0]['proof_of_address_admin_comment'] ?? ''));
            $expectedReason = trim((string)$reason);

            $alreadyApplied = $target === 'proof'
                ? ($currentProofStatus === $status && $currentProofComment === $expectedReason)
                : ($currentKYCStatus === $status && $currentVerified === $verified && $currentAdminComment === $expectedReason);

            if (!$alreadyApplied) {
                $api_status_code_class_call->respondInternalError("Failed to update User KYC record.");
            }
        }

        // Sync User Table from combined KYC + proof statuses so approval is automatic when verification is complete.
        $latestKYC = $db_call_class->selectRows("users_kyc_verifications",
            "status, proof_of_address_document, proof_of_address_status",
            [[ ['column' => 'id', 'operator' => '=', 'value' => $kyc_id] ]]
        );

        if ($utility_class_call->input_is_invalid($latestKYC)) {
            $api_status_code_class_call->respondInternalError("Unable to fetch updated KYC status.");
        }

        $mainKycStatus = strtolower($latestKYC[0]['status'] ?? '');
        $proofDoc = trim((string)($latestKYC[0]['proof_of_address_document'] ?? ''));
        $proofStatus = strtolower($latestKYC[0]['proof_of_address_status'] ?? '');
        $proofRequired = $proofDoc !== "";

        $finalUserStatus = "pending";
        $finalKycVerified = "pending";

        $hasRejection = ($mainKycStatus === "rejected") || ($proofRequired && $proofStatus === "rejected");
        $isFullyApproved = ($mainKycStatus === "approved") && (!$proofRequired || $proofStatus === "approved");

        if ($hasRejection) {
            $finalUserStatus = "rejected";
        } elseif ($isFullyApproved) {
            $finalUserStatus = "approved";
            $finalKycVerified = "verified";
        }

        // Update only when a change is needed; updateRows() returns false when values are unchanged.
        $currentUserStatus = strtolower($getUser[0]['status'] ?? '');
        $currentUserKycVerified = strtolower($getUser[0]['kyc_verified'] ?? '');
        $currentUserProofResidence = trim((string)($getUser[0]['proof_of_residence'] ?? ''));
        $finalUserProofResidence = $proofDoc;
        if (
            $currentUserStatus !== $finalUserStatus ||
            $currentUserKycVerified !== $finalKycVerified ||
            $currentUserProofResidence !== $finalUserProofResidence
        ) {
            $updateUser = $db_call_class->updateRows("users", [
                "kyc_verified" => $finalKycVerified,
                "status"       => $finalUserStatus,
                "proof_of_residence" => $finalUserProofResidence
            ], [
                ['column' => 'id', 'operator' => '=', 'value' => $user_id]
            ]);

            if ($updateUser < 1) {
                $api_status_code_class_call->respondInternalError("Failed to update User record.");
            }
        }

        // Send Email Notification
        $systemname = $_ENV['APP_NAME'];

        $greetingText = "Dear $fname $lname,<br><br>";
        $targetLabel = $target === 'proof' ? "Proof of Residence" : "KYC/NIN";
        $messagetitle = $targetLabel . " Verification Update";

        if ($decision === 'approved') {
            $subject = $target === 'proof'
                ? "Your Proof of Residence Has Been Approved - $systemname"
                : "Your KYC/NIN Has Been Approved - $systemname";

            $mailText = "
                Congratulations!<br><br>
                Your " . ($target === 'proof' ? "proof of residence" : "KYC/NIN verification") . " has been <strong>approved</strong>.<br><br>
                <strong>ID Number:</strong> $gov_id <br><br>
                <strong>Admin Remark:</strong> $reason <br><br>
                You can now fully access all features of <strong>$systemname</strong>.<br><br>
                Warm regards,<br><strong>$systemname Verification Team</strong>
            ";

        } else {
            $subject = $target === 'proof'
                ? "Your Proof of Residence Was Rejected - $systemname"
                : "Your KYC/NIN Was Rejected - $systemname";

            $mailText = "
                We could not verify your " . ($target === 'proof' ? "proof of residence" : "KYC/NIN documents") . ".<br><br>
                <strong>Reason:</strong> $reason <br><br>
                Please log in and resubmit the correct documents.<br><br>
                Warm regards,<br><strong>$systemname Verification Team</strong>
            ";
        }

        $messageText = $target === 'proof'
            ? "Your proof of residence has been $status. Please check your email for details."
            : "Your KYC/NIN has been $status. Please check your email for details.";
        $messageHTML = $mail_sms_call->generalMailTemplate($messagetitle, $greetingText, $mailText, "");

        $mail_sms_call->sendUserMail($subject, $user_email, $messageText, $messageHTML);

        // Response
        $api_status_code_class_call->respondOK([
            "kyc_action_result" => [
                "user_id"   => $user_id,
                "target"    => $target,
                "target_label" => $targetLabel,
                "kyc_status"=> $status,
                "reason"    => $reason
            ]
        ], $targetLabel . " " . $status . " successfully");

    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($utility_class_call->get_details_from_exception($e));
    }

} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
