<?php

class pm2checkout_convertplus {
	var $code, $title, $description, $enabled, $sort_order, $secret_word, $seller_id, $secret_key, $test_mode;

	/**
	 * pm2checkout_convertplus constructor.
	 */
	function pm2checkout_convertplus() {
		$this->code        = 'pm2checkout_convertplus';
		$this->title       = MODULE_PAYMENT_2CHECKOUT_CONVERTPLUS_TEXT_TITLE;
		$this->description = MODULE_PAYMENT_2CHECKOUT_CONVERTPLUS_TEXT_DESCRIPTION;
		$this->sort_order  = MODULE_PAYMENT_2CHECKOUT_CONVERTPLUS_SORT_ORDER;
		$this->enabled     = ( ( MODULE_PAYMENT_2CHECKOUT_CONVERTPLUS_STATUS == 'True' ) ? true : false );
		$this->secret_word = MODULE_PAYMENT_2CHECKOUT_CONVERTPLUS_SECRET_WORD;
		$this->secret_key = MODULE_PAYMENT_2CHECKOUT_CONVERTPLUS_SECRET_KEY;
		$this->seller_id    = MODULE_PAYMENT_2CHECKOUT_CONVERTPLUS_SELLER_ID;

		if ( (int) MODULE_PAYMENT_2CHECKOUT_CONVERTPLUS_ORDER_STATUS_ID > 0 ) {
			$this->order_status = MODULE_PAYMENT_2CHECKOUT_CONVERTPLUS_ORDER_STATUS_ID;
		}

		if ( MODULE_PAYMENT_2CHECKOUT_CONVERTPLUS_TESTMODE == 'Test' ) {
			$this->test_mode = '1';
		} else {
			$this->test_mode = '0';
		}
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
		global $cart_pm2checkout_convertplus_id;

		if (tep_session_is_registered('cart_pm2checkout_convertplus_id')) {
			$order_id = substr($cart_pm2checkout_convertplus_id, strpos($cart_pm2checkout_convertplus_id, '-')+1);

			$check_query = tep_db_query('select orders_id from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id = "' . (int)$order_id . '" limit 1');

			if (tep_db_num_rows($check_query) < 1) {
				tep_db_query('delete from ' . TABLE_ORDERS . ' where orders_id = "' . (int)$order_id . '"');
				tep_db_query('delete from ' . TABLE_ORDERS_TOTAL . ' where orders_id = "' . (int)$order_id . '"');
				tep_db_query('delete from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id = "' . (int)$order_id . '"');
				tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS . ' where orders_id = "' . (int)$order_id . '"');
				tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . ' where orders_id = "' . (int)$order_id . '"');
				tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS_DOWNLOAD . ' where orders_id = "' . (int)$order_id . '"');

