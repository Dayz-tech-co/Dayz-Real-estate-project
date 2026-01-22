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
        //Validate Admin Token
        $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1, 1);
        $admin_pubkey = $decodedToken->usertoken;

        $getAdmin = $db_call_class->selectRows("admins", "id", [[
            ['column' => 'adminpubkey', 'operator' => '=', 'value' => $admin_pubkey]
        ]], ['limit' => 1]);

        if ($utility_class_call->input_is_invalid($getAdmin)) {
            $api_status_code_class_call->respondUnauthorized();
        }

        $admin_id = $getAdmin[0]['id'];

        //Collect Inputs
        $property_id = $utility_class_call->clean_user_data($_POST['property_id'] ?? '', 1);
        $status = strtolower($utility_class_call->clean_user_data($_POST['status'] ?? '', 1));
        $reason = $utility_class_call->clean_user_data($_POST['reason'] ?? '', 1);

        // (metadata updates)
        $fieldsToUpdate = [
            "title", "description", "price", "property_category", "property_type",
            "bed", "bath", "balc", "hall", "kitc", "floor", "asize",
            "city", "state", "location", "feature"
        ];

        $updateData = ["status" => $status];

        foreach ($fieldsToUpdate as $field) {
            if (isset($_POST[$field]) && $_POST[$field] !== '') {
                $value = $utility_class_call->clean_user_data($_POST[$field], 1);
                $updateData[$field] = $value;
            }
        }

        //Validate status
        $validStatuses = ['pending', 'approved', 'rejected', 'flagged'];
        if (!in_array($status, $validStatuses)) {
            $api_status_code_class_call->respondBadRequest($validStatuses, $status, API_User_Response::$invalidpropertystatus);
        }

        if (empty($property_id)) {
            $api_status_code_class_call->respondBadRequest(API_User_Response::$propertyidrequired);
        }

        //Fetch Property
        $getProperty = $db_call_class->selectRows(
            "properties",
            "id, title, status, agent_id",
            [[['column' => 'id', 'operator' => '=', 'value' => $property_id]]],
            ['limit' => 1]
        );

        if ($utility_class_call->input_is_invalid($getProperty)) {
            $api_status_code_class_call->respondNotFound(API_User_Response::$propertynotfound);
        }

        $old_status = $getProperty[0]['status'];
        $agent_id = $getProperty[0]['agent_id'];
        $propertyTitle = $getProperty[0]['title'] ?? "Property #$property_id";

        //Verify Agent
        $getAgent = $db_call_class->selectRows(
            "agents",
            "id, agency_name, email, phoneno, status",
            [[['column' => 'id', 'operator' => '=', 'value' => $agent_id]]],
            ['limit' => 1]
        );

        if ($utility_class_call->input_is_invalid($getAgent)) {
            $api_status_code_class_call->respondBadRequest(API_User_Response::$agentnotfound);
        }

        $agent = $getAgent[0];

        //Update Property
        $updateResponse = $db_call_class->updateRows(
            "properties",
            $updateData,
            [[['column' => 'id', 'operator' => '=', 'value' => $property_id]]]
        );

        if ($updateResponse <= 0) {
            $api_status_code_class_call->respondInternalError($updateResponse, API_User_Response::$failtoupdateproperty);
        }

        //Log in History
        $insertHistory = $db_call_class->insertRow("property_status_history", [
            "property_id" => $property_id,
            "old_status" => $old_status,
            "new_status" => $status,
            "reason" => $reason,
            "changed_by" => $admin_pubkey,
            "created_at" => date('Y-m-d H:i:s')
        ]);

        if (!$insertHistory) {
            $api_status_code_class_call->respondInternalError($insertHistory, "Failed to insert property history.");
        }

        //Prepare and Send Email Notification
        $systemname = $_ENV['APP_NAME'] ?? "BOTS System";
        $messagetitle = "Property Status Update";
        $greetingText = "Dear {$agent['agency_name']},<br><br>";
        $subject = "";
        $mailText = "";

        switch ($status) {
            case 'approved':
                $subject = "Property Approved - $systemname";
                $mailText = "
                    Congratulations! 🎉<br><br>
                    Your property titled <strong>{$propertyTitle}</strong> has been successfully <strong>approved</strong> on <strong>{$systemname}</strong>.<br><br>
                    <strong>Admin Remark:</strong> " . (!empty($reason) ? $reason : 'No remark provided') . "<br><br>
                    Your property is now visible to potential buyers on our platform.<br><br>
                    Thank you for your continued partnership.<br><br>
                    Warm regards,<br><strong>{$systemname} Property Review Team</strong>
                ";
                break;

            case 'rejected':
                $subject = "Property Rejected - $systemname";
                $mailText = "
                    We regret to inform you that your property titled <strong>{$propertyTitle}</strong> has been <strong>rejected</strong> after review.<br><br>
                    <strong>Reason:</strong> " . (!empty($reason) ? $reason : 'No specific reason provided') . "<br><br>
                    Please review your property details and resubmit with accurate or complete information.<br><br>
                    Warm regards,<br><strong>{$systemname} Property Review Team</strong>
                ";
                break;

            case 'flagged':
                $subject = "Property Flagged for Review - $systemname";
                $mailText = "
                    Your property titled <strong>{$propertyTitle}</strong> has been <strong>flagged</strong> for additional review.<br><br>
                    <strong>Reason:</strong> " . (!empty($reason) ? $reason : 'No specific reason provided') . "<br><br>
                    Our team will contact you if further information is needed.<br><br>
                    Warm regards,<br><strong>{$systemname} Property Compliance Team</strong>
                ";
                break;

            case 'pending':
                $subject = "Property Moved to Pending - $systemname";
                $mailText = "
                    Your property titled <strong>{$propertyTitle}</strong> has been moved back to <strong>pending</strong> status.<br><br>
                    <strong>Reason:</strong> " . (!empty($reason) ? $reason : 'Under internal review') . "<br><br>
                    Our verification team will review and update you shortly.<br><br>
                    Warm regards,<br><strong>{$systemname} Property Review Team</strong>
                ";
                break;
        }

        $messageText = "Your property '{$propertyTitle}' status has been updated to '{$status}'. Please check your email for details.";
        $messageHTML = $mail_sms_call->generalMailTemplate($messagetitle, $greetingText, $mailText, "");

        try {
            $mail_sms_call->sendUserMail($subject, $agent['email'], $messageText, $messageHTML);
        } catch (Exception $mailEx) {
            error_log("Mail sending failed: " . $mailEx->getMessage());
        }

        // Success Response
        $api_status_code_class_call->respondOK(
            [
                "property_id" => $property_id,
                "old_status" => $old_status,
                "new_status" => $status,
                "reason" => $reason,
                "agent" => [
                    "id" => $agent['id'],
                    "agency_name" => $agent['agency_name'],
                    "email" => $agent['email'],
                    "phone" => $agent['phoneno'],
                    "status" => $agent['status']
                ]
            ],
            API_User_Response::$propertyupdated
        );

    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}

?>
