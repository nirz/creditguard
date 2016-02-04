<?php
/**
 * Created by PhpStorm.
 * User: tomer ofer
 * Date: 05/01/2016
 * Time: 11:56
 */

namespace TomerOfer\CreditGuard;
use SoapBox\Formatter\Formatter;

class CreditGuard
{
    private $user_name;
    private $password;
    private $terminal;
    private $mid;
    private $gateway;
    private $language; // Eng, Heb
    private $currency; // ILS, USD
    private $successUrl = "";
    private $errorUrl = "";
    private $cancelUrl = "";
    private $creditType = "RegularCredit"; //RegularCredit, Payments, IsraCredit, SpecialCredit, SpecialAlpha, PaymentsClub
    private $uniqueId = "";
    private $validation = "AutoComm"; //Normal, Token, Verify, AutoComm, AutoCommHold, CardNo
    private $mpiValidation = "Normal"; //Normal, Token, Verify, AutoComm, AutoCommHold, CardNo
    private $cardOwnerId = "123456789"; //Israel Credit Cards Require owner id

    // ISO-4217 currencies
    public static $CURRENCY_USD = "USD";
    public static $CURRENCY_ILS = "ILS";
    public static $CURRENCY_EUG = "EUG";
    public static $CURRENCY_GBP = "GBP";
    public static $CURRENCY_JPY = "JPY";

    // mpiValidation options
    public static $VALIDATION_NORMAL = "Normal";
    // only getting the token for later transactions
    public static $VALIDATION_TOKEN = "Token";
    // getting the token and holding funds for later transaction
    public static $VALIDATION_VERIFY = "Verify";
    public static $VALIDATION_AUTOCOMM = "AutoComm";
    public static $VALIDATION_AUTOCOMMHOLD = "AutoCommHold";
    public static $VALIDATION_CARDNO = "CardNo";

    /**
     * CreditGuard constructor.
     * @param String $user_name
     * @param String $password
     * @param String $terminal
     * @param String $mid
     * @param String $gateway
     * @param String $language (Eng|Heb)
     * @param String $currency (ILS|USD)
     *
     */
    public function __construct($user_name = "",$password = "",$terminal = "",$mid = "",$gateway = "",$language = "Eng",$currency = "ILS")
    {
        $this->user_name = $user_name;
        $this->password = $password;
        $this->terminal = $terminal;
        $this->mid = $mid;
        $this->gateway = $gateway;
        $this->language = $language;
        $this->currency = $currency;
    }

