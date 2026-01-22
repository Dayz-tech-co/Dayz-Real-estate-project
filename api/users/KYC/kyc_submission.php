<?php

require __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . "/../../../bootstrap.php";

use Config\API_Status_Code;
use Config\API_User_Response;
use Config\DB_Calls_Functions;
use Config\Utility_Functions;
use Config\Mail_SMS_Responses;

$api_status = new API_Status_Code;
$db_call = new DB_Calls_Functions;
$utility = new Utility_Functions;
$mail_sms = new Mail_SMS_Responses;

header("Content-Type: application/json");

$method = "POST";

$UPLOAD_PATH = __DIR__ . "/../../../public/assets/uploads/kyc_documents/";
$UPLOAD_URL  = $_ENV['APP_URL'] . "/assets/uploads/kyc_documents/";

if ($_SERVER['REQUEST_METHOD'] !== $method) {
    $api_status->respondMethodNotAlowed();
    exit;
}

try {
    // Validate user token (forwho = 3)
    $decoded = $api_status->ValidateAPITokenSentIN(1, 3);
    $user_pubkey = $decoded->usertoken;

    // Fetch user data
    $user = $db_call->selectRows("users", "id, fname, lname, email, kyc_verified", [[
        ['column' => 'userpubkey', 'operator' => '=', 'value' => $user_pubkey]
    ]]);

    if (empty($user)) {
        $api_status->respondUnauthorized();
    }

    $user_id = $user[0]['id'];
    $fname = $user[0]['fname'];
    $lname = $user[0]['lname'];
    $fullname = $fname . ' ' . $lname;
    $email = $user[0]['email'];
    $kyc_verified = (int)$user[0]['kyc_verified'];

    if ($kyc_verified === 1) {
        $api_status->respondBadRequest(API_User_Response::$kycAlreadyVerified);
    }

    // Clean user inputs
    $id_type    = $utility->clean_user_data($_POST['id_type'] ?? '', 1);  // NIN, Driver’s License, Passport, etc.
    $id_number  = $utility->clean_user_data($_POST['id_number'] ?? '', 1);
    $address    = $utility->clean_user_data($_POST['address'] ?? '', 1);
    $city       = $utility->clean_user_data($_POST['city'] ?? '', 1);
    $state      = $utility->clean_user_data($_POST['state'] ?? '', 1);
    $country    = $utility->clean_user_data($_POST['country'] ?? '', 1);

    if (empty($id_type) || empty($id_number) || empty($address) || empty($city) || empty($state) || empty($country)) {
        $api_status->respondBadRequest(API_User_Response::$missingrequiredfields);
    }

    // Check if user has already submitted KYC
    $existingKYC = $db_call->selectRows("users_kyc_verifications", "id, status, verified", [[
        ['column' => 'user_id', 'operator' => '=', 'value' => $user_id]
    ]]);

    if (!empty($existingKYC)) {
        $kycStatus = strtolower($existingKYC[0]['status'] ?? '');
        $kycVerified = (int)($existingKYC[0]['verified'] ?? 0);

        if ($kycStatus === 'pending') {
            $api_status->respondBadRequest(API_User_Response::$kycpendingreview);
        }

        if ($kycVerified === 1 || $kycStatus === 'approved') {
            $api_status->respondBadRequest(API_User_Response::$kycAlreadyVerified);
        }
    }

    // Validate uploads
    $requiredFiles = ['id_front', 'id_back'];
    foreach ($requiredFiles as $fileKey) {
        if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
            $api_status->respondBadRequest("Missing or invalid file: $fileKey");
        }
    }

    // Optional proof of address
    $proof_of_address_path = null;
    if (isset($_FILES['proof_of_address']) && $_FILES['proof_of_address']['error'] === UPLOAD_ERR_OK) {
        $proof_of_address_path = $utility->uploadImage($_FILES['proof_of_address'], $UPLOAD_PATH, $UPLOAD_PATH, $UPLOAD_URL, $fullname)['imagepath'];
    }

    // Upload identity docs
    $uploadFront = $utility->uploadImage($_FILES['id_front'], $UPLOAD_PATH, $UPLOAD_PATH, $UPLOAD_URL, $fullname);
    $uploadBack  = $utility->uploadImage($_FILES['id_back'], $UPLOAD_PATH, $UPLOAD_PATH, $UPLOAD_URL, $fullname);

    $document_front = $uploadFront['imagepath'];
    $document_back  = $uploadBack['imagepath'];

    // Prepare data
    $kycData = [
        "user_id"             => $user_id,
        "government_id_type"  => $id_type,
        "government_id_number"=> $id_number,
        "document_front"      => $document_front,
        "document_back"       => $document_back,
        "address"             => $address,
        "city"                => $city,
        "state"               => $state,
        "country"             => $country,
        "status"              => "pending",
        "verified"            => 0,
        "admin_comment"       => null,
        "created_at"          => date("Y-m-d H:i:s"),
        "updated_at"          => date("Y-m-d H:i:s")
    ];

    if ($proof_of_address_path) {
        $kycData['proof_of_address'] = $proof_of_address_path;
    }

    // Insert or Update KYC
    if (empty($existingKYC)) {
        $insert = $db_call->insertRow("users_kyc_verifications", $kycData);
    } else {
        $insert = $db_call->updateRows("users_kyc_verifications", $kycData, [
            ['column' => 'user_id', 'operator' => '=', 'value' => $user_id]
        ]);
    }

    if ($insert > 0) {
        // Reflect in users table
        $db_call->updateRows("users", ["kyc_verified" => 0], [
            ['column' => 'id', 'operator' => '=', 'value' => $user_id]
        ]);

        // Send mail
        $systemname = $_ENV['APP_NAME'];
        $subject = "KYC Submission Received – Pending Review";
        $messagetitle = "KYC Under Review";
        $greetingText = "Dear $fullname,<br><br>";
        $mailText = "
            Your KYC submission with ID Number <strong>$id_number</strong> has been successfully received.<br><br>
            Our team will review your documents and verify your identity within <strong>2–3 working days</strong>.<br><br>
            You’ll receive an update once your verification is approved or requires further attention.<br><br>
            Regards,<br>
            <strong>$systemname Verification Team</strong>
        ";

        $messageText = "Your KYC submission for $systemname has been received and is currently under review.";
        $messageHTML = $mail_sms->generalMailTemplate($messagetitle, $greetingText, $mailText, "");

        $mail_sms->sendUserMail($subject, $email, $messageText, $messageHTML);

        // Final success response
        $api_status->respondOK([
            "kyc_status" => "pending",
            "document_front" => $document_front,
            "document_back" => $document_back,
            "proof_of_address" => $proof_of_address_path,
            "message" => "KYC successfully submitted and is under review"
        ], API_User_Response::$kycSubmittedSuccessfully);

    } else {
        $api_status->respondInternalError(API_User_Response::$kycsubmittedfailed);
    }

} catch (Exception $e) {
    $api_status->respondInternalError($e->getMessage());
}
