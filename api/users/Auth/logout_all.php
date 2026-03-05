<?php

require __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . "/../../../bootstrap.php";

use Config\API_Status_Code;
use Config\Utility_Functions;

header("Content-Type: application/json");

$api_status_call = new API_Status_Code();
$utility_class_call = new Utility_Functions();
$api_method = "POST";

if ($_SERVER['REQUEST_METHOD'] !== $api_method) {
    $api_status_call->respondMethodNotAlowed();
}

try {
    $decodedToken = $api_status_call->ValidateAPITokenSentIN(1, 3);
    $userPubkey = isset($decodedToken->usertoken) ? $utility_class_call->clean_user_data($decodedToken->usertoken, 1) : '';

    if ($utility_class_call->input_is_invalid($userPubkey)) {
        $api_status_call->respondUnauthorized();
    }

    $revoked = $api_status_call->revokeAllAuthSessionsForUser($userPubkey, 3);
    if (!$revoked) {
        $api_status_call->respondBadRequest("No active sessions found to revoke.");
    }

    $api_status_call->respondOK([], "All sessions revoked successfully.");
} catch (\Exception $e) {
    $api_status_call->respondInternalError($utility_class_call->get_details_from_exception($e));
}

