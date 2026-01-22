<?php
require __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . "/../../../bootstrap.php";

use Config\API_Status_Code;
use Config\API_User_Response;
use Config\DB_Calls_Functions;
use Config\Utility_Functions;
use Config\Mail_SMS_Responses;

$api_status_code_class_call = new API_Status_Code();
$db_call_class = new DB_Calls_Functions();
$utility_class_call = new Utility_Functions();
$mail_sms_call = new Mail_SMS_Responses();

header("Content-type: application/json");

$api_method = "POST";

if ($_SERVER['REQUEST_METHOD'] === $api_method) {
    try {
        // 1) Validate admin token
        $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1, 1);
        $admin_pubkey = $decodedToken->usertoken;

        // 2) Verify admin exists
        $getAdmin = $db_call_class->selectRows("admins", "id", [[
            ['column' => 'adminpubkey', 'operator' => '=', 'value' => $admin_pubkey]
        ]], ['limit' => 1]);

        if ($utility_class_call->input_is_invalid($getAdmin)) {
            $api_status_code_class_call->respondUnauthorized();
        }

        // 3) Read & validate inputs
        $property_id = $utility_class_call->clean_user_data($_POST['property_id'] ?? '', 1);
        $reason = $utility_class_call->clean_user_data($_POST['reason'] ?? '', 1);

        if (empty($property_id) || empty($reason)) {
            $api_status_code_class_call->respondBadRequest(API_User_Response::$propertyidandresonrequired);
        }

        // 4) Fetch property
        $getProperty = $db_call_class->selectRows(
            "properties",
            "id, title, description, property_type, property_category, bed, bath, balc, hall, kitc, floor, asize, price, feature, city, state, location, agent_id, status, created_at",
            [[
                ['column' => 'id', 'operator' => '=', 'value' => $property_id]
            ]],
            ['limit' => 1]
        );

        if ($utility_class_call->input_is_invalid($getProperty)) {
            $api_status_code_class_call->respondNotFound(API_User_Response::$propertynotfound);
        }

        $property = $getProperty[0];
        $old_status = strtolower($property['status'] ?? '');

        // 5) Fetch agent (ensure agent exists)
        $agent_id = $property['agent_id'] ?? 0;
        $getAgent = $db_call_class->selectRows(
            "agents",
            "id, agency_name, email, status",
            [[
                ['column' => 'id', 'operator' => '=', 'value' => $agent_id]
            ]],
            ['limit' => 1]
        );

        if ($utility_class_call->input_is_invalid($getAgent)) {
            $api_status_code_class_call->respondBadRequest(API_User_Response::$agentnotfound);
        }

        $agent = $getAgent[0];

        // Optional: Prevent rejecting already rejected properties
        if ($old_status === 'rejected') {
            $api_status_code_class_call->respondBadRequest("Property is already rejected.");
        }

        // 6) Update property status to rejected
        $update = $db_call_class->updateRows(
            "properties",
            ["status" => "rejected"],
            [[ ['column' => 'id', 'operator' => '=', 'value' => $property_id] ]]
        );

        // updateRows() returns boolean (true on success). Check and then verify DB state.
        if (! $update) {
            // Try to fetch current DB status to give clearer diagnostic
            $verify = $db_call_class->selectRows("properties", "status", [[
                ['column' => 'id', 'operator' => '=', 'value' => $property_id]
            ]], ['limit' => 1]);

            $currentStatus = $utility_class_call->input_is_invalid($verify) ? null : ($verify[0]['status'] ?? null);

            // Clear, actionable internal error
            $api_status_code_class_call->respondInternalError([
                "message" => API_User_Response::$failtoapproveproperty,
                "details" => "Failed to update property status to 'rejected'. updateRows returned false.",
                "current_status_in_db" => $currentStatus
            ]);
        }

        // 7) Insert into property_status_history
        // Note: your table uses 'created_at' (not changed_at)
        $historyData = [
            "property_id" => $property_id,
            "old_status"  => $old_status,
            "new_status"  => "rejected",
            "reason"      => $reason,
            "changed_by"  => $admin_pubkey,
            "created_at"  => date('Y-m-d H:i:s')
        ];

        $historyInsert = $db_call_class->insertRow("property_status_history", $historyData);

        if (! $historyInsert || $historyInsert <= 0) {
            // Attempt to roll back status to previous to avoid inconsistent state
            // (best-effort, not a transaction)
            $db_call_class->updateRows("properties", ["status" => $old_status], [[
                ['column' => 'id', 'operator' => '=', 'value' => $property_id]
            ]]);

            $api_status_code_class_call->respondInternalError([
                "message" => "Failed to insert property_status_history for rejected property.",
                "history_data" => $historyData
            ]);
        }

        // Send rejection email to agent
        $title = $property['title'];
        $agency_name = $agent['agency_name'];
        $agent_email = $agent['email'];
        $systemname = $_ENV['APP_NAME'];
        $subject = "Property Rejection Notification - $systemname";
        $messagetitle = "Property Rejected";
        $greetingText = "Dear $agency_name,<br><br>";
        $mailText = "
            We regret to inform you that your property listing titled <strong>\"$title\"</strong> has been rejected after review.<br><br>
            Reason: $reason<br><br>
            Please review our submission guidelines and try resubmitting with corrections if needed.<br><br>
            Thank you for your understanding.<br><br>
            Warm regards,<br>
            <strong>$systemname Verification Team</strong>
        ";
        $messageText = "Your property \"$title\" has been rejected. Reason: $reason";
        $messageHTML = $mail_sms_call->generalMailTemplate($messagetitle, $greetingText, $mailText, "");
        $mail_sms_call->sendUserMail($subject, $agent_email, $messageText, $messageHTML);

        // 8) All good — respond success. Use agency_name (your agents table doesn't have firstname/lastname)
        $api_status_code_class_call->respondOK(
            [
                "property_id" => (string)$property_id,
                "old_status"  => $old_status,
                "new_status"  => "rejected",
                "reason"      => $reason,
                "agent" => [
                    "id" => $agent['id'],
                    "agency_name" => $agent['agency_name'] ?? '',
                    "email" => $agent['email'] ?? '',
                    "status" => $agent['status'] ?? ''
                ]
            ],
            API_User_Response::$propertyrejected
        );

    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
