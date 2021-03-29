<?php

class twocheckout_2payjs_ipn {
	//Order Status Values:
	const ORDER_STATUS_PENDING = 'PENDING';
	const ORDER_STATUS_PAYMENT_AUTHORIZED = 'PAYMENT_AUTHORIZED';
	const ORDER_STATUS_AUTHRECEIVED = 'AUTHRECEIVED';
	const ORDER_STATUS_COMPLETE = 'COMPLETE';
	const ORDER_STATUS_PURCHASE_PENDING = 'PURCHASE_PENDING';
	const ORDER_STATUS_PENDING_APPROVAL = 'PENDING_APPROVAL';
	const ORDER_STATUS_PAYMENT_RECEIVED = 'PAYMENT_RECEIVED';
	const ORDER_STATUS_INVALID = 'INVALID';
	const ORDER_STATUS_REFUND = 'REFUND';

	// fraud status
	const FRAUD_STATUS_APPROVED = 'APPROVED';
	const FRAUD_STATUS_DENIED = 'DENIED';

	private $order_status = MODULE_PAYMENT_2CHECKOUT_2PAYJS_ORDER_STATUS_ID;
	private $secret_key = MODULE_PAYMENT_2CHECKOUT_2PAYJS_SECRET_KEY;

	/**
	 * @return string
	 * @throws Exception
	 */
	public function indexAction( $params ) {
		if ( ! isset( $params['REFNOEXT'] ) && ( ! isset( $params['REFNO'] ) && empty( $params['REFNO'] ) ) ) {
			throw new Exception( sprintf( 'Cannot identify order: "%s".',
				$params['REFNOEXT'] ) );
		}

		if ( ! $this->isIpnResponseValid( $params, $this->secret_key ) ) {
			throw new Exception( sprintf( 'MD5 hash mismatch for 2Checkout IPN with date: "%s".',
				$params['IPN_DATE'] ) );
		}

		// do not wrap this in a try catch
		// it's intentionally left out so that the exceptions will bubble up
		// and kill the script if one should arise

		$this->processOrderStatus( $params );

		echo $this->calculateIpnResponse(
			$params,
			$this->secret_key
		);
		die;
	}

