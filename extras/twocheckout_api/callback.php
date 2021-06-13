<?php

// callback
require('zen_tco.php');
include( DIR_WS_CLASSES . 'order.php' );
include( DIR_WS_CLASSES . 'shipping.php' );
include( DIR_WS_CLASSES . 'order_total.php' );
require( DIR_WS_CLASSES . 'payment.php' );
global $db;

$order            = new order;
$payment_modules  = new payment( $_SESSION['payment'] );
$shipping_modules = new shipping( $_SESSION['shipping'] );
$order_total_modules  = new order_total;
$zen_tco = new zen_tco($db, $order, $payment_modules, $shipping_modules, $order_total_modules);
$zen_tco->checkRedirect3DS();
$zen_tco->checkSuccessPayment();
$zen_tco->checkCart();
$zen_tco->checkHasProducts();
$zen_tco->setTcoApiPaymentMethod();
$order_params = $zen_tco->getOrderParams();
$api_response = $zen_tco->callApi($order_params);
die( json_encode($api_response) );