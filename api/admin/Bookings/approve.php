<?php

require __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . "/../../../bootstrap.php";

use Config\API_Status_Code;
use Config\API_User_Response;
use Config\DB_Calls_Functions;
use Config\Utility_Functions;
use Config\Mail_SMS_Responses;
use Config\Notification_Function;
use Config\Notification_Queue;


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

        // 3) Input
        $booking_id = $utility_class_call->clean_user_data($_POST['booking_id'] ?? '', 1);
        if (empty($booking_id) || !is_numeric($booking_id)) {
            $api_status_code_class_call->respondBadRequest("booking_id is required and must be numeric");
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
                "b.scheduled_time",
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
                "p.title AS property_title",
                "p.city",
                "p.state"
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

        // 5) Status guard
        if (!Utility_Functions::canTransitionBookingStatus($old_status, 'approved')) {
            $api_status_code_class_call->respondBadRequest("Booking cannot be approved from current status");
        }
        // idempotent guard
        if (strtolower($old_status) === 'approved') {
            $api_status_code_class_call->respondOK(
                [
                    "booking_id" => $booking_id,
                    "old_status" => $old_status,
                    "new_status" => "approved",
                    "note" => "No change; already approved"
                ],
                "Booking already approved"
            );
        }
        // Require successful payment before approval
        if (strtolower($booking['payment_status']) !== 'paid') {
            $api_status_code_class_call->respondBadRequest("Payment must be completed before approval");
        }

        // 6) Update booking within transaction
        $now = date('Y-m-d H:i:s');
        DB_Calls_Functions::beginTransaction();
        try {
            $update = $db_call_class->updateRows(
                "bookings",
                [
                    "status" => "approved",
                    "approved_by" => $admin_id,
                    "approved_at" => $now
                ],
                [[
                    ['column' => 'id', 'operator' => '=', 'value' => $booking_id]
                ]]
            );

            if (!$update) {
                throw new \Exception("Failed to approve booking");
            }

            if (DB_Calls_Functions::tableExists("booking_status_history")) {
                $ins = $db_call_class->insertRow("booking_status_history", [
                    "booking_id" => $booking_id,
                    "old_status" => $old_status,
                    "new_status" => "approved",
                    "note" => "Approved by admin",
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

        // 8) Queue notifications for async processing
        $propertyTitle = $booking['property_title'] ?? 'property';
        $visitDate     = $booking['visit_date'] ?? '';

        $queue = new Notification_Queue();

        // Queue user email notification
        if (!empty($booking['user_email'])) {
            $subject = "Your booking has been approved";
            $html    = $mail_sms_call->generalMailTemplate(
                "Booking Approved",
                "Hello {$booking['user_fname']},",
                "Your booking for <strong>$propertyTitle</strong> on <strong>$visitDate</strong> has been approved.",
                ""
            );
            $queue->queueEmail($booking['user_email'], $subject, $html,
                "Your booking for $propertyTitle on $visitDate has been approved.",
                ['recipient_type' => 'user', 'recipient_id' => $booking['user_id']]
            );
        }

        // Queue user in-app notification
        $queue->queueInAppNotification(
            $booking['user_id'],
            "Booking approved",
            "Your booking for $propertyTitle on $visitDate has been approved.",
            "booking"
        );

        // Queue agent email notification
        if (!empty($booking['agent_email'])) {
            $subject = "New booking approved";
            $html    = $mail_sms_call->generalMailTemplate(
                "Booking Approved",
                "Hello {$booking['agency_name']},",
                "A booking for <strong>$propertyTitle</strong> on <strong>$visitDate</strong> has been approved.",
                ""
            );
            $queue->queueEmail($booking['agent_email'], $subject, $html,
                "A booking for $propertyTitle on $visitDate has been approved.",
                ['recipient_type' => 'agent', 'recipient_id' => $booking['agent_id']]
            );
        }

        // Queue agent in-app notification
        $queue->queueInAppNotification(
            $booking['agent_id'],
            "Booking approved",
            "A booking for $propertyTitle on $visitDate has been approved.",
            "booking",
            ['recipient_type' => 'agent']
        );

        // 9) Respond
        $api_status_code_class_call->respondOK(
            [
                "booking_id" => $booking_id,
                "old_status" => $old_status,
                "new_status" => "approved",
                "approved_by" => $admin_id,
                "approved_at" => $now
            ],
            "Booking approved successfully"
        );
    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