	/**
	 * @param $params
	 * @param $secretKey
	 *
	 * @return bool
	 */
	public function isIpnResponseValid( $params, $secretKey ) {
		$result       = '';
		$receivedHash = $params['HASH'];
		foreach ( $params as $key => $val ) {
			if ( $key != "HASH" ) {
				if ( is_array( $val ) ) {
					$result .= $this->arrayExpand( $val );
				} else {
					$size   = strlen( stripslashes( $val ) );
					$result .= $size . stripslashes( $val );
				}
			}
		}

		if ( isset( $params['REFNO'] ) && ! empty( $params['REFNO'] ) ) {
			$calcHash = $this->hmac( $secretKey, $result );
			if ( $receivedHash === $calcHash ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param $ipnParams
	 * @param $secret_key
	 *
	 * @return string
	 */
	public function calculateIpnResponse( $ipnParams, $secret_key ) {
		$resultResponse    = '';
		$ipnParamsResponse = [];
		// we're assuming that these always exist, if they don't then the problem is on 2CO side
		$ipnParamsResponse['IPN_PID'][0]   = $ipnParams['IPN_PID'][0];
		$ipnParamsResponse['IPN_PNAME'][0] = $ipnParams['IPN_PNAME'][0];
		$ipnParamsResponse['IPN_DATE']     = $ipnParams['IPN_DATE'];
		$ipnParamsResponse['DATE']         = date( 'YmdHis' );

		foreach ( $ipnParamsResponse as $key => $val ) {
			$resultResponse .= $this->arrayExpand( (array) $val );
		}

		return sprintf(
			'<EPAYMENT>%s|%s</EPAYMENT>',
			$ipnParamsResponse['DATE'],
			$this->hmac( $secret_key, $resultResponse )
		);
	}

	/**
	 * @param $array
	 *
	 * @return string
	 */
	private function arrayExpand( $array ) {
		$result = '';
		foreach ( $array as $key => $value ) {
			$size   = strlen( stripslashes( $value ) );
			$result .= $size . stripslashes( $value );
		}

		return $result;
	}

	/**
	 * @param $key
	 * @param $data
	 *
	 * @return string
	 */
	private function hmac( $key, $data ) {
		$b = 64; // byte length for md5
		if ( strlen( $key ) > $b ) {
			$key = pack( "H*", md5( $key ) );
		}

		$key    = str_pad( $key, $b, chr( 0x00 ) );
		$ipad   = str_pad( '', $b, chr( 0x36 ) );
		$opad   = str_pad( '', $b, chr( 0x5c ) );
		$k_ipad = $key ^ $ipad;
		$k_opad = $key ^ $opad;

		return md5( $k_opad . pack( "H*", md5( $k_ipad . $data ) ) );
	}

	/**
	 * @param $orderId
	 * @param $status
	 * @param $comment
	 */
	private function updateOrderHistory( $orderId, $status, $comment ) {
		tep_db_query( "update " . TABLE_ORDERS . " set orders_status = " . (int) $status . ", last_modified = now()  where orders_id = " . (int) $orderId );
		tep_db_perform( TABLE_ORDERS_STATUS_HISTORY,
			[
				'orders_id'         => (int)$orderId,
				'orders_status_id'  => (int)$status,
				'date_added'        => 'now()',
				'customer_notified' => ( SEND_EMAILS == 'true' ) ? '1' : '0',
				'comments'          => $comment
			] );
	}

	/**
	 * @param $params
	 *
	 * @return bool
	 */
	private function isChargeBack( $params ) {
		return ! empty( $params['CHARGEBACK_RESOLUTION'] ) && $params['CHARGEBACK_RESOLUTION'] !== 'NONE' &&
		       ! empty( $params['CHARGEBACK_REASON_CODE'] );
	}

	/**
	 * @param $params
	 *
	 * @return string
	 */
	private function getChargeBackReason( $params ) {
		// list of chargeback reasons on 2CO platform
		$reasons = [
			'UNKNOWN'                  => 'Unknown', //default
			'MERCHANDISE_NOT_RECEIVED' => 'Order not fulfilled/not delivered',
			'DUPLICATE_TRANSACTION'    => 'Duplicate order',
			'FRAUD / NOT_RECOGNIZED'   => 'Fraud/Order not recognized',
			'FRAUD'                    => 'Fraud',
			'CREDIT_NOT_PROCESSED'     => 'Agreed refund not processed',
			'NOT_RECOGNIZED'           => 'New/renewal order not recognized',
			'AUTHORIZATION_PROBLEM'    => 'Authorization problem',
			'INFO_REQUEST'             => 'Information request',
			'CANCELED_RECURRING'       => 'Recurring payment was canceled',
			'NOT_AS_DESCRIBED'         => 'Product(s) not as described/not functional'
		];

		$why = isset( $reasons[ trim( $params['CHARGEBACK_REASON_CODE'] ) ] ) ?
			$reasons[ trim( $params['CHARGEBACK_REASON_CODE'] ) ] :
			$reasons['UNKNOWN'];

		return '2Checkout chargeback status is now ' . $params['CHARGEBACK_RESOLUTION'] . '. Reason: ' . $why . '!';
	}

	/**
	 * @param $params
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function processOrderStatus( $params ) {
		$order_status = ($params['FRAUD_STATUS'] && $params['FRAUD_STATUS'] === self::FRAUD_STATUS_DENIED) ?
			self::FRAUD_STATUS_DENIED : $params['ORDERSTATUS'];

		$order_id       = $params['REFNOEXT'];
		$order_query    = tep_db_query( 'select orders_id, orders_status, payment_method from ' . TABLE_ORDERS . ' where orders_id = "' . (int) $order_id . '" limit 1' );
		$existing_order = tep_db_fetch_array( $order_query );

		if ( $existing_order['orders_id'] != $order_id ) {
			return false;
		}
		if ( $existing_order['payment_method'] != '2Checkout API' ) {
			return false;
		}

		switch ( trim( $order_status ) ) {
			case self::FRAUD_STATUS_DENIED:
				$this->updateOrderHistory($order_id, $existing_order['orders_status'], '2Checkout transaction status status updated to: DENIED/FRAUD' );
				break;

			case self::ORDER_STATUS_REFUND:
				$this->updateOrderHistory( $order_id, $existing_order['orders_status'], 'Full amount was refunded from 2Checkout Control Panel');
				break;

			case self::FRAUD_STATUS_APPROVED:
			case self::ORDER_STATUS_PENDING:
			case self::ORDER_STATUS_PURCHASE_PENDING:
			case self::ORDER_STATUS_AUTHRECEIVED:
			case self::ORDER_STATUS_PAYMENT_RECEIVED:
			case self::ORDER_STATUS_PENDING_APPROVAL:
			case self::ORDER_STATUS_PAYMENT_AUTHORIZED:
			case self::ORDER_STATUS_INVALID:
				$this->updateOrderHistory( $order_id, 1, '2Checkout transaction status status updated to: ' . $order_status );
				break;
			case self::ORDER_STATUS_COMPLETE:
				if ( $this->isChargeBack( $params ) ) {
					$this->updateOrderHistory( $order_id, $existing_order['orders_status'], $this->getChargeBackReason( $params ) );
				} else {
					$this->updateOrderHistory( $order_id, $this->order_status, '2Checkout transaction status status updated to: ' . $order_status );
				}
				break;

			default:
				throw new Exception( 'Cannot handle Ipn message type for message' );
		}
	}

}