				tep_session_unregister('cart_pm2checkout_convertplus_id');
			}
		}

		$co_cc_txt = $this->description;
		$fields[] = array('title' => '', //MODULE_PAYMENT_2CHECKOUT_TEXT_TITLE,
		                  'field' => '<div><b>' . $co_cc_txt . '</b></div>');
		return array('id' => $this->code,
		             'module' => $this->title,
		             'fields' => $fields);
	}

	/**
	 *
	 */
	function pre_confirmation_check() {
		global $cartID, $cart;

		if (empty($cart->cartID)) {
			$cartID = $cart->cartID = $cart->generate_cart_id();
		}

		if (!tep_session_is_registered('cartID')) {
			tep_session_register('cartID');
		}
	}

	/**
	 * @return false
	 */
	function confirmation() {
		global $cartID, $cart_pm2checkout_convertplus_id, $customer_id, $languages_id, $order, $order_total_modules;

		$insert_order = false;

		if (tep_session_is_registered('cart_pm2checkout_convertplus_id')) {
			$order_id = substr($cart_pm2checkout_convertplus_id, strpos($cart_pm2checkout_convertplus_id, '-')+1);

			$curr_check = tep_db_query("select currency from " . TABLE_ORDERS . " where orders_id = '" . (int)$order_id . "'");
			$curr = tep_db_fetch_array($curr_check);

			if ( ($curr['currency'] != $order->info['currency']) || ($cartID != substr($cart_pm2checkout_convertplus_id, 0, strlen($cartID))) ) {
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

			$cart_pm2checkout_convertplus_id = $cartID . '-' . $insert_id;
			tep_session_register('cart_pm2checkout_convertplus_id');
		}

		return false;
	}

	/**
	 * @return string
	 */
	function pass_through_products() {
		global $order, $currency, $db, $currencies;

		$process_button_string_lineitems;
		$products                        = $order->products;
		$process_button_string_lineitems .= tep_draw_hidden_field( 'mode', '2CO' );
		for ( $i = 0; $i < sizeof( $products ); $i ++ ) {
			$prod_array                      = explode( ':', $products[ $i ]['id'] );
			$product_id                      = $prod_array[0];
			$process_button_string_lineitems .= tep_draw_hidden_field( 'li_' . ( $i + 1 ) . '_quantity', $products[ $i ]['qty'] );
			$process_button_string_lineitems .= tep_draw_hidden_field( 'li_' . ( $i + 1 ) . '_name', $products[ $i ]['name'] );
			$process_button_string_lineitems .= tep_draw_hidden_field( 'li_' . ( $i + 1 ) . '_description', $products[ $i ]['model'] );
			$process_button_string_lineitems .= tep_draw_hidden_field( 'li_' . ( $i + 1 ) . '_price', number_format( ( $currencies->get_value( $order->info['currency'] ) * $products[ $i ]['final_price'] ), 2, '.', '' ) );
		}
		//shipping
		if ( $order->info['shipping_method'] ) {
			$i ++;
			$process_button_string_lineitems .= tep_draw_hidden_field( 'li_' . ( $i ) . '_type', 'shipping' );
			$process_button_string_lineitems .= tep_draw_hidden_field( 'li_' . ( $i ) . '_name', $order->info['shipping_method'] );
			$process_button_string_lineitems .= tep_draw_hidden_field( 'li_' . ( $i ) . '_price', number_format( ( $currencies->get_value( $order->info['currency'] ) * $order->info['shipping_cost'] ), 2, '.', '' ) );
		}
		//tax
		if ( $order->info['tax'] > 0 ) {
			$i ++;
			$process_button_string_lineitems .= tep_draw_hidden_field( 'li_' . ( $i ) . '_type', 'tax' );
			$process_button_string_lineitems .= tep_draw_hidden_field( 'li_' . ( $i ) . '_name', 'Tax' );
			$process_button_string_lineitems .= tep_draw_hidden_field( 'li_' . ( $i ) . '_price', number_format( ( $currencies->get_value( $order->info['currency'] ) * $order->info['tax'] ), 2, '.', '' ) );
		}

		return $process_button_string_lineitems;
	}

	/**
	 * @param $merchant_order_id
	 *
	 * @return string
	 */
	function third_party_cart( $merchant_order_id ) {
		global $order, $currency, $db, $currencies;

		$process_button_string_cprod;
		$products                    = $order->products;
		$process_button_string_cprod .= tep_draw_hidden_field( 'id_type', '1' );
		$process_button_string_cprod .= tep_draw_hidden_field( 'total', number_format( ( $currencies->get_value( $order->info['currency'] ) * $order->info['total'] ), 2, '.', '' ) );
		$process_button_string_cprod .= tep_draw_hidden_field( 'cart_order_id', $merchant_order_id );
		for ( $i = 0; $i < sizeof( $products ); $i ++ ) {
			$prod_array                  = explode( ':', $products[ $i ]['id'] );
			$product_id                  = $prod_array[0];
			$process_button_string_cprod .= tep_draw_hidden_field( 'c_prod_' . ( $i + 1 ), $product_id . ',' . $products[ $i ]['qty'] );
			$process_button_string_cprod .= tep_draw_hidden_field( 'c_name_' . ( $i + 1 ), $products[ $i ]['name'] );
			$process_button_string_cprod .= tep_draw_hidden_field( 'c_description_' . ( $i + 1 ), $products[ $i ]['model'] );
			$process_button_string_cprod .= tep_draw_hidden_field( 'c_price_' . ( $i + 1 ), number_format( ( $currencies->get_value( $order->info['currency'] ) * $products[ $i ]['final_price'] ), 2, '.', '' ) );
		}

		return $process_button_string_cprod;
	}

	/**
	 * @return float|int|string
	 */
	function check_total() {
		global $order, $currency, $db, $currencies;
		$lineitem_total = 0;
		$products       = $order->products;
		for ( $i = 0; $i < sizeof( $products ); $i ++ ) {
			$lineitem_total += $products[ $i ]['qty'] * number_format( ( $currencies->get_value( $order->info['currency'] ) * $products[ $i ]['final_price'] ), 2, '.', '' );
		}
		//shipping
		if ( $order->info['shipping_method'] ) {
			$lineitem_total += number_format( ( $currencies->get_value( $order->info['currency'] ) * $order->info['shipping_cost'] ), 2, '.', '' );
		}
		//tax
		if ( $order->info['tax'] > 0 ) {
			$lineitem_total += number_format( ( $currencies->get_value( $order->info['currency'] ) * $order->info['tax'] ), 2, '.', '' );
		}

		return $lineitem_total;
	}

	/**
	 * @return string
	 */
	function process_button() {
		global $cart_pm2checkout_convertplus_id, $HTTP_POST_VARS, $order, $currency, $currencies, $demo;
		global $i, $n, $shipping, $text, $languages_id;

		$order_id = substr($cart_pm2checkout_convertplus_id, strpos($cart_pm2checkout_convertplus_id, '-')+1);

		$tcoLangCode_query = tep_db_query( "select code from " . TABLE_LANGUAGES . " where languages_id = '" . (int) $languages_id . "'" );
		$tcoLangCode       = tep_db_fetch_array( $tcoLangCode_query );
		$tcoLangCodeID     = strtolower( $tcoLangCode['code'] );
		$cOrderTotal   = $currencies->get_value( DEFAULT_CURRENCY ) * $order->info['total'];

		$lineitem_total        = $this->check_total();
		$process_button_string = tep_draw_hidden_field( 'prod', 'Cart_' . $order_id ) .
		                         tep_draw_hidden_field( 'price', $lineitem_total ) .
		                         tep_draw_hidden_field( 'qty', 1 ) .
		                         tep_draw_hidden_field( 'type', 'PRODUCT' ) .
		                         tep_draw_hidden_field( 'tangible', '0' ) .
		                         tep_draw_hidden_field( 'src', 'OsCommerce_23' ) .
		                         tep_draw_hidden_field( 'return-type', 'redirect' ) .
		                         tep_draw_hidden_field( 'return-url', tep_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL', true)) .
		                         tep_draw_hidden_field( 'expiration', time() + ( 3600 * 5 ) ) .
		                         tep_draw_hidden_field( 'order-ext-ref', $order_id ) .
		                         tep_draw_hidden_field( 'item-ext-ref', date( 'YmdHis' ) ) .
		                         tep_draw_hidden_field( 'customer-ext-ref', $order->customer['email_address'] ) .
		                         tep_draw_hidden_field( 'currency', strtolower($order->info['currency']) ) .
		                         tep_draw_hidden_field( 'language', $tcoLangCodeID ) .
		                         tep_draw_hidden_field( 'test', $this->test_mode ) .
		                         tep_draw_hidden_field( 'merchant', $this->seller_id ) .
		                         tep_draw_hidden_field( 'dynamic', 1 ) .
		                         tep_draw_hidden_field( 'name', $order->billing['firstname'] . ' ' . $order->billing['lastname'] ) .
		                         tep_draw_hidden_field( 'phone', $order->customer['telephone'] ) .
		                         tep_draw_hidden_field( 'country', substr($order->billing['country']['iso_code_2'], 0, 2)) .
		                         tep_draw_hidden_field( 'state', $order->billing['state'] != '' ? $order->billing['state'] : 'XX' ) .
		                         tep_draw_hidden_field( 'email', $order->customer['email_address'] ) .
		                         tep_draw_hidden_field( 'address', $order->billing['street_address'] ) .
		                         tep_draw_hidden_field( 'address2', $order->billing['suburb'] ) .
		                         tep_draw_hidden_field( 'city', $order->billing['city']) .
		                         tep_draw_hidden_field( 'company-name', $order->billing['company'] ) .
		                         tep_draw_hidden_field( 'ship-name', $order->delivery['firstname'] . " " . $order->delivery['lastname'] ) .
		                         tep_draw_hidden_field( 'ship-country', $order->delivery['country']['iso_code_2'] ) .
		                         tep_draw_hidden_field( 'ship-state', $order->delivery['state'] != '' ? $order->delivery['state'] : 'XX') .
		                         tep_draw_hidden_field( 'ship-city', $order->delivery['city'] ) .
		                         tep_draw_hidden_field( 'ship-email', $order->customer['email_address'] ) .
		                         tep_draw_hidden_field( 'ship-address', $order->delivery['street_address'] ) .
		                         tep_draw_hidden_field( 'ship-address2', !empty($order->delivery['suburb']) ? $order->delivery['suburb'] : '') .
		                         tep_draw_hidden_field( 'zip',  $order->billing['postcode'] );

		if ( sprintf( "%01.2f", $lineitem_total ) == sprintf( "%01.2f", number_format( ( $currencies->get_value( $order->info['currency'] ) * $cOrderTotal ), 2, '.', '' ) ) ) {
			$process_button_string .= $this->pass_through_products();
		} else {
			$process_button_string .= $this->third_party_cart( $order_id);
		}

		return $process_button_string;
	}

	/**
	 * @param $merchant_id
	 * @param $buy_link_secret_word
	 * @param $payload
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function get_signature( $merchant_id, $buy_link_secret_word, $payload ) {
		$jwtToken = $this->generate_jwt_token(
			$merchant_id,
			time(),
			time() + 3600,
			$buy_link_secret_word
		);

		$curl = curl_init();
		curl_setopt_array( $curl, [
			CURLOPT_URL            => "https://secure.2checkout.com/checkout/api/encrypt/generate/signature",
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CUSTOMREQUEST  => 'POST',
			CURLOPT_POSTFIELDS     => json_encode( $payload ),
			CURLOPT_HTTPHEADER     => [
				'content-type: application/json',
				'cache-control: no-cache',
				'merchant-token: ' . $jwtToken,
			],
		] );
		$response = curl_exec( $curl );
		$err      = curl_error( $curl );
		curl_close( $curl );

		if ( $err ) {
			throw new Exception( sprintf( 'Unable to get proper response from signature generation API. In file %s at line %s', __FILE__, __LINE__ ) );
		}

		$response = json_decode( $response, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! isset( $response['signature'] ) ) {
			throw new Exception( sprintf( 'Unable to get proper response from signature generation API. Signature not set. In file %s at line %s', __FILE__, __LINE__ ) );
		}

		return $response['signature'];
	}

	/**
	 * @param $sub
	 * @param $iat
	 * @param $exp
	 * @param $buy_link_secret_word
	 *
	 * @return string
	 */
	function generate_jwt_token( $sub, $iat, $exp, $buy_link_secret_word ) {
		$header    = $this->encode( json_encode( [ 'alg' => 'HS512', 'typ' => 'JWT' ] ) );
		$payload   = $this->encode( json_encode( [ 'sub' => $sub, 'iat' => $iat, 'exp' => $exp ] ) );
		$signature = $this->encode(
			hash_hmac( 'sha512', "$header.$payload", $buy_link_secret_word, true )
		);

		return implode( '.', [
			$header,
			$payload,
			$signature
		] );
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
	 * @param $post_params
	 *
	 * @return array
	 */
	function get_params_array( $post_params ) {
		return array(
			'prod'             => $post_params['prod'],
			'price'            => $post_params['price'],
			'qty'              => $post_params['qty'],
			'type'             => $post_params['type'],
			'tangible'         => $post_params['tangible'],
			'src'              => $post_params['src'],
			'return-type'      => $post_params['return-type'],
			'return-url'       => $post_params['return-url'],
			'expiration'       => $post_params['expiration'],
			'order-ext-ref'    => $post_params['order-ext-ref'],
			'item-ext-ref'     => $post_params['item-ext-ref'],
			'customer-ext-ref' => $post_params['customer-ext-ref'],
			'currency'         => $post_params['currency'],
			'language'         => $post_params['language'],
			'test'             => $post_params['test'],
			'merchant'         => $post_params['merchant'],
			'dynamic'          => $post_params['dynamic'],
			'name'             => $post_params['name'],
			'phone'            => $post_params['phone'],
			'country'          => $post_params['country'],
			'state'            => $post_params['state'],
			'email'            => $post_params['email'],
			'address'          => $post_params['address'],
			'address2'         => $post_params['address2'],
			'city'             => $post_params['city'],
			'company-name'     => $post_params['company-name'],
			'ship-name'        => $post_params['ship-name'],
			'ship-country'     => $post_params['ship-country'],
			'ship-state'       => $post_params['ship-state'],
			'ship-city'        => $post_params['ship-city'],
			'ship-email'       => $post_params['ship-email'],
			'ship-address'     => $post_params['ship-address'],
			'ship-address2'    => $post_params['ship-address2'],
			'zip'              => $post_params['zip']
		);
	}

	/**
	 * @return false
	 */
	function before_process() {
		global $HTTP_POST_VARS, $order, $text, $cartID, $cart_pm2checkout_convertplus_id, $order_total_modules, $cart;

		if (tep_session_is_registered('cart_pm2checkout_convertplus_id') && !empty($_REQUEST['refno'])) {
			$order_id = substr($cart_pm2checkout_convertplus_id, strpos($cart_pm2checkout_convertplus_id, '-')+1);

			tep_db_query("update " . TABLE_ORDERS . " set orders_status = ".$this->order_status." where orders_id = " . $order_id);
			$sql_data_array = array('orders_id' => $order_id,
			                        'orders_status_id' => $this->order_status,
			                        'date_added' => 'now()',
			                        'customer_notified' => (SEND_EMAILS == 'true') ? '1' : '0',
			                        'comments' => '2checkout transaction ID: '.$_REQUEST['refno']);
			tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
			tep_session_unregister('cart_pm2checkout_convertplus_id');
			$cart->reset(true);
			tep_redirect(tep_href_link(FILENAME_CHECKOUT_SUCCESS, '', 'SSL'));
		}
		
		if ( ! empty( $_POST['order-ext-ref'] ) ) {
			$post_params                  = $_POST;
			$buy_link_params              = $this->get_params_array( $post_params );
			$buy_link_params['signature'] = $this->get_signature(
				$this->seller_id,
				html_entity_decode($this->secret_word),
				$buy_link_params );

			$tco_query_strings = http_build_query( $buy_link_params );
			$redirect_url      = 'https://secure.2checkout.com/checkout/buy/?' . $tco_query_strings;
			header( "Location: " . $redirect_url );
			exit();
		}

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
			'title' => MODULE_PAYMENT_2CHECKOUT_CONVERTPLUS_TEXT_ERROR,
			'error' => stripslashes( urldecode( $HTTP_GET_VARS['error'] ) )
		);

		return $error;
	}

	/**
	 * @return false|int
	 */
	function check() {
		if ( ! isset( $this->_check ) ) {
			$check_query  = tep_db_query( "select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_2CHECKOUT_CONVERTPLUS_STATUS'" );
			$this->_check = tep_db_num_rows( $check_query );
		}

		return $this->_check;
	}

	/**
	 * install function
	 */
	function install() {
		tep_db_query( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable 2CheckOut Convert Plus Module', 'MODULE_PAYMENT_2CHECKOUT_CONVERTPLUS_STATUS', 'True', 'Do you want to accept 2CheckOut Convert Plus payments?', '6', '1', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())" );
		tep_db_query( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Seller ID', 'MODULE_PAYMENT_2CHECKOUT_CONVERTPLUS_SELLER_ID', '18157', 'Seller ID used for the 2CheckOut service', '6', '2', now())" );
		tep_db_query( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Secret Key', 'MODULE_PAYMENT_2CHECKOUT_CONVERTPLUS_SECRET_KEY', 'tango', 'Secret key for the 2CheckOut MD5 hash facility', '6', '10', now())" );
		tep_db_query( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Secret Word', 'MODULE_PAYMENT_2CHECKOUT_CONVERTPLUS_SECRET_WORD', 'tango', 'Secret word for the 2CheckOut MD5 hash facility', '6', '10', now())" );
		tep_db_query( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Transaction Mode', 'MODULE_PAYMENT_2CHECKOUT_CONVERTPLUS_TESTMODE', 'Test', 'Transaction mode used for the 2Checkout Convert Plus service', '6', '3', 'tep_cfg_select_option(array(\'Test\', \'Production\'), ', now())" );
		tep_db_query( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_2CHECKOUT_CONVERTPLUS_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '6', now())" );
		tep_db_query( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Payment Zone', 'MODULE_PAYMENT_2CHECKOUT_CONVERTPLUS_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '7', 'tep_get_zone_class_title', 'tep_cfg_pull_down_zone_classes(', now())" );
		tep_db_query( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Order Status', 'MODULE_PAYMENT_2CHECKOUT_CONVERTPLUS_ORDER_STATUS_ID', '0', 'Set the status of orders made with this payment module to this value', '6', '8', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())" );
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
			'MODULE_PAYMENT_2CHECKOUT_CONVERTPLUS_STATUS',
			'MODULE_PAYMENT_2CHECKOUT_CONVERTPLUS_SELLER_ID',
			'MODULE_PAYMENT_2CHECKOUT_CONVERTPLUS_SECRET_KEY',
			'MODULE_PAYMENT_2CHECKOUT_CONVERTPLUS_SECRET_WORD',
			'MODULE_PAYMENT_2CHECKOUT_CONVERTPLUS_TESTMODE',
			'MODULE_PAYMENT_2CHECKOUT_CONVERTPLUS_SORT_ORDER',
			'MODULE_PAYMENT_2CHECKOUT_CONVERTPLUS_ZONE',
			'MODULE_PAYMENT_2CHECKOUT_CONVERTPLUS_ORDER_STATUS_ID'
		);
	}
}

?>
