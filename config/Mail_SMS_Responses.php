<?php

namespace Config;

/**
 * System Messages Class
 *
 * PHP version 5.4
 */
class Mail_SMS_Responses extends DB_Connect
{
  public static function sendThePhpMailerMail($subject, $emailTo,  $text, $message, $toname)
  {
    try {
      $mail = new \PHPMailer\PHPMailer\PHPMailer;
      $mail->isSMTP();
      $mail->SMTPDebug = 0;
      $mail->Timeout = 30; // 30 second timeout

      $mail->Host = $_ENV['MAILHOST'];
      $mail->SMTPAuth = true;
      $mail->Port = $_ENV['MAILPORT'];
      $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
      $mail->Username = $_ENV['MAILSENDER'];
      $mail->Password = $_ENV['MAILSENDERPASS'];
      $mail->setFrom($_ENV['MAILSENDER'], $_ENV['APP_NAME']);
      $mail->addReplyTo($_ENV['MAILSENDER'], $_ENV['APP_NAME']);
      $mail->addAddress($emailTo, $toname);
      $mail->Subject = $subject;
      $mail->Body = $message;
      $mail->AltBody = $text;

      if (!$mail->send()) {
        // Log the error instead of echoing
        self::logEmailError($emailTo, $subject, $mail->ErrorInfo);
        return false;
      } else {
        return true;
      }
    } catch (\Exception $e) {
      self::logEmailError($emailTo, $subject, $e->getMessage());
      return false;
    }
  }

