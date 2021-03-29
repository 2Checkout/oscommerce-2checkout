<?php

class twocheckout_convertplus_library
{

	const API_URL = 'https://api.2checkout.com/rest/';
	const API_VERSION = '6.0';

	/**
	 * @param string $endpoint
	 * @param array  $params
	 * @param string $method
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function call( string $endpoint, array $params, $method = 'POST', $sellerId, $secretKey) {
		// if endpoint does not starts or end with a '/' we add it, as the API needs it
		if ( $endpoint[0] !== '/' ) {
			$endpoint = '/' . $endpoint;
		}
		if ( $endpoint[ - 1 ] !== '/' ) {
			$endpoint = $endpoint . '/';
		}

		try {
			$url = self::API_URL . self::API_VERSION . $endpoint;
			$ch  = curl_init();
			curl_setopt( $ch, CURLOPT_URL, $url );
			curl_setopt( $ch, CURLOPT_HTTPHEADER, $this->getHeaders($sellerId, $secretKey) );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_HEADER, false );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
			if ( $method === 'POST' ) {
				curl_setopt( $ch, CURLOPT_POST, true );
				curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $params, JSON_UNESCAPED_UNICODE ) );
			}
			$response = curl_exec( $ch );

			if ( $response === false ) {
				exit( curl_error( $ch ) );
			}
			curl_close( $ch );

			return json_decode( $response, true );
		} catch ( Exception $e ) {
			throw new Exception( $e->getMessage() );
		}
	}

	/**
	 *
	 * @return mixed
	 * @throws Exception
	 */
	private function getHeaders($sellerId, $secretKey)
	{

		if (!$sellerId || !$secretKey) {
			throw new Exception('Merchandiser needs a valid 2Checkout SellerId and SecretKey to authenticate!');
		}
		$gmtDate = gmdate('Y-m-d H:i:s');
		$string = strlen($sellerId) . $sellerId . strlen($gmtDate) . $gmtDate;
		$hash = hash_hmac('md5', $string, $secretKey);

		$headers[] = 'Content-Type: application/json';
		$headers[] = 'Accept: application/json';
		$headers[] = 'X-Avangate-Authentication: code="' . $sellerId . '" date="' . $gmtDate . '" hash="' . $hash . '"';

		return $headers;
	}

}
