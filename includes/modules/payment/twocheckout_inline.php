<?php /** @noinspection ALL */
require_once('twocheckout/twocheckout_inline_helper.php');

if (!defined('TABLE_2CHECKOUT_INLINE')) {
    define('TABLE_2CHECKOUT_INLINE', DB_PREFIX . '2checkout_inline');
}

/**
 * Class twocheckout_inline
 */
class twocheckout_inline
{
    public  $code;
    public  $title;
    public  $description;
    public  $enabled;
    private $tco_helper;

    function __construct()
    {
        global $order;

        $this->tco_helper = new twocheckout_inline_helper(MODULE_PAYMENT_TWOCHECKOUT_INLINE_TEST);
        $this->code = 'twocheckout_inline';
        $this->title = MODULE_PAYMENT_TWOCHECKOUT_INLINE_TEXT_TITLE;
        $this->description = MODULE_PAYMENT_TWOCHECKOUT_INLINE_TEXT_DESCRIPTION;
        $this->sort_order = defined('MODULE_PAYMENT_TWOCHECKOUT_INLINE_SORT_ORDER') ? MODULE_PAYMENT_TWOCHECKOUT_INLINE_SORT_ORDER : null;
        $this->enabled = (defined('MODULE_PAYMENT_TWOCHECKOUT_INLINE_STATUS') && MODULE_PAYMENT_TWOCHECKOUT_INLINE_STATUS == 'True');

        if (defined('MODULE_PAYMENT_TWOCHECKOUT_INLINE_ORDER_STATUS_ID') && (int)MODULE_PAYMENT_TWOCHECKOUT_INLINE_ORDER_STATUS_ID > 0) {
            $this->order_status = MODULE_PAYMENT_TWOCHECKOUT_INLINE_ORDER_STATUS_ID;
        }

        if (is_object($order)) {
            $this->update_status();
        }
    }

