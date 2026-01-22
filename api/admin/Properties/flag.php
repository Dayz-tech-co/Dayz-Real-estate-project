<?php

require __DIR__ . '/../../../vendor/autoload.php';
use Config\API_Status_Code;
use Config\API_User_Response;
use Config\Mail_SMS_Responses;

require_once __DIR__ . "/../../../bootstrap.php";

$api_status_code_class_call = new Config\API_Status_Code;
$db_call_class = new Config\DB_Calls_Functions;
$utility_class_call = new Config\Utility_Functions;
$mail_sms_call = new Config\Mail_SMS_Responses;

$api_method = "POST";

if (getenv('REQUEST_METHOD') === $api_method) {
    try {
        //Validate token for admin
        $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1,1);
        $admin_pubkey = $decodedToken->usertoken;

        // Check if admin exists
        $getAdmin = $db_call_class->selectRows("admins", "id, adminpubkey", [[
            ['column' => 'adminpubkey', 'operator' => '=', 'value' => $admin_pubkey]
        ]]);

        if ($utility_class_call->input_is_invalid($getAdmin)) {
            $api_status_code_class_call->respondUnauthorized();
        }

        $admin_id = $getAdmin[0]['id'];

        // Collect POST data
        $property_id = isset($_POST['property_id']) ? $utility_class_call->clean_user_data($_POST['property_id'], 1) : '';
        $reason = isset($_POST['reason']) ? $utility_class_call->clean_user_data($_POST['reason'], 1) : '';

        if (empty($property_id) || empty($reason)) {
            $api_status_code_class_call->respondBadRequest([$property_id, $reason], API_User_Response::$propertyidandresonrequired);
        }

        // Check if property exists and belongs to a valid agent
        $getProperty = $db_call_class->selectRows(
            "properties",
            "id, agent_id, status, title, property_type, sale_type, city, state",
            [[
                ['column' => 'id', 'operator' => '=', 'value' => $property_id]
            ]]
        );

        if ($utility_class_call->input_is_invalid($getProperty)) {
            $api_status_code_class_call->respondNotFound($getProperty, API_User_Response::$propertynotfound);
        }

        $agent_id = $getProperty[0]['agent_id'];
        $old_status = $getProperty[0]['status'];

        // Confirm agent exists before flagging
        $getAgent = $db_call_class->selectRows("agents", "id, agency_name, email, agentpubkey, is_suspended", [[
            ['column' => 'id', 'operator' => '=', 'value' => $agent_id]
        ]]);

        if ($utility_class_call->input_is_invalid($getAgent)) {
            $api_status_code_class_call->respondNotFound($getAgent, API_User_Response::$agentnotfound);
        }

        // Check if agent is already flagged or suspended
        if ($getAgent[0]['is_suspended'] == 1) {
            $api_status_code_class_call->respondBadRequest([], API_User_Response::$agentundersuspensionorflagged);
        }

        // Flag the property
        $update = $db_call_class->updateRows("properties", ["status" => "flagged"], [[
            ['column' => 'id', 'operator' => '=', 'value' => $property_id]
        ]]);

        if ($update > 0) {
            // Record the property flag history
            $db_call_class->insertRow("property_status_history", [
                "property_id" => $property_id,
                "old_status" => $old_status,
                "new_status" => "flagged",
                "reason" => $reason,
            ]);

            // Optionally suspend the agent for investigation
            $db_call_class->updateRows("agents", ["is_suspended" => 1], [[
                ['column' => 'id', 'operator' => '=', 'value' => $agent_id]
            ]]);

            // Send flagging email to agent
            $title = $getProperty[0]['title'];
            $agency_name = $getAgent[0]['agency_name'];
            $agent_email = $getAgent[0]['email'];
            $systemname = $_ENV['APP_NAME'];
            $subject = "Property Flagged Notification - $systemname";
            $messagetitle = "Property Flagged";
            $greetingText = "Dear $agency_name,<br><br>";
            $mailText = "
                Your property listing titled <strong>\"$title\"</strong> has been flagged by our administrators after review.<br><br>
                Reason: $reason<br><br>
                As a result, your account has been temporarily suspended for investigation. Please contact our support team for further assistance.<br><br>
                Warm regards,<br>
                <strong>$systemname Verification Team</strong>
            ";
            $messageText = "Your property \"$title\" has been flagged and your account suspended. Reason: $reason";
            $messageHTML = $mail_sms_call->generalMailTemplate($messagetitle, $greetingText, $mailText, "");
            $mail_sms_call->sendUserMail($subject, $agent_email, $messageText, $messageHTML);

            $api_status_code_class_call->respondOK(
                [
                    "property_id" => $property_id,
                    "agent_id" => $agent_id,
                    "old_status" => $old_status,
                    "new_status" => "flagged",
                    "reason" => $reason
                ],
                API_User_Response::$propertyflagged
            );
        } else {
            $api_status_code_class_call->respondInternalError($update, API_User_Response::$failtoflaproperty);
        }

    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}

?>
