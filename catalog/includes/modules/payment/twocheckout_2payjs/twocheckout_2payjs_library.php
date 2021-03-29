<?php

class twocheckout_2payjs_library
{

	const SIGNATURE_URL = "https://secure.2checkout.com/checkout/api/encrypt/generate/signature";
	const   API_URL = 'https://api.2checkout.com/rest/';
	const   API_VERSION = '6.0';
	private $sellerId = MODULE_PAYMENT_2CHECKOUT_2PAYJS_SELLER_ID;
	private $secretKey = MODULE_PAYMENT_2CHECKOUT_2PAYJS_SECRET_KEY;
	private $secretWord = MODULE_PAYMENT_2CHECKOUT_2PAYJS_SECRET_WORD;

	/**
	 * @return mixed
	 * @throws \Exception
	 */
	private function getHeaders()
	{
		if (!$this->sellerId || !$this->secretKey) {
			throw new Exception('Merchandiser needs a valid 2Checkout SellerId and SecretKey to authenticate!');
		}
		$gmtDate = gmdate('Y-m-d H:i:s');
		$string = strlen($this->sellerId) . $this->sellerId . strlen($gmtDate) . $gmtDate;
		$hash = hash_hmac('md5', $string, $this->secretKey);

		$headers[] = 'Content-Type: application/json';
		$headers[] = 'Accept: application/json';
		$headers[] = 'X-Avangate-Authentication: code="' . $this->sellerId . '" date="' . $gmtDate . '" hash="' . $hash . '"';

		return $headers;
	}

	/**
	 * @param $params
	 * @return mixed
	 * @throws \Exception
	 */
	public function call($params)
	{

		try {
			$url = self::API_URL . self::API_VERSION . '/orders/';
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getHeaders());
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HEADER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params, JSON_UNESCAPED_UNICODE));
			$response = curl_exec($ch);

			if ($response === false) {
				exit(curl_error($ch));
			}
			curl_close($ch);

			return json_decode($response, true);
		} catch (Exception $e) {
			throw new Exception($e->getMessage());
		}
	}

	/**
	 * @param $payload
	 * @return mixed
	 * @throws \Exception
	 */
	public function getApiSignature($payload)
	{
		$jwtToken = $this->generateJWT();
		$curl = curl_init();
		curl_setopt_array($curl, [
			CURLOPT_URL            => self::SIGNATURE_URL,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CUSTOMREQUEST  => 'POST',
			CURLOPT_POSTFIELDS     => json_encode($payload),
			CURLOPT_HTTPHEADER     => [
				'content-type: application/json',
				'cache-control: no-cache',
				'merchant-token: ' . $jwtToken,
			]
		]);
		$response = curl_exec($curl);
		$err = curl_error($curl);
		curl_close($curl);
		if ($err) {
			throw new Exception('Error when trying to place order');
		}

		$response = json_decode($response, true);

		if (JSON_ERROR_NONE !== json_last_error() || !isset($response['signature'])) {
			throw new Exception('Unable to get proper response from signature generation API');
		}

		return $response['signature'];
	}

	/**
	 * @param $sellerId
	 * @param $secretWord
	 * @return string
	 */
	private function generateJWT()
	{
		$secretWord = html_entity_decode($this->secretWord);
		$header = $this->encode(json_encode(['alg' => 'HS512', 'typ' => 'JWT']));
		$payload = $this->encode(json_encode(['sub' => $this->sellerId, 'iat' => time(), 'exp' => time() + 3600]));
		$signature = $this->encode(hash_hmac('sha512', "$header.$payload", $secretWord, true));

		return implode('.', [$header, $payload, $signature]);
	}

	/**
	 * @param $data
	 *
	 * @return string|string[]
	 */
	private function encode($data)
	{
		return str_replace('=', '', strtr(base64_encode($data), '+/', '-_'));
	}

	/**
	 * @param array  $post_data
	 * @param string $country_iso
	 *
	 * @return array
	 */
	public function getBillingDetails($order)
	{
		$address = [
			'Address1'    => $order->billing['street_address'],
			'City'        => $order->billing['city'],
			'State'       => $order->billing['state'] != '' ? $order->billing['state'] : 'XX',
			'CountryCode' => $order->billing['country']['iso_code_2'],
			'Email'       => $order->customer['email_address'],
			'FirstName'   => $order->billing['firstname'],
			'LastName'    => $order->billing['lastname'],
			'Phone'       => str_replace(' ', '', $order->customer['telephone']),
			'Zip'         => $order->billing['postcode'],
			'Company'     => $order->billing['company'],
		];

		if ($order->billing['suburb']) {
			$address['Address2'] = $order->billing['suburb'];
		}

		return $address;
	}

	/**
	 * get customer ip or returns a default ip
	 * @return mixed|string
	 */
	public function getCustomerIp()
	{
		return '127.0.0.1';
		if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else {
			$ip = $_SERVER['REMOTE_ADDR'];
		}
		if (!filter_var($ip, FILTER_VALIDATE_IP) === false) {
			return $ip;
		}

		return '1.0.0.1';
	}

	/**
	 * @param $id
	 * @param $total
	 *
	 * @return mixed
	 */
	public function getItem($id, $total)
	{
		$items[] = [
			'Code'             => null,
			'Quantity'         => 1,
			'Name'             => 'Cart_' . $id,
			'Description'      => 'N/A',
			'RecurringOptions' => null,
			'IsDynamic'        => true,
			'Tangible'         => false,
			'PurchaseType'     => 'PRODUCT',
			'Price'            => [
				'Amount' => number_format($total, 2, '.', ''),
				'Type'   => 'CUSTOM'
			]
		];

		return $items;
	}

	/**
	 * @param string $type
	 * @param string $token
	 * @param string $currency
	 * @return array
	 */
	public function getPaymentDetails($type, $token, $currency)
	{
		$cancel_url =  HTTPS_SERVER . DIR_WS_HTTPS_CATALOG.'ext/modules/payment/twocheckout_2payjs/callback.php?threeds=cancel_url';
		$success_url =  HTTPS_SERVER . DIR_WS_HTTPS_CATALOG.'ext/modules/payment/twocheckout_2payjs/callback.php?payment_success=1';
		return [
			'Type'          => $type,
			'Currency'      => $currency,
			'CustomerIP'    => $this->getCustomerIp(),
			'PaymentMethod' => [
				'EesToken'           => $token,
				'Vendor3DSReturnURL' => $success_url,
				'Vendor3DSCancelURL' => $cancel_url
			],
		];

	}

	/**
	 * @param mixed $has3ds
	 *
	 * @return string|null
	 */
	public function hasAuthorize3DS($has3ds)
	{

		return (isset($has3ds) && isset($has3ds['Href']) && !empty($has3ds['Href'])) ?
			$has3ds['Href'] . '?avng8apitoken=' . $has3ds['Params']['avng8apitoken'] :
			null;
	}
}
