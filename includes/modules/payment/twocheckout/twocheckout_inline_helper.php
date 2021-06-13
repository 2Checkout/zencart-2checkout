<?php
if (!defined('TABLE_2CHECKOUT_INLINE')) {
    define('TABLE_2CHECKOUT_INLINE', DB_PREFIX . '2checkout_inline');
}

class twocheckout_inline_helper
{
    const SIGNATURE_URL = "https://secure.2checkout.com/checkout/api/encrypt/generate/signature";
    const   API_URL = 'https://api.2checkout.com/rest/';
    const   API_VERSION = '6.0';

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

    public $test;

    public function __construct($testOrder = 0)
    {
        $this->test = $testOrder ? 1 : 0;
    }

    /**
     * we check if the orders was placed using our gateway
     * @param $transaction_id
     * @return bool
     */
    public function is_tco_inline_order($transaction_id)
    {
        global $db;
        $transaction_id = zen_db_input(trim($transaction_id));
        $sql = "SELECT * FROM " . TABLE_2CHECKOUT_INLINE . "  WHERE tco_transaction_id = '" . $transaction_id . "'";
        $transaction = $db->Execute($sql);
        if (!$transaction || !$transaction->fields['tco_transaction_id'] || !$transaction->fields['order_id']) {
            return false;
        }
        $sql_order = "SELECT * FROM orders WHERE orders_id = " . $transaction->fields['order_id'];
        $order = $db->Execute($sql_order);

        return $order && $order->fields['payment_module_code'] === 'twocheckout_inline';
    }

    /**
     * @param $sellerId
     * @param $secretKey
     * @return array
     * @throws \Exception
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
        $headers[] = 'X-Avangate-Authentication: code="' . $sellerId . '" date="' . $gmtDate . '" hash="' . $hash . '"';;

        return $headers;
    }

    /**
     * @param $endpoint
     * @param $sellerId
     * @param $secretKey
     * @return mixed
     * @throws \Exception
     */
    public function call($endpoint, $sellerId, $secretKey)
    {
        // if endpoint does not starts or end with a '/' we add it, as the API needs it
        if ($endpoint[0] !== '/') {
            $endpoint = '/' . $endpoint;
        }
        if ($endpoint[-1] !== '/') {
            $endpoint = $endpoint . '/';
        }

        try {
            $url = self::API_URL . self::API_VERSION . $endpoint;

            return $this->make($url, [], $this->getHeaders($sellerId, $secretKey));
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }


    /**
     * @param $sellerId
     * @param $secretWord
     * @param $payload
     * @return mixed
     * @throws \Exception
     */
    public function getInlineSignature($sellerId, $secretWord, $payload)
    {
        $jwtToken = $this->generateJWT($sellerId, $secretWord);
        $headers = [
            'content-type: application/json',
            'cache-control: no-cache',
            'merchant-token: ' . $jwtToken,
        ];

        $response = $this->make(self::SIGNATURE_URL, $payload, $headers, 'POST');
        if (!isset($response['signature'])) {
            throw new Exception('Unable to get proper response from signature generation API');
        }

        return $response['signature'];
    }

    /**
     * @param        $endpoint
     * @param array  $payload
     * @param array  $headers
     * @param string $method
     * @return mixed
     * @throws \Exception
     */
    private function make($endpoint, $payload = [], $headers = [], $method = 'GET')
    {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);

            if ($this->test) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            }

            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
            }
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
     * @param $params
     * @return bool
     */
    public function isIpnResponseValid($params)
    {
        $result = '';
        $secretKey = MODULE_PAYMENT_TWOCHECKOUT_CONVERT_PLUS_SECRET_KEY;
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

    /**
     * @param $ipnParams
     * @param $secret_key
     * @return string
     */
    public function calculateIpnResponse($ipnParams)
    {
        $resultResponse = '';
        $secret_key = MODULE_PAYMENT_TWOCHECKOUT_INLINE_SECRET_KEY;
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
                    zen_update_orders_history($order['orders_id'], $comment, null, MODULE_PAYMENT_TWOCHECKOUT_CONVERT_PLUS_ORDER_STATUS_ID);
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
    }


    /**
     * @param $sellerId
     * @param $secretWord
     * @return string
     */
    private function generateJWT($sellerId, $secretWord)
    {
        $secretWord = html_entity_decode($secretWord);
        $header = $this->encode(json_encode(['alg' => 'HS512', 'typ' => 'JWT']));
        $payload = $this->encode(json_encode(['sub' => $sellerId, 'iat' => time(), 'exp' => time() + 3600]));
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
     * @param $transaction_id
     * @return \queryFactoryResult
     */
    public function get_zen_order($transaction_id)
    {
        global $db;
        $transaction_id = zen_db_input(trim($transaction_id));
        $sql = "SELECT * FROM " . TABLE_2CHECKOUT_INLINE . " WHERE tco_transaction_id = '" . $transaction_id . "'";
        $transaction = $db->Execute($sql);

        $sql_order = "SELECT * FROM orders WHERE orders_id = " . zen_db_input($transaction->fields['order_id']);

        return $db->Execute($sql_order)->fields;
    }

}
