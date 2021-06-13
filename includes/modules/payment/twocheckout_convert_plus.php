<?php
/**
 * include the helper
 */
require_once('twocheckout/twocheckout_cplus_helper.php');

if (!defined('TABLE_2CHECKOUT_CONVERT_PLUS')) {
    define('TABLE_2CHECKOUT_CONVERT_PLUS', DB_PREFIX . '2checkout_convert_plus');
}

/**
 * Class twocheckout_convert_plus
 */
class twocheckout_convert_plus
{
    public $code;
    public $title;
    public $description;
    public $enabled;
    public $tco_helper;

    function __construct()
    {
        global $order;

        $this->tco_helper = new twocheckout_cplus_helper();
        $this->code = 'twocheckout_convert_plus';
        $this->title = MODULE_PAYMENT_TWOCHECKOUT_CONVERT_PLUS_TEXT_TITLE;
        $this->description = MODULE_PAYMENT_TWOCHECKOUT_CONVERT_PLUS_TEXT_DESCRIPTION;
        $this->sort_order = defined('MODULE_PAYMENT_TWOCHECKOUT_CONVERT_PLUS_SORT_ORDER') ? MODULE_PAYMENT_TWOCHECKOUT_CONVERT_PLUS_SORT_ORDER : null;
        $this->enabled = (defined('MODULE_PAYMENT_TWOCHECKOUT_CONVERT_PLUS_STATUS') && MODULE_PAYMENT_TWOCHECKOUT_CONVERT_PLUS_STATUS == 'True');

        if (defined('MODULE_PAYMENT_TWOCHECKOUT_CONVERT_PLUS_ORDER_STATUS_ID') && (int)MODULE_PAYMENT_TWOCHECKOUT_CONVERT_PLUS_ORDER_STATUS_ID > 0) {
            $this->order_status = MODULE_PAYMENT_TWOCHECKOUT_CONVERT_PLUS_ORDER_STATUS_ID;
        }

        if (is_object($order)) {
            $this->update_status();
        }
    }

