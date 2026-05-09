<?php
/**
 * Binance Pay Production Module for WHMCS
 */
if (!defined("WHMCS")) die("This file cannot be accessed directly");

function binancepay_config() {
    return array(
        'FriendlyName' => array('Type' => 'System', 'Value' => 'Binance Pay Live'),
        'apiKey'       => array('FriendlyName' => 'Merchant API Key', 'Type' => 'text', 'Size' => '64'),
        'secretKey'    => array('FriendlyName' => 'Merchant Secret Key', 'Type' => 'password', 'Size' => '64'),
    );
}

function binancepay_link($params) {
    // Live Production Endpoint
    $url = "https://bpay.binanceapi.com/binancepay/openapi/v2/order";

    $timestamp = round(microtime(true) * 1000);
    $nonce = substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789"), 0, 32);
    
    $data = array(
        "env" => array("terminalType" => "WEB"),
        "orderAmount" => number_format($params['amount'], 2, '.', ''),
        "orderCurrency" => $params['currency'],
        "merchantTradeNo" => "INV" . $params['invoiceid'] . "T" . time(),
        "goods" => array(
            "goodsType" => "01",
            "goodsCategory" => "6000",
            "referenceGoodsId" => (string)$params['invoiceid'],
            "goodsName" => "Invoice #" . $params['invoiceid']
        ),
        "returnUrl" => $params['returnurl'],
        "cancelUrl" => $params['returnurl']
    );

    $json_payload = json_encode($data);
    $payload = $timestamp . "\n" . $nonce . "\n" . $json_payload . "\n";
    $signature = strtoupper(hash_hmac('sha512', $payload, $params['secretKey']));

    $headers = array(
        "Content-Type: application/json",
        "BinancePay-Timestamp: " . $timestamp,
        "BinancePay-Nonce: " . $nonce,
        "BinancePay-Certificate-SN: " . $params['apiKey'],
        "BinancePay-Signature: " . $signature
    );

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Required for Production
    
    $response_raw = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    // LOGGING: Check WHMCS > Billing > Gateway Log for these details if it fails
    logModuleCall("binancepay", "create_order", $json_payload, $response_raw, $response_raw, array($params['apiKey']));

    if ($curl_error) {
        return '<div class="alert alert-danger">Network Error: ' . $curl_error . '</div>';
    }

    $response = json_decode($response_raw, true);

    if (isset($response['status']) && $response['status'] === 'SUCCESS') {
        return '<a href="' . $response['data']['checkoutUrl'] . '" class="btn btn-success" style="font-weight:bold;">Pay with Binance Pay</a>';
    } else {
        $errorMsg = isset($response['errorMessage']) ? $response['errorMessage'] : "HTTP Status: $http_code";
        // Displaying detailed error for debugging
        return '<div class="alert alert-warning">
                    <strong>Payment Unavailable:</strong> ' . $errorMsg . '<br>
                    <small>Please ensure your server IP is whitelisted in Binance Merchant Portal.</small>
                </div>';
    }
}