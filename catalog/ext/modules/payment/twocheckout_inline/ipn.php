<?php

if (strtoupper($_SERVER['REQUEST_METHOD']) !== 'POST') {
    exit('Not allowed');
}

chdir('../../../../');
require_once('includes/application_top.php');
require_once(DIR_WS_MODULES . 'payment/twocheckout_inline/twocheckout.php');
$helper = new twocheckout();

$secret_key = MODULE_PAYMENT_2CHECKOUT_INLINE_SECRET_KEY;
$params = $_POST;
$order = tep_db_fetch_array(tep_db_query('SELECT * FROM orders WHERE orders_id = ' . $params['REFNOEXT']));

//        ignore all other payment methods
if ($order && $order['payment_method'] === '2Checkout Inline') {

    if (!isset($params['REFNOEXT']) || (!isset($params['REFNO']) || empty($params['REFNO']))) {
        throw new Exception(sprintf('Cannot identify order: "%s".', $params['REFNOEXT']));
    }
    if (!$helper->isIpnResponseValid($params, $secret_key)) {
        throw new Exception(sprintf('MD5 hash mismatch for 2Checkout IPN with date: "%s".', $params['IPN_DATE']));
    }

    $helper->processOrderStatus($params);

    echo $helper->calculateIpnResponse($params, $secret_key);
    require('includes/application_bottom.php');
}

