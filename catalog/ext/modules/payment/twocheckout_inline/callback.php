<?php

$params = $_GET;
// callback
if (isset($params['refno']) && !empty($params['refno'])) {
    chdir('../../../../');
    require_once('includes/application_top.php');
    require_once(DIR_WS_MODULES . 'payment/twocheckout_inline/twocheckout.php');
    $helper = new twocheckout();
    $apiResponse = $helper->call(
        'orders/' . $params['refno'],
        MODULE_PAYMENT_2CHECKOUT_INLINE_SELLER_ID,
        MODULE_PAYMENT_2CHECKOUT_INLINE_SECRET_KEY
    );
    global $cart_2Checkout_Inline_ID, $cart;
    $order_id = substr($cart_2Checkout_Inline_ID, strpos($cart_2Checkout_Inline_ID, '-') + 1);

    $status = (int)DEFAULT_ORDERS_STATUS_ID; //pending
    if (in_array($apiResponse['Status'], ['COMPLETE', 'AUTHRECEIVED'])) {
        $status = (int)MODULE_PAYMENT_2CHECKOUT_INLINE_ORDER_STATUS_ID;
    }
    $helper->updateOrderHistory(
        $order_id,
        $status,
        '2checkout transaction ID: ' . $params['refno']
    );

    $cart->reset(true);

    // unregister session variables used during checkout
    tep_session_unregister('sendto');
    tep_session_unregister('billto');
    tep_session_unregister('shipping');
    tep_session_unregister('payment');
    tep_session_unregister('comments');

    tep_session_unregister('cart_2Checkout_Inline_ID');
    tep_redirect(tep_href_link(FILENAME_CHECKOUT_SUCCESS, '', 'SSL'));

}

require('includes/application_bottom.php');
