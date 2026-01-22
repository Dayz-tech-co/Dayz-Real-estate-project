<?php
require __DIR__ . '/../../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

require_once __DIR__ . "/../../../bootstrap.php";

use Config\API_Status_Code;
use Config\Mail_SMS_Responses;
use Config\API_User_Response;

header("Content-Type: application/json");
$api_method = 'POST';

$api_status_call = new Config\API_Status_Code;
$db_call = new Config\DB_Calls_Functions;
$mail_sms_call = new Mail_SMS_Responses;

if (getenv('REQUEST_METHOD') == $api_method) {
    try {
        // Get POST data
        $fname = isset($_POST["fname"]) ? $utility_class_call->clean_user_data($_POST["fname"], 1) : '';
        $lname = isset($_POST["lname"]) ? $utility_class_call->clean_user_data($_POST["lname"], 1) : '';
        $email = isset($_POST["email"]) ? $utility_class_call->clean_user_data($_POST["email"], 1) : '';
        $password = isset($_POST["password"]) ? $utility_class_call->clean_user_data($_POST["password"], 1) : '';  
        $phoneno = isset($_POST["phoneno"]) ? $utility_class_call->clean_user_data($_POST["phoneno"], 1) : '';
        $country = $utility_class_call->clean_user_data($_POST['country'] ?? '');
        $city = isset($_POST["city"]) ? $utility_class_call->clean_user_data($_POST["city"], 1) : '';
        $state = isset($_POST["state"]) ? $utility_class_call->clean_user_data($_POST["state"], 1) : '';
        $postal_code = isset($_POST["postal_code"]) ? $utility_class_call->clean_user_data($_POST["postal_code"], 1) : '';
        $streetname = isset($_POST["streetname"]) ? $utility_class_call->clean_user_data($_POST["streetname"], 1) : '';

        // Validate required inputs
        if (
            $utility_class_call->input_is_invalid($fname) ||
            $utility_class_call->input_is_invalid($lname) ||
            $utility_class_call->input_is_invalid($email) ||
            $utility_class_call->input_is_invalid($phoneno) ||
            $utility_class_call->input_is_invalid($password)
        ) {
            $api_status_call->respondBadRequest(API_User_Response::$request_body_invalid);
        } elseif (!$utility_class_call->isPasswordStrong($password)) {
            $api_status_call->respondBadRequest(API_User_Response::$invalidPassword);
        } elseif (!$utility_class_call->isEmailValid($email)) {
            $api_status_call->respondBadRequest(API_User_Response::$invalidemail);
        } elseif ($db_call->checkIfRowExistAndCount('users', [
            [['column' => 'email', 'operator' => '=', 'value' => $email]]
        ]) > 0) {
            $api_status_call->respondBadRequest(API_User_Response::$already_created_record);
        } elseif ($_ENV["ALLOW_USER_TO_LOGIN_REGISTER"] == 0) {
            $api_status_call->respondNotCompleted(API_User_Response::$serverUnderMaintainance);
        } else {
            // Registration process
            $systemname = $_ENV["APP_NAME"];
            $password = $utility_class_call->Password_encrypt($password);
            $status = 'pending';
            $kyc_verified = 'pending';

            // Generate User Public Key
            $User_pub_Key = $db_call->createUniqueRandomStringForATableCol(
                20,
                'users',
                'userpubkey',
                $systemname,
                true,
                true,
                true
            );

            // Compose welcome email
            $subject = "Welcome to $systemname, $fname!";
            $messageText = "Welcome to $systemname! Your user account has been successfully created.";
            $messagetitle = "Welcome Aboard 🎉";
            $greetingText = "Hello $fname";
            $mailText = "We’re happy to have you join the $systemname platform. You can now explore properties to buy or rent.";
            $messageHTML = $mail_sms_call->generalMailTemplate($messagetitle, $greetingText, $mailText, "Start Exploring 🏡");

            $mail_sms_call->sendUserMail($subject, $email, $messageText, $messageHTML);

            // Insert user record
            $InsertResponseData = $db_call->insertRow("users", [
                'fname' => $fname,
                'lname' => $lname,
                'email' => $email,
                'password' => $password,
                'phoneno' => $phoneno,
                'status' => $status,
                'kyc_verified' => $kyc_verified,
                'email_verified' => 'not_verified',
                'phone_verified' => 'not_verified',
                'userpubkey' => $User_pub_Key,
                "country" => $country,
                'city' => $city,
                'state' => $state,
                'postal_code' => $postal_code,
                'streetname' => $streetname
            ]);

            if ($InsertResponseData > 0) {
                // Create session record
                $sesscode = $db_call->createUniqueRandomStringForATableCol(20, "user_sessions", "sessioncode", time(), true, true, true);
                $ipaddress = $utility_class_call->getIpAddress();
                $browser = $utility_class_call->getBrowserInfo()['name'] . ' on ' . ucfirst($utility_class_call->getBrowserInfo()['platform']);
                $location = '';

                $db_call->insertRow("user_sessions", [
                    'email' => $email,
                    'sessioncode' => $sesscode,
                    'ipaddress' => $ipaddress,
                    'browser' => $browser,
                    'forwho' => 2, // 2 for user
                    'location' => $location
                ]);

                // Generate JWT token
                $tokentype = 1;
                $accesstoken = $api_status_call->getTokenToSendAPI($User_pub_Key, $tokentype);

                $maindata = [
                    'access_token' => $accesstoken,
                    'email_verified' => 'not_verified',
                    'phone_verified' => 'not_verified'
                ];

                $api_status_call->respondOK([$maindata], API_User_Response::$userRegisterSuccessful);
            } else {
                $api_status_call->respondInternalError(API_User_Response::$error_creating_record, API_User_Response::$error_creating_record);
            }
        }
    } catch (\Exception $e) {
        $api_status_call->respondInternalError($utility_class_call->get_details_from_exception($e));
    }
} else {
    $api_status_call->respondMethodNotAlowed();
}
