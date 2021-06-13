<?php

if ( ! defined( 'TABLE_2CHECKOUT_API' ) ) {
	define( 'TABLE_2CHECKOUT_API', DB_PREFIX . '2checkout_api' );
}

class twocheckout_api_helper {

	const API_URL = 'https://api.2checkout.com/rest/';
	const API_VERSION = '6.0';

	//Order Status Values:
	const ORDER_STATUS_PENDING = 'PENDING';
	const ORDER_STATUS_PAYMENT_AUTHORIZED = 'PAYMENT_AUTHORIZED';
	const ORDER_STATUS_AUTHRECEIVED = 'AUTHRECEIVED';
	const ORDER_STATUS_COMPLETE = 'COMPLETE';
	const ORDER_STATUS_PURCHASE_PENDING = 'PURCHASE_PENDING';
	const ORDER_STATUS_PAYMENT_RECEIVED = 'PAYMENT_RECEIVED';
	const ORDER_STATUS_PENDING_APPROVAL = 'PENDING_APPROVAL';
	const ORDER_STATUS_REFUND = 'REFUND';

	// fraud status
	const FRAUD_STATUS_APPROVED = 'APPROVED';
	const FRAUD_STATUS_DENIED = 'DENIED';
	private $sellerId = MODULE_PAYMENT_TWOCHECKOUT_API_SELLER_ID;
	private $secretKey = MODULE_PAYMENT_TWOCHECKOUT_API_SECRET_KEY;


	/**
	 * we check if the orders was placed using our gateway
	 * @param $transaction_id
	 * @return bool
	 */
	public function is_2payjs_order($transaction_id)
	{
		$transaction_id = trim($transaction_id);
		global $db;
		$sql = "SELECT * FROM ". TABLE_2CHECKOUT_API."  WHERE tco_transaction_id = '" . $transaction_id."'";
		$transaction = $db->Execute($sql);
		if (!$transaction || !$transaction->fields['tco_transaction_id'] || !$transaction->fields['order_id']) {
			return false;
		}
		$sql_order = "SELECT * FROM orders WHERE orders_id = " . $transaction->fields['order_id'];
		$order = $db->Execute($sql_order);

		return $order && $order->fields['payment_module_code'] === 'twocheckout_api';
	}

	/**
	 * @param $transaction_id
	 * @return \queryFactoryResult
	 */
	public function get_zen_order($transaction_id)
	{
		global $db;
		$sql = "SELECT * FROM " . TABLE_2CHECKOUT_API . " WHERE tco_transaction_id = '" . trim($transaction_id)."'";
		$transaction = $db->Execute($sql);
		$sql_order = "SELECT * FROM orders WHERE orders_id = " . $transaction->fields['order_id'];
		return $db->Execute($sql_order)->fields;
	}

	/**
	 * @return mixed
	 * @throws \Exception
	 */
	private function getHeaders() {
		if ( ! $this->sellerId || ! $this->secretKey ) {
			throw new Exception( 'Merchandiser needs a valid 2Checkout SellerId and SecretKey to authenticate!' );
		}
		$gmtDate = gmdate( 'Y-m-d H:i:s' );
		$string  = strlen( $this->sellerId ) . $this->sellerId . strlen( $gmtDate ) . $gmtDate;
		$hash    = hash_hmac( 'md5', $string, $this->secretKey );

		$headers[] = 'Content-Type: application/json';
		$headers[] = 'Accept: application/json';
		$headers[] = 'X-Avangate-Authentication: code="' . $this->sellerId . '" date="' . $gmtDate . '" hash="' . $hash . '"';

		return $headers;
	}

	/**
	 * @param $params
	 * @param $test
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function call( $params, $test ) {

		try {
			$url = self::API_URL . self::API_VERSION . '/orders/';
			$ch  = curl_init();
			curl_setopt( $ch, CURLOPT_URL, $url );
			curl_setopt( $ch, CURLOPT_HTTPHEADER, $this->getHeaders() );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_HEADER, false );
			if ( $test === true ) {
				curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false ); //by default value is 2
				curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false ); //by default value is 1
			}
			curl_setopt( $ch, CURLOPT_POST, true );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $params, JSON_UNESCAPED_UNICODE ) );
			$response = curl_exec( $ch );

			if ( $response === false ) {
				throw new Exception('The API response was empty with the following error: '.curl_error( $ch ));
			}
			curl_close( $ch );

			return json_decode( $response, true );
		}
		catch ( Exception $e ) {
			throw new Exception( $e->getMessage() );
		}
	}

	/**
	 * @param $order
	 *
	 * @return array
	 */
	public function getBillingDetails( $order ) {
		$address = [
			'Address1'    => $order->billing['street_address'],
			'City'        => $order->billing['city'],
			'State'       => $order->billing['state'] != '' ? $order->billing['state'] : 'XX',
			'CountryCode' => $order->billing['country']['iso_code_2'],
			'Email'       => $order->customer['email_address'],
			'FirstName'   => $order->billing['firstname'],
			'LastName'    => $order->billing['lastname'],
			'Phone'       => str_replace( ' ', '', $order->customer['telephone'] ),
			'Zip'         => $order->billing['postcode'],
			'Company'     => $order->billing['company'],
		];

		if ( $order->billing['suburb'] ) {
			$address['Address2'] = $order->billing['suburb'];
		}

		return $address;
	}

