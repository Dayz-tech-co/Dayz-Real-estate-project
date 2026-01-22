<?php
require __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . "/../../../bootstrap.php";

use Config\API_Status_Code;
use Config\API_User_Response;
use Config\DB_Calls_Functions;
use Config\Utility_Functions;
use Config\Mail_SMS_Responses;

$api_status_call = new API_Status_Code;
$db_call = new DB_Calls_Functions;
$utility_class_call = new Utility_Functions;
$mail_sms_call = new Mail_SMS_Responses;

header("Content-Type: application/json");

$apimethod = "POST";

if ($_SERVER['REQUEST_METHOD'] !== $apimethod) {
    $api_status_call->respondMethodNotAlowed();
    exit;
}

try {
    // Validate user token
    $decodedToken = $api_status_call->ValidateAPITokenSentIN(1, 3);
    $user_pubkey = $decodedToken->usertoken;

    // Fetch user info
    $getUser = $db_call->selectRows("users", "id, fname, lname, email, kyc_verified", [[
        ['column' => 'userpubkey', 'operator' => '=', 'value' => $user_pubkey]
    ]]);

    if ($utility_class_call->input_is_invalid($getUser)) {
        $api_status_call->respondUnauthorized();
    }

    // Check KYC verification
    if ($getUser[0]['kyc_verified'] !== 'verified') {
        $api_status_call->respondBadRequest([], API_User_Response::$kyc_required);
    }

    $user_id = $getUser[0]['id'];
    $fullname = $getUser[0]['fname'] . " " . $getUser[0]['lname'];
    $user_email = $getUser[0]['email'];

    // Get booking_id from POST
    $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
    if ($booking_id <= 0) {
        $api_status_call->respondBadRequest("Invalid booking ID");
    }

    // Fetch booking
    $booking = $db_call->selectRows("bookings", "*", [[
        ['column' => 'id', 'operator' => '=', 'value' => $booking_id],
        ['column' => 'user_id', 'operator' => '=', 'value' => $user_id]
    ]]);

    if ($utility_class_call->input_is_invalid($booking)) {
        $api_status_call->respondNotFound([], "Booking not found");
    }

    $booking = $booking[0];

    // Check if booking is cancellable
    if ($booking['status'] !== 'pending') {
        $api_status_call->respondBadRequest("Only pending bookings can be cancelled");
    }

    // Update booking status
    $now = date("Y-m-d H:i:s");
    DB_Calls_Functions::beginTransaction();
    try {
        $update = $db_call->updateRows("bookings", [
            "status" => "cancelled",
            "cancelled_by" => $user_id,
            "cancelled_at" => $now
        ], [[
            ['column' => 'id', 'operator' => '=', 'value' => $booking_id]
        ]]);

        if (!$update) {
            throw new \Exception("Failed to cancel booking");
        }

        // Write history
        if (DB_Calls_Functions::tableExists("booking_status_history")) {
            $historyIns = $db_call->insertRow("booking_status_history", [
                "booking_id" => $booking_id,
                "old_status" => $booking['status'],
                "new_status" => "cancelled",
                "note" => "Cancelled by user",
                "changed_by" => $user_id,
                "changed_by_role" => "user",
                "created_at" => $now
            ]);
            if (!$historyIns) {
                throw new \Exception("Failed to write booking history");
            }
        }

        DB_Calls_Functions::commitTransaction();
    } catch (\Exception $e) {
        DB_Calls_Functions::rollbackTransaction();
        $api_status_call->respondInternalError("Failed to cancel booking: " . $e->getMessage());
    }

    // Send cancellation email
    $subject = "Booking Cancelled Successfully";
    $messageText = "Your booking has been cancelled successfully.";
    $messagetitle = $subject;
    $greetingText = "Hello $fullname,";
    $mailText = "Your booking for the property has been cancelled successfully. If this was a mistake, please make a new booking.";
    $messageHTML = $mail_sms_call->generalMailTemplate($messagetitle, $greetingText, $mailText, "");
    $mail_sms_call->sendUserMail($subject, $user_email, $messageText, $messageHTML);

    $api_status_call->respondOK([], "Booking cancelled successfully");

} catch (Exception $e) {
    $api_status_call->respondInternalError($utility_class_call->get_details_from_exception($e));
}