    function update_status()
    {
        global $order, $db;

        if ($this->enabled && (int)MODULE_PAYMENT_TWOCHECKOUT_CONVERT_PLUS_ZONE > 0 && isset($order->delivery['country']['id'])) {
            $check_flag = false;
            $check = $db->Execute("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_TWOCHECKOUT_CONVERT_PLUS_ZONE . "' and zone_country_id = '" . (int)$order->delivery['country']['id'] . "' order by zone_id");
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
        return false;
    }

    function before_process()
    {
        global $order, $currencies;
        if (isset($_GET['refno']) && $_GET['refno'] !== ''
            && isset($_GET['order-ext-ref']) && $_GET['order-ext-ref'] !== '') {
            $_SESSION['tco_refno'] = $_GET['refno'];
            $id = $_GET['order-ext-ref'];
            zen_db_perform(
                TABLE_2CHECKOUT_CONVERT_PLUS,
                ['tco_transaction_id' => $_GET['refno']],
                'update',
                'id = ' . $id
            );
        } else {
            $total = number_format(($currencies->get_value($order->info['currency']) *
                $order->info['total']), 2, '.', '');
            $cartId = $_SESSION['cart'] ? $_SESSION['cart']->cartID : uniqid('',true);
            zen_db_perform(TABLE_2CHECKOUT_CONVERT_PLUS, ['cart_id' => $cartId]);
            $tco_query_strings = $this->tco_helper->get_order_params($order, $total, zen_db_insert_id());
            zen_redirect('https://secure.2checkout.com/checkout/buy/?' . $tco_query_strings);
            exit();
        }

        return false;
    }

    function after_process()
    {
        global $db, $insert_id;
        $refno = $_SESSION['tco_refno'];
        unset($_SESSION['tco_refno']);

        $check = $db->Execute("select id from " . TABLE_2CHECKOUT_CONVERT_PLUS . " where  tco_transaction_id = '" . zen_db_prepare_input($refno) . "' limit 1");
        zen_db_perform(
            TABLE_2CHECKOUT_CONVERT_PLUS,
            ['order_id' => $insert_id],
            'update',
            'id = ' . $check->fields['id']
        );
        $new_status = 1; //default status: pending
        $api_response = $this->tco_helper->call($refno);
        if (!empty($api_response['Status']) && isset($api_response['Status'])) {
            if (in_array($api_response['Status'], ['AUTHRECEIVED', 'COMPLETE'])) {
                $new_status = MODULE_PAYMENT_TWOCHECKOUT_CONVERT_PLUS_ORDER_STATUS_ID;
            }
        }
        $comment = '2Checkout transaction ID: <strong>' . $refno . '</strong>';
        zen_update_orders_history($insert_id, $comment, null, $new_status);

        return true;

    }

    function get_error()
    {
        return false;
    }

    function check()
    {
        global $db;
        if (!isset($this->_check)) {
            $check_query = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_TWOCHECKOUT_CONVERT_PLUS_STATUS'");
            $this->_check = $check_query->RecordCount();
        }

        return $this->_check;
    }

    function install()
    {
        global $db, $messageStack;
        if (defined('MODULE_PAYMENT_TWOCHECKOUT_CONVERT_PLUS_STATUS')) {
            $messageStack->add_session('2Checkout Convert Plus module already installed.', 'error');
            zen_redirect(zen_href_link(FILENAME_MODULES, 'set=payment&module=twocheckout_convert_plus', 'NONSSL'));

            return 'failed';
        }
        $this->createTable();

        $ipn_url = HTTPS_SERVER . DIR_WS_HTTPS_CATALOG . 'twocheckout_cplus_ipn.php';
        $style = 'style="color:red"';

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) 
        VALUES ('Test mode', 'MODULE_PAYMENT_TWOCHECKOUT_CONVERT_PLUS_TEST', 'True', 'Place test orders!', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) 
        VALUES ('Enable Twocheckout Convert Plus', 'MODULE_PAYMENT_TWOCHECKOUT_CONVERT_PLUS_STATUS', 'True', 'Pay with credit card using 2Checkout Convert Plus?', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
        VALUES ('Seller Id', 'MODULE_PAYMENT_TWOCHECKOUT_CONVERT_PLUS_SELLER_ID', '123456789', 'Get your SELLER ID from your 2Checkout account!', '6', '1', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
        VALUES ('Secret key', 'MODULE_PAYMENT_TWOCHECKOUT_CONVERT_PLUS_SECRET_KEY', 'secret key', 'Get your SECRET KEY from your 2Checkout account!', '6', '2', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
        VALUES ('Secret word', 'MODULE_PAYMENT_TWOCHECKOUT_CONVERT_PLUS_SECRET_WORD', 'secret word', 'Get your SECRET WORD from your 2Checkout account!', '6', '3', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) 
        VALUES ('Payment Zone', 'MODULE_PAYMENT_TWOCHECKOUT_CONVERT_PLUS_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '4', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Set Order Status', 'MODULE_PAYMENT_TWOCHECKOUT_CONVERT_PLUS_ORDER_STATUS_ID', '2', 'Set the status of orders made with this payment module that have completed payment to this value<br />(\'Processing\' recommended)', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
        VALUES ('Sort order of display.', 'MODULE_PAYMENT_TWOCHECKOUT_CONVERT_PLUS_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '5', '0', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) 
        VALUES ('IPN url', 'MODULE_PAYMENT_TWOCHECKOUT_CONVERT_PLUS_IPN_URL', '$ipn_url', 'Enter this URL into your 2Checkout account (Integrations->Webhooks&Api->IPN settings) <br><small $style >Do not edit this field!</small>', '6', '1','tco_cplus_edit_readonly_text( ', now())");
    }


    function remove()
    {
        global $db;
        $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    function keys()
    {
        return [
            'MODULE_PAYMENT_TWOCHECKOUT_CONVERT_PLUS_SELLER_ID',
            'MODULE_PAYMENT_TWOCHECKOUT_CONVERT_PLUS_SECRET_KEY',
            'MODULE_PAYMENT_TWOCHECKOUT_CONVERT_PLUS_SECRET_WORD',
            'MODULE_PAYMENT_TWOCHECKOUT_CONVERT_PLUS_STATUS',
            'MODULE_PAYMENT_TWOCHECKOUT_CONVERT_PLUS_ZONE',
            'MODULE_PAYMENT_TWOCHECKOUT_CONVERT_PLUS_ORDER_STATUS_ID',
            'MODULE_PAYMENT_TWOCHECKOUT_CONVERT_PLUS_IPN_URL',
            'MODULE_PAYMENT_TWOCHECKOUT_CONVERT_PLUS_TEST',
            'MODULE_PAYMENT_TWOCHECKOUT_CONVERT_PLUS_SORT_ORDER'
        ];
    }

    protected function createTable()
    {
        global $db, $sniffer;
        if (!$sniffer->table_exists(TABLE_2CHECKOUT_CONVERT_PLUS)) {
            $sql = "CREATE TABLE `" . TABLE_2CHECKOUT_CONVERT_PLUS . "` (
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

function tco_cplus_edit_readonly_text($text, $key = '')
{
    return tco_cplus_zen_draw_input_field($key, $text);
}

function tco_cplus_zen_draw_input_field($name, $value = '', $parameters = '', $type = 'text', $reinsert_value = true, $required = false)
{
    // -----
    // Give an observer the opportunity to **totally** override this function's operation.
    //
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
    // -----
    // Give an observer the opportunity to modify the just-rendered field.
    //
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
