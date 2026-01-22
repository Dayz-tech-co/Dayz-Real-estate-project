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

header("Content-Type: application/json");

$api_method = "POST";

if (getenv('REQUEST_METHOD') === $api_method) {
    try {
        //Validate agent token
        $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1, 2);
        $agent_pubkey = $decodedToken->usertoken;

        //Fetch agent record
        $getAgent = $db_call_class->selectRows("agents", "id, agency_name, email, kyc_verified", [[
            ['column' => 'agentpubkey', 'operator' => '=', 'value' => $agent_pubkey]
        ]]);

        if ($utility_class_call->input_is_invalid($getAgent)) {
            $api_status_code_class_call->respondUnauthorized();
        }

        $agent_id = $getAgent[0]['id'];

        //Ensure KYC verified
        if (strtolower($getAgent[0]['kyc_verified']) !== 'verified') {
            $api_status_code_class_call->respondBadRequest(API_User_Response::$kycnotverified);
        }

        //Sanitize property_id input
        $property_id = $utility_class_call->clean_user_data($_POST['property_id'] ?? '', 1);

        if (empty($property_id)) {
            $api_status_code_class_call->respondBadRequest(API_User_Response::$propertyidrequired);
        }

        //Fetch the property for this agent
        $property = $db_call_class->selectRows(
            "properties",
            "id, title, description, property_type, property_category, bed, bath, balc, hall, kitc, floor, asize, price, feature, city, state, location, verified, sold_status, status, images, created_at, updated_at",
            [[
                ['column' => 'id', 'operator' => '=', 'value' => $property_id],
                ['column' => 'agent_id', 'operator' => '=', 'value' => $agent_id],
                ['column' => 'is_deleted', 'operator' => '=', 'value' => 0]
            ]],
            ['limit' => 1]
        );

        //Handle missing property
        if ($utility_class_call->input_is_invalid($property)) {
            $api_status_code_class_call->respondNotFound(API_User_Response::$propertynotfound);
        }

        $property = $property[0];

        //Decode images (if JSON stored)
        $images = json_decode($property['images'] ?? '[]', true);
        if (!is_array($images)) $images = [];

        //Prepare structured data response
        $data = [
            "property_id"   => $property['id'],
            "title"         => $property['title'],
            "description"   => $property['description'],
            "property_type" => $property['property_type'],
            "property_category" => $property['property_category'],
            "bed"           => $property['bed'],
            "bath"          => $property['bath'],
            "balc"       => $property['balc'],
            "hall"          => $property['hall'],
            "kitc"       => $property['kitc'],
            "floor"         => $property['floor'],
            "area_size"     => $property['asize'],
            "price"         => $property['price'],
            "feature"      => $property['feature'],
            "city"          => $property['city'],
            "state"         => $property['state'],
            "location"      => $property['location'],
            "verified"      => (int)$property['verified'],
            "sold_status"   => $property['sold_status'],
            "status"        => $property['status'],
            "images"        => $images,
            "created_at"    => $property['created_at'],
            "updated_at"    => $property['updated_at']
        ];

        //Respond OK
        $api_status_code_class_call->respondOK($data, API_User_Response::$propertydetailsretrieved);

    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}

?>
