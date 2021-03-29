<?php

class twocheckout_inline
{
	var $code, $title, $description, $enabled;

// class constructor
    function twocheckout_inline()
    {
        global $order;

        $this->signature = '2checkout|twocheckout_inline|2.0|2.2';

        $this->code = 'twocheckout_inline';
        $this->title = MODULE_PAYMENT_2CHECKOUT_INLINE_TEXT_TITLE;
        $this->public_title = MODULE_PAYMENT_2CHECKOUT_INLINE_TEXT_PUBLIC_TITLE;
        $this->description = MODULE_PAYMENT_2CHECKOUT_INLINE_TEXT_DESCRIPTION;
        $this->sort_order = MODULE_PAYMENT_2CHECKOUT_INLINE_SORT_ORDER;
        $this->enabled = ((MODULE_PAYMENT_2CHECKOUT_INLINE_STATUS == 'True') ? true : false);

        if ((int)MODULE_PAYMENT_2CHECKOUT_INLINE_ORDER_STATUS_ID > 0) {
            $this->order_status = MODULE_PAYMENT_2CHECKOUT_INLINE_ORDER_STATUS_ID;
        }

        if (is_object($order)) {
            $this->update_status();
        }

        $this->form_action_url = tep_href_link('ext/modules/payment/twocheckout_inline/inline.php', '', 'SSL', true);
    }

