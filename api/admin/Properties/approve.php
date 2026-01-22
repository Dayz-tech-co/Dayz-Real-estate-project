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
        // Validate admin token
        $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1, 1);
        $admin_pubkey = $decodedToken->usertoken;

        // Verify admin
        $getAdmin = $db_call_class->selectRows("admins", "id", [[
            ['column' => 'adminpubkey', 'operator' => '=', 'value' => $admin_pubkey]
        ]]);

        if ($utility_class_call->input_is_invalid($getAdmin)) {
            $api_status_code_class_call->respondUnauthorized();
        }

        $property_id = $utility_class_call->clean_user_data($_POST['property_id'] ?? '', 1);
        if (empty($property_id) || !is_numeric($property_id)) {
            $api_status_code_class_call->respondBadRequest("Invalid property ID");
        }

        // Fetch property
        $getProperty = $db_call_class->selectRows(
            "properties",
            "id, title, description, property_type, bed, bath, balc, hall, kitc, floor, asize, price, feature, featured, city, state, location, agent_id, status, created_at",
            [[
                ['column' => 'id', 'operator' => '=', 'value' => $property_id]
            ]]
        );

        if ($utility_class_call->input_is_invalid($getProperty)) {
            $api_status_code_class_call->respondNotFound(API_User_Response::$propertynotfound);
        }

        // Verify agent
        $agent_id = $getProperty[0]['agent_id'];
        $getAgent = $db_call_class->selectRows(
            "agents",
            "id,  agency_name, email, status",
            [[
                ['column' => 'id', 'operator' => '=', 'value' => $agent_id]
            ]]
        );

        if ($utility_class_call->input_is_invalid($getAgent)) {
            $api_status_code_class_call->respondBadRequest(API_User_Response::$agentnotfound);
        }

        // Ensure agent is approved
        if (strtolower($getAgent[0]['status']) !== "approved") {
            $api_status_code_class_call->respondBadRequest(API_User_Response::$agentnotapprovetoaddproperty);
        }

        // Check if already approved
        $old_status = strtolower($getProperty[0]['status']);
        if ($old_status === 'approved') {
            $api_status_code_class_call->respondBadRequest("Property already approved");
        }

        // Update property status
        $update = $db_call_class->updateRows("properties", ["status" => "approved"], [[
            ['column' => 'id', 'operator' => '=', 'value' => $property_id]
        ]]);

        if ($update) {
            $db_call_class->insertRow("property_status_history", [
                "property_id" => $property_id,
                "old_status" => $old_status,
                "new_status" => "approved",
                "changed_by" => $admin_pubkey,
                "created_at" => date('Y-m-d H:i:s')
            ]);

            // Send approval email to agent
            $title = $getProperty[0]['title'];
            $agency_name = $getAgent[0]['agency_name'];
            $agent_email = $getAgent[0]['email'];
            $systemname = $_ENV['APP_NAME'];
            $subject = "Property Approval Notification - $systemname";
            $messagetitle = "Property Approved";
            $greetingText = "Dear $agency_name,<br><br>";
            $mailText = "
                Congratulations! Your property listing titled <strong>\"$title\"</strong> has been approved by our administrators.<br><br>
                It is now live and available for users to view and book.<br><br>
                Thank you for your patience and continued trust in <strong>$systemname</strong>.<br><br>
                Warm regards,<br>
                <strong>$systemname Verification Team</strong>
            ";
            $messageText = "Your property \"$title\" has been approved and is now live.";
            $messageHTML = $mail_sms_call->generalMailTemplate($messagetitle, $greetingText, $mailText, "");
            $mail_sms_call->sendUserMail($subject, $agent_email, $messageText, $messageHTML);

            $api_status_code_class_call->respondOK(
                [
                    "property_id" => $property_id,
                    "old_status" => $old_status,
                    "new_status" => "approved",
                    "agent" => [
                        "id" => $getAgent[0]['id'],
                        "agency_name" => $getAgent[0]['agency_name'],
                        "email" => $getAgent[0]['email'],
                        "status" => $getAgent[0]['status']
                    ]
                ],
                API_User_Response::$propertyapproved
            );
        } else {
            $api_status_code_class_call->respondInternalError(API_User_Response::$failtoapproveproperty);
        }

    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
