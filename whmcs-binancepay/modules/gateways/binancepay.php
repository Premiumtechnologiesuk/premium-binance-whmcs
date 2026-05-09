<?php
/**
 * Binance Pay WHMCS Gateway
 * 
 * @package    Premium Technologies Private Limited
 * @author     Premium Technologies <info@premiumtech.uk>
 * @copyright  Copyright (c) 2026 Premium Technologies Private Limited
 * @license    MIT License
 * @link       https://www.premiumtech.uk
 */

if (!defined("WHMCS")) die("This file cannot be accessed directly");

function binancepay_MetaData() {
    return array(
        'DisplayName' => 'Binance Pay by Premium Technologies',
        'APIVersion' => '1.1',
    );
}

function binancepay_config() {
    return array(
        'FriendlyName' => array('Type' => 'System', 'Value' => 'Binance Pay (Premium Tech)'),
        'apiKey'       => array('FriendlyName' => 'Binance API Key', 'Type' => 'text', 'Size' => '64'),
        'secretKey'    => array('FriendlyName' => 'Binance Secret Key', 'Type' => 'password', 'Size' => '64'),
    );
}

function binancepay_link($params) {
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
        "BinancePay-Timestamp: $timestamp",
        "BinancePay-Nonce: $nonce",
        "BinancePay-Certificate-SN: " . $params['apiKey'],
        "BinancePay-Signature: $signature"
    );

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
    
    $response_raw = curl_exec($ch);
    $response = json_decode($response_raw, true);
    curl_close($ch);

    if (isset($response['status']) && $response['status'] === 'SUCCESS') {
        return '<a href="' . $response['data']['checkoutUrl'] . '" class="btn btn-success" style="padding: 10px 20px;">Pay with Binance Pay</a>';
    } else {
        return '<div class="alert alert-danger">Error: ' . ($response['errorMessage'] ?? 'Connection Failed') . '</div>';
    }
}
