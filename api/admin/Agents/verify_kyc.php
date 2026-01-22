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

        // Collect Input Data
        $agent_id = isset($_POST['agent_id']) ? $utility_class_call->clean_user_data($_POST['agent_id'], 1) : "";
        $decision = isset($_POST["decision"]) ? strtolower($utility_class_call->clean_user_data($_POST['decision'], 1)) : "";
        $reason = isset($_POST["reason"]) ? $utility_class_call->clean_user_data($_POST['reason'], 1) : "";

        if (empty($agent_id) || empty($decision) || empty($reason)) {
            $api_status_code_class_call->respondBadRequest(API_User_Response::$missingrequiredfields);
        }

        // Validate Decision
        if (!in_array($decision, ['approved', 'rejected'])) {
            $api_status_code_class_call->respondBadRequest([
                "message" => "Decision must be either 'approved' or 'rejected'."
            ]);
        }

        // Fetch Agent and KYC record
        $getAgent = $db_call_class->selectRows("agents", "id, agency_name, email, kyc_verified, status", [[
            ['column' => 'id', 'operator' => '=', 'value' => $agent_id]
        ]]);

        // Check if already verified to prevent duplicate updates or mail
        if (strtolower($getAgent[0]['kyc_verified']) === 'verified' && strtolower($decision) === 'approved') {
            $api_status_code_class_call->respondOK([
                "kyc_action_result" => [
                    "agent_id" => $agent_id,
                    "kyc_status" => "approved",
                    "reason" => "Already verified before, no new action taken."
                ]
            ], "KYC already verified and approved previously. No duplicate action performed.");
        }


        if ($utility_class_call->input_is_invalid($getAgent)) {
            $api_status_code_class_call->respondNotFound(["message" => "Agent not found."]);
        }

        $agency_name = $getAgent[0]['agency_name'];
        $agent_email = $getAgent[0]['email'];
        $old_status = $getAgent[0]['status'];

        $getKYC = $db_call_class->selectRows("kyc_verifications", "id, business_reg_no AS cac_number, status", [[
            ['column' => 'agent_id', 'operator' => '=', 'value' => $agent_id]
        ]]);

        if ($utility_class_call->input_is_invalid($getKYC)) {
            $api_status_code_class_call->respondNotFound(["message" => "No KYC record found for this agent."]);
        }

        $kyc_id = $getKYC[0]['id'];
        $cac_number = $getKYC[0]['cac_number'];

        // Determine status and verified flag
        $status = $decision === 'approved' ? "approved" : "rejected";
        $verified = $decision === 'approved' ? 1 : 0;

        // Update KYC Record
        $updateKYC = $db_call_class->updateRows("kyc_verifications", [
            "status" => $status,
            "verified" => $verified,
            "admin_comment" => $reason,
            "updated_at" => date("Y-m-d H:i:s")
        ], [
            ['column' => 'id', 'operator' => '=', 'value' => $kyc_id]
        ]);

        if ($updateKYC < 1) {
            $api_status_code_class_call->respondInternalError(["message" => "Failed to update KYC record."]);
        }

        // Update Agent Table (status + verified flag)
        // Update Agent Table
        $agent_update_data = [];

        if ($decision === 'approved') {
            $agent_update_data = [
                "status" => "approved",
                "kyc_verified" => "verified"
            ];
        } else {
            $agent_update_data = [
                "status" => "rejected",
                "kyc_verified" => "pending"
            ];
        }

        $updateAgent = $db_call_class->updateRows("agents", $agent_update_data, [
            ['column' => 'id', 'operator' => '=', 'value' => $agent_id]
        ]);

        if ($updateAgent < 1) {
            $api_status_code_class_call->respondInternalError(["message" => "Failed to update Agent record."]);
        }


        // Log into agent_status_history
        // Check if a record already exists for this decision
        $existing_history = $db_call_class->selectRows("agent_status_history", "id", [[
            ['column' => 'agent_id', 'operator' => '=', 'value' => $agent_id],
            ['column' => 'new_status', 'operator' => '=', 'value' => $decision]
        ]]);

        if (empty($existing_history)) {
            $db_call_class->insertRow("agent_status_history", [
                "agent_id"   => $agent_id,
                "old_status" => $getAgent[0]['kyc_verified'] === 'verified' ? 'approved' : 'pending',
                "new_status" => $decision,
                "changed_by" => $admin_pubkey,
                "reason"     => $reason,
                "created_at" => date("Y-m-d H:i:s")
            ]);
        }


        // Prepare Mail Message
        $systemname = $_ENV['APP_NAME'];
        $messagetitle = "KYC Verification Update";
        $greetingText = "Dear $agency_name,<br><br>";

        if ($decision === 'approved') {
            $subject = "KYC Verification Approved $systemname";
            $mailText = "
                Congratulations! 🎉<br><br>
                Your KYC verification for CAC Number <strong>$cac_number</strong> has been successfully <strong>approved</strong>.<br><br>
                <strong>Admin Remark:</strong> $reason<br><br>
                Thank you for trusting <strong>$systemname</strong>.<br><br>
                Warm regards,<br><strong>$systemname Verification Team</strong>
            ";
        } else {
            $subject = "KYC Verification Rejected $systemname";
            $mailText = "
                Your KYC verification for CAC Number <strong>$cac_number</strong> has been <strong>rejected</strong>.<br><br>
                <strong>Reason:</strong> $reason<br><br>
                Kindly review your submission and resubmit with correct information or valid documents.<br><br>
                Warm regards,<br><strong>$systemname Verification Team</strong>
            ";
        }

        $messageText = "Your KYC verification has been $status. Please check your email for details.";
        $messageHTML = $mail_sms_call->generalMailTemplate($messagetitle, $greetingText, $mailText, "");
        $mail_sms_call->sendUserMail($subject, $agent_email, $messageText, $messageHTML);

        // Paginated List (View Updated Pending KYC)
        $page = (int)($_POST['page'] ?? 1);
        $limit = 10;
        $offset = ($page - 1) * $limit;

        $pendingKYC = $db_call_class->selectRows(
            'kyc_verifications k',
            [
                'k.id',
                'a.agency_name',
                'a.email',
                'k.business_reg_no AS cac_number',
                'k.status',
                'k.created_at'
            ],
            [
                ['column' => 'k.status', 'operator' => '=', 'value' => 'pending']
            ],
            [
                'joins' => [
                    [
                        'type' => 'INNER',
                        'table' => 'agents a',
                        'condition' => 'a.id = k.agent_id'
                    ]
                ],
                'orderBy' => 'k.created_at',
                'orderDirection' => 'DESC',
                'limit' => $limit,
                'pageno' => $page
            ]
        );

        $totalPending = $db_call_class->selectRows(
            "kyc_verifications",
            "COUNT(*) AS total",
            [[
                ['column' => 'status', 'operator' => '=', 'value' => 'pending']
            ]]
        );

        $total_records = (int)$totalPending[0]['total'];

        $api_status_code_class_call->respondOK([
            "kyc_action_result" => [
                "agent_id" => $agent_id,
                "kyc_status" => $status,
                "reason" => $reason
            ],
            "pending_kyc_list" => $pendingKYC,
            "pagination" => [
                "current_page" => $page,
                "total_records" => $total_records,
                "limit_per_page" => $limit,
                "total_pages" => ceil($total_records / $limit)
            ]
        ], "KYC $status successfully and pending list refreshed.");
    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
