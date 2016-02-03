<?php
/**
 * Created by PhpStorm.
 * User: tomer ofer
 * Date: 05/01/2016
 * Time: 11:56
 */

namespace TomerOfer\CreditGuard;


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
    private $mpiValidation = "Normal"; //Normal, Token, Verify, AutoComm, AutoCommHold, CardNo

    // ISO-4217 currencies
    public static $CURRENCY_USD = "USD";
    public static $CURRENCY_ILS = "ILS";
    public static $CURRENCY_EUG = "EUG";
    public static $CURRENCY_GBP = "GBP";
    public static $CURRENCY_JPY = "JPY";

    // mpiValidation options
    public static $MPI_VALIDATION_NORMAL = "Normal";
    public static $MPI_VALIDATION_TOKEN = "Token";
    public static $MPI_VALIDATION_VERIFY = "Verify";
    public static $MPI_VALIDATION_AUTOCOMM = "AutoComm";
    public static $MPI_VALIDATION_AUTOCOMMHOLD = "AutoCommHold";
    public static $MPI_VALIDATION_CARDNO = "CardNo";

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
        $result = ["result" => ""];
        $request_string = 'user='.$this->user_name.'&password='.$this->password.'&int_in='.$dataXml;
        file_put_contents("creditGuard.log",$request_string);
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
                $responseXml = simplexml_load_string($curl_result);
            }catch(\Exception $e){
                $responseXml = false;
                $result["responseParsingException"] = $e->getMessage();
            }
            if($responseXml !== false){
                if(isset($responseXml->response->result)){
                    if($responseXml->response->result == "000"){
                        $result["result"] = (string)$responseXml->response->result;
                        if(isset($responseXml->response->doDeal->mpiHostedPageUrl)){
                            $result["url"] = (string)$responseXml->response->doDeal->mpiHostedPageUrl;
                        }
                        if(isset($responseXml->response->doDeal->token)){
                            $result["token"] = (string)$responseXml->response->doDeal->token;
                        }
                        if(isset($responseXml->response->doDeal->token)){
                            $result["token"] = (string)$responseXml->response->doDeal->token;
                        }
                        if(isset($responseXml->response->doDeal->uniqueid)){
                            $result["uniqueid"] = (string)$responseXml->response->doDeal->uniqueid;
                        }
                        if(isset($responseXml->response->doDeal->email)){
                            $result["email"] = (string)$responseXml->response->doDeal->email;
                        }
                    }else{
                        $result["result"] = (string)$responseXml->response->result;
                        $result["message"] = (string)$responseXml->response->userMessage;
                        $result["additionalInfo"] = (string)$responseXml->response->additionalInfo;
                    }
                }
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

        // build the xml for the request
        $dataXml = <<<XML
<ashrait>
    <request>
        <command>doDeal</command>
        <requestId/>
        <version>1001</version>
        <language>{$this->language}</language>
        <doDeal>
            <successUrl>{$this->successUrl}</successUrl>
            <errorUrl>{$this->errorUrl}</errorUrl>
            <cancelUrl>{$this->errorUrl}</cancelUrl>
            <terminalNumber>{$this->terminal}</terminalNumber>
            <cardNo>CGMPI</cardNo>
            <creditType>{$this->creditType}</creditType>
            <currency>{$this->currency}</currency>
            <transactionCode>Phone</transactionCode>
            <transactionType>Debit</transactionType>
            <cardId></cardId>
            <total>{$total}</total>
            <validation>TxnSetup</validation>
            <user></user>
            <mainTerminalNumber></mainTerminalNumber>
            <authNumber></authNumber>
            <numberOfPayments>{$payments}</numberOfPayments>
            <firstPayment></firstPayment>
            <periodicalPayment></periodicalPayment>
            <dealerNumber></dealerNumber>
            <mid>{$this->mid}</mid>
            <uniqueid>$uniqueId</uniqueid>
            <mpiValidation>{$this->mpiValidation}</mpiValidation>
            <description></description>
            <email></email>
            <clientIP></clientIP>
            <saleDetailsMAC></saleDetailsMAC>
            <customerData>
                <userData1/>
                <userData2/>
                <userData3/>
                <userData4/>
                <userData5/>
                <userData6/>
                <userData7/>
                <userData8/>
                <userData9/>
                <userData10/>
            </customerData>
        </doDeal>
    </request>
</ashrait>
XML;
        // build the request string with the username, password and the xml
        return $this->makeRequest($dataXml);
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