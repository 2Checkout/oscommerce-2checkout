<?php

function format_raw($number, $currency_code = '', $currency_value = '')
{
	global $currencies, $currency;

	if (empty($currency_code) || !$currencies->is_set($currency_code)) {
		$currency_code = $currency;
	}

	if (empty($currency_value) || !is_numeric($currency_value)) {
		$currency_value = $currencies->currencies[$currency_code]['value'];
	}

	return number_format(tep_round($number * $currency_value, $currencies->currencies[$currency_code]['decimal_places']), $currencies->currencies[$currency_code]['decimal_places'], '.', '');
}

 function updateOrderHistory( $order_id, $status, $comment ) {
	tep_db_query( "update " . TABLE_ORDERS . " set orders_status = " . (int) $status . ", last_modified = now()  where orders_id = " . (int) $order_id );
	tep_db_perform( TABLE_ORDERS_STATUS_HISTORY,
		[
			'orders_id'         => (int)$order_id,
			'orders_status_id'  => (int)$status,
			'date_added'        => 'now()',
			'customer_notified' => ( SEND_EMAILS == 'true' ) ? '1' : '0',
			'comments'          => $comment
		] );
}

// callback
    chdir('../../../../');
    require('includes/application_top.php');
    require_once(DIR_WS_MODULES . 'payment/twocheckout_2payjs/twocheckout_2payjs_library.php');
    $helper = new twocheckout_2payjs_library();
	global $languages_id, $cart_pm2checkout_2payjs_id, $cart;
	include(DIR_WS_CLASSES . 'order.php');
	if( isset($_REQUEST['threeds'])){
		tep_redirect(tep_href_link(FILENAME_SHOPPING_CART));
	}
	if(isset($_REQUEST['payment_success'])){
		// unregister session variables used during checkout
		tep_session_unregister('sendto');
		tep_session_unregister('billto');
		tep_session_unregister('shipping');
		tep_session_unregister('payment');
		tep_session_unregister('comments');
		tep_session_unregister('cart_pm2checkout_2payjs_id');
		$cart->reset(true);
		tep_redirect(tep_href_link(FILENAME_CHECKOUT_SUCCESS, '', 'SSL'));
	}

	$quotes_array = array();
	$order = new order;
	$lineitem_total = $order->info['total'];

	if ($cart->get_content_type() != 'virtual') {
		$total_weight = $cart->show_weight();
		include(DIR_WS_CLASSES . 'shipping.php');
		$shipping_modules = new shipping;

		if ( (tep_count_shipping_modules() > 0) ) {
		// get all available shipping quotes
				$quotes = $shipping_modules->quote();
				foreach ($quotes as $quote) {
					if (!isset($quote['error'])) {
						foreach ($quote['methods'] as $rate) {
							$quotes_array[] = array('id' => $quote['id'] . '_' . $rate['id'],
							                        'name' => $quote['module'],
							                        'label' => $rate['title'],
							                        'cost' => $rate['cost'],
							                        'tax' => isset($quote['tax']) ? $quote['tax'] : '0');
						}
					}
				}
		}
	} else {
		$quotes_array[] = array('id' => 'null',
		                        'name' => 'No Shipping',
		                        'label' => '',
		                        'cost' => '0',
		                        'tax' => '0');
	}

	include(DIR_WS_CLASSES . 'order_total.php');
	$order_total_modules = new order_total;
	$order_totals = $order_total_modules->process();
	$items_total = format_raw($order->info['subtotal']);
	foreach ($order_totals as $ot) {
		if ( $ot['code'] == 'ot_total') {
			$lineitem_total = $ot['value'];
		}
	}

    $order_id = substr($cart_pm2checkout_2payjs_id, strpos($cart_pm2checkout_2payjs_id, '-') + 1);
	$lang_query = tep_db_query("select code from " . TABLE_LANGUAGES . " where languages_id = '" . (int)$languages_id . "'");
	$lang = tep_db_fetch_array($lang_query);
	$type  =  ( MODULE_PAYMENT_2CHECKOUT_2PAYJS_TESTMODE == 'Test') ? 'TEST' : 'EES_TOKEN_PAYMENT';
	$token = $_REQUEST['ess_token'];
	$success_url =  HTTPS_SERVER . DIR_WS_HTTPS_CATALOG.'ext/modules/payment/twocheckout_2payjs/callback.php?payment_success=1';

	$order_params = [
		'Currency'          => $order->info['currency'],
		'Language'          => strtolower(substr(strtoupper($lang['code']), 0, 2)),
		'Country'           => $order->billing['country']['iso_code_2'],
		'CustomerIP'        => $helper->getCustomerIp(),
		'Source'            => 'OSCOMMERCE_'.substr(str_replace('.','_', tep_get_version()),0,3),
		'ExternalReference' => $order_id,
		'Items'             => $helper->getItem($order_id, format_raw($lineitem_total)),
		'BillingDetails'    => $helper->getBillingDetails($order),
		'PaymentDetails'    => $helper->getPaymentDetails($type, $token, $order->info['currency']),
	];

	try {
		$api_response = $helper->call($order_params);
		if (!$api_response || isset($api_response['error_code']) && !empty($api_response['error_code'])) { // we dont get any response from 2co or internal account related error
			$error_message = 'There has been an error processing your credit card. Please try again.';
			if ($api_response && isset($api_response['message']) && !empty($api_response['message'])) {
				$error_message = $api_response['message'];
			}
			$json_response = ['success' => false, 'messages' => $error_message, 'redirect' => null];
		} else {
			if ($api_response['Errors']) { // errors that must be shown to the client
				$error_message = '';
				foreach ($api_response['Errors'] as $key => $value) {
					$error_message .= $value . PHP_EOL;
				}
				$json_response = ['success' => false, 'messages' => $error_message, 'redirect' => null];
			} else {
				$has3ds = null;
				if (isset($api_response['PaymentDetails']['PaymentMethod']['Authorize3DS'])) {
					$has3ds = $helper->hasAuthorize3DS($api_response['PaymentDetails']['PaymentMethod']['Authorize3DS']);
				}
				if ($has3ds) {
					updateOrderHistory( $order_id, MODULE_PAYMENT_2CHECKOUT_2PAYJS_ORDER_STATUS_ID, '2Checkout transaction ID: ' . $api_response["RefNo"] );
					$redirect_url = $has3ds;
					$json_response = [
						'success'  => true,
						'messages' => '3dSecure Redirect',
						'redirect' => $redirect_url
					];
				} else {
					updateOrderHistory( $order_id, MODULE_PAYMENT_2CHECKOUT_2PAYJS_ORDER_STATUS_ID, '2Checkout transaction ID: ' . $api_response["RefNo"] );
					$json_response = [
						'success'  => true,
						'messages' => 'Order payment success',
						'redirect' => $success_url
					];
				}
			}
		}
	} catch (Exception $e) {

		$json_response = [
			'success'  => false,
			'messages' => $e->getMessage(),
			'redirect' => null
		];
	}
	die(json_encode($json_response));