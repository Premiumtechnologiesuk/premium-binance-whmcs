<?php
/**
 * Binance Pay Callback Handler
 */
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

$gatewayModuleName = "binancepay";
$gatewayParams = getGatewayVariables($gatewayModuleName);

if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

$rawPayload = file_get_contents('php://input');
$headers = array_change_key_case(getallheaders(), CASE_LOWER);

$bSignature = isset($headers['binancepay-signature']) ? $headers['binancepay-signature'] : '';
$bTimestamp = isset($headers['binancepay-timestamp']) ? $headers['binancepay-timestamp'] : '';
$bNonce     = isset($headers['binancepay-nonce']) ? $headers['binancepay-nonce'] : '';

// Verify Webhook Signature
$payloadToVerify = $bTimestamp . "\n" . $bNonce . "\n" . $rawPayload . "\n";
$expectedSignature = strtoupper(hash_hmac('sha512', $payloadToVerify, $gatewayParams['secretKey']));

if ($bSignature !== $expectedSignature) {
    logTransaction($gatewayParams['name'], $rawPayload, "Invalid Webhook Signature");
    exit("Signature mismatch");
}

$data = json_decode($rawPayload, true);
if ($data['bizType'] == 'PAY' && $data['bizStatus'] == 'PAY_SUCCESS') {
    $bizData = json_decode($data['bizData'], true);
    
    // Extract Invoice ID
    $tradeNo = $bizData['merchantTradeNo'];
    $invoiceId = preg_replace("/[^0-9]/", "", explode('T', $tradeNo)[0]);

    $invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);
    checkCbTransID($bizData['tradeGuid']); 

    addInvoicePayment(
        $invoiceId,
        $bizData['tradeGuid'],
        $bizData['orderAmount'],
        0,
        $gatewayModuleName
    );

    logTransaction($gatewayParams['name'], $data, "Payment Successful");
    echo json_encode(array("returnCode" => "SUCCESS", "returnMessage" => null));
}