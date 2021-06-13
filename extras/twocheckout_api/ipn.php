<?php

if (strtoupper($_SERVER['REQUEST_METHOD']) !== 'POST') {
    exit('Not allowed');
}
chdir('../..');
require('includes/application_top.php');
require_once( DIR_WS_MODULES . 'payment/twocheckout/twocheckout_api_helper.php' );
$helper = new twocheckout_api_helper();
$params = $_POST;

if (!isset($params['REFNOEXT']) || (!isset($params['REFNO']) || empty($params['REFNO']))) {
    throw new Exception(sprintf('Cannot identify order: "%s".', $params['REFNOEXT']));
}
// ignore all other payment methods
if ($helper->is_2payjs_order($params['REFNO'])) {
    if (!$helper->isIpnResponseValid($params)) {
        throw new Exception(sprintf('MD5 hash mismatch for 2Checkout IPN with date: "%s".', $params['IPN_DATE']));
    }
    $helper->processOrderStatus($params);
    echo $helper->calculateIpnResponse($params); 
}

