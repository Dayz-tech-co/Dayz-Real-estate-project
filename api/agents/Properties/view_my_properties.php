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

$api_method = "POST";

header("content-type: application/json");
if (getenv('REQUEST_METHOD') === $api_method) {
    try {
        //Validate Agent Token
        $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1, 2);
        $agent_pubkey = $decodedToken->usertoken;

        //Fetch Agent
        $getAgent = $db_call_class->selectRows(
            "agents",
            "id, agency_name, email, status, kyc_verified",
            [[
                ['column' => 'agentpubkey', 'operator' => '=', 'value' => $agent_pubkey]
            ]]
        );

        if ($utility_class_call->input_is_invalid($getAgent)) {
            $api_status_code_class_call->respondUnauthorized();
        }

        $agent_id = $getAgent[0]['id'];
        $agency_name = $getAgent[0]['agency_name'];

        //Ensure KYC verified

        if (strtolower($getAgent[0]['kyc_verified']) !== 'verified') {
            $api_status_code_class_call->respondBadRequest(API_User_Response::$kycnotverified);
        }

        //Pagination inputs
        $page  = isset($_POST['page']) ? (int)$_POST['page'] : 1;
        $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 10;
        $offset = ($page - 1) * $limit;
        if ($page < 1) $page = 1;
        if ($limit < 1) $limit = 10;

        // Build conditions
        $conditions = [[
            ['column' => 'agent_id', 'operator' => '=', 'value' => $agent_id]
        ]];

        //Count total records safely
        $totalRow = $db_call_class->selectRows(
            "properties",
            "COUNT(*) AS total",
            $conditions
        );

        $total = isset($totalRow[0]['total']) ? (int)$totalRow[0]['total'] : 0;

        // Fetch paginated properties
        $properties = $db_call_class->selectRows(
            "properties",
            "id, title, description, property_type, property_category, bed, bath, balc AS balcony, hall, kitc, floor, asize AS area_size, price, feature, city, state, location, agent_id, status, created_at, thumbnail, images, verified, sold_status, updated_at",
            $conditions,
            [
                'limit' => $limit,
                'pageno' => $page,
                'orderBy' => 'id',
                'orderDirection' => 'DESC'
            ]
        );

        if ($utility_class_call->input_is_invalid($properties)) {
            $api_status_code_class_call->respondNotFound(API_User_Response::$propertynotfound);
        }

        //Format response data
        $formatted = [];
        foreach ($properties as $property) {
            $formatted[] = [
                "property_id"   => $property['id'],
                "title"         => $property['title'],
                "description"   => $property['description'],
                "property_type" => $property['property_type'],
                "property_category"     => $property['property_category'],
                "bed"           => $property['bed'],
                "bath"          => $property['bath'],
                "balcony"       => $property['balcony'],
                "hall"          => $property['hall'],
                "kitc"       => $property['kitc'],
                "floor"         => $property['floor'],
                "area_size"     => $property['area_size'],
                "price"         => $property['price'],
                "feature"      => $property['feature'],
                "city"          => $property['city'],
                "state"         => $property['state'],
                "location"      => $property['location'],
                "thumbnail"     => $property['thumbnail'],
                "images"        => json_decode($property['images'], true) ?? [],
                "verified"      => (int)$property['verified'],
                "sold_status"   => $property['sold_status'],
                "status"        => $property['status'],
                "created_at"    => $property['created_at'],
                "updated_at"    => $property['updated_at']
            ];
        }

        //Pagination meta
        $pagination = [
            "current_page" => $page,
            "per_page"     => $limit,
            "total"        => $total,
            "total_pages"  => ceil($total / $limit)
        ];

        //Final success response
        $api_status_code_class_call->respondOK([
            "pagination" => $pagination,
            "properties" => $formatted
        ], API_User_Response::$propertydetailsretrieved);
    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