  /**
   * Log email sending errors
   */
  private static function logEmailError($emailTo, $subject, $error)
  {
    $logData = [
      'timestamp' => date('Y-m-d H:i:s'),
      'to' => $emailTo,
      'subject' => $subject,
      'error' => $error,
      'server' => $_ENV['MAILHOST'] ?? 'unknown'
    ];

    $logFile = __DIR__ . '/../logs/email_errors_' . date('Y-m-d') . '.log';
    if (!is_dir(dirname($logFile))) {
      mkdir(dirname($logFile), 0777, true);
    }

    $logEntry = json_encode($logData) . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND);
  }
  public static function sendSMSWithTermi($sendto, $smstosend, $usewhatsapp = 0)
  {

    $key = $_ENV['TERMI_PRIVATEKEY'];
    $smstype = $_ENV['TERMI_CHANNEL_TO_USE'];
    $baseurl = $_ENV['TERMI_BASE_URL'];


    $smssent = false;
    $starttimefortoday = strtotime("6:00 PM");
    $endtimefortoday = strtotime("9:20 AM");
    $currenttimeis = time();
    // echo $currenttimeis;
    // echo "<br>";
    // check for if data is to next day 10PM-8AM
    // if($starttimefortoday>$endtimefortoday){
    //     if($currenttimeis >=$starttimefortoday || $currenttimeis <$endtimefortoday){
    //          $channel=$termidata['smschannel2']; 
    //     }
    // }else if($currenttimeis>=$starttimefortoday && $currenttimeis<=$endtimefortoday){

    // }




    $arr = array(
      "to" => $sendto,
      "sms" => $smstosend,
      "api_key" => $key,
      "from" => "Bots",
      "type" => $smstype,
      "channel" => $usewhatsapp == 1 ? "whatsapp" : 'generic',
    );
    //below is the base url
    $url = "$baseurl/api/sms/send";
    $params =  json_encode($arr);
    $curl = curl_init();
    curl_setopt_array($curl, array(
      //u change the url infront based on the request u want
      CURLOPT_URL => $url,
      CURLOPT_POSTFIELDS => $params,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      //change this based on what u need post,get etc
      CURLOPT_CUSTOMREQUEST => "POST",
      CURLOPT_HTTPHEADER => array(
        "content-type: application/json",
      ),
    ));
    $resp = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    //   print($resp);
    //   print( $params );
    if ($err) {
      $smssent = false;
    } else {
      $theresponse = json_decode($resp);
      //   print_r($theresponse);
      if (isset($theresponse->code) && $theresponse->code == "ok") {
        $smssent = true;
        $msgid = $theresponse->message_id;
        // later log sms sent
        // $systype="Termii";
        // $insert_data4 = $connect->prepare("INSERT INTO smslog(message,sentto,sentwith,messageid,sentrom) VALUES (?,?,?,?,?)");
        // $insert_data4->bind_param("sssss", $msg,$sendto,$systype,$msgid,$sendfrom);
        // $insert_data4->execute();
        // $insert_data4->close();
      } elseif (isset($theresponse->message) && strtolower($theresponse->message) == "success") {
        $smssent = true;
      } else {
        $smssent = false;
      }
    }
    return $smssent;
  }
  // for OTP sms
  public static function sendOTPWithTermi($sendto, $smstosend, $usewhatsapp = 0)
  {

    $key = $_ENV['TERMI_PRIVATEKEY'];
    $smstype = $_ENV['TERMI_CHANNEL_TO_USE'];
    $baseurl = $_ENV['TERMI_BASE_URL'];


    $smssent = false;
    $starttimefortoday = strtotime("6:00 PM");
    $endtimefortoday = strtotime("9:20 AM");
    $currenttimeis = time();
    // echo $currenttimeis;
    // echo "<br>";
    // check for if data is to next day 10PM-8AM
    // if($starttimefortoday>$endtimefortoday){
    //     if($currenttimeis >=$starttimefortoday || $currenttimeis <$endtimefortoday){
    //          $channel=$termidata['smschannel2']; 
    //     }
    // }else if($currenttimeis>=$starttimefortoday && $currenttimeis<=$endtimefortoday){

    // }




    $arr = array(
      "to" => $sendto,
      "sms" => $smstosend,
      "api_key" => $key,
      "from" => "N-Alert", //$usewhatsapp==1? "Absvendor" :"N-Alert",
      "type" => $smstype,
      "channel" => $usewhatsapp == 1 ? "whatsapp_otp" : 'dnd',
    );
    if ($usewhatsapp == 1) {
      $arr['time_in_minutes'] = "5 minutes";
    }
    //below is the base url
    $url = "$baseurl/api/sms/send";
    $params =  json_encode($arr);
    $curl = curl_init();
    curl_setopt_array($curl, array(
      //u change the url infront based on the request u want
      CURLOPT_URL => $url,
      CURLOPT_POSTFIELDS => $params,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      //change this based on what u need post,get etc
      CURLOPT_CUSTOMREQUEST => "POST",
      CURLOPT_HTTPHEADER => array(
        "content-type: application/json",
      ),
    ));
    $resp = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    // print($resp);
    // print( $params );
    if ($err) {
      $smssent = false;
    } else {
      $theresponse = json_decode($resp);
      //   print_r($theresponse);
      if (isset($theresponse->code) && $theresponse->code == "ok") {
        $smssent = true;
        $msgid = $theresponse->message_id;
        // later log sms sent
        // $systype="Termii";
        // $insert_data4 = $connect->prepare("INSERT INTO smslog(message,sentto,sentwith,messageid,sentrom) VALUES (?,?,?,?,?)");
        // $insert_data4->bind_param("sssss", $msg,$sendto,$systype,$msgid,$sendfrom);
        // $insert_data4->execute();
        // $insert_data4->close();
      } elseif (isset($theresponse->message) && strtolower($theresponse->message) == "success") {
        $smssent = true;
      } else {
        $smssent = false;
      }
    }
    return $smssent;
  }
  // ALL FUNCTIONS TO SEND SETUP
  public static function sendUserMail($subject, $toemail, $msgintext, $messageinhtml)
  {
    // 1 sendGrid, 2
    $mailsent = false;
    $activemailsystem = 1;
    if ($activemailsystem == 1) {
      $mailsent = self::sendThePhpMailerMail($subject, $toemail, $msgintext, $messageinhtml, '');
    }
    return $mailsent;
  }
  public static function call_user_the_otp($user_id, $otp, $phone, $expiresin)
  {
    $userdata = DB_Calls_Functions::selectRows("users", 'username,country_id',        [
      [
        ['column' => 'id', 'operator' => '=', 'value' => $user_id],
      ]
    ]);
    $userusername = $userdata[0]['username'];
    $country_id = $userdata[0]['country_id'];
    $countrydata = DB_Calls_Functions::selectRows("countries", 'phonecode',        [
      [
        ['column' => 'trackid', 'operator' => '=', 'value' => $country_id],
      ]
    ]);
    $phonecode = $countrydata[0]['phonecode'];
    // get country code stuff add country code at phone number
    // its only if zero starts the number we need to remove it and add the country code
    if ($phone[0] === '0') {
      $phone = substr_replace($phone, $phonecode, 0, 1);
    } else {
      $phone = "$phonecode$phone";
    }

    return self::sendWithTextComm($otp, $phone, $user_id);
  }
  public static function sendWithTextComm($otp, $phone, $userid, $repeat = 2)
  {
    $key = $_ENV['TEXTCOM_PRIVATEKEY'];
    $api_url = $_ENV['TEXTCOM_BASE_URL'];
    $smssent = false;
    /*
      Sending messages using the KudiSMS API
      Requirements - PHP, file_get_contents (enabled) function
      */
    // Set your domain's API URL
    $data = [
      "key" => $key,
      "phone" => $phone,
      "message-opt-code" => $otp,
      "otp-repeat" => $repeat,
      "custom_ref" => $userid,
    ];

    $curl = curl_init();

    curl_setopt_array(
      $curl,
      array(
        CURLOPT_URL => $api_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $data,
      ),
    );

    $response = curl_exec($curl);

    curl_close($curl);
    //    print_r($response);
    $decodedResponse = json_decode($response, true);

    if (isset($decodedResponse['status'])) {
      if (strtolower($decodedResponse['status']) == 'successful') {
        $smssent = true;
      }
    }

    return $smssent;
  }
  public static function sendUserSMSOTP($sendto, $smstosend)
  { // send to is phone number, smsto send (call the function in the smstemplate)
    // 1 Termi, 2 kudi 3 smart solution
    $withoutplus = str_replace('+', '', $sendto);


    $smssent = false;
    $activemailsystem = 1;
    if ($activemailsystem == 1) {
      $smssent = self::sendOTPWithTermi($withoutplus, $smstosend);
    } else if ($activemailsystem == 2) {
    } else if ($activemailsystem == 3) {
    }
    return $smssent;
  }
  public static function sendUserWhatsappOTP($sendto, $smstosend)
  { // send to is phone number, smsto send (call the function in the smstemplate)
    // 1 Termi, 2 kudi 3 smart solution
    $withoutplus = str_replace('+', '', $sendto);

    $smssent = false;
    $activemailsystem = 1;
    if ($activemailsystem == 1) {
      $smssent = self::sendOTPWithTermi($withoutplus, $smstosend, 1);
    }
    return $smssent;
  }
  public static function generalMailTemplate($messagetitle, $greetingText, $mailText, $otp = '', $calltoactionlink = '', $calltoactiontext = '', $calltoactionDangerlink = '', $calltoactionDangertext = '')
  {
    $supportemail = $_ENV['SUPPORT_EMAIL'];
    $appname = $_ENV['APP_NAME'];
    $logourl = $_ENV['LOGO_URL'];
    $appiosllink = $_ENV['APP_IOS_LINK'];
    $appandroidlink = $_ENV['APP_ANDROID_LINK'];
    $facebooklink = $_ENV['FACEBOOK_LINK'];
    $twitterlink = $_ENV['TWITTERLINK'];
    $instagramlink = $_ENV['INSTAGRAM_LINK'];
    $otpCode = '';
    if (strlen($otp) > 0) {
      $otpCode = '<br><br>Your OTP is:<br style="box-sizing: border-box;"><br style="box-sizing: border-box;"><span style="font-size: 30px;font-weight: bold;">' . $otp . '</span>';
    }
    $bottomtext = "If you have any questions, don't hesitate to reach us via our several support channels, or open a support ticket by sending a mail to <a style='text-decoration: none; color: #0ab930; letter-spacing: .2px; font-weight: 600;  font-size: 14px;'>$supportemail</a>";
    $buttonis = "";
    if (strlen($calltoactionlink) > 0 && strlen($calltoactiontext) > 0) {
      $buttonis .= '<br><br> 
            <table role="presentation" border="0" cellpadding="0" cellspacing="0">
              <tbody>
                <tr>
                  <td> <a style="background-color:#0ab930;border: solid 1px #0ab930;
                                border-radius: 5px;
                                box-sizing: border-box;
                                color: white;
                                display: inline-block;
                                font-size: 14px;
                                font-weight: bold;
                                margin: 0;
                                padding: 12px 25px;
                                text-decoration: none;
                                text-transform: capitalize;" href="' . $calltoactionlink . '" target="_blank">' . $calltoactiontext . '</a> </td>
                </tr>
              </tbody>
            </table>';
    }
    if (strlen($calltoactionDangerlink) > 0 && strlen($calltoactionDangertext) > 0) {
      $buttonis .= '<br><br> 
            <table role="presentation" border="0" cellpadding="0" cellspacing="0">
              <tbody>
                <tr>
                  <td> <a style="background-color:red;border: solid 1px red;
                                border-radius: 5px;
                                box-sizing: border-box;
                                color: white;
                                display: inline-block;
                                font-size: 14px;
                                font-weight: bold;
                                margin: 0;
                                padding: 12px 25px;
                                text-decoration: none;
                                text-transform: capitalize;" href="' . $calltoactionDangerlink . '" target="_blank">' . $calltoactionDangertext . '</a> </td>
                </tr>
              </tbody>
            </table>';
    }

    $mailtemplate = '
        <!DOCTYPE html>
       <html lang="en" style="--white: #fff; --green-dark: #0a6836; --green-light: #0ab930; --green-lighter: #12a733; --black: #000;">
       <head>
       <meta charset="utf-8">
       <meta name="viewport" content="width=device-width, initial-scale=1.0">
       <meta http-equiv="Content-Type" content="text/html;text/css; charset=UTF-8">
       <title>' . $messagetitle . '</title>
       <style>.im{color:black !important}</style>
       </head>
       <div><div class="gmail_signature" dir="ltr" data-smartmail="gmail_signature">
   <div dir="ltr">
     <div dir="ltr">
       <div dir="ltr">
         <div dir="ltr">
           <div dir="ltr">
             <div dir="ltr">
               <div dir="ltr">
                 <div dir="ltr">
                   <div style="font-size: small;">
                     <div dir="ltr">
                       <div dir="ltr">
                         <div dir="ltr">
                           <div dir="ltr">
                             <div dir="ltr">
                               <div dir="ltr">
                                 <div dir="ltr">
                                   <div dir="ltr">
                                     <div dir="ltr">
                                       <div dir="ltr">
                                         <div dir="ltr">
                                           <div dir="ltr">
                                             <div dir="ltr">
                                               <div style="color: #444444; font-family: \'Open Sans\',sans-serif; font-size: 14px;">
                                                 <br class="gmail-Apple-interchange-newline">
                                                 <table style="color: #222222; font-family: Arial, Helvetica, sans-serif; font-size: small; background-color: #fafeff;" role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
                                                   <tbody style="box-sizing: border-box;">
                                                     <tr style="box-sizing: border-box;">
                                                       <td style="box-sizing: border-box;">
                                                         <table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0" align="center">
                                                           <tbody style="box-sizing: border-box;">
                                                             <tr style="box-sizing: border-box;">
                                                               <td style="box-sizing: border-box;">
                                                                 <table class="row-content m_-7842681516560346380stack" style="border-radius: 0px; color: #000000; width: 600px; margin: 0px auto;" role="presentation" border="0" width="600" cellspacing="0" cellpadding="0" align="center">
                                                                   <tbody style="box-sizing: border-box;">
                                                                     <tr style="box-sizing: border-box;">
                                                                       <td class="column" style="box-sizing: border-box; padding-bottom: 2px; padding-top: 6px; vertical-align: top; border: 0px initial initial;" width="100%">
                                                                         <table class="image_block" role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
                                                                           <tbody style="box-sizing: border-box;">
                                                                             <tr style="box-sizing: border-box;">
                                                                               <td style="box-sizing: border-box; padding-left: 10px; padding-top: 15px; width: 600px; padding-right: 0px;">
                                                                                 <div style="box-sizing: border-box; line-height: 10px;" align="left">
                                                                                   <div style="box-sizing: border-box; max-width: 180px;">
                                                                                     <img style="box-sizing: border-box; display: block; height: auto; border: 0px; width: 140px;" src="' . $logourl . '" width="140">
                                                                                   </div>
                                                                                 </div>
                                                                               </td>
                                                                             </tr>
                                                                           </tbody>
                                                                         </table>
                                                                         <table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="10">
                                                                           <tbody style="box-sizing: border-box;">
                                                                             <tr style="box-sizing: border-box;">
                                                                               <td style="box-sizing: border-box;">
                                                                                 <div style="box-sizing: border-box;" align="center">
                                                                                   <table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
                                                                                     <tbody style="box-sizing: border-box;">
                                                                                       <tr style="box-sizing: border-box;">
                                                                                         <td style="box-sizing: border-box; font-size: 1px; line-height: 1px; border-top: 1px solid #bbbbbb;">&nbsp;</td>
                                                                                       </tr>
                                                                                     </tbody>
                                                                                   </table>
                                                                                 </div>
                                                                               </td>
                                                                             </tr>
                                                                           </tbody>
                                                                         </table>
                                                                       </td>
                                                                     </tr>
                                                                   </tbody>
                                                                 </table>
                                                               </td>
                                                             </tr>
                                                           </tbody>
                                                         </table>
                                                         <table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0" align="center">
                                                           <tbody style="box-sizing: border-box;">
                                                             <tr style="box-sizing: border-box;">
                                                               <td style="box-sizing: border-box;">
                                                                 <table class="row-content m_-7842681516560346380stack" style="color: #000000; width: 600px; margin: 0px auto;" role="presentation" border="0" width="600" cellspacing="0" cellpadding="0" align="center">
                                                                   <tbody style="box-sizing: border-box;">
                                                                     <tr style="box-sizing: border-box;">
                                                                       <td class="column" style="box-sizing: border-box; padding-bottom: 5px; padding-top: 5px; vertical-align: top; border: 0px initial initial;" width="100%">
                                                                         <table style="word-break: break-word;" role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
                                                                           <tbody style="box-sizing: border-box;">
                                                                             <tr style="box-sizing: border-box;">
                                                                               <td style="box-sizing: border-box; padding: 30px 20px;">
                                                                                 <div style="box-sizing: border-box; font-family: Arial, sans-serif;">
                                                                                   <div style="box-sizing: border-box; font-size: 14px; font-family: Arial, \'Helvetica Neue\', Helvetica, sans-serif; line-height: 1.5;">
                                                                                     <p style="box-sizing: border-box; line-height: inherit; margin: 0px; font-size: 15px;">
                                                                                       <span style="box-sizing: border-box;">
                                                                                         <strong style="box-sizing: border-box;">' . $greetingText . '</strong>
                                                                                       </span>
                                                                                     </p>
                                                                                     <p style="box-sizing: border-box; line-height: inherit; margin: 0px; font-size: 15px;">&nbsp;</p>


                                                                                     <p style="box-sizing: border-box; line-height: inherit; margin: 0px; font-size: 15px;color:black">' . $mailText . '
                                                                                      ' . $otpCode . '

                                                                                      ' . $buttonis . '

                                                                                       <br style="box-sizing: border-box;">
                                                                                       <br style="box-sizing: border-box;">' . $bottomtext . '
                                                                                       <br style="box-sizing: border-box;">
                                                                                       <br style="box-sizing: border-box;">
                                                                                       <style="box-sizing: border-box;">Thanks and best regards!<br> With ❤️ from your friends at<br> ' . $appname . '™
</strong>
                                                                                     </p>
                                                                                   </div>
                                                                                 </div>
                                                                               </td>
                                                                             </tr>
                                                                           </tbody>
                                                                         </table>
                                                                       </td>
                                                                     </tr>
                                                                   </tbody>
                                                                 </table>
                                                               </td>
                                                             </tr>
                                                           </tbody>
                                                         </table>
                                                        
                                                         <table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0" align="center">
                                                           <tbody style="box-sizing: border-box;">
                                                             <tr style="box-sizing: border-box;">
                                                               <td style="box-sizing: border-box;">
                                                                 <table class="row-content m_-7842681516560346380stack" style="color: #000000; width: 600px; margin: 0px auto;" role="presentation" border="0" width="600" cellspacing="0" cellpadding="0" align="center">
                                                                   <tbody style="box-sizing: border-box;">
                                                                     <tr style="box-sizing: border-box;">
                                                                       <td class="column" style="box-sizing: border-box; padding-bottom: 0px; padding-top: 0px; vertical-align: top; border: 0px initial initial;" width="100%">
                                                                         <table style="word-break: break-word;" role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
                                                                           <tbody style="box-sizing: border-box;">
                                                                             <tr style="box-sizing: border-box;">
                                                                               <td style="box-sizing: border-box; padding: 10px 5px;">
                                                                                 <div style="box-sizing: border-box;
                                                                                       
                                                                                        
                                                                                     
                                                                                     </p>
                                                                                   </div>
                                                                                 </div>
                                                                               </td>
                                                                             </tr>
                                                                           </tbody>
                                                                         </table>
                                                                       </td>
                                                                     </tr>
                                                                   </tbody>
                                                                 </table>
                                                               </td>
                                                             </tr>
                                                           </tbody>
                                                         </table>
                                                         <table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0" align="center">
                                                           <tbody style="box-sizing: border-box;">
                                                             <tr style="box-sizing: border-box;">
                                                               <td style="box-sizing: border-box;">
                                                                 <table class="row-content m_-7842681516560346380stack" style="border-radius: 0px; color: #000000; width: 600px; margin: 0px auto;" role="presentation" border="0" width="600" cellspacing="0" cellpadding="0" align="center">
                                                                   <tbody style="box-sizing: border-box;">
                                                                     <tr style="box-sizing: border-box;">
                                                                       <td class="column" style="box-sizing: border-box; padding-bottom: 0px; padding-top: 0px; vertical-align: top; border: 0px initial initial;" width="100%">
                                                                         <table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="20">
                                                                           <tbody style="box-sizing: border-box;">
                                                                             <tr style="box-sizing: border-box;">
                                                                               <td style="box-sizing: border-box;">
                                                                                 <div style="box-sizing: border-box;" align="center">
                                                                                   <table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
                                                                                     <tbody style="box-sizing: border-box;">
                                                                                       <tr style="box-sizing: border-box;">
                                                                                         <td style="box-sizing: border-box; font-size: 1px; line-height: 1px; border-top: 1px solid #bbbbbb;">&nbsp;</td>
                                                                                       </tr>
                                                                                     </tbody>
                                                                                   </table>
                                                                                 </div>
                                                                               </td>
                                                                             </tr>
                                                                           </tbody>
                                                                         </table>
                                                                         <table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
                                                                           <tbody style="box-sizing: border-box;">
                                                                             <tr style="box-sizing: border-box;">
                                                                               <td style="box-sizing: border-box; padding-bottom: 5px; padding-left: 20px; padding-right: 20px; text-align: center; width: 600px;">
                                                                                 <h1 style="box-sizing: border-box; margin: 0px; direction: ltr; font-family: Arial, \'Helvetica Neue\', Helvetica, sans-serif; font-size: 29px; line-height: 34.8px; text-align: left;">
                                                                                   <span style="box-sizing: border-box;">Download the ' . $appname . ' app now!</span>
                                                                                 </h1>
                                                                               </td>
                                                                             </tr>
                                                                           </tbody>
                                                                         </table>
                                                                         <table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
                                                                           <tbody style="box-sizing: border-box;">
                                                                             <tr style="box-sizing: border-box;">
                                                                               <td style="box-sizing: border-box; vertical-align: middle; font-family: inherit; font-size: 14px; letter-spacing: 0px; padding-left: 20px; padding-right: 20px;">
                                                                                 <table role="presentation" cellspacing="0" cellpadding="0" align="left">
                                                                                   <tbody style="box-sizing: border-box;">
                                                                                     <tr style="box-sizing: border-box;">
                                                                                       <td style="box-sizing: border-box; vertical-align: middle; text-align: center; padding: 5px 5px 5px 0px;">
                                                                                         <a href="' . $appandroidlink . '" target="_blank" style="box-sizing: border-box; text-decoration-line: none;" rel="noopener">
                                                                                           <img style="box-sizing: border-box; display: block; height: auto; margin: 0px auto; border: 0px;" src="https://ci3.googleusercontent.com/meips/ADKq_NafbOLXSMpslCJUTS0Jn0WOSxOX_lznP9vwKNLSY4xOIA-psEh0HHR5Ew_diXTCMFUutgBRPE69QzY36d0uoucVySIf18AoX5bF9ZZVDXLEGrTWEHgwOMb217X8i4zujCqX2jkLb36oYp4aJ5-npDhiCrtjKY2g0Klqo89Y24loHUEYlmfY2MT8sRQaicof=s0-d-e1-ft#https://userimg-assets-eu.customeriomail.com/images/client-env-143870/1696511131911_google_01HBZZ3176BDNYECDVQQPE1028.png" width="108" height="32" align="center">
                                                                                         </a>
                                                                                       </td>
                                                                                       <td style="box-sizing: border-box; vertical-align: middle; text-align: center; padding: 5px 0px 5px 5px;">
                                                                                         <a href="' . $appiosllink . '" target="_blank" style="box-sizing: border-box; text-decoration-line: none;" rel="noopener">
                                                                                           <img style="box-sizing: border-box; display: block; height: auto; margin: 0px auto; border: 0px;" src="https://ci3.googleusercontent.com/meips/ADKq_NYSJNQktRdHnRg90Zp2V6XUVhzuC9lljSh_r0Pc8ZB8NW8EcgHB2crP6WKFFQcZZA_35H8LMQzMpwn5WK0BNhKNFG0UzkipoZ9-H2skt52FIqHrpzbpaF78OwzoUrkrmGHYVgdSm2MD45ULyTO5BWu7wDBnW25puXVRSPnhUw54lC1-jhAaIQlocjAYApk=s0-d-e1-ft#https://userimg-assets-eu.customeriomail.com/images/client-env-143870/1696511206643_apple_01HBZZ59XJXPFSGZVSQR6JAR69.png" width="108" height="32" align="center">
                                                                                         </a>
                                                                                       </td>
                                                                                     </tr>
                                                                                   </tbody>
                                                                                 </table>
                                                                               </td>
                                                                             </tr>
                                                                           </tbody>
                                                                         </table>
                                                                         <table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="20">
                                                                           <tbody style="box-sizing: border-box;">
                                                                             <tr style="box-sizing: border-box;">
                                                                               <td style="box-sizing: border-box;">
                                                                                 <div style="box-sizing: border-box;" align="center">
                                                                                   <table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
                                                                                     <tbody style="box-sizing: border-box;">
                                                                                       <tr style="box-sizing: border-box;">
                                                                                         <td style="box-sizing: border-box; font-size: 1px; line-height: 1px; border-top: 1px solid #bbbbbb;">&nbsp;</td>
                                                                                       </tr>
                                                                                     </tbody>
                                                                                   </table>
                                                                                 </div>
                                                                               </td>
                                                                             </tr>
                                                                           </tbody>
                                                                         </table>
                                                                         <table class="social_block" role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
                                                                           <tbody style="box-sizing: border-box;">
                                                                             <tr style="box-sizing: border-box;">
                                                                               <td style="box-sizing: border-box; padding-left: 20px; padding-right: 20px;">
                                                                                 <div style="box-sizing: border-box;" align="left">
                                                                                   <table class="social-table" style="display: inline-block;" role="presentation" border="0" width="108px" cellspacing="0" cellpadding="0">
                                                                                     <tbody style="box-sizing: border-box;">
                                                                                       <tr style="box-sizing: border-box;">
                                                                                         <td style="box-sizing: border-box; padding: 0px 4px 0px 0px;">
                                                                                           <a href="' . $instagramlink . '" target="_blank" style="box-sizing: border-box;" rel="noopener">
                                                                                             <img style="box-sizing: border-box; display: block; height: auto; border: 0px;" title="Instagram" src="https://ci3.googleusercontent.com/meips/ADKq_NYpBngxGw6wIwjfHvTxRT5lEodzU9mkQWpCO9rJfd9X3DMEoHefhZcG5Jsst5CsLYqVCJohvITS-2BFFTaUAuYplJm0kjH1We_qMMIqFCTRg-IMC4GpXf6k9t2MOBEU7ycPaosk4WlaArGKf8kIOQET3jn510wAMxyG4hX530MHPfY=s0-d-e1-ft#https://app-rsrc.getbee.io/public/resources/social-networks-icon-sets/t-only-logo-dark-gray/instagram@2x.png" alt="Instagram" width="32" height="32">
                                                                                           </a>
                                                                                         </td>
                                                                                         <td style="box-sizing: border-box; padding: 0px 4px 0px 0px;">
                                                                                           <a href="' . $facebooklink . '" target="_blank" style="box-sizing: border-box;" rel="noopener">
                                                                                             <img style="box-sizing: border-box; display: block; height: auto; border: 0px;" title="Facebook" src="https://ci3.googleusercontent.com/meips/ADKq_Nbvqiy988FW3t8GaG7sEwHn6SWGf1s33CzVUbzs84_vj9kwsISLI8fK-rwdqd0KeOMQ_9TbgrtEyA_ZJtZ1l92HNEIGl4PU6ccEGpfHbrwmml9PVsh4yfsOutURaEPW4tyFVP2LGjqdYi_6S3z5CZTi6m7WKafUvHgraUDRYMXHKg=s0-d-e1-ft#https://app-rsrc.getbee.io/public/resources/social-networks-icon-sets/t-only-logo-dark-gray/facebook@2x.png" alt="Facebook" width="32" height="32">
                                                                                           </a>
                                                                                         </td>
                                                                                         <td style="box-sizing: border-box; padding: 0px 4px 0px 0px;">
                                                                                           <a href="' . $twitterlink . '" target="_blank" style="box-sizing: border-box;" rel="noopener">
                                                                                             <img style="box-sizing: border-box; display: block; height: auto; border: 0px;" title="Twitter" src="https://ci3.googleusercontent.com/meips/ADKq_NZF8UbcGTE4ZU6Iy8JYXXWf18O4oVhMHBQcwlZt6iwY9BceVBO6WJx1iBr4U9qO7xN_GvlfqrZQrbt4TVPIpWezVZlaTjVmcMBs2-FxQ0sMebk1qKgMJHCuNdihshxr32nyCQvfPipGmMyuuPazTcsnKWo1sKOoka4EuOdg_G8S=s0-d-e1-ft#https://app-rsrc.getbee.io/public/resources/social-networks-icon-sets/t-only-logo-dark-gray/twitter@2x.png" alt="Twitter" width="32" height="32">
                                                                                           </a>
                                                                                         </td>
                                                                                       </tr>
                                                                                     </tbody>
                                                                                   </table>
                                                                                 </div>
                                                                               </td>
                                                                             </tr>
                                                                           </tbody>
                                                                         </table>
                                                                         <table style="word-break: break-word;" role="presentation" border="0" width="100%" cellspacing="0" cellpadding="20">
                                                                           <tbody style="box-sizing: border-box;">
                                                                             <tr style="box-sizing: border-box;">
                                                                               <td style="box-sizing: border-box;">
                                                                                 <div style="box-sizing: border-box; font-family: Arial, sans-serif;">
                                                                                   <div style="box-sizing: border-box; font-size: 14px; font-family: Arial, \'Helvetica Neue\', Helvetica, sans-serif; color: #ffffff; line-height: 1.2;">
                                                                                     <p style="box-sizing: border-box; line-height: inherit; margin: 0px; font-size: 11px;">
                                                                                       <span style="box-sizing: border-box; color: #000000;">Copyright © 2022-2025 ' . $appname . ', All rights reserved.</span>
                                                                                     </p>
                                                                                     <p style="box-sizing: border-box; line-height: inherit; margin: 0px; font-size: 11px; text-align: center;">
                                                                                       <span style="box-sizing: border-box;">&nbsp;</span>
                                                                                     </p>
                                                                                     <p style="box-sizing: border-box; line-height: inherit; margin: 0px; font-size: 11px; text-align: center;">
                                                                                       <span style="box-sizing: border-box;">&nbsp;</span>
                                                                                     </p>
                                                                                     <p style="box-sizing: border-box; line-height: inherit; margin: 0px; font-size: 11px;">
                                                                                       <span style="box-sizing: border-box; color: #000000;">All messages from ' . $appname . ', including our Customer Support team, will always come from an @ 
                                                                                         <a href="' . $_ENV['BASE_URL'] . '" target="_blank" style="box-sizing: border-box;" rel="noopener">' . $_ENV['BASE_URL'] . '</a>&nbsp;email address. We will never ask you for account information like your login details and password via Email, Phone call, WhatsApp or Social media.
                                                                                       </span>
                                                                                     </p>
                                                                                     <p style="box-sizing: border-box; line-height: inherit; margin: 0px; font-size: 11px;">&nbsp;</p>
                                                                                     <p style="box-sizing: border-box; line-height: inherit; margin: 0px; font-size: 11px;">
                                                                                      
                                                                                     </p>
                                                                                   </div>
                                                                                 </div>
                                                                               </td>
                                                                             </tr>
                                                                           </tbody>
                                                                         </table>
                                                                         <table class="image_block" role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
                                                                           <tbody style="box-sizing: border-box;">
                                                                             <tr style="box-sizing: border-box;">
                                                                               <td style="box-sizing: border-box; padding-left: 10px; padding-right: 20px; width: 600px;">
                                                                                 <div style="box-sizing: border-box; line-height: 20px;" align="left">
                                                                                   <div style="box-sizing: border-box; max-width: 120px;">
                                                                                     <img style="box-sizing: border-box; display: block; height: auto; border: 0px; width: 120px;" src="' . $logourl . '" width="120">
                                                                                   </div>
                                                                                 </div>
                                                                               </td>
                                                                             </tr>
                                                                           </tbody>
                                                                         </table>
                                                                       </td>
                                                                     </tr>
                                                                   </tbody>
                                                                 </table>
                                                               </td>
                                                             </tr>
                                                           </tbody>
                                                         </table>
                                                       </td>
                                                     </tr>
                                                   </tbody>
                                                 </table>
                                               </div>
                                             </div>
                                           </div>
                                         </div>
                                       </div>
                                     </div>
                                   </div>
                                 </div>
                               </div>
                             </div>
                           </div>
                         </div>
                       </div>
                     </div>
                   </div>
                 </div>
               </div>
             </div>
           </div>
         </div>
       </div>
     </div>
   </div>
 </div>
</div>
   ';

    return $mailtemplate;
  }
  //  this function is used to get the system specific functions
  public static function sendOTPText($otp)
  {
    $appname =  $_ENV['APP_NAME'];
    $mailtext = "Your $appname confirmation code is $otp. It expires in 5 minutes.";
    return $mailtext;
  }
  public static function sendOTPSubject()
  {
    $appname =  $_ENV['APP_NAME'];
    $currentDateTime = date('Y-m-d H:i:s');

    $subject = "$appname - OTP Code 🔒 $currentDateTime";
    return $subject;
  }
  public static function sendMailOTP($userid, $otp, $token, $sendToEmail)
  {
    // for all mail OTP it verify email otp or forgot password otp
    $userdata = DB_Calls_Functions::selectRows("users", 'id',        [
      [
        ['column' => 'id', 'operator' => '=', 'value' => $userid],
      ]
    ]);
    $userdata = $userdata[0];
    $subject = self::sendOTPSubject();
    $messageText = self::sendOTPText($otp);

    $messagetitle = $subject;
    $greetingText = "Hello,";
    $mailText = "You need an OTP to proceed with the requested action.";
    $calltoactionlink = "";
    $calltoactiontext = "";
    $messageHTML = self::generalMailTemplate($messagetitle, $greetingText, $mailText, $otp, $calltoactionlink, $calltoactiontext);
    // self::sendOTPtoEmailHTML($userdata, $token,$otp);
    $sentit = self::sendUserMail($subject, $sendToEmail, $messageText, $messageHTML);
    return $sentit;
  }
  public static function sendSMSOTP($userid, $otp, $phone)
  {
    $userdata = DB_Calls_Functions::selectRows("users", 'username,country_id',        [
      [
        ['column' => 'id', 'operator' => '=', 'value' => $userid],
      ]
    ]);
    $country_id = $userdata[0]['country_id'];
    $countrydata = DB_Calls_Functions::selectRows("countries", 'phonecode',        [
      [
        ['column' => 'trackid', 'operator' => '=', 'value' => $country_id],
      ]
    ]);
    $phonecode = $countrydata[0]['phonecode'];
    // get country code stuff add country code at phone number
    // its only if zero starts the number we need to remove it and add the country code
    if ($phone[0] === '0') {
      $phone = substr_replace($phone, $phonecode, 0, 1);
    } else {
      $phone = "$phonecode$phone";
    }
    $messageText = self::sendOTPText($otp);
    $sendit = self::sendUserSMSOTP($phone, $messageText);
    return $sendit;
  }
  public static function sendWhatsappOTP($userid, $otp, $phone)
  {
    $messageText = self::sendOTPText($otp);
    $userdata = DB_Calls_Functions::selectRows("users", 'country_id',        [
      [
        ['column' => 'id', 'operator' => '=', 'value' => $userid],
      ]
    ]);
    $country_id = $userdata[0]['country_id'];
    $countrydata = DB_Calls_Functions::selectRows("countries", 'phonecode',        [
      [
        ['column' => 'trackid', 'operator' => '=', 'value' => $country_id],
      ]
    ]);
    $phonecode = $countrydata[0]['phonecode'];
    // get country code stuff add country code at phone number
    // its only if zero starts the number we need to remove it and add the country code
    if ($phone[0] === '0') {
      $phone = substr_replace($phone, $phonecode, 0, 1);
    } else {
      $phone = "$phonecode$phone";
    }
    $sendit = self::sendUserWhatsappOTP($phone, $otp);
    return $sendit;
  }
}
