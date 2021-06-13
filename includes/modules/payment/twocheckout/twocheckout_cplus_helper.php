<?php
if (!defined('TABLE_2CHECKOUT_CONVERT_PLUS')) {
    define('TABLE_2CHECKOUT_CONVERT_PLUS', DB_PREFIX . '2checkout_convert_plus');
}
class twocheckout_cplus_helper
{
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


    /**
     * we check if the orders was placed using our gateway
     * @param $transaction_id
     * @return bool
     */
    public function is_cplus_order($transaction_id)
    {
        $transaction_id = trim($transaction_id);
        global $db;
        $sql = "SELECT * FROM ". TABLE_2CHECKOUT_CONVERT_PLUS."  WHERE tco_transaction_id = '" . $transaction_id."'";
        $transaction = $db->Execute($sql);
        if (!$transaction || !$transaction->fields['tco_transaction_id'] || !$transaction->fields['order_id']) {
            return false;
        }
        $sql_order = "SELECT * FROM orders WHERE orders_id = " . $transaction->fields['order_id'];
        $order = $db->Execute($sql_order);

        return $order && $order->fields['payment_module_code'] === 'twocheckout_convert_plus';
    }

    /**
     * @param $transaction_id
     * @return \queryFactoryResult
     */
    public function get_zen_order($transaction_id)
    {
        global $db;

        $sql = "SELECT * FROM " . TABLE_2CHECKOUT_CONVERT_PLUS . " WHERE tco_transaction_id = '" . trim($transaction_id)."'";
        $transaction = $db->Execute($sql);

        $sql_order = "SELECT * FROM orders WHERE orders_id = " . $transaction->fields['order_id'];

        return $db->Execute($sql_order)->fields;
    }

    /**
     * @param $refno
     * @return mixed
     * @throws \Exception
     */
    public function call($refno)
    {

        try {
            $url = 'https://api.2checkout.com/rest/6.0/orders/' . $refno . '/';
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getHeaders());
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

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
     * @return array
     */
    private function getHeaders()
    {
        $sellerId = MODULE_PAYMENT_TWOCHECKOUT_CONVERT_PLUS_SELLER_ID;
        $secretKey = MODULE_PAYMENT_TWOCHECKOUT_CONVERT_PLUS_SECRET_KEY;

        $gmtDate = gmdate('Y-m-d H:i:s');
        $string = strlen($sellerId) . $sellerId . strlen($gmtDate) . $gmtDate;
        $hash = hash_hmac('md5', $string, $secretKey);

        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Accept: application/json';
        $headers[] = 'X-Avangate-Authentication: code="' . $sellerId . '" date="' . $gmtDate . '" hash="' . $hash . '"';

        return $headers;
    }


    /**
     * @param $order
     * @param $total
     * @param $new_order_ref
     * @return string
     * @throws \Exception
     */
    public function get_order_params($order, $total, $new_order_ref)
    {
        $data = [
            'prod'             => 'Cart_' . $new_order_ref,
            'price'            => $total,
            'qty'              => 1,
            'type'             => 'PRODUCT',
            'tangible'         => 0,
            'src'              => 'ZENCART_1_5',
            'return-type'      => 'redirect',
            'return-url'       => zen_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL'),
            'expiration'       => time() + (3600 * 5),
            'order-ext-ref'    => $new_order_ref,
            'item-ext-ref'     => date('YmdHis'),
            'customer-ext-ref' => $order->customer['email_address'],
            'currency'         => strtolower($order->info['currency']),
            'language'         => $_SESSION['languages_code'],
            'test'             => MODULE_PAYMENT_TWOCHECKOUT_CONVERT_PLUS_TEST == 'True' ? 1 : 0,
            'merchant'         => MODULE_PAYMENT_TWOCHECKOUT_CONVERT_PLUS_SELLER_ID,
            'dynamic'          => 1,
            'email'            => $order->customer['email_address'],
            'name'             => $order->billing['firstname'] . ' ' . $order->customer['lastname'],
            'phone'            => preg_replace('/\D/', '', $order->customer['telephone']),
            'country'          => $order->billing['country']['iso_code_2'],
            'state'            => $order->billing['state'] ? $order->billing['state'] : 'XX',
            'address'          => $order->billing['street_address'],
            'address2'         => $order->billing['suburb'],
            'city'             => $order->billing['city'],
            'company-name'     => $order->billing['company'],
            'zip'              => $order->billing['postcode'],
            'ship-email'       => $order->customer['email_address'],
            'ship-name'        => $order->delivery['firstname'] . ' ' . $order->customer['lastname'],
            'ship-country'     => $order->delivery['country']['iso_code_2'],
            'ship-state'       => $order->delivery['state'] ? $order->billing['state'] : 'XX',
            'ship-city'        => $order->delivery['city'],
            'ship-address'     => $order->delivery['street_address'],
            'ship-address2'    => $order->delivery['suburb']
        ];

        $data['signature'] = $this->get_signature($data);

        return http_build_query($data);
    }

    /**
     * @param $payload
     * @return mixed
     * @throws \Exception
     */
    public function get_signature($payload)
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => 'https://secure.2checkout.com/checkout/api/encrypt/generate/signature',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'content-type: application/json',
                'cache-control: no-cache',
                'merchant-token: ' . $this->generate_jwt_token(),
            ],
        ]);
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            throw new Exception(sprintf('Unable to get proper response from signature generation API. In file %s at line %s', __FILE__, __LINE__));
        }

        $response = json_decode($response, true);
        if (JSON_ERROR_NONE !== json_last_error() || !isset($response['signature'])) {
            throw new Exception(sprintf('Unable to get proper response from signature generation API. Signature not set. In file %s at line %s', __FILE__, __LINE__));
        }

        return $response['signature'];
    }

    /**
     * @param $seller_id
     * @param $secret_word
     * @return string
     */
    private function generate_jwt_token()
    {
        $header = $this->encode(json_encode(['alg' => 'HS512', 'typ' => 'JWT']));
        $payload = $this->encode(json_encode([
            'sub' => MODULE_PAYMENT_TWOCHECKOUT_CONVERT_PLUS_SELLER_ID,
            'iat' => time(),
            'exp' => time() + 3600
        ]));
        $signature = $this->encode(hash_hmac('sha512', "$header.$payload", MODULE_PAYMENT_TWOCHECKOUT_CONVERT_PLUS_SECRET_WORD, true));

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
     * @param $params
     * @param $secretKey
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
    public function calculateIpnResponse($ipnParams)
    {
        $resultResponse = '';
        $secret_key = MODULE_PAYMENT_TWOCHECKOUT_CONVERT_PLUS_SECRET_KEY;
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

        return false;
    }
}
