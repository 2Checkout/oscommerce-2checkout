<?php
require('twocheckout_2payjs/twocheckout_2payjs_library.php');

class pm2checkout_2payjs {
	var $code, $title, $description, $enabled, $sort_order, $secret_word, $seller_id, $secret_key, $test_mode, $order_status;

	/**
	 * pm2checkout_2payjs constructor.
	 */
	function pm2checkout_2payjs() {
		$this->signature = '2checkout|pm2checkout_2payjs|2.0|2.2';
		$this->code        = 'pm2checkout_2payjs';
		$this->title       = MODULE_PAYMENT_2CHECKOUT_2PAYJS_TEXT_TITLE;
		$this->description = MODULE_PAYMENT_2CHECKOUT_2PAYJS_TEXT_DESCRIPTION;
		$this->sort_order  = MODULE_PAYMENT_2CHECKOUT_2PAYJS_SORT_ORDER;
		$this->enabled     = ( ( MODULE_PAYMENT_2CHECKOUT_2PAYJS_STATUS == 'True' ) ? true : false );
		$this->secret_word = MODULE_PAYMENT_2CHECKOUT_2PAYJS_SECRET_WORD;
		$this->secret_key = MODULE_PAYMENT_2CHECKOUT_2PAYJS_SECRET_KEY;
		$this->seller_id    = MODULE_PAYMENT_2CHECKOUT_2PAYJS_SELLER_ID;

		if ( (int) MODULE_PAYMENT_2CHECKOUT_2PAYJS_ORDER_STATUS_ID > 0 ) {
			$this->order_status = (int)MODULE_PAYMENT_2CHECKOUT_2PAYJS_ORDER_STATUS_ID;
		}

		if ( MODULE_PAYMENT_2CHECKOUT_2PAYJS_TESTMODE == 'Test' ) {
			$this->test_mode = 1;
		} else {
			$this->test_mode = 0;
		}
		$this->form_action_url = tep_href_link('ext/modules/payment/twocheckout_2payjs/2payjs.php', '', 'SSL', true);
	}

	/**
	 * @return false
	 */
	function javascript_validation() {
		return false;
	}

	/**
	 * @return array
	 */
	function selection() {
		global $cart_pm2checkout_2payjs_id;

		if (tep_session_is_registered('cart_pm2checkout_2payjs_id')) {
			$order_id = substr($cart_pm2checkout_2payjs_id, strpos($cart_pm2checkout_2payjs_id, '-')+1);

			$check_query = tep_db_query('select orders_id from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id = "' . (int)$order_id . '" limit 1');

			if (tep_db_num_rows($check_query) < 1) {
				tep_db_query('delete from ' . TABLE_ORDERS . ' where orders_id = "' . (int)$order_id . '"');
				tep_db_query('delete from ' . TABLE_ORDERS_TOTAL . ' where orders_id = "' . (int)$order_id . '"');
				tep_db_query('delete from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id = "' . (int)$order_id . '"');
				tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS . ' where orders_id = "' . (int)$order_id . '"');
				tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . ' where orders_id = "' . (int)$order_id . '"');
				tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS_DOWNLOAD . ' where orders_id = "' . (int)$order_id . '"');