    /**
     * @param string $dataXml
     * @return array
     */
    private function makeRequest($dataXml = ""){
        // clean the xml from header and root
        $dataXml = str_replace('<?xml version="1.0" encoding="utf-8"?>','',str_replace('<xml>','',str_replace('</xml>','',$dataXml)));
        $result = ["result" => ""];
        $request_string = 'user='.$this->user_name.'&password='.$this->password.'&int_in='.$dataXml;
        $CR = curl_init();
        curl_setopt($CR, CURLOPT_URL, $this->gateway);
        curl_setopt($CR, CURLOPT_POST, 1);
        curl_setopt($CR, CURLOPT_FAILONERROR, true);
        curl_setopt($CR, CURLOPT_POSTFIELDS, $request_string);
        curl_setopt($CR, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($CR, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($CR, CURLOPT_FAILONERROR,true);
        $curl_result = curl_exec( $CR );
        $error = curl_error ( $CR );
        if( !empty( $error ) ){
            $result["message"] = $error;
        }else{
            if(stripos($curl_result,'HEB') !== false){
                $curl_result = iconv("utf-8", "iso-8859-8", $curl_result);
            }
            try{
                $formatter = Formatter::make($curl_result,Formatter::XML);
                $response = $formatter->toArray();
                if(isset($response["response"])){
                    $result = $response["response"];
                }else{
                    $result["responseFormatError"] = "no response root in XML";
                }
            }catch(\Exception $e){
                $responseXml = false;
                $result["responseParsingException"] = $e->getMessage();
            }
        }
        curl_close( $CR );
        return $result;
    }

    /**
     * @param String $uniqueId
     * @param Float $total
     * @param int $payments
     * @return array
     */
    public function getRedirectUrl($uniqueId = "", $total = 1.0, $payments = 0){
        if(empty($uniqueId)){
            $uniqueId = uniqid();
        }
        if(is_numeric($payments) && $payments > 1){
            $this->creditType = "Payments";
        }

        // total need to be in Agorot (or cents in USD or EUR)
        $total = $total * 100;

        $dataArray = [
            "ashrait" => [
                "request" => [
                    "command" => "doDeal",
                    "requestId" => "",
                    "version" => "1001",
                    "language" => $this->language,
                    "doDeal" => [
                        "successUrl" => $this->successUrl,
                        "errorUrl" => $this->errorUrl,
                        "cancelUrl" => $this->errorUrl,
                        "terminalNumber" => $this->terminal,
                        "cardNo" => "CGMPI",
                        "creditType" => $this->creditType,
                        "currency" => $this->currency,
                        "transactionCode" => "Phone",
                        "transactionType" => "Debit",
                        "cardId" => "",
                        "total" => $total,
                        "validation" => "TxnSetup",
                        "user" => "",
                        "mainTerminalNumber" => "",
                        "authNumber" => "",
                        "numberOfPayments" => $payments,
                        "firstPayment" => "",
                        "periodicalPayment" => "",
                        "dealerNumber" => "",
                        "mid" => $this->mid,
                        "uniqueid" => $uniqueId,
                        "mpiValidation" => $this->mpiValidation,
                        "description" => "",
                        "email" => "",
                        "clientIP" => "",
                        "saleDetailsMAC" => "",
                        "customerData" => [
                            "userData1" => "",
                            "userData2" => "",
                            "userData3" => "",
                            "userData4" => "",
                            "userData5" => "",
                            "userData6" => "",
                            "userData7" => "",
                            "userData8" => "",
                            "userData9" => "",
                            "userData10" => ""
                        ]
                    ]
                ]
            ]
        ];

        // build the xml for the request
        $formatter = Formatter::make($dataArray,Formatter::ARR);
        // build the request string with the username, password and the xml
        return $this->makeRequest($formatter->toXml());
    }

    public function getRedirectUrlForToken($uniqueId = "", $total = 1.0){
        $this->mpiValidation = self::$VALIDATION_TOKEN;
        return $this->getRedirectUrl($uniqueId, $total);
    }

    public function getRedirectUrlForTokenWithVerify($uniqueId = "", $total = 1.0){
        $this->mpiValidation = self::$VALIDATION_VERIFY;
        return $this->getRedirectUrl($uniqueId, $total);
    }

    public function makeTransactionWithToken($token = null,$cardExpiration = "", $uniqueId = "", $total = 1.0){
        if(is_null($token)){
            return ['response' => 'invalid token'];
        }
        if(empty($uniqueId)){
            $uniqueId = uniqid();
        }

        // total need to be in Agorot (or cents in USD or EUR)
        $total = $total * 100;

        $dataArray = [
            "ashrait" => [
                "request" => [
                    "command" => "doDeal",
                    "requestId" => "",
                    "dateTime" => date('Y-m-d H:i:s'),
                    "version" => "1001",
                    "language" => $this->language,
                    "mayBeDuplicate" => 0,
                    "doDeal" => [
                        "terminalNumber" => $this->terminal,
                        "cardNo" => $token,
                        "cardExpiration" => $cardExpiration,
                        "id" => $this->cardOwnerId,
                        "creditType" => $this->creditType,
                        "currency" => $this->currency,
                        "transactionCode" => "Phone",
                        "transactionType" => "Debit",
                        "total" => $total,
                        "validation" => $this->validation,
                        "user" => $uniqueId,
                    ]
                ]
            ]
        ];
        // build the xml for the request
        $formatter = Formatter::make($dataArray,Formatter::ARR);
        // build the request string with the username, password and the xml
        return $this->makeRequest($formatter->toXml());
    }

    /**
     * @return bool
     */
    public function credentialsIsValid(){
        $response = $this->getRedirectUrl();
        if(empty($response["result"])){
            return false;
        }else{
            if($response["result"] == "000"){
                return true;
            }
            return false;
        }
    }

    /**
     * @param string $user_name
     */
    public function setUserName($user_name)
    {
        $this->user_name = $user_name;
    }

    /**
     * @param string $password
     */
    public function setPassword($password)
    {
        $this->password = $password;
    }

    /**
     * @param string $terminal
     */
    public function setTerminal($terminal)
    {
        $this->terminal = $terminal;
    }

    /**
     * @param string $mid
     */
    public function setMid($mid)
    {
        $this->mid = $mid;
    }

    /**
     * @param string $gateway
     */
    public function setGateway($gateway)
    {
        $this->gateway = $gateway;
    }

    /**
     * @param string $language
     */
    public function setLanguage($language)
    {
        $this->language = $language;
    }

    /**
     * @param string $currency
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
    }

    /**
     * @param string $successUrl
     */
    public function setSuccessUrl($successUrl)
    {
        $this->successUrl = $successUrl;
    }

    /**
     * @param string $errorUrl
     */
    public function setErrorUrl($errorUrl)
    {
        $this->errorUrl = $errorUrl;
    }

    /**
     * @param string $cancelUrl
     */
    public function setCancelUrl($cancelUrl)
    {
        $this->cancelUrl = $cancelUrl;
    }

    /**
     * @param string $uniqueId
     */
    public function setUniqueId($uniqueId)
    {
        $this->uniqueId = $uniqueId;
    }

    public function setMpiValidation($mpiValidation){
        $this->mpiValidation = $mpiValidation;
    }
}