    function update_status()
    {
        global $order, $db;

        if ($this->enabled && (int)MODULE_PAYMENT_TWOCHECKOUT_INLINE_ZONE > 0 && isset($order->delivery['country']['id'])) {
            $check_flag = false;
            $check = $db->Execute("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_TWOCHECKOUT_INLINE_ZONE . "' and zone_country_id = '" . (int)$order->delivery['country']['id'] . "' order by zone_id");
            while (!$check->EOF) {
                if ($check->fields['zone_id'] < 1) {
                    $check_flag = true;
                    break;
                } elseif ($check->fields['zone_id'] == $order->delivery['zone_id']) {
                    $check_flag = true;
                    break;
                }
                $check->MoveNext();
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
        return [
            'id'     => $this->code,
            'module' => $this->title
        ];
    }

    function pre_confirmation_check()
    {
        return false;
    }

    function confirmation()
    {
        return false;
    }

    function process_button()
    {

        global $order, $currencies;
        $order_id = $_SESSION['cart'] ? $_SESSION['cart']->cartID : uniqid('', true);
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
        $total = number_format(($currencies->get_value($order->info['currency']) *
            $order->info['total']), 2, '.', '');
        $payload['products'][] = [
            'type'     => 'PRODUCT',
            'name'     => 'Cart_' . $order_id,
            'price'    => $total,
            'tangible' => 0,
            'qty'      => 1,
        ];
        $payload['return-method'] = [
            'type' => 'redirect',
            'url'  => zen_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL'),
        ];

        $payload['currency'] = strtoupper($order->info['currency']);
        $payload['language'] = strtoupper($_SESSION['languages_code']);
        $payload['test'] = MODULE_PAYMENT_TWOCHECKOUT_INLINE_TEST == 'True' ? 1 : 0;
        $payload['order-ext-ref'] = $order_id;
        $payload['customer-ext-ref'] = $order->customer['email_address'];
        $payload['src'] = 'ZENCART_1_5';
        $payload['mode'] = 'DYNAMIC';
        $payload['dynamic'] = '1';
        $payload['country'] = strtoupper($order->billing['country']['iso_code_2']);
        $payload['merchant'] = MODULE_PAYMENT_TWOCHECKOUT_INLINE_SELLER_ID;
        $payload['shipping_address'] = ($shippingAddressData);
        $payload['billing_address'] = ($billingAddressData);

        $payload['signature'] = $this->tco_helper->getInlineSignature(
            MODULE_PAYMENT_TWOCHECKOUT_INLINE_SELLER_ID,
            MODULE_PAYMENT_TWOCHECKOUT_INLINE_SECRET_WORD,
            $payload);

        return "<script>
            let payload = " . json_encode($payload) . ";
          $('body').on('click','#btn_submit',function (e){
              e.preventDefault();
              initInline();
          })
            function initInline() {
                console.log('run inline');
                (function (document, src, libName, config) {
                    if (window.hasOwnProperty(libName)) {
                        delete window[libName];
                    }
                    let script = document.createElement('script');
                    script.src = src;
                    script.async = true;
                    let firstScriptElement = document.getElementsByTagName('script')[0];
                    script.onload = function () {
                        for (let namespace in config) {
                            if (config.hasOwnProperty(namespace)) {
                                window[libName].setup.setConfig(namespace, config[namespace]);
                            }
                        }
                        window[libName].register();
                        TwoCoInlineCart.setup.setMerchant(payload['merchant']);
                        TwoCoInlineCart.setup.setMode('DYNAMIC');
                        TwoCoInlineCart.register();
                        TwoCoInlineCart.products.removeAll();
                        TwoCoInlineCart.cart.setAutoAdvance(true);
                        TwoCoInlineCart.cart.setReset(true); // erase previous cart sessions
                        TwoCoInlineCart.cart.setCurrency(payload['currency']);
                        TwoCoInlineCart.cart.setLanguage(payload['language']);
                        TwoCoInlineCart.cart.setReturnMethod(payload['return-method']);
                        TwoCoInlineCart.cart.setTest(payload['test']);
                        TwoCoInlineCart.cart.setOrderExternalRef(payload['order-ext-ref']);
                        TwoCoInlineCart.cart.setExternalCustomerReference(payload['customer-ext-ref']);
                        TwoCoInlineCart.cart.setSource(payload['src']);
                        TwoCoInlineCart.products.addMany(payload['products']);
                        TwoCoInlineCart.billing.setData(payload['billing_address']);
                        TwoCoInlineCart.billing.setCompanyName(payload['billing_address']['company-name']);
                        TwoCoInlineCart.shipping.setData(payload['shipping_address']);
                        TwoCoInlineCart.cart.setSignature(payload['signature']);
                        TwoCoInlineCart.cart.checkout();
                    };
                    firstScriptElement.parentNode.insertBefore(script, firstScriptElement);
                })(document, 'https://secure.2checkout.com/checkout/client/twoCoInlineCart.js', 'TwoCoInlineCart',
                    {'app': {'merchant': payload['merchant']}, 'cart': {'host': 'https:\/\/secure.2checkout.com'}}
                );
            }
            </script>";
    }

    function before_process()
    {
        return false;
    }

    function after_process()
    {
        global $insert_id;
        $refno = $_GET['refno'];
        $order_ext = $_GET['order-ext-ref'];
        zen_db_perform(TABLE_2CHECKOUT_INLINE, [
            'tco_transaction_id' => $refno,
            'order_id'           => $insert_id,
            'cart_id'            => $order_ext
        ]);

        $new_status = 1; //default status: pending
        $api_response = $this->tco_helper->call(
            'orders/' . $refno,
            MODULE_PAYMENT_TWOCHECKOUT_INLINE_SELLER_ID,
            MODULE_PAYMENT_TWOCHECKOUT_INLINE_SECRET_KEY
        );
        if (!empty($api_response['Status']) && isset($api_response['Status'])) {
            if (in_array($api_response['Status'], ['AUTHRECEIVED', 'COMPLETE'])) {
                $new_status = MODULE_PAYMENT_TWOCHECKOUT_INLINE_ORDER_STATUS_ID;
            }
        }
        $comment = '2Checkout transaction ID: ' . $refno;
        zen_update_orders_history($insert_id, $comment, null, $new_status);

        return false;
    }

    function get_error()
    {
        return false;
    }

    function check()
    {
        global $db;
        if (!isset($this->_check)) {
            $check_query = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_TWOCHECKOUT_INLINE_STATUS'");
            $this->_check = $check_query->RecordCount();
        }

        return $this->_check;
    }

    function install()
    {
        global $db, $messageStack;
        if (defined('MODULE_PAYMENT_TWOCHECKOUT_INLINE_STATUS')) {
            $messageStack->add_session('2Checkout Inline module already installed.', 'error');
            zen_redirect(zen_href_link(FILENAME_MODULES, 'set=payment&module=twocheckout_inline', 'NONSSL'));

            return 'failed';
        }
        $this->createTable();

        $ipn_url = HTTPS_SERVER . DIR_WS_HTTPS_CATALOG . 'twocheckout_inline_ipn.php';
        $style = 'style="color:red"';

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) 
        VALUES ('Test mode', 'MODULE_PAYMENT_TWOCHECKOUT_INLINE_TEST', 'True', 'Place test orders!', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) 
        VALUES ('Enable Twocheckout Inline', 'MODULE_PAYMENT_TWOCHECKOUT_INLINE_STATUS', 'True', 'Pay with credit card using 2Checkout Inline?', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
        VALUES ('Seller Id', 'MODULE_PAYMENT_TWOCHECKOUT_INLINE_SELLER_ID', '123456789', 'Get your SELLER ID from your 2Checkout account!', '6', '1', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
        VALUES ('Secret key', 'MODULE_PAYMENT_TWOCHECKOUT_INLINE_SECRET_KEY', 'secret key', 'Get your SECRET KEY from your 2Checkout account!', '6', '2', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
        VALUES ('Secret word', 'MODULE_PAYMENT_TWOCHECKOUT_INLINE_SECRET_WORD', 'secret word', 'Get your SECRET WORD from your 2Checkout account!', '6', '3', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) 
        VALUES ('Payment Zone', 'MODULE_PAYMENT_TWOCHECKOUT_INLINE_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '4', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Set Order Status', 'MODULE_PAYMENT_TWOCHECKOUT_INLINE_ORDER_STATUS_ID', '2', 'Set the status of orders made with this payment module that have completed payment to this value<br />(\'Processing\' recommended)', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
        VALUES ('Sort order of display.', 'MODULE_PAYMENT_TWOCHECKOUT_INLINE_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '5', '0', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) 
        VALUES ('IPN url', 'MODULE_PAYMENT_TWOCHECKOUT_INLINE_IPN_URL', '$ipn_url', 'Enter this URL into your 2Checkout account (Integrations->Webhooks&Api->IPN settings) <br><small $style >Do not edit this field!</small>', '6', '1','tco_inline_edit_readonly_text( ', now())");
    }


    function remove()
    {
        global $db;
        $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    function keys()
    {
        return [
            'MODULE_PAYMENT_TWOCHECKOUT_INLINE_SELLER_ID',
            'MODULE_PAYMENT_TWOCHECKOUT_INLINE_SECRET_KEY',
            'MODULE_PAYMENT_TWOCHECKOUT_INLINE_SECRET_WORD',
            'MODULE_PAYMENT_TWOCHECKOUT_INLINE_STATUS',
            'MODULE_PAYMENT_TWOCHECKOUT_INLINE_ZONE',
            'MODULE_PAYMENT_TWOCHECKOUT_INLINE_ORDER_STATUS_ID',
            'MODULE_PAYMENT_TWOCHECKOUT_INLINE_IPN_URL',
            'MODULE_PAYMENT_TWOCHECKOUT_INLINE_TEST',
            'MODULE_PAYMENT_TWOCHECKOUT_INLINE_SORT_ORDER'
        ];
    }

    protected function createTable()
    {
        global $db, $sniffer;
        if (!$sniffer->table_exists(TABLE_2CHECKOUT_INLINE)) {
            $sql = "CREATE TABLE `" . TABLE_2CHECKOUT_INLINE . "` (
              `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
              `cart_id` varchar(40) default NULL,
              `tco_transaction_id` varchar(40) default NULL,
              `order_id` varchar(40) default NULL,
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`)
            )";
            $db->Execute($sql);
        }
    }
}

function tco_inline_edit_readonly_text($text, $key = '')
{
    return tco_inline_zen_draw_input_field($key, $text);
}

function tco_inline_zen_draw_input_field($name, $value = '', $parameters = '', $type = 'text', $reinsert_value = true, $required = false)
{
    // Give an observer the opportunity to **totally** override this function's operation.
    $field = false;
    $GLOBALS['zco_notifier']->notify(
        'NOTIFY_ZEN_DRAW_INPUT_FIELD_OVERRIDE',
        [
            'name'           => $name,
            'value'          => $value,
            'parameters'     => $parameters,
            'type'           => $type,
            'reinsert_value' => $reinsert_value,
            'required'       => $required,
        ],
        $field
    );
    if ($field !== false) {
        return $field;
    }
    $field = '<input type="' . zen_output_string($type) . '" name="' . zen_sanitize_string(zen_output_string($name)) . '"';
    if (isset($GLOBALS[$name]) && is_string($GLOBALS[$name]) && $reinsert_value == true) {
        $field .= ' value="' . zen_output_string(stripslashes($GLOBALS[$name])) . '"';
    } elseif (zen_not_null($value)) {
        $field .= ' value="' . zen_output_string($value) . '"';
    }
    if (zen_not_null($parameters)) {
        $field .= ' ' . $parameters;
    }
    $field .= ' class="form-control" ';
    $field .= ' readonly = "readonly" />';
    // Give an observer the opportunity to modify the just-rendered field.
    $GLOBALS['zco_notifier']->notify(
        'NOTIFY_ZEN_DRAW_INPUT_FIELD',
        [
            'name'           => $name,
            'value'          => $value,
            'parameters'     => $parameters,
            'type'           => $type,
            'reinsert_value' => $reinsert_value,
            'required'       => $required,
        ],
        $field
    );
    if ($required == true && !empty(TEXT_FIELD_REQUIRED)) {
        $field .= TEXT_FIELD_REQUIRED;
    }

    return $field;
}