				tep_session_unregister('cart_pm2checkout_2payjs_id');
			}
		}

		$co_cc_txt = $this->description;
		$fields[] = array('title' => $this->title,
		                  'field' => '<div><b>' . $co_cc_txt . '</b></div>');
		return array('id' => $this->code,
		             'module' => $this->title,
		             'fields' => $fields);
	}

	/**
	 *
	 */
	function pre_confirmation_check() {
		global $cartID, $cart, $order;

		if (empty($cart->cartID)) {
			$cartID = $cart->cartID = $cart->generate_cart_id();
		}

		if (!tep_session_is_registered('cartID')) {
			tep_session_register('cartID');
		}
		$order->info['payment_method_raw'] = $order->info['payment_method'];
		$order->info['payment_method'] = '2Checkout API';
	}

	/**
	 * @return false
	 */
	function confirmation() {
		global $cartID, $cart_pm2checkout_2payjs_id, $customer_id, $languages_id, $order, $order_total_modules;

		$insert_order = false;

		if (tep_session_is_registered('cart_pm2checkout_2payjs_id')) {
			$order_id = substr($cart_pm2checkout_2payjs_id, strpos($cart_pm2checkout_2payjs_id, '-')+1);

			$curr_check = tep_db_query("select currency from " . TABLE_ORDERS . " where orders_id = '" . (int)$order_id . "'");
			$curr = tep_db_fetch_array($curr_check);

			if ( ($curr['currency'] != $order->info['currency']) || ($cartID != substr($cart_pm2checkout_2payjs_id, 0, strlen($cartID))) ) {
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
			$order_totals = array();
			if (is_array($order_total_modules->modules)) {
				reset($order_total_modules->modules);
				while (list(, $value) = each($order_total_modules->modules)) {
					$class = substr($value, 0, strrpos($value, '.'));
					if ($GLOBALS[$class]->enabled) {
						for ($i=0, $n=sizeof($GLOBALS[$class]->output); $i<$n; $i++) {
							if (tep_not_null($GLOBALS[$class]->output[$i]['title']) && tep_not_null($GLOBALS[$class]->output[$i]['text'])) {
								$order_totals[] = array('code' => $GLOBALS[$class]->code,
								                        'title' => $GLOBALS[$class]->output[$i]['title'],
								                        'text' => $GLOBALS[$class]->output[$i]['text'],
								                        'value' => $GLOBALS[$class]->output[$i]['value'],
								                        'sort_order' => $GLOBALS[$class]->sort_order);
							}
						}
					}
				}
			}

			if (isset($order->info['payment_method_raw'])) {
				$order->info['payment_method'] = $order->info['payment_method_raw'];
				unset($order->info['payment_method_raw']);
			}
			$sql_data_array = array('customers_id' => $customer_id,
			                        'customers_name' => $order->customer['firstname'] . ' ' . $order->customer['lastname'],
			                        'customers_company' => $order->customer['company'],
			                        'customers_street_address' => $order->customer['street_address'],
			                        'customers_suburb' => $order->customer['suburb'],
			                        'customers_city' => $order->customer['city'],
			                        'customers_postcode' => $order->customer['postcode'],
			                        'customers_state' => $order->customer['state'],
			                        'customers_country' => $order->customer['country']['title'],
			                        'customers_telephone' => $order->customer['telephone'],
			                        'customers_email_address' => $order->customer['email_address'],
			                        'customers_address_format_id' => $order->customer['format_id'],
			                        'delivery_name' => $order->delivery['firstname'] . ' ' . $order->delivery['lastname'],
			                        'delivery_company' => $order->delivery['company'],
			                        'delivery_street_address' => $order->delivery['street_address'],
			                        'delivery_suburb' => $order->delivery['suburb'],
			                        'delivery_city' => $order->delivery['city'],
			                        'delivery_postcode' => $order->delivery['postcode'],
			                        'delivery_state' => $order->delivery['state'],
			                        'delivery_country' => $order->delivery['country']['title'],
			                        'delivery_address_format_id' => $order->delivery['format_id'],
			                        'billing_name' => $order->billing['firstname'] . ' ' . $order->billing['lastname'],
			                        'billing_company' => $order->billing['company'],
			                        'billing_street_address' => $order->billing['street_address'],
			                        'billing_suburb' => $order->billing['suburb'],
			                        'billing_city' => $order->billing['city'],
			                        'billing_postcode' => $order->billing['postcode'],
			                        'billing_state' => $order->billing['state'],
			                        'billing_country' => $order->billing['country']['title'],
			                        'billing_address_format_id' => $order->billing['format_id'],
			                        'payment_method' => $order->info['payment_method'],
			                        'cc_type' => $order->info['cc_type'],
			                        'cc_owner' => $order->info['cc_owner'],
			                        'cc_number' => $order->info['cc_number'],
			                        'cc_expires' => $order->info['cc_expires'],
			                        'date_purchased' => 'now()',
			                        'orders_status' => 1,
			                        'currency' => $order->info['currency'],
			                        'currency_value' => $order->info['currency_value']);

			tep_db_perform(TABLE_ORDERS, $sql_data_array);

			$insert_id = tep_db_insert_id();

			for ($i=0, $n=sizeof($order_totals); $i<$n; $i++) {
				$sql_data_array = array('orders_id' => $insert_id,
				                        'title' => $order_totals[$i]['title'],
				                        'text' => $order_totals[$i]['text'],
				                        'value' => $order_totals[$i]['value'],
				                        'class' => $order_totals[$i]['code'],
				                        'sort_order' => $order_totals[$i]['sort_order']);

				tep_db_perform(TABLE_ORDERS_TOTAL, $sql_data_array);
			}

			for ($i=0, $n=sizeof($order->products); $i<$n; $i++) {
				$sql_data_array = array('orders_id' => $insert_id,
				                        'products_id' => tep_get_prid($order->products[$i]['id']),
				                        'products_model' => $order->products[$i]['model'],
				                        'products_name' => $order->products[$i]['name'],
				                        'products_price' => $order->products[$i]['price'],
				                        'final_price' => $order->products[$i]['final_price'],
				                        'products_tax' => $order->products[$i]['tax'],
				                        'products_quantity' => $order->products[$i]['qty']);

				tep_db_perform(TABLE_ORDERS_PRODUCTS, $sql_data_array);

				$order_products_id = tep_db_insert_id();

				$attributes_exist = '0';
				if (isset($order->products[$i]['attributes'])) {
					$attributes_exist = '1';
					for ($j=0, $n2=sizeof($order->products[$i]['attributes']); $j<$n2; $j++) {
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

						$sql_data_array = array('orders_id' => $insert_id,
						                        'orders_products_id' => $order_products_id,
						                        'products_options' => $attributes_values['products_options_name'],
						                        'products_options_values' => $attributes_values['products_options_values_name'],
						                        'options_values_price' => $attributes_values['options_values_price'],
						                        'price_prefix' => $attributes_values['price_prefix']);

						tep_db_perform(TABLE_ORDERS_PRODUCTS_ATTRIBUTES, $sql_data_array);

						if ((DOWNLOAD_ENABLED == 'true') && isset($attributes_values['products_attributes_filename']) && tep_not_null($attributes_values['products_attributes_filename'])) {
							$sql_data_array = array('orders_id' => $insert_id,
							                        'orders_products_id' => $order_products_id,
							                        'orders_products_filename' => $attributes_values['products_attributes_filename'],
							                        'download_maxdays' => $attributes_values['products_attributes_maxdays'],
							                        'download_count' => $attributes_values['products_attributes_maxcount']);

							tep_db_perform(TABLE_ORDERS_PRODUCTS_DOWNLOAD, $sql_data_array);
						}
					}
				}
			}

			$cart_pm2checkout_2payjs_id = $cartID . '-' . $insert_id;
			tep_session_register('cart_pm2checkout_2payjs_id');
		}

		return false;
	}

	/**
	 * @param        $number
	 * @param string $currency_code
	 * @param string $currency_value
	 *
	 * @return string
	 */
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


	/**
	 * @return string
	 */
	function process_button() {
		return false;
	}

	/**
	 * @param $data
	 *
	 * @return string|string[]
	 */
	function encode( $data ) {
		return str_replace( '=', '', strtr( base64_encode( $data ), '+/', '-_' ) );
	}

	/**
	 * @return false
	 */
	function before_process() {
		return false;
	}

	/**
	 * @return false
	 */
	function after_process() {
		return false;
	}

	/**
	 * @return array
	 */
	function get_error() {
		global $HTTP_GET_VARS;

		$error = array(
			'title' => MODULE_PAYMENT_2CHECKOUT_2PAYJS_TEXT_ERROR,
			'error' => stripslashes( urldecode( $HTTP_GET_VARS['error'] ) )
		);

		return $error;
	}

	/**
	 * @return false|int
	 */
	function check() {
		if ( ! isset( $this->_check ) ) {
			$check_query  = tep_db_query( "select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_2CHECKOUT_2PAYJS_STATUS'" );
			$this->_check = tep_db_num_rows( $check_query );
		}

		return $this->_check;
	}

	/**
	 * @param        $text
	 * @param string $key
	 *
	 * @return string
	 */
	function tep_cfg_textarea_edit($text,$key = '') {
		return tep_draw_textarea_field($key, false, 35, 5, $text);
	}

	/**
	 * install function
	 */
	function install() {
		$default_style = '{
                    "margin": "0",
                    "fontFamily": "Helvetica, sans-serif",
                    "fontSize": "1rem",
                    "fontWeight": "400",
                    "lineHeight": "1.5",
                    "color": "#212529",
                    "textAlign": "left",
                    "backgroundColor": "#FFFFFF",
                    "*": {
                        "boxSizing": "border-box"
                    },
                    ".no-gutters": {
                        "marginRight": 0,
                        "marginLeft": 0
                    },
                    ".row": {
                        "display": "flex",
                        "flexWrap": "wrap"
                    },
                    ".col": {
                        "flexBasis": "0",
                        "flexGrow": "1",
                        "maxWidth": "100%",
                        "padding": "0",
                        "position": "relative",
                        "width": "100%"
                    },
                    "div": {
                        "display": "block"
                    },
                    ".field-container": {
                        "paddingBottom": "14px"
                    },
                    ".field-wrapper": {
                        "paddingRight": "25px"
                    },
                    ".input-wrapper": {
                        "position": "relative"
                    },
                    "label": {
                        "display": "inline-block",
                        "marginBottom": "9px",
                        "color": "red",
                        "fontSize": "14px",
                        "fontWeight": "300",
                        "lineHeight": "17px"
                    },
                    "input": {
                        "overflow": "visible",
                        "margin": 0,
                        "fontFamily": "inherit",
                        "display": "block",
                        "width": "100%",
                        "height": "42px",
                        "padding": "10px 12px",
                        "fontSize": "18px",
                        "fontWeight": "400",
                        "lineHeight": "22px",
                        "color": "#313131",
                        "backgroundColor": "#FFF",
                        "backgroundClip": "padding-box",
                        "border": "1px solid #CBCBCB",
                        "borderRadius": "3px",
                        "transition": "border-color .15s ease-in-out,box-shadow .15s ease-in-out",
                        "outline": 0
                    },
                    "input:focus": {
                        "border": "1px solid #5D5D5D",
                        "backgroundColor": "#FFFDF2"
                    },
                    ".is-error input": {
                        "border": "1px solid #D9534F"
                    },
                    ".is-error input:focus": {
                        "backgroundColor": "#D9534F0B"
                    },
                    ".is-valid input": {
                        "border": "1px solid #1BB43F"
                    },
                    ".is-valid input:focus": {
                        "backgroundColor": "#1BB43F0B"
                    },
                    ".validation-message": {
                        "color": "#D9534F",
                        "fontSize": "10px",
                        "fontStyle": "italic",
                        "marginTop": "6px",
                        "marginBottom": "-5px",
                        "display": "block",
                        "lineHeight": "1"
                    },
                    ".card-expiration-date": {
                        "paddingRight": ".5rem"
                    },
                    ".is-empty input": {
                        "color": "#EBEBEB"
                    },
                    ".lock-icon": {
                        "top": "calc(50% - 7px)",
                        "right": "10px"
                    },
                    ".valid-icon": {
                        "top": "calc(50% - 8px)",
                        "right": "-25px"
                    },
                    ".error-icon": {
                        "top": "calc(50% - 8px)",
                        "right": "-25px"
                    },
                    ".card-icon": {
                        "top": "calc(50% - 10px)",
                        "left": "10px",
                        "display": "none"
                    },
                    ".is-empty .card-icon": {
                        "display": "block"
                    },
                    ".is-focused .card-icon": {
                        "display": "none"
                    },
                    ".card-type-icon": {
                        "right": "30px",
                        "display": "block"
                    },
                    ".card-type-icon.visa": {
                        "top": "calc(50% - 14px)"
                    },
                    ".card-type-icon.mastercard": {
                        "top": "calc(50% - 14.5px)"
                    },
                    ".card-type-icon.amex": {
                        "top": "calc(50% - 14px)"
                    },
                    ".card-type-icon.discover": {
                        "top": "calc(50% - 14px)"
                    },
                    ".card-type-icon.jcb": {
                        "top": "calc(50% - 14px)"
                    },
                    ".card-type-icon.dankort": {
                        "top": "calc(50% - 14px)"
                    },
                    ".card-type-icon.cartebleue": {
                        "top": "calc(50% - 14px)"
                    },
                    ".card-type-icon.diners": {
                        "top": "calc(50% - 14px)"
                    },
                    ".card-type-icon.elo": {
                        "top": "calc(50% - 14px)"
                    }
                }';

		$default_style_description = 'This is the styling object that styles your form.
                     Do not remove or add new classes. You can modify the existing ones. Use
                      double quotes for all keys and values!';

		$ipn_url =  HTTPS_SERVER . DIR_WS_HTTPS_CATALOG.'ext/modules/payment/twocheckout_2payjs/ipn.php';
		tep_db_query( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable 2CheckOut API Module', 'MODULE_PAYMENT_2CHECKOUT_2PAYJS_STATUS', 'True', 'Do you want to accept 2CheckOut API payments?', '6', '1', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())" );
		tep_db_query( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Seller ID', 'MODULE_PAYMENT_2CHECKOUT_2PAYJS_SELLER_ID', '', 'Seller ID used for the 2CheckOut service', '6', '2', now())" );
		tep_db_query( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Secret Key', 'MODULE_PAYMENT_2CHECKOUT_2PAYJS_SECRET_KEY', '', 'Secret key for the 2CheckOut MD5 hash facility', '6', '10', now())" );
		tep_db_query( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Secret Word', 'MODULE_PAYMENT_2CHECKOUT_2PAYJS_SECRET_WORD', '', 'Secret word for the 2CheckOut MD5 hash facility', '6', '10', now())" );
		tep_db_query( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Transaction Mode', 'MODULE_PAYMENT_2CHECKOUT_2PAYJS_TESTMODE', 'Test', 'Transaction mode used for the 2Checkout API service', '6', '3', 'tep_cfg_select_option(array(\'Test\', \'Production\'), ', now())" );
		tep_db_query( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('IPN_URL', 'MODULE_PAYMENT_2CHECKOUT_2PAYJS_IPN_URL', '$ipn_url', 'The callback endpoint for IPN requests from 2Checkout', '6', '2', 'tco_api_edit_readonly_text( ', now())");
		tep_db_query( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Use default style', 'MODULE_PAYMENT_2CHECKOUT_2PAYJS_DEFAULT_STYLE', 'True', 'Yes, I like the default style', '6', '1', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())" );
		tep_db_query( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Custom style', 'MODULE_PAYMENT_2CHECKOUT_2PAYJS_STYLE', '$default_style', '$default_style_description', '6', '2','tco_api_edit_textarea( ', now())" );
		tep_db_query( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_2CHECKOUT_2PAYJS_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '6', now())" );
		tep_db_query( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Payment Zone', 'MODULE_PAYMENT_2CHECKOUT_2PAYJS_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '7', 'tep_get_zone_class_title', 'tep_cfg_pull_down_zone_classes(', now())" );
		tep_db_query( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Order Status', 'MODULE_PAYMENT_2CHECKOUT_2PAYJS_ORDER_STATUS_ID', '0', 'Set the status of orders made with this payment module to this value', '6', '8', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())" );
	}

	/**
	 * remove function
	 */
	function remove() {
		tep_db_query( "delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode( "', '", $this->keys() ) . "')" );
	}

	/**
	 * @return string[]
	 */
	function keys() {
		return array(
			'MODULE_PAYMENT_2CHECKOUT_2PAYJS_STATUS',
			'MODULE_PAYMENT_2CHECKOUT_2PAYJS_SELLER_ID',
			'MODULE_PAYMENT_2CHECKOUT_2PAYJS_SECRET_KEY',
			'MODULE_PAYMENT_2CHECKOUT_2PAYJS_SECRET_WORD',
			'MODULE_PAYMENT_2CHECKOUT_2PAYJS_TESTMODE',
			'MODULE_PAYMENT_2CHECKOUT_2PAYJS_IPN_URL',
			'MODULE_PAYMENT_2CHECKOUT_2PAYJS_DEFAULT_STYLE',
			'MODULE_PAYMENT_2CHECKOUT_2PAYJS_STYLE',
			'MODULE_PAYMENT_2CHECKOUT_2PAYJS_SORT_ORDER',
			'MODULE_PAYMENT_2CHECKOUT_2PAYJS_ZONE',
			'MODULE_PAYMENT_2CHECKOUT_2PAYJS_ORDER_STATUS_ID'
		);
	}
}

	/**
	 * @param        $text
	 * @param string $key
	 *
	 * @return string
	 */
	function tco_api_edit_textarea($text, $key = '') {
		return tco_api_draw_textarea_field($key,  $text,35, 5);
	}

	/**
	 * @param        $text
	 * @param string $key
	 *
	 * @return string
	 */
	function tco_api_edit_readonly_text($text, $key= ''){
		return tco_api_tep_draw_input_field($key, $text);
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
	function tco_api_tep_draw_input_field($name, $value = '', $parameters = '', $required = false, $type = 'text', $reinsert_value = true) {
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

	/**
	 * @param        $name
	 * @param        $text
	 * @param        $width
	 * @param        $height
	 * @param string $parameters
	 * @param bool   $reinsert_value
	 *
	 * @return string
	 */
	function tco_api_draw_textarea_field($name, $text, $width, $height, $parameters = '', $reinsert_value = true) {
		global $HTTP_GET_VARS, $HTTP_POST_VARS;

		$field = '<textarea name="configuration[' . tep_output_string($name) . ']" cols="' . tep_output_string($width) . '" rows="' . tep_output_string($height) . '"';

		if (tep_not_null($parameters)) $field .= ' ' . $parameters;

		$field .= '>';

		if ( ($reinsert_value == true) && ( (isset($HTTP_GET_VARS[$name]) && is_string($HTTP_GET_VARS[$name])) || (isset($HTTP_POST_VARS[$name]) && is_string($HTTP_POST_VARS[$name])) ) ) {
			if (isset($HTTP_GET_VARS[$name]) && is_string($HTTP_GET_VARS[$name])) {
				$field .= tep_output_string_protected(stripslashes($HTTP_GET_VARS[$name]));
			} elseif (isset($HTTP_POST_VARS[$name]) && is_string($HTTP_POST_VARS[$name])) {
				$field .= tep_output_string_protected(stripslashes($HTTP_POST_VARS[$name]));
			}
		} elseif (tep_not_null($text)) {
			$field .= tep_output_string_protected($text);
		}

		$field .= '</textarea>';

		return $field;
	}

?>
