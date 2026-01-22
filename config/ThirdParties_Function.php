<?php

namespace Config;

/**
 * Third Parties Handler
 * 
 * Procedural-style static functions for Paystack, Monnify, SH, etc.
 * Matches structure & naming style.
 */
class ThirdParties_Functions
{

    /* ============================================================
        GENERAL FUNCTIONS 
    ============================================================ */

    /**
     * Resolve user bank account name through active payment providers
     * (Paystack / Monnify / SH / etc)
     * 
     * @param string $bnkcode  - system bank code from bankaccountsallowed.sysbankcode
     * @param string $accno    - account number
     * @return array|string    - returns array with account details OR empty string on failure
     */
    public static function getUserAccountName($bnkcode, $accno)
    {
        // fetch full row for this bank
        $systemData = DB_Calls_Functions::selectRows(
            "allowed_banks",
            "oneappbankcode, monifybankcode, paystackbankcode, shbankcodes, banncode, baanlistcode",
            [
                [
                    ['column' => 'sysbankcode', 'operator' => '=', 'value' => $bnkcode],
                ]
            ],
            ['limit' => 1]
        );

        $oneappcode = $monifycode = $paystackcode = $shcodes = $banncode = $baanlist = '';

        if (!Utility_Functions::input_is_invalid($systemData)) {
            $bank = $systemData[0];

            $oneappcode  = $bank['oneappbankcode'];
            $monifycode  = $bank['monifybankcode'];
            $paystackcode = $bank['paystackbankcode'];
            $shcodes     = $bank['shbankcodes'];
            $banncode    = $bank['banncode'];
            $baanlist    = $bank['baanlistcode'];
        }

        // if no bank codes found → invalid bank request
        if (Utility_Functions::input_is_invalid($systemData)) {
            return "";
        }

        /**
         * At this point:
         * $paystackcode = paystack bank identifier
         * $monifycode   = monnify bank identifier
         * etc…
         *
         * Now you choose which provider to call.
         * For now, ONLY Paystack is implemented.
         */

        // Use Paystack to verify the account
        $account = self::verifyAccount_Paystack($paystackcode, $accno);

        return $account;
    }
    /**
     * Resolve bank account name using Paystack
     * 
     * @param string $paystackBankCode
     * @param string $accountNumber
     * @return array|string
     */
    public static function verifyAccount_Paystack($paystackBankCode, $accountNumber)
    {
        if (!$paystackBankCode || !$accountNumber) {
            return "";
        }

        $secretKey = GetActivePayStackApi()['secretekey'] ?? "";

        if (!$secretKey) {
            return "";
        }

        $url = "https://api.paystack.co/bank/resolve?account_number={$accountNumber}&bank_code={$paystackBankCode}";


        
        // Log API call (Imran style)
        DB_Calls_Functions::insertRow("responsesfromapicalllog", [
            'user_id' => "",
            'apilink' => $url,
            'apimethod' => 'GET',
            'request_payload' => null,
            'response_payload' => null
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $secretKey"
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        // update log
        DB_Calls_Functions::updateRows(
            "responsesfromapicalllog",
            ['response_payload' => $response],
            [['column' => 'apilink', 'operator' => '=', 'value' => $url]]
        );

        $data = json_decode($response, true);

        if (!empty($data['status']) && isset($data['data']['account_name'])) {
            return [
                'account_name' => $data['data']['account_name'],
                'account_number' => $accountNumber,
                'bank_code' => $paystackBankCode
            ];
        }

        return "";
    }
}