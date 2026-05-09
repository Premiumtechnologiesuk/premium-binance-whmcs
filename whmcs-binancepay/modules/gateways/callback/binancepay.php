<?php
/**
 * Binance Pay WHMCS Callback
 * 
 * @package    Premium Technologies Private Limited
 * @link       https://www.premiumtech.uk
 */

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

$gatewayModuleName = "binancepay";
$gatewayParams = getGatewayVariables($gatewayModuleName);

if (!$gatewayParams['type']) die("Module Not Activated");

$raw_payload = file_get_contents('php://input');
$data = json_decode($raw_payload, true);

if ($data['bizStatus'] === 'PAY_SUCCESS') {
    $invoiceId = str_replace('Invoice #', '', $data['data']['goods']['goodsName']);
    $transactionId = $data['data']['transactionId'];
    $paymentAmount = $data['data']['orderAmount'];

    $invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);
    checkCbTransID($transactionId);
    addInvoicePayment($invoiceId, $transactionId, $paymentAmount, 0, $gatewayModuleName);
    
    logTransaction($gatewayParams['name'], $raw_payload, "Success");
    echo "SUCCESS";
} else {
    logTransaction($gatewayParams['name'], $raw_payload, "Failed or Pending");
    echo "FAIL";
}
