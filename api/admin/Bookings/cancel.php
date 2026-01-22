<?php

require __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . "/../../../bootstrap.php";

use Config\API_Status_Code;
use Config\API_User_Response;
use Config\DB_Calls_Functions;
use Config\Utility_Functions;
use Config\Mail_SMS_Responses;
use Config\Notification_Function;

header("Content-Type: application/json");

$api_status_code_class_call = new API_Status_Code;
$db_call_class              = new DB_Calls_Functions;
$utility_class_call         = new Utility_Functions;
$mail_sms_call              = new Mail_SMS_Responses;
$notify_call                = new Notification_Function;

$api_method = "POST";

if (getenv('REQUEST_METHOD') === $api_method) {
    try {
        // 1) Validate admin token
        $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1, 1);
        $admin_pubkey = $decodedToken->usertoken;

        // 2) Verify admin
        $getAdmin = $db_call_class->selectRows("admins", "id, fname, lname, email", [[
            ['column' => 'adminpubkey', 'operator' => '=', 'value' => $admin_pubkey]
        ]], ['limit' => 1]);

        if ($utility_class_call->input_is_invalid($getAdmin)) {
            $api_status_code_class_call->respondUnauthorized();
        }
        $admin_id = $getAdmin[0]['id'];

        // 3) Inputs
        $booking_id = $utility_class_call->clean_user_data($_POST['booking_id'] ?? '', 1);
        $reason     = $utility_class_call->clean_user_data($_POST['reason'] ?? '', 1);

        if (empty($booking_id) || !is_numeric($booking_id)) {
            $api_status_code_class_call->respondBadRequest("booking_id is required and must be numeric");
        }
        if (empty($reason)) {
            $api_status_code_class_call->respondBadRequest("reason is required");
        }
        $booking_id = (int)$booking_id;

        // 4) Fetch booking with joins
        $bookingRows = $db_call_class->selectRows(
            "bookings b",
            [
                "b.id",
                "b.user_id",
                "b.property_id",
                "b.agent_id",
                "b.visit_date",
                "b.status",
                "b.booking_type",
                "b.payment_status",
                "b.payment_reference",
                "b.payment_amount",
                "u.email AS user_email",
                "u.fname AS user_fname",
                "u.lname AS user_lname",
                "a.email AS agent_email",
                "a.agency_name",
                "p.title AS property_title"
            ],
            [[
                ['column' => 'b.id', 'operator' => '=', 'value' => $booking_id]
            ]],
            [
                'limit' => 1,
                'joins' => [
                    ['type' => 'LEFT', 'table' => 'users u', 'condition' => 'b.user_id = u.id'],
                    ['type' => 'LEFT', 'table' => 'agents a', 'condition' => 'b.agent_id = a.id'],
                    ['type' => 'LEFT', 'table' => 'properties p', 'condition' => 'b.property_id = p.id'],
                ]
            ]
        );

        if ($utility_class_call->input_is_invalid($bookingRows)) {
            $api_status_code_class_call->respondNotFound("Booking not found");
        }

        $booking = $bookingRows[0];
        $old_status = $booking['status'];

        // 5) Allowed status transitions for cancel
        if (!Utility_Functions::canTransitionBookingStatus($old_status, 'cancelled')) {
            $api_status_code_class_call->respondBadRequest("Booking cannot be cancelled from current status");
        }
        if (strtolower($old_status) === 'cancelled') {
            $api_status_code_class_call->respondOK(
                [
                    "booking_id" => $booking_id,
                    "old_status" => $old_status,
                    "new_status" => "cancelled",
                    "note" => "No change; already cancelled"
                ],
                "Booking already cancelled"
            );
        }

        // 6) Update booking in transaction
        $now = date('Y-m-d H:i:s');
        DB_Calls_Functions::beginTransaction();
        try {
            $update = $db_call_class->updateRows(
                "bookings",
                [
                    "status" => "cancelled",
                    "admin_comment" => $reason,
                    "cancelled_by" => $admin_id,
                    "cancelled_at" => $now
                ],
                [[
                    ['column' => 'id', 'operator' => '=', 'value' => $booking_id]
                ]]
            );

            if (!$update) {
                throw new \Exception("Failed to cancel booking");
            }

            if (DB_Calls_Functions::tableExists("booking_status_history")) {
                $ins = $db_call_class->insertRow("booking_status_history", [
                    "booking_id" => $booking_id,
                    "old_status" => $old_status,
                    "new_status" => "cancelled",
                    "reason" => $reason,
                    "note" => "Cancelled by admin",
                    "changed_by" => $admin_id,
                    "changed_by_role" => "admin",
                    "created_at" => $now
                ]);
                if (!$ins) {
                    throw new \Exception("Failed to write booking history");
                }
            }

            DB_Calls_Functions::commitTransaction();
        } catch (\Exception $e) {
            DB_Calls_Functions::rollbackTransaction();
            $api_status_code_class_call->respondInternalError($e->getMessage());
        }

        $propertyTitle = $booking['property_title'] ?? 'property';
        $visitDate     = $booking['visit_date'] ?? '';

        // 8) Notify user
        if (!empty($booking['user_email'])) {
            $subject = "Your booking was cancelled";
            $plain   = "Your booking for $propertyTitle on $visitDate was cancelled. Reason: $reason";
            $html    = $mail_sms_call->generalMailTemplate(
                "Booking Cancelled",
                "Hello {$booking['user_fname']},",
                "Your booking for <strong>$propertyTitle</strong> on <strong>$visitDate</strong> was cancelled.<br>Reason: $reason",
                ""
            );
            $mail_sms_call->sendUserMail($subject, $booking['user_email'], $plain, $html);
        }
        $notify_call->sendToUser(
            $booking['user_id'],
            "Booking cancelled",
            "Your booking for $propertyTitle on $visitDate was cancelled. Reason: $reason",
            "booking"
        );

        // 9) Notify agent
        if (!empty($booking['agent_email'])) {
            $subject = "Booking was cancelled";
            $plain   = "A booking for $propertyTitle on $visitDate was cancelled. Reason: $reason";
            $html    = $mail_sms_call->generalMailTemplate(
                "Booking Cancelled",
                "Hello {$booking['agency_name']},",
                "A booking for <strong>$propertyTitle</strong> on <strong>$visitDate</strong> was cancelled.<br>Reason: $reason",
                ""
            );
            $mail_sms_call->sendUserMail($subject, $booking['agent_email'], $plain, $html);
        }
        $notify_call->sendToAgent(
            $booking['agent_id'],
            "Booking cancelled",
            "A booking for $propertyTitle on $visitDate was cancelled. Reason: $reason",
            "booking"
        );

        // 10) Respond
        $api_status_code_class_call->respondOK(
            [
                "booking_id" => $booking_id,
                "old_status" => $old_status,
                "new_status" => "cancelled",
                "reason" => $reason,
                "cancelled_by" => $admin_id,
                "cancelled_at" => $now
            ],
            "Booking cancelled successfully"
        );
    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
