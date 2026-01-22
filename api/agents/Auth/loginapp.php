<?php

require __DIR__ . '/../../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

require_once __DIR__ . "/../../../bootstrap.php";

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

        $maindata["frozedata"] = "";

        $email = isset($_POST["email"]) ? $utility_class_call->clean_user_data($_POST["email"], 1) : '';
        $password = isset($_POST["password"]) ? $utility_class_call->clean_user_data($_POST["password"], 1) : "";

        $responseData = $db_call->selectRows(
            "agents",
            "id, email, emailverified, password, Agentpubkey, phoneno, status, phoneverified",
            [
                [
                    ["column" => "email", "operator" => "=", "value" => $email],
                    "operator" => "OR"
                ]
            ]
        );

        if ($utility_class_call->input_is_invalid($email) || $utility_class_call->input_is_invalid($password)) {
            $api_status_call->respondBadRequest(API_User_Response::$request_body_invalid);
        } elseif ($utility_class_call->input_is_invalid($responseData)) {
            $api_status_call->respondBadRequest(API_User_Response::$invalidUserDetail);
        } elseif ($_ENV["ALLOW_USER_TO_LOGIN_REGISTER"] == 0) {
            $api_status_call->respondBadRequest(API_User_Response::$serverUnderMaintainance);
        } else {
            $found = $responseData[0];
            $agent_id = $found["id"];
            $dash_mail = $found["email"];
            $emailverified = $found["emailverified"];
            $phoneverified = $found["phoneverified"];
            $pass = $found["password"];
            $phone = $found["phoneno"];
            $agentkey = $found["Agentpubkey"];
            $statusis = $found["status"];
            $banreason = "You have been Banned";

            $verifypass = $utility_class_call->is_password_hash_valid($password, $pass);

            if ($verifypass) {
                $systemname = $_ENV["APP_NAME"];

                $agentpubkey = $db_call->createUniqueRandomStringForATableCol(29, "agents", "Agentpubkey", "$systemname", true, true, true);
                $updateddate = $db_call->updateRows(
                    "agents",
                    ["Agentpubkey" => $agentpubkey],
                    [
                        [
                            ["column" => 'id', "operator" => "=", "value" => $agent_id]
                        ]
                    ]
                );

                if ($updateddate) {

                    //Agents can now log in regardless of 'pending' status
                    if ($statusis == 'approved' || $statusis == 'pending') {
                        $maindata = [];

                        $maindata["email_verified"] = $emailverified;
                        $maindata["phoneverified"] = $phoneverified;
                        $maindata["phone_no"] = $phone;
                        $maindata["email"] = $dash_mail;

                        $ipaddress = $utility_class_call->getIpAddress();
                        $getcount = 0;

                        $responseData = $db_call->selectRows(
                            "user_sessions",
                            "email",
                            [
                                [
                                    [
                                        "column" => "ipaddress",
                                        "operator" => "=",
                                        "value" => $ipaddress
                                    ]
                                ]
                            ]
                        );

                        if (!$utility_class_call->input_is_invalid($responseData)) {
                            $getcount = count($responseData);
                        }

                        $datatosave = time();
                        $db_call->updateRows(
                            "agents",
                            ["login_last_with" => 2, "last_time_logged_in" => $datatosave],
                            [
                                ["column" => "id", "operator" => "=", "value" => $agent_id]
                            ]
                        );





                        $seescode = $db_call->createUniqueRandomStringForATableCol(20, "user_sessions", "sessioncode", time(), true, true, true);
                        $browser = ' ' . $utility_class_call->getBrowserInfo()['name'] . ' on ' . ucfirst($utility_class_call->getBrowserInfo()['platform']);
                        $location = '';

                        $db_call->insertRow("user_sessions", [
                            "email" => $email,
                            'sessioncode' => $seescode,
                            'ipaddress' => $ipaddress,
                            'browser' => $browser,
                            'forwho' => 3,
                            'location' => $location,
                        ]);

                        $accesstoken = $api_status_call->getTokenToSendAPI($agentpubkey);
                        $maindata['access_token'] = $accesstoken;
                        $maindata = [$maindata];

                        $text = API_User_Response::$loginSuccessful;

                        $api_status_call->respondOK($maindata, $text);
                    } elseif ($statusis == 'suspended') {
                        $api_status_call->respondBadRequest(API_User_Response::$acct_suspended);
                    } elseif ($statusis == 'frozen') {
                        $api_status_call->respondBadRequest(API_User_Response::$acct_frozen);
                    } elseif ($statusis == 'rejected') {
                        $api_status_call->respondBadRequest(API_User_Response::$acct_rejected);
                    } else {
                        $api_status_call->respondBadRequest(API_User_Response::$user_permanetly_banned);
                    }
                } else {
                    $api_status_call->respondBadRequest(API_User_Response::$invalidUserDetail);
                }
            }
        }
    } catch (\Exception $e) {
        $api_status_call->respondInternalError($utility_class_call->get_details_from_exception($e));
    }
} else {
    $api_status_call->respondMethodNotAlowed();
}
