<?php

chdir( '../..' );
require( 'includes/application_top.php' );
require_once( DIR_WS_MODULES . 'payment/twocheckout/twocheckout_api_helper.php' );
if ( ! defined( 'TABLE_2CHECKOUT_API' ) ) {
	define( 'TABLE_2CHECKOUT_API', DB_PREFIX . '2checkout_api' );
}

class zen_tco {

	private $db;
	private $status = MODULE_PAYMENT_TWOCHECKOUT_API_ORDER_STATUS_ID;
	private $helper;
	private $order;
	private $payment_modules;
	private $shipping_modules;
	private $order_total_modules;
	private $test_order = MODULE_PAYMENT_TWOCHECKOUT_API_TEST;

	/**
	 * zen_tco constructor.
	 *
	 * @param $db
	 * @param $order
	 * @param $payment_modules
	 * @param $shipping_modules
	 * @param $order_total_modules
	 */
	public function __construct($db, $order, $payment_modules, $shipping_modules, $order_total_modules){
		$this->helper = new twocheckout_api_helper();
		$this->db = $db;
		$this->order = $order;
		$this->payment_modules = $payment_modules;
		$this->shipping_modules = $shipping_modules;
		$this->order_total_modules = $order_total_modules;
	}

	/**
	 * redirect to shopping cart if 3ds
	 */
	public function checkRedirect3DS(){
		if ( isset( $_REQUEST['threeds'] ) ) {
			zen_redirect( zen_href_link( FILENAME_SHOPPING_CART ) );
		}
	}

	/**
	 * redirect to success page if payment was a success
	 */
	public function checkSuccessPayment(){
		if ( isset( $_REQUEST['payment_success'] ) ) {
			$_SESSION['cart']->reset( true );
			unset( $_SESSION['sendto'] );
			unset( $_SESSION['billto'] );
			unset( $_SESSION['shipping'] );
			unset( $_SESSION['payment'] );
			unset( $_SESSION['comments'] );
			zen_redirect( zen_href_link( FILENAME_CHECKOUT_SUCCESS, '', 'SSL' ) );
		}
	}

	/**
	 * @return array
	 */
	public function getOrderParams(){
		$lineitem_total  = $this->order->info['total'];
		$order_totals = $this->order_total_modules->process();
		$this->payment_modules->before_process();
		foreach ( $order_totals as $ot ) {
			if ( $ot['code'] == 'ot_total' ) {
				$lineitem_total = $ot['value'];
			}
		}
		$insert_id = $this->order->create( $order_totals );
		$this->payment_modules->after_order_create( $insert_id );
		$this->order->create_add_products( $insert_id );
		$order_id = $insert_id;
		$this->order->send_order_email($insert_id, 2);
		$type  = ( $this->test_order == 'True' ) ? 'TEST' : 'EES_TOKEN_PAYMENT';
		$token = $_REQUEST['ess_token'];

		return [
			'Currency'          => $this->order->info['currency'],
			'Language'          => strtolower( substr($_SESSION['languages_code'] , 0, 2 ) ),
			'Country'           => $this->order->billing['country']['iso_code_2'],
			'CustomerIP'        => $this->helper->getCustomerIp(),
			'Source'            => 'ZENCART_1_5',
			'ExternalReference' => $order_id,
			'Items'             => $this->helper->getItem( $order_id, number_format( $lineitem_total, 2, '.', '' ) ),
			'BillingDetails'    => $this->helper->getBillingDetails( $this->order ),
			'PaymentDetails'    => $this->helper->getPaymentDetails( $type, $token, $this->order->info['currency'] ),
		];
	}

	/**
	 * check cart on session
	 */
	public function checkCart(){
		if ( isset( $_SESSION['cart']->cartID ) && $_SESSION['cartID'] ) {
			if ( $_SESSION['cart']->cartID != $_SESSION['cartID'] ) {
				$this->payment_modules->clear_payment();
				$this->order_total_modules->clear_posts();
				unset( $_SESSION['payment'] );
				unset( $_SESSION['shipping'] );
				zen_redirect( zen_href_link( FILENAME_CHECKOUT_SHIPPING, '', 'SSL' ) );
			}
		}
	}

	/**
	 * check if order has products
	 */
	public function checkHasProducts(){
		if ( sizeof( $this->order->products ) < 1 ) {
			zen_redirect( zen_href_link( FILENAME_SHOPPING_CART ) );
		}
	}

	/**
	 * set payment method to order
	 */
	public function setTcoApiPaymentMethod(){
		$this->order->info['payment_method']  = MODULE_PAYMENT_TWOCHECKOUT_API_TEXT_TITLE;
		$this->order->info['payment_module_code'] = 'twocheckout_api';
	}

	/**
	 * @param $helper
	 * @param $order_params
	 *
	 * @return mixed
	 */
	public function callApi($order_params){
		$json_response = null;
		$success_url  = HTTP_SERVER . DIR_WS_CATALOG . 'extras/twocheckout_api/callback.php?payment_success=1';
		$test = ( $this->test_order == 'True' ) ? true : false;
		$api_response = $this->helper->getApiResponse($order_params, $test);
		if(!empty($api_response["RefNo"])){
			$json_response = $this->helper->getJsonResponseFromApi($api_response, $success_url);
			$this->updateOrderHistory($order_params['ExternalReference'], $api_response["RefNo"]);
			$this->updateTcoTable($order_params['ExternalReference'], $api_response["RefNo"] );
		}else{
			$json_response = $api_response;
		}
		return $json_response;
	}

	/**
	 * @param $order_id
	 * @param $refno
	 */
	private function updateOrderHistory($order_id, $refno ) {
		$this->db->Execute( "update " . TABLE_ORDERS . " set orders_status = " . (int) $this->status . ", last_modified = now()  where orders_id = " . (int) $order_id );
		zen_db_perform(TABLE_ORDERS_STATUS_HISTORY,
			[
				'orders_id'         => (int) $order_id,
				'orders_status_id'  => (int) $this->status,
				'date_added'        => 'now()',
				'customer_notified' => ( SEND_EMAILS == 'true' ) ? '1' : '0',
				'comments'          => '2Checkout transaction ID: ' . $refno
			] );
	}

	/**
	 * @param $order_id
	 * @param $refno
	 */
	private function updateTcoTable($order_id, $refno){
		zen_db_perform( TABLE_2CHECKOUT_API, array(
			'cart_id'            => $_SESSION['cart']->cartID,
			'order_id'           => $order_id,
			'tco_transaction_id' => $refno
		) );
	}
}