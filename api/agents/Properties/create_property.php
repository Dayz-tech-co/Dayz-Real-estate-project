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
        // Validate Agent Token
        $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1, 2);
        $agent_pubkey = $decodedToken->usertoken;

        // Fetch agent details
        $getAgent = $db_call_class->selectRows("agents", "id, agency_name, email, status, kyc_verified", [[
            ['column' => 'agentpubkey', 'operator' => '=', 'value' => $agent_pubkey]
        ]]);

        if ($utility_class_call->input_is_invalid($getAgent)) {
            $api_status_code_class_call->respondUnauthorized();
        }

        $agent_id     = $getAgent[0]['id'];
        $agency_name  = $getAgent[0]['agency_name'];
        $agent_email  = $getAgent[0]['email'];

        // Ensure KYC Verified
        if (strtolower($getAgent[0]['kyc_verified']) !== 'verified') {
            $api_status_code_class_call->respondBadRequest(API_User_Response::$kycnotverified);
        }

        // Clean Inputs
        $title              = isset($_POST["title"]) ? $utility_class_call->clean_user_data($_POST['title'], 1) : "";
        $description        = isset($_POST["description"]) ? $utility_class_call->clean_user_data($_POST['description'], 1) : "";
        $property_type      = isset($_POST["property_type"]) ? $utility_class_call->clean_user_data($_POST['property_type'], 1) : "";
        $property_category  = isset($_POST["property_category"]) ? $utility_class_call->clean_user_data($_POST['property_category'], 1) : "";
        $bed                = isset($_POST["bed"]) ? $utility_class_call->clean_user_data($_POST['bed'], 1) : "";
        $bath               = isset($_POST["bath"]) ? $utility_class_call->clean_user_data($_POST['bath'], 1) : "";
        $balc               = isset($_POST["balcony"]) ? $utility_class_call->clean_user_data($_POST['balcony'], 1) : "";
        $hall               = isset($_POST["hall"]) ? $utility_class_call->clean_user_data($_POST['hall'], 1) : "";
        $kitc               = isset($_POST["kitchen"]) ? $utility_class_call->clean_user_data($_POST['kitchen'], 1) : "";
        $floor              = isset($_POST["floor"]) ? $utility_class_call->clean_user_data($_POST['floor'], 1) : "";
        $asize              = isset($_POST["asize"]) ? $utility_class_call->clean_user_data($_POST['asize'], 1) : "";
        $price              = isset($_POST["price"]) ? $utility_class_call->clean_user_data($_POST['price'], 1) : "";
        $feature            = isset($_POST["features"]) ? $utility_class_call->clean_user_data($_POST['features'], 1) : "";
        $city               = isset($_POST["city"]) ? $utility_class_call->clean_user_data($_POST['city'], 1) : "";
        $state              = isset($_POST["state"]) ? $utility_class_call->clean_user_data($_POST['state'], 1) : "";
        $location           = isset($_POST["location"]) ? $utility_class_call->clean_user_data($_POST['location'], 1) : "";

        // Validate Required Fields
        if (empty($title) || empty($price) || empty($city) || empty($state) || empty($property_type)) {
            $api_status_code_class_call->respondBadRequest(API_User_Response::$missingrequiredfields);
        }

        // Duplicate Property Check
        $titleClean = strtolower(trim($title));
        $locationClean = strtolower(trim($location));

        $existingProperty = $db_call_class->selectRows(
            "properties",
            "id, status, title",
            [[
                ['column' => 'agent_id', 'operator' => '=', 'value' => $agent_id],
                ['column' => 'LOWER(title)', 'operator' => '=', 'value' => $titleClean],
                ['column' => 'LOWER(location)', 'operator' => '=', 'value' => $locationClean]
            ]],
            ['limit' => 1]
        );

        if (!empty($existingProperty)) {
            $existingStatus = strtolower($existingProperty[0]['status'] ?? '');
            if (in_array($existingStatus, ['pending', 'approved'])) {
                $api_status_code_class_call->respondBadRequest([
                    "status" => false,
                    "message" => "You have already submitted a property with the same title and location. Duplicate listing is not allowed.",
                    "existing_property_id" => $existingProperty[0]['id'],
                    "existing_property_status" => $existingStatus
                ]);
            }
        }

        // Generate Unique Slug
        $slug = $utility_class_call->generateSlug($title);
        $existingSlug = $db_call_class->selectRows("properties", "id", [[
            ['column' => 'slug', 'operator' => '=', 'value' => $slug]
        ]]);
        if (!empty($existingSlug)) {
            $slug .= '-' . time();
        }

        // Handle Image Uploads
        $uploadDir = __DIR__ . "/../../../public/uploads/properties/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $imagePaths = [];
        if (!empty($_FILES['images']['name'][0])) {
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

            foreach ($_FILES['images']['tmp_name'] as $key => $tmpName) {
                if (!is_uploaded_file($tmpName)) continue;

                $fileType = $_FILES['images']['type'][$key];
                $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['images']['name'][$key]));
                $targetFile = $uploadDir . $fileName;

                if (!in_array($fileType, $allowedTypes)) continue;

                if (move_uploaded_file($tmpName, $targetFile)) {
                    $imagePaths[] = "uploads/properties/" . $fileName;
                }
            }
        }

        $imagesJSON = json_encode($imagePaths);

        // Insert Property Data
        $propertyData = [
            "agency_name"       => $agency_name,
            "title"             => $title,
            "slug"              => $slug,
            "description"       => $description,
            "property_type"     => $property_type,
            "property_category" => $property_category,
            "bed"               => $bed,
            "bath"              => $bath,
            "balc"              => $balc,
            "hall"              => $hall,
            "kitc"              => $kitc,
            "floor"              => $floor,
            "asize"             => $asize,
            "price"             => $price,
            "feature"           => $feature,
            "city"              => $city,
            "state"             => $state,
            "location"          => $location,
            "agent_id"          => $agent_id,
            "images"            => $imagesJSON,
            "status"            => "pending"
        ];

        $insert = $db_call_class->insertRow("properties", $propertyData);

        if ($insert > 0) {
            $systemname = $_ENV['APP_NAME'];
            $subject = "Your Property Has Been Submitted - $systemname";
            $messageText = "Your property has been successfully submitted and is pending admin review.";
            $messagetitle = "Property Submitted Successfully";
            $greetingText = "Hello $agency_name,";

            $mailText = "
                Your property listing titled <strong>\"$title\"</strong> has been successfully submitted and is currently under review by our administrators.<br><br>
                Once approved, it will go live and become available for users to view and book.<br><br>
                Thank you for trusting <strong>$systemname</strong>.
            ";

            $messageHTML = $mail_sms_call->generalMailTemplate($messagetitle, $greetingText, $mailText, "");
            $mail_sms_call->sendUserMail($subject, $agent_email, $messageText, $messageHTML);

            $api_status_code_class_call->respondOK([
                "property_id" => $insert,
                "title"       => $title,
                "status"      => "pending",
                "images"      => $imagePaths,
                "message"     => "Property successfully submitted and is pending admin review"
            ], API_User_Response::$propertycreatedsuccessfully);
        } else {
            $api_status_code_class_call->respondInternalError(API_User_Response::$propertycreationfailed);
        }
    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