    function update_status()
    {

        global $order;

        if (($this->enabled == true) && ((int)MODULE_PAYMENT_2CHECKOUT_INLINE_ZONE > 0)) {
            $check_flag = false;
            $check_query = tep_db_query("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_2CHECKOUT_INLINE_ZONE . "' and zone_country_id = '" . $order->billing['country']['id'] . "' order by zone_id");
            while ($check = tep_db_fetch_array($check_query)) {
                if ($check['zone_id'] < 1) {
                    $check_flag = true;
                    break;
                } elseif ($check['zone_id'] == $order->billing['zone_id']) {
                    $check_flag = true;
                    break;
                }
            }

            if ($check_flag == false) {
                $this->enabled = false;
            }
        }
    }

    function javascript_validation()
    {
        return false;
    }

    function selection()
    {

        global $cart_2Checkout_Inline_ID;

        if (tep_session_is_registered('cart_2Checkout_Inline_ID')) {
            $order_id = substr($cart_2Checkout_Inline_ID, strpos($cart_2Checkout_Inline_ID, '-') + 1);

            $check_query = tep_db_query('select orders_id from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id = "' . (int)$order_id . '" limit 1');

            if (tep_db_num_rows($check_query) < 1) {
                tep_db_query('delete from ' . TABLE_ORDERS . ' where orders_id = "' . (int)$order_id . '"');
                tep_db_query('delete from ' . TABLE_ORDERS_TOTAL . ' where orders_id = "' . (int)$order_id . '"');
                tep_db_query('delete from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id = "' . (int)$order_id . '"');
                tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS . ' where orders_id = "' . (int)$order_id . '"');
                tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . ' where orders_id = "' . (int)$order_id . '"');
                tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS_DOWNLOAD . ' where orders_id = "' . (int)$order_id . '"');

                tep_session_unregister('cart_2Checkout_Inline_ID');
            }
        }

        return [
            'id'     => $this->code,
            'module' => $this->public_title
        ];
    }

    function pre_confirmation_check()
    {
        global $cartID, $cart, $order;

        if (empty($cart->cartID)) {
            $cartID = $cart->cartID = $cart->generate_cart_id();
        }

        if (!tep_session_is_registered('cartID')) {
            tep_session_register('cartID');
        }

        $order->info['payment_method_raw'] = $order->info['payment_method'];
        $order->info['payment_method'] = '2Checkout Inline';
    }

    function confirmation()
    {
        global $cartID, $cart_2Checkout_Inline_ID, $customer_id, $languages_id, $order, $order_total_modules;

        if (tep_session_is_registered('cartID')) {
            $insert_order = false;

            if (tep_session_is_registered('cart_2Checkout_Inline_ID')) {
                $order_id = substr($cart_2Checkout_Inline_ID, strpos($cart_2Checkout_Inline_ID, '-') + 1);
                $curr_check = tep_db_query("select currency from " . TABLE_ORDERS . " where orders_id = '" . (int)$order_id . "'");
                $curr = tep_db_fetch_array($curr_check);
                if (($curr['currency'] != $order->info['currency']) || ($cartID != substr($cart_2Checkout_Inline_ID, 0, strlen($cartID)))) {
                    $check_query = tep_db_query('select orders_id from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id = "' . (int)$order_id . '" limit 1');
                    if (tep_db_num_rows($check_query) < 1) {
                        tep_db_query('delete from ' . TABLE_ORDERS . ' where orders_id = "' . (int)$order_id . '"');
                        tep_db_query('delete from ' . TABLE_ORDERS_TOTAL . ' where orders_id = "' . (int)$order_id . '"');
                        tep_db_query('delete from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id = "' . (int)$order_id . '"');
                        tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS . ' where orders_id = "' . (int)$order_id . '"');
                        tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . ' where orders_id = "' . (int)$order_id . '"');
                        tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS_DOWNLOAD . ' where orders_id = "' . (int)$order_id . '"');
                    }
                    $insert_order = true;
                }
            } else {
                $insert_order = true;
            }
            if ($insert_order == true) {
                $order_totals = [];
                if (is_array($order_total_modules->modules)) {
                    foreach ($order_total_modules->modules as $value) {
                        $class = substr($value, 0, strrpos($value, '.'));
                        if ($GLOBALS[$class]->enabled) {
                            for ($i = 0, $n = sizeof($GLOBALS[$class]->output); $i < $n; $i++) {
                                if (tep_not_null($GLOBALS[$class]->output[$i]['title']) && tep_not_null($GLOBALS[$class]->output[$i]['text'])) {
                                    $order_totals[] = [
                                        'code'       => $GLOBALS[$class]->code,
                                        'title'      => $GLOBALS[$class]->output[$i]['title'],
                                        'text'       => $GLOBALS[$class]->output[$i]['text'],
                                        'value'      => $GLOBALS[$class]->output[$i]['value'],
                                        'sort_order' => $GLOBALS[$class]->sort_order
                                    ];
                                }
                            }
                        }
                    }
                }
                if (isset($order->info['payment_method_raw'])) {
                    $order->info['payment_method'] = $order->info['payment_method_raw'];
                    unset($order->info['payment_method_raw']);
                }
                $sql_data_array = [
                    'customers_id'                => $customer_id,
                    'customers_name'              => $order->customer['firstname'] . ' ' . $order->customer['lastname'],
                    'customers_company'           => $order->customer['company'],
                    'customers_street_address'    => $order->customer['street_address'],
                    'customers_suburb'            => $order->customer['suburb'],
                    'customers_city'              => $order->customer['city'],
                    'customers_postcode'          => $order->customer['postcode'],
                    'customers_state'             => $order->customer['state'],
                    'customers_country'           => $order->customer['country']['title'],
                    'customers_telephone'         => $order->customer['telephone'],
                    'customers_email_address'     => $order->customer['email_address'],
                    'customers_address_format_id' => $order->customer['format_id'],
                    'delivery_name'               => $order->delivery['firstname'] . ' ' . $order->delivery['lastname'],
                    'delivery_company'            => $order->delivery['company'],
                    'delivery_street_address'     => $order->delivery['street_address'],
                    'delivery_suburb'             => $order->delivery['suburb'],
                    'delivery_city'               => $order->delivery['city'],
                    'delivery_postcode'           => $order->delivery['postcode'],
                    'delivery_state'              => $order->delivery['state'],
                    'delivery_country'            => $order->delivery['country']['title'],
                    'delivery_address_format_id'  => $order->delivery['format_id'],
                    'billing_name'                => $order->billing['firstname'] . ' ' . $order->billing['lastname'],
                    'billing_company'             => $order->billing['company'],
                    'billing_street_address'      => $order->billing['street_address'],
                    'billing_suburb'              => $order->billing['suburb'],
                    'billing_city'                => $order->billing['city'],
                    'billing_postcode'            => $order->billing['postcode'],
                    'billing_state'               => $order->billing['state'],
                    'billing_country'             => $order->billing['country']['title'],
                    'billing_address_format_id'   => $order->billing['format_id'],
                    'payment_method'              => $order->info['payment_method'],
                    'cc_type'                     => $order->info['cc_type'],
                    'cc_owner'                    => $order->info['cc_owner'],
                    'cc_number'                   => $order->info['cc_number'],
                    'cc_expires'                  => $order->info['cc_expires'],
                    'date_purchased'              => 'now()',
                    'orders_status'               => (int)DEFAULT_ORDERS_STATUS_ID,
                    'currency'                    => $order->info['currency'],
                    'currency_value'              => $order->info['currency_value']
                ];
                tep_db_perform(TABLE_ORDERS, $sql_data_array);
                $insert_id = tep_db_insert_id();
                for ($i = 0, $n = sizeof($order_totals); $i < $n; $i++) {
                    $sql_data_array = [
                        'orders_id'  => $insert_id,
                        'title'      => $order_totals[$i]['title'],
                        'text'       => $order_totals[$i]['text'],
                        'value'      => $order_totals[$i]['value'],
                        'class'      => $order_totals[$i]['code'],
                        'sort_order' => $order_totals[$i]['sort_order']
                    ];
                    tep_db_perform(TABLE_ORDERS_TOTAL, $sql_data_array);
                }
                for ($i = 0, $n = sizeof($order->products); $i < $n; $i++) {
                    $sql_data_array = [
                        'orders_id'         => $insert_id,
                        'products_id'       => tep_get_prid($order->products[$i]['id']),
                        'products_model'    => $order->products[$i]['model'],
                        'products_name'     => $order->products[$i]['name'],
                        'products_price'    => $order->products[$i]['price'],
                        'final_price'       => $order->products[$i]['final_price'],
                        'products_tax'      => $order->products[$i]['tax'],
                        'products_quantity' => $order->products[$i]['qty']
                    ];
                    tep_db_perform(TABLE_ORDERS_PRODUCTS, $sql_data_array);
                    $order_products_id = tep_db_insert_id();
                    if (isset($order->products[$i]['attributes'])) {
                        for ($j = 0, $n2 = sizeof($order->products[$i]['attributes']); $j < $n2; $j++) {
                            if (DOWNLOAD_ENABLED == 'true') {
                                $attributes_query = "select popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix, pad.products_attributes_maxdays, pad.products_attributes_maxcount , pad.products_attributes_filename
                                       from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " . TABLE_PRODUCTS_ATTRIBUTES . " pa
                                       left join " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad
                                       on pa.products_attributes_id=pad.products_attributes_id
                                       where pa.products_id = '" . $order->products[$i]['id'] . "'
                                       and pa.options_id = '" . $order->products[$i]['attributes'][$j]['option_id'] . "'
                                       and pa.options_id = popt.products_options_id
                                       and pa.options_values_id = '" . $order->products[$i]['attributes'][$j]['value_id'] . "'
                                       and pa.options_values_id = poval.products_options_values_id
                                       and popt.language_id = '" . $languages_id . "'
                                       and poval.language_id = '" . $languages_id . "'";
                                $attributes = tep_db_query($attributes_query);
                            } else {
                                $attributes = tep_db_query("select popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " . TABLE_PRODUCTS_ATTRIBUTES . " pa where pa.products_id = '" . $order->products[$i]['id'] . "' and pa.options_id = '" . $order->products[$i]['attributes'][$j]['option_id'] . "' and pa.options_id = popt.products_options_id and pa.options_values_id = '" . $order->products[$i]['attributes'][$j]['value_id'] . "' and pa.options_values_id = poval.products_options_values_id and popt.language_id = '" . $languages_id . "' and poval.language_id = '" . $languages_id . "'");
                            }
                            $attributes_values = tep_db_fetch_array($attributes);
                            $sql_data_array = [
                                'orders_id'               => $insert_id,
                                'orders_products_id'      => $order_products_id,
                                'products_options'        => $attributes_values['products_options_name'],
                                'products_options_values' => $attributes_values['products_options_values_name'],
                                'options_values_price'    => $attributes_values['options_values_price'],
                                'price_prefix'            => $attributes_values['price_prefix']
                            ];
                            tep_db_perform(TABLE_ORDERS_PRODUCTS_ATTRIBUTES, $sql_data_array);
                            if ((DOWNLOAD_ENABLED == 'true') && isset($attributes_values['products_attributes_filename']) && tep_not_null($attributes_values['products_attributes_filename'])) {
                                $sql_data_array = [
                                    'orders_id'                => $insert_id,
                                    'orders_products_id'       => $order_products_id,
                                    'orders_products_filename' => $attributes_values['products_attributes_filename'],
                                    'download_maxdays'         => $attributes_values['products_attributes_maxdays'],
                                    'download_count'           => $attributes_values['products_attributes_maxcount']
                                ];
                                tep_db_perform(TABLE_ORDERS_PRODUCTS_DOWNLOAD, $sql_data_array);
                            }
                        }
                    }
                }
                $cart_2Checkout_Inline_ID = $cartID . '-' . $insert_id;
                tep_session_register('cart_2Checkout_Inline_ID');
            }
        }

        return false;
    }

    function process_button()
    {
        global $order, $languages_id, $cart_2Checkout_Inline_ID;
        $order_id = substr($cart_2Checkout_Inline_ID, strpos($cart_2Checkout_Inline_ID, '-') + 1);

        $billingAddressData = [
            'name'         => $order->billing['firstname'] . ' ' . $order->billing['lastname'],
            'phone'        => str_replace(' ', '', $order->customer['telephone']),
            'country'      => $order->billing['country']['iso_code_2'],
            'state'        => $order->billing['state'] != '' ? $order->billing['state'] : 'XX',
            'email'        => $order->customer['email_address'],
            'address'      => $order->billing['street_address'],
            'address2'     => $order->billing['suburb'],
            'city'         => $order->billing['city'],
            'company-name' => $order->billing['company'],
            'zip'          => $order->billing['postcode'],
        ];

        $shippingAddressData = [
            'ship-name'     => $order->delivery['firstname'] . ' ' . $order->delivery['lastname'],
            'ship-country'  => $order->delivery['country']['iso_code_2'],
            'ship-state'    => $order->delivery['state'] != '' ? $order->delivery['state'] : 'XX',
            'ship-city'     => $order->delivery['city'],
            'ship-email'    => $order->customer['email_address'],
            'ship-phone'    => str_replace(' ', '', $order->customer['telephone']),
            'ship-address'  => $order->delivery['street_address'],
            'ship-address2' => !empty($order->delivery['suburb']) ? $order->delivery['suburb'] : '',
        ];


        $payload['products'][] = [
            'type'     => 'PRODUCT',
            'name'     => 'Cart_' . $order_id,
            'price'    => $this->format_raw($order->info['total']),
            'tangible' => 0,
            'qty'      => 1,
        ];

        $lang_query = tep_db_query("select code from " . TABLE_LANGUAGES . " where languages_id = '" . (int)$languages_id . "'");
        $lang = tep_db_fetch_array($lang_query);

        $payload['currency'] = strtoupper($order->info['currency']);
        $payload['language'] = strtoupper($lang['code']);
        $payload['return-method'] = [
            'type' => 'redirect',
            'url'  => tep_href_link('ext/modules/payment/twocheckout_inline/callback.php', '', 'SSL', true)
        ];

        $payload['test'] = MODULE_PAYMENT_2CHECKOUT_INLINE_TESTMODE === 'Test' ? 1 : 0;
        $payload['order-ext-ref'] = $order_id;
        $payload['customer-ext-ref'] = $order->customer['email_address'];
        $payload['src'] = 'OSCOMMERCE_2_3';
        $payload['mode'] = 'DYNAMIC';
        $payload['dynamic'] = '1';
        $payload['country'] = strtoupper($order->billing['country']['iso_code_2']);
        $payload['merchant'] = MODULE_PAYMENT_2CHECKOUT_INLINE_SELLER_ID;
        $payload['shipping_address'] = ($shippingAddressData);
        $payload['billing_address'] = ($billingAddressData);
        array_merge($payload, $billingAddressData);
        array_merge($payload, $billingAddressData);

        require_once(DIR_WS_MODULES . 'payment/twocheckout_inline/twocheckout.php');
        $helper = new twocheckout();

        $payload['signature'] = $helper->getInlineSignature(
            MODULE_PAYMENT_2CHECKOUT_INLINE_SELLER_ID,
            MODULE_PAYMENT_2CHECKOUT_INLINE_SECRET_WORD,
            $payload);

        $inputs = '';
        foreach ($payload as $name => $value) {
            if (!is_array($value)) {
                $inputs .= tep_draw_hidden_field($name, $value);
            }
        }
        foreach ($shippingAddressData as $name => $value) {
            $inputs .= tep_draw_hidden_field('shipping_address[' . $name . ']', $value);
        }
        foreach ($billingAddressData as $name => $value) {
            $inputs .= tep_draw_hidden_field('billing_address[' . $name . ']', $value);
        }
        foreach ($payload['return-method'] as $name => $value) {
            $inputs .= tep_draw_hidden_field('return-method[' . $name . ']', $value);
        }
        foreach ($payload['products'] as $value) {
            foreach ($value as $k => $v) {
                $inputs .= tep_draw_hidden_field('products[0][' . $k . ']', $v);
            }
        }

        return $inputs;
    }

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

    function before_process()
    {
        return false;
    }

    function after_process()
    {

        return false;
    }

    function get_error()
    {

        $error = [
            'title' => '',
            'error' => MODULE_PAYMENT_2CHECKOUT_INLINE_TEXT_ERROR_MESSAGE
        ];

        return $error;
    }

    function check()
    {

        if (!isset($this->_check)) {
            $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_2CHECKOUT_INLINE_STATUS'");
            $this->_check = tep_db_num_rows($check_query);
        }

        return $this->_check;
    }

    function install()
    {
	    $ipn_url =  HTTPS_SERVER . DIR_WS_HTTPS_CATALOG.'ext/modules/payment/twocheckout_inline/ipn.php';
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable 2CheckOut Inline Module', 'MODULE_PAYMENT_2CHECKOUT_INLINE_STATUS', 'True', 'Do you want to accept 2CheckOut Inline payments?', '6', '1', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('IPN url', 'MODULE_PAYMENT_2CHECKOUT_INLINE_IPN','$ipn_url', 'The callback endpoint for IPN requests from 2Checkout', '6', '2', 'tco_inline_edit_readonly_text( ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Seller ID', 'MODULE_PAYMENT_2CHECKOUT_INLINE_SELLER_ID', '', 'Seller ID used for the 2CheckOut service', '6', '2', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Secret Key', 'MODULE_PAYMENT_2CHECKOUT_INLINE_SECRET_KEY', '', 'Secret key for the 2CheckOut MD5 hash facility', '6', '10', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Secret Word', 'MODULE_PAYMENT_2CHECKOUT_INLINE_SECRET_WORD', '', 'Secret word for the 2CheckOut MD5 hash facility', '6', '10', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Transaction Mode', 'MODULE_PAYMENT_2CHECKOUT_INLINE_TESTMODE', 'Test', 'Transaction mode used for the 2Checkout Inline service', '6', '3', 'tep_cfg_select_option(array(\'Test\', \'Production\'), ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_2CHECKOUT_INLINE_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '6', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Payment Zone', 'MODULE_PAYMENT_2CHECKOUT_INLINE_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '7', 'tep_get_zone_class_title', 'tep_cfg_pull_down_zone_classes(', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Order Status', 'MODULE_PAYMENT_2CHECKOUT_INLINE_ORDER_STATUS_ID', '0', 'Set the status of orders made with this payment module to this value', '6', '8', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
    }

    function remove()
    {
        tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

// format prices without currency formatting

    function keys()
    {
        return [
            'MODULE_PAYMENT_2CHECKOUT_INLINE_STATUS',
            'MODULE_PAYMENT_2CHECKOUT_INLINE_SELLER_ID',
            'MODULE_PAYMENT_2CHECKOUT_INLINE_IPN',
            'MODULE_PAYMENT_2CHECKOUT_INLINE_SECRET_KEY',
            'MODULE_PAYMENT_2CHECKOUT_INLINE_SECRET_WORD',
            'MODULE_PAYMENT_2CHECKOUT_INLINE_TESTMODE',
            'MODULE_PAYMENT_2CHECKOUT_INLINE_SORT_ORDER',
            'MODULE_PAYMENT_2CHECKOUT_INLINE_ZONE',
            'MODULE_PAYMENT_2CHECKOUT_INLINE_ORDER_STATUS_ID'
        ];
    }

	function getCurrencies($value, $key = '')
	{
		$name = (($key) ? 'configuration[' . $key . ']' : 'configuration_value');

		$currencies_array = [];

		$currencies_query = tep_db_query("select code, title from " . TABLE_CURRENCIES . " order by title");
		while ($currencies = tep_db_fetch_array($currencies_query)) {
			$currencies_array[] = [
				'id'   => $currencies['code'],
				'text' => $currencies['title']
			];
		}

		return tep_draw_pull_down_menu($name, $currencies_array, $value);
	}

}


/**
 * @param        $text
 * @param string $key
 *
 * @return string
 */
function tco_inline_edit_readonly_text($text, $key= ''){
	return tco_inline_tep_draw_input_field($key, $text);
}

/**
 * @param        $name
 * @param string $value
 * @param string $parameters
 * @param false  $required
 * @param string $type
 * @param bool   $reinsert_value
 *
 * @return string
 */
function tco_inline_tep_draw_input_field($name, $value = '', $parameters = '', $required = false, $type = 'text', $reinsert_value = true) {
	global $HTTP_GET_VARS, $HTTP_POST_VARS;

	$field = '<input type="' . tep_output_string($type) . '" name="' . tep_output_string($name) . '"';

	if ( ($reinsert_value == true) && ( (isset($HTTP_GET_VARS[$name]) && is_string($HTTP_GET_VARS[$name])) || (isset($HTTP_POST_VARS[$name]) && is_string($HTTP_POST_VARS[$name])) ) ) {
		if (isset($HTTP_GET_VARS[$name]) && is_string($HTTP_GET_VARS[$name])) {
			$value = stripslashes($HTTP_GET_VARS[$name]);
		} elseif (isset($HTTP_POST_VARS[$name]) && is_string($HTTP_POST_VARS[$name])) {
			$value = stripslashes($HTTP_POST_VARS[$name]);
		}
	}

	if (tep_not_null($value)) {
		$field .= ' value="' . tep_output_string($value) . '"';
	}

	if (tep_not_null($parameters)) $field .= ' ' . $parameters;

	$field .= ' readonly = "readonly" />';

	if ($required == true) $field .= TEXT_FIELD_REQUIRED;

	return $field;
}

?>