	/**
	 * get customer ip or returns a default ip
	 * @return mixed|string
	 */
	public function getCustomerIp() {
		return '127.0.0.1';
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else {
			$ip = $_SERVER['REMOTE_ADDR'];
		}
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) === false ) {
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
	public function getItem( $id, $total ) {
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
				'Amount' => number_format( $total, 2, '.', '' ),
				'Type'   => 'CUSTOM'
			]
		];

		return $items;
	}

	/**
	 * @param string $type
	 * @param string $token
	 * @param string $currency
	 *
	 * @return array
	 */
	public function getPaymentDetails( $type, $token, $currency ) {
		$cancel_url  = HTTP_SERVER . DIR_WS_CATALOG . 'extras/twocheckout_api/callback.php?threeds=cancel_url';
		$success_url = HTTP_SERVER . DIR_WS_CATALOG . 'extras/twocheckout_api/callback.php?payment_success=1';

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
	public function hasAuthorize3DS( $has3ds ) {

		return ( isset( $has3ds ) && isset( $has3ds['Href'] ) && ! empty( $has3ds['Href'] ) ) ?
			$has3ds['Href'] . '?avng8apitoken=' . $has3ds['Params']['avng8apitoken'] :
			null;
	}

	/**
	 * @param $order_params
	 * @param $test
	 *
	 * @return array|mixed
	 */
	public function getApiResponse( $order_params, $test ) {
		try {
			return $this->call( $order_params, $test );
		}
		catch ( Exception $e ) {
			$json_response = [
				'success'  => false,
				'messages' => $e->getMessage(),
				'redirect' => null
			];
			return $json_response;
		}
	}

	public function getJsonResponseFromApi( $api_response, $success_url ) {
		if ( ! $api_response || isset( $api_response['error_code'] ) && ! empty( $api_response['error_code'] ) ) { // we dont get any response from 2co or internal account related error
			$error_message = 'There has been an error processing your credit card. Please try again.';
			if ( $api_response && isset( $api_response['message'] ) && ! empty( $api_response['message'] ) ) {
				$error_message = $api_response['message'];
			}
			$json_response = [ 'success' => false, 'messages' => $error_message, 'redirect' => null ];
		} else {
			if ( $api_response['Errors'] ) { // errors that must be shown to the client
				$error_message = '';
				foreach ( $api_response['Errors'] as $key => $value ) {
					$error_message .= $value . PHP_EOL;
				}
				$json_response = [ 'success' => false, 'messages' => $error_message, 'redirect' => null ];
			} else {
				$has3ds = null;
				if ( isset( $api_response['PaymentDetails']['PaymentMethod']['Authorize3DS'] ) ) {
					$has3ds = $this->hasAuthorize3DS( $api_response['PaymentDetails']['PaymentMethod']['Authorize3DS'] );
				}
				if ( $has3ds ) {
					$redirect_url  = $has3ds;
					$json_response = [
						'success'  => true,
						'messages' => '3dSecure Redirect',
						'redirect' => $redirect_url
					];
				} else {
					$json_response = [
						'success'  => true,
						'messages' => 'Order payment success',
						'redirect' => $success_url
					];
				}
			}
		}
		return $json_response;
	}

	/**
	 * @param $params
	 * @param $secretKey
	 * @return bool
	 */
	public function isIpnResponseValid($params)
	{
		$result = '';
		$secretKey = MODULE_PAYMENT_TWOCHECKOUT_API_SECRET_KEY;
		$receivedHash = $params['HASH'];
		foreach ($params as $key => $val) {
			if ($key != "HASH") {
				if (is_array($val)) {
					$result .= $this->arrayExpand($val);
				} else {
					$size = strlen(stripslashes($val));
					$result .= $size . stripslashes($val);
				}
			}
		}

		if (isset($params['REFNO']) && !empty($params['REFNO'])) {
			$calcHash = $this->hmac($secretKey, $result);
			if ($receivedHash === $calcHash) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param $ipnParams
	 * @param $secret_key
	 * @return string
	 */
	public function calculateIpnResponse($ipnParams)
	{
		$resultResponse = '';
		$secret_key = MODULE_PAYMENT_TWOCHECKOUT_API_SECRET_KEY;
		$ipnParamsResponse = [];
		// we're assuming that these always exist, if they don't then the problem is on 2CO side
		$ipnParamsResponse['IPN_PID'][0] = $ipnParams['IPN_PID'][0];
		$ipnParamsResponse['IPN_PNAME'][0] = $ipnParams['IPN_PNAME'][0];
		$ipnParamsResponse['IPN_DATE'] = $ipnParams['IPN_DATE'];
		$ipnParamsResponse['DATE'] = date('YmdHis');

		foreach ($ipnParamsResponse as $key => $val) {
			$resultResponse .= $this->arrayExpand((array)$val);
		}

		return sprintf(
			'<EPAYMENT>%s|%s</EPAYMENT>',
			$ipnParamsResponse['DATE'],
			$this->hmac($secret_key, $resultResponse)
		);
	}

	/**
	 * @param $array
	 *
	 * @return string
	 */
	private function arrayExpand($array)
	{
		$result = '';
		foreach ($array as $key => $value) {
			$size = strlen(stripslashes($value));
			$result .= $size . stripslashes($value);
		}

		return $result;
	}

	/**
	 * @param $key
	 * @param $data
	 * @return string
	 */
	private function hmac($key, $data)
	{
		$b = 64; // byte length for md5
		if (strlen($key) > $b) {
			$key = pack("H*", md5($key));
		}

		$key = str_pad($key, $b, chr(0x00));
		$ipad = str_pad('', $b, chr(0x36));
		$opad = str_pad('', $b, chr(0x5c));
		$k_ipad = $key ^ $ipad;
		$k_opad = $key ^ $opad;

		return md5($k_opad . pack("H*", md5($k_ipad . $data)));
	}

	/**
	 * @param $params
	 * @throws \Exception
	 */
	public function processOrderStatus($params)
	{
		$order = $this->get_zen_order($params['REFNO']);
		$order_status = ($params['FRAUD_STATUS'] && $params['FRAUD_STATUS'] === self::FRAUD_STATUS_DENIED) ?
			self::FRAUD_STATUS_DENIED : $params['ORDERSTATUS'];
		$comment = '2Checkout transaction status status updated to: ' . $order_status;

		switch (trim($order_status)) {
			//fraud status
			case self::FRAUD_STATUS_DENIED:
				zen_update_orders_history($order['orders_id'], '2Checkout transaction status updated to: DENIED/FRAUD', null, $order['orders_status']);
				break;
			case self::FRAUD_STATUS_APPROVED:
			case self::ORDER_STATUS_PENDING:
			case self::ORDER_STATUS_PURCHASE_PENDING:
			case self::ORDER_STATUS_PAYMENT_RECEIVED:
			case self::ORDER_STATUS_PENDING_APPROVAL:
			case self::ORDER_STATUS_PAYMENT_AUTHORIZED:
				zen_update_orders_history($order['orders_id'], $comment, null, $order['orders_status']);
				break;
			case self::ORDER_STATUS_REFUND:
				zen_update_orders_history($order['orders_id'], 'Full amount was refunded from 2Checkout Control Panel', null, $order['orders_status']);
				break;
			case self::ORDER_STATUS_COMPLETE:
			case self::ORDER_STATUS_AUTHRECEIVED:
				if (!$this->isChargeBack($params, $order)) {
					zen_update_orders_history($order['orders_id'], $comment, null, MODULE_PAYMENT_TWOCHECKOUT_API_ORDER_STATUS_ID);
				}
				break;
			default:
				throw new Exception('Cannot handle Ipn message type for message');
		}
	}

	/**
	 * Update status & place a note on the Order
	 * @param $params
	 * @param $order
	 * @return bool
	 */
	private function isChargeBack($params, $order)
	{
		// we need to mock up a message with some params in order to add this note
		if (!empty(trim($params['CHARGEBACK_RESOLUTION']) && trim($params['CHARGEBACK_RESOLUTION']) !== 'NONE') &&
		    !empty(trim($params['CHARGEBACK_REASON_CODE']))) {

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

			$why = isset($reasons[trim($params['CHARGEBACK_REASON_CODE'])]) ?
				$reasons[trim($params['CHARGEBACK_REASON_CODE'])] :
				$reasons['UNKNOWN'];
			$comment = '2Checkout chargeback status is now ' . $params['CHARGEBACK_RESOLUTION'] . '. Reason: ' . $why . '!';
			zen_update_orders_history($order['orders_id'], $comment, null, $order['orders_status']);
			return true;
		}
		return false;
	}

}
