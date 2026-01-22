<?php

require __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . "/../../../bootstrap.php";

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Config\Mail_SMS_Responses;
use Config\API_User_Response;
use Config\Utility_Functions;

header('Content-Type: application/json');

$apimethod = "POST";
$cacheit = 0;
$expiredata = ["hr" => 0, "min" => 0, "sec" => 20];

$api_status_call = new Config\API_Status_Code;
$db_call = new Config\DB_Calls_Functions;
$utility_class_call = new Utility_Functions();

if ($_SERVER["REQUEST_METHOD"] == $apimethod) {
    try {

        $email = isset($_POST["email"]) ? $utility_class_call->clean_user_data($_POST["email"], 1) : '';
        $password = isset($_POST["password"]) ? $utility_class_call->clean_user_data($_POST["password"], 1) : "";

        // Validate input
        if ($utility_class_call->input_is_invalid($email) || $utility_class_call->input_is_invalid($password)) {
            $api_status_call->respondBadRequest(API_User_Response::$request_body_invalid);
        }

        // Fetch user data
        $userData = $db_call->selectRows(
            "users",
            "id, fname, lname, email, email_verified, password, userpubkey, phoneno, phone_verified, status",
            [
                [
                    ["column" => "email", "operator" => "=", "value" => $email],
                    "operator" => "OR"
                ]
            ]
        );

        if ($utility_class_call->input_is_invalid($userData)) {
            $api_status_call->respondBadRequest(API_User_Response::$invalidUserDetail);
        }

        if ($_ENV["ALLOW_USER_TO_LOGIN_REGISTER"] == 0) {
            $api_status_call->respondBadRequest(API_User_Response::$serverUnderMaintainance);
        }

        $found = $userData[0];
        $user_id = $found["id"];
        $fname = $found["fname"];
        $lname = $found["lname"];
        $user_email = $found["email"];
        $email_verified = $found["email_verified"];
        $phone_verified = $found["phone_verified"];
        $hash_pass = $found["password"];
        $user_phone = $found["phoneno"];
        $userpubkey = $found["userpubkey"];
        $statusis = $found["status"];

        $verifypass = $utility_class_call->is_password_hash_valid($password, $hash_pass);

        if (!$verifypass) {
            $api_status_call->respondBadRequest(API_User_Response::$invalidUserDetail);
        }

        // Proceed if password is valid
        $systemname = $_ENV["APP_NAME"];

        // Regenerate userpubkey on each login
        $new_userpubkey = $db_call->createUniqueRandomStringForATableCol(29, "users", "userpubkey", "$systemname", true, true, true);
        $db_call->updateRows(
            "users",
            ["userpubkey" => $new_userpubkey],
            [
                [
                    ["column" => 'id', "operator" => "=", "value" => $user_id]
                ]
            ]
        );

        // Only approved or pending users can log in
        if (in_array($statusis, ['approved', 'pending'])) {

            $maindata = [];
            $maindata["first_name"] = $fname;
            $maindata["last_name"] = $lname;
            $maindata["email"] = $user_email;
            $maindata["email_verified"] = $email_verified;
            $maindata["phone_verified"] = $phone_verified;
            $maindata["phoneno"] = $user_phone;

            $ipaddress = $utility_class_call->getIpAddress();
            $getcount = 0;

            // Track session count by IP
            $sessionData = $db_call->selectRows(
                "user_sessions",
                "email",
                [
                    [
                        ["column" => "ipaddress", "operator" => "=", "value" => $ipaddress]
                    ]
                ]
            );

            if (!$utility_class_call->input_is_invalid($sessionData)) {
                $getcount = count($sessionData);
            }

            // Create session record
            $sessioncode = $db_call->createUniqueRandomStringForATableCol(20, "user_sessions", "sessioncode", time(), true, true, true);
            $browser = $utility_class_call->getBrowserInfo()['name'] . ' on ' . ucfirst($utility_class_call->getBrowserInfo()['platform']);
            $location = ''; // optional if not using geo lookup

            $db_call->insertRow("user_sessions", [
                "email" => $email,
                "sessioncode" => $sessioncode,
                "ipaddress" => $ipaddress,
                "browser" => $browser,
                "forwho" => 2, // user type
                "location" => $location,
            ]);

            // Create access token
            $accesstoken = $api_status_call->getTokenToSendAPI($new_userpubkey);
            $maindata['access_token'] = $accesstoken;

            $api_status_call->respondOK([$maindata], API_User_Response::$loginSuccessful);

        } elseif ($statusis == 'suspended') {
            $api_status_call->respondBadRequest(API_User_Response::$acct_suspended);
        } elseif ($statusis == 'rejected') {
            $api_status_call->respondBadRequest(API_User_Response::$acct_rejected);
        } else {
            $api_status_call->respondBadRequest(API_User_Response::$user_permanetly_banned);
        }

    } catch (\Exception $e) {
        $api_status_call->respondInternalError($utility_class_call->get_details_from_exception($e));
    }

} else {
    $api_status_call->respondMethodNotAlowed();
}
