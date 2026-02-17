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

    $submission_type = strtolower($utility->clean_user_data($_POST['submission_type'] ?? 'documents', 1));
    if (!in_array($submission_type, ['documents', 'proof'], true)) {
        $submission_type = 'documents';
    }

    if ($submission_type === 'documents' && $kyc_verified === 1) {
        $api_status->respondBadRequest(API_User_Response::$kycAlreadyVerified);
    }

    // Clean user inputs
    $id_type    = $utility->clean_user_data($_POST['id_type'] ?? '', 1);
    $id_number  = $utility->clean_user_data($_POST['id_number'] ?? '', 1);
    $address    = $utility->clean_user_data($_POST['address'] ?? '', 1);
    $city       = $utility->clean_user_data($_POST['city'] ?? '', 1);
    $state      = $utility->clean_user_data($_POST['state'] ?? '', 1);
    $country    = $utility->clean_user_data($_POST['country'] ?? '', 1);

    if (
        $submission_type === 'documents' &&
        (empty($id_type) || empty($id_number) || empty($address) || empty($city) || empty($state) || empty($country))
    ) {
        $api_status->respondBadRequest(API_User_Response::$missingrequiredfields);
    }

    // Check if user has already submitted KYC
    $existingKYC = $db_call->selectRows("users_kyc_verifications", "id, status, verified, proof_of_address_document", [[
        ['column' => 'user_id', 'operator' => '=', 'value' => $user_id]
    ]]);

    if (!empty($existingKYC)) {
        $kycStatus = strtolower($existingKYC[0]['status'] ?? '');
        $kycVerified = (int)($existingKYC[0]['verified'] ?? 0);
        $proofAlreadySubmitted = !empty($existingKYC[0]['proof_of_address_document']);

        if ($submission_type === 'documents') {
            if ($kycStatus === 'pending') {
                $api_status->respondBadRequest(API_User_Response::$kycpendingreview);
            }
            if ($kycVerified === 1 || $kycStatus === 'approved') {
                $api_status->respondBadRequest(API_User_Response::$kycAlreadyVerified);
            }
        }

        if ($submission_type === 'proof' && $proofAlreadySubmitted) {
            $api_status->respondBadRequest("Proof of residence already submitted.");
        }
    }

    $maxBytes = 25 * 1024 * 1024;
    $proof_of_address_path = null;
    $proof_of_address_type = null;
    $document_front = null;
    $document_back = null;

    if ($submission_type === 'documents') {
        // Validate uploads
        $requiredFiles = ['id_front', 'id_back'];
        foreach ($requiredFiles as $fileKey) {
            if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
                $api_status->respondBadRequest("Missing or invalid file: $fileKey");
            }
            if ($_FILES[$fileKey]['size'] > $maxBytes) {
                $api_status->respondBadRequest("File too large: $fileKey (max 25MB).");
            }
        }

        // Optional proof of address
        if (isset($_FILES['proof_of_address']) && $_FILES['proof_of_address']['error'] === UPLOAD_ERR_OK) {
            if ($_FILES['proof_of_address']['size'] > $maxBytes) {
                $api_status->respondBadRequest("File too large: proof_of_address (max 25MB).");
            }
            $proof_of_address_path = $utility->uploadImage($_FILES['proof_of_address'], $UPLOAD_PATH, $UPLOAD_PATH, $UPLOAD_URL, $fullname)['imagepath'];
            $proof_of_address_type = strtolower(pathinfo($_FILES['proof_of_address']['name'], PATHINFO_EXTENSION));
        }

        // Upload identity docs
        $uploadFront = $utility->uploadImage($_FILES['id_front'], $UPLOAD_PATH, $UPLOAD_PATH, $UPLOAD_URL, $fullname);
        $uploadBack  = $utility->uploadImage($_FILES['id_back'], $UPLOAD_PATH, $UPLOAD_PATH, $UPLOAD_URL, $fullname);

        $document_front = $uploadFront['imagepath'];
        $document_back  = $uploadBack['imagepath'];
    } else {
        if (empty($existingKYC)) {
            $api_status->respondBadRequest("Submit identity documents before uploading proof of residence.");
        }
        if (!isset($_FILES['proof_of_address']) || $_FILES['proof_of_address']['error'] !== UPLOAD_ERR_OK) {
            $api_status->respondBadRequest("Missing or invalid file: proof_of_address");
        }
        if ($_FILES['proof_of_address']['size'] > $maxBytes) {
            $api_status->respondBadRequest("File too large: proof_of_address (max 25MB).");
        }
        $proof_of_address_path = $utility->uploadImage($_FILES['proof_of_address'], $UPLOAD_PATH, $UPLOAD_PATH, $UPLOAD_URL, $fullname)['imagepath'];
        $proof_of_address_type = strtolower(pathinfo($_FILES['proof_of_address']['name'], PATHINFO_EXTENSION));
    }

    if ($submission_type === 'documents') {
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
            $kycData['proof_of_address_document'] = $proof_of_address_path;
            $kycData['proof_of_address_type'] = $proof_of_address_type;
        }

        // Insert or Update KYC
        if (empty($existingKYC)) {
            $insert = $db_call->insertRow("users_kyc_verifications", $kycData);
        } else {
            $insert = $db_call->updateRows("users_kyc_verifications", $kycData, [
                ['column' => 'user_id', 'operator' => '=', 'value' => $user_id]
            ]);
        }
    } else {
        $kycData = [
            "proof_of_address_document" => $proof_of_address_path,
            "proof_of_address_type"     => $proof_of_address_type,
            "updated_at"                => date("Y-m-d H:i:s")
        ];
        $insert = $db_call->updateRows("users_kyc_verifications", $kycData, [
            ['column' => 'user_id', 'operator' => '=', 'value' => $user_id]
        ]);
    }

    if ($insert > 0) {
        // Reflect in users table
        if ($submission_type === 'documents') {
            $db_call->updateRows("users", ["kyc_verified" => 0], [
                ['column' => 'id', 'operator' => '=', 'value' => $user_id]
            ]);
        }

        if (!empty($proof_of_address_path)) {
            $db_call->updateRows("users", ["proof_of_residence" => $proof_of_address_path], [
                ['column' => 'id', 'operator' => '=', 'value' => $user_id]
            ]);
        }

        // Send mail
        $systemname = $_ENV['APP_NAME'];
        $subject = $submission_type === 'documents'
            ? "KYC Submission Received - Pending Review"
            : "Proof of Residence Received";
        $messagetitle = $submission_type === 'documents'
            ? "KYC Under Review"
            : "Proof of Residence Under Review";
        $greetingText = "Dear $fullname,<br><br>";

        $mailText = $submission_type === 'documents'
            ? "Your KYC submission with ID Number <strong>$id_number</strong> has been successfully received.<br><br>
            Our team will review your documents and verify your identity within <strong>2-3 working days</strong>.<br><br>
            You'll receive an update once your verification is approved or requires further attention.<br><br>
            Regards,<br>
            <strong>$systemname Verification Team</strong>"
            : "Your proof of residence has been successfully received.<br><br>
            Our team will review your documents within <strong>2-3 working days</strong>.<br><br>
            You'll receive an update once your verification is approved or requires further attention.<br><br>
            Regards,<br>
            <strong>$systemname Verification Team</strong>";

        $messageText = $submission_type === 'documents'
            ? "Your KYC submission for $systemname has been received and is currently under review."
            : "Your proof of residence for $systemname has been received and is currently under review.";
        $messageHTML = $mail_sms->generalMailTemplate($messagetitle, $greetingText, $mailText, "");

        $mail_sms->sendUserMail($subject, $email, $messageText, $messageHTML);

        // Final success response
        $api_status->respondOK([
            "kyc_status" => $submission_type === 'documents' ? "pending" : ($existingKYC[0]['status'] ?? "pending"),
            "document_front" => $document_front,
            "document_back" => $document_back,
            "proof_of_address" => $proof_of_address_path,
            "message" => $submission_type === 'documents'
                ? "KYC successfully submitted and is under review"
                : "Proof of residence submitted and is under review"
        ], API_User_Response::$kycSubmittedSuccessfully);

    } else {
        $api_status->respondInternalError(API_User_Response::$kycsubmittedfailed);
    }

} catch (Exception $e) {
    $api_status->respondInternalError($e->getMessage());
}
