<?php
/**
 * Created by PhpStorm.
 * User: tomer ofer
 * Date: 05/01/2016
 * Time: 12:06
 */

$autoloader = require '../vendor/autoload.php';
$autoloader->add('App',__DIR__.'/../src/');

use TomerOfer\CreditGuard\CreditGuard;

$creditGuard = new CreditGuard("israeli","I!fr43s!34","0962832","938","https://cguat2.creditguard.co.il/xpo/Relay");

$creditGuard->setLanguage("Eng"); // default: Eng
$creditGuard->setSuccessUrl("https://your-domain.com/transactionSuccess");
$creditGuard->setErrorUrl("https://your-domain.com/transactionError");
$creditGuard->setCancelUrl("https://your-domain.com/transactionFailed");

// check if user credentials is valid
// $creditGuard->credentialsIsValid(); // return true or false
//  $response = $creditGuard->getRedirectUrlForToken(uniqid(),100);
$response = $creditGuard->makeTransactionWithToken("1074946602134580",uniqid(),200);
print_r($response);

// get the url to redirect the user
// $response = $creditGuard->getRedirectUrl(uniqid(),200,2); // return array
// print_r($response);

/*
 * Success response example:
Array
(
    "result" => 000,
    "url" => "https://cgmpiuat.creditguard.co.il//CGMPI_Server/PerformTransaction?txId=6ac533db-6b9d-4aa1-bf7a-465e021c324c",
    "token" => "6ac533db-6b9d-4aa1-bf7a-465e021c324c",
    "uniqueid" => "568bd169b7907",
    "email" => ""
);

* Save those token and uniqid in your database and wen the user coming back to "successUrl" collect the data from CreditGuard
*/