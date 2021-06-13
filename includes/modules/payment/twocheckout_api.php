<?php
/**
 * include the helper
 */
require_once('twocheckout/twocheckout_api_helper.php');

if (!defined('TABLE_2CHECKOUT_API')) {
	define('TABLE_2CHECKOUT_API', DB_PREFIX . '2checkout_api');
}

/**
 * Class twocheckout_api
 */
class twocheckout_api
{
	public $code;
	public $title;
	public $description;
	public $enabled;
	public $tco_helper;

	function __construct()
	{
		global $order;

		$this->tco_helper = new twocheckout_api_helper();
		$this->code = 'twocheckout_api';
		$this->title = MODULE_PAYMENT_TWOCHECKOUT_API_TEXT_TITLE;
		$this->description = MODULE_PAYMENT_TWOCHECKOUT_API_TEXT_DESCRIPTION;
		$this->sort_order = defined('MODULE_PAYMENT_TWOCHECKOUT_API_SORT_ORDER') ? MODULE_PAYMENT_TWOCHECKOUT_API_SORT_ORDER : null;
		$this->enabled = (defined('MODULE_PAYMENT_TWOCHECKOUT_API_STATUS') && MODULE_PAYMENT_TWOCHECKOUT_API_STATUS == 'True');

		if (defined('MODULE_PAYMENT_TWOCHECKOUT_API_ORDER_STATUS_ID') && (int)MODULE_PAYMENT_TWOCHECKOUT_API_ORDER_STATUS_ID > 0) {
			$this->order_status = MODULE_PAYMENT_TWOCHECKOUT_API_ORDER_STATUS_ID;
		}

		if (is_object($order)) {
			$this->update_status();
		}

		$this->form_action_url = HTTP_SERVER.DIR_WS_CATALOG.'extras/twocheckout_api/2payjs.php';
	}

	function update_status()
	{
		global $order, $db;

		if ($this->enabled && (int)MODULE_PAYMENT_TWOCHECKOUT_API_ZONE > 0 && isset($order->delivery['country']['id'])) {
			$check_flag = false;
			$check = $db->Execute("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_TWOCHECKOUT_API_ZONE . "' and zone_country_id = '" . (int)$order->delivery['country']['id'] . "' order by zone_id");
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
		return false;
	}

	function after_process()
	{
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
			$check_query = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_TWOCHECKOUT_API_STATUS'");
			$this->_check = $check_query->RecordCount();
		}

		return $this->_check;
	}

	function install()
	{
		global $db, $messageStack;
		if (defined('MODULE_PAYMENT_TWOCHECKOUT_API_STATUS')) {
			$messageStack->add_session('2Checkout Api module already installed.', 'error');
			zen_redirect(zen_href_link(FILENAME_MODULES, 'set=payment&module=twocheckout_api', 'NONSSL'));

			return 'failed';
		}
		$this->createTable();
		$default_style = '{
                    "margin": "0",
                    "fontFamily": "Helvetica, sans-serif",
                    "fontSize": "1rem",
                    "fontWeight": "400",
                    "lineHeight": "1.5",
                    "color": "#212529",
                    "textAlign": "left",
                    "backgroundColor": "#FFFFFF",
                    "*": {
                        "boxSizing": "border-box"
                    },
                    ".no-gutters": {
                        "marginRight": 0,
                        "marginLeft": 0
                    },
                    ".row": {
                        "display": "flex",
                        "flexWrap": "wrap"
                    },
                    ".col": {
                        "flexBasis": "0",
                        "flexGrow": "1",
                        "maxWidth": "100%",
                        "padding": "0",
                        "position": "relative",
                        "width": "100%"
                    },
                    "div": {
                        "display": "block"
                    },
                    ".field-container": {
                        "paddingBottom": "14px"
                    },
                    ".field-wrapper": {
                        "paddingRight": "25px"
                    },
                    ".input-wrapper": {
                        "position": "relative"
                    },
                    "label": {
                        "display": "inline-block",
                        "marginBottom": "9px",
                        "color": "red",
                        "fontSize": "14px",
                        "fontWeight": "300",
                        "lineHeight": "17px"
                    },
                    "input": {
                        "overflow": "visible",
                        "margin": 0,
                        "fontFamily": "inherit",
                        "display": "block",
                        "width": "100%",
                        "height": "42px",
                        "padding": "10px 12px",
                        "fontSize": "18px",
                        "fontWeight": "400",
                        "lineHeight": "22px",
                        "color": "#313131",
                        "backgroundColor": "#FFF",
                        "backgroundClip": "padding-box",
                        "border": "1px solid #CBCBCB",
                        "borderRadius": "3px",
                        "transition": "border-color .15s ease-in-out,box-shadow .15s ease-in-out",
                        "outline": 0
                    },
                    "input:focus": {
                        "border": "1px solid #5D5D5D",
                        "backgroundColor": "#FFFDF2"
                    },
                    ".is-error input": {
                        "border": "1px solid #D9534F"
                    },
                    ".is-error input:focus": {
                        "backgroundColor": "#D9534F0B"
                    },
                    ".is-valid input": {
                        "border": "1px solid #1BB43F"
                    },
                    ".is-valid input:focus": {
                        "backgroundColor": "#1BB43F0B"
                    },
                    ".validation-message": {
                        "color": "#D9534F",
                        "fontSize": "10px",
                        "fontStyle": "italic",
                        "marginTop": "6px",
                        "marginBottom": "-5px",
                        "display": "block",
                        "lineHeight": "1"
                    },
                    ".card-expiration-date": {
                        "paddingRight": ".5rem"
                    },
                    ".is-empty input": {
                        "color": "#EBEBEB"
                    },
                    ".lock-icon": {
                        "top": "calc(50% - 7px)",
                        "right": "10px"
                    },
                    ".valid-icon": {
                        "top": "calc(50% - 8px)",
                        "right": "-25px"
                    },
                    ".error-icon": {
                        "top": "calc(50% - 8px)",
                        "right": "-25px"
                    },
                    ".card-icon": {
                        "top": "calc(50% - 10px)",
                        "left": "10px",
                        "display": "none"
                    },
                    ".is-empty .card-icon": {
                        "display": "block"
                    },
                    ".is-focused .card-icon": {
                        "display": "none"
                    },
                    ".card-type-icon": {
                        "right": "30px",
                        "display": "block"
                    },
                    ".card-type-icon.visa": {
                        "top": "calc(50% - 14px)"
                    },
                    ".card-type-icon.mastercard": {
                        "top": "calc(50% - 14.5px)"
                    },
                    ".card-type-icon.amex": {
                        "top": "calc(50% - 14px)"
                    },
                    ".card-type-icon.discover": {
                        "top": "calc(50% - 14px)"
                    },
                    ".card-type-icon.jcb": {
                        "top": "calc(50% - 14px)"
                    },
                    ".card-type-icon.dankort": {
                        "top": "calc(50% - 14px)"
                    },
                    ".card-type-icon.cartebleue": {
                        "top": "calc(50% - 14px)"
                    },
                    ".card-type-icon.diners": {
                        "top": "calc(50% - 14px)"
                    },
                    ".card-type-icon.elo": {
                        "top": "calc(50% - 14px)"
                    }
                }';
		$default_style_description = 'This is the styling object that styles your form.
                     Do not remove or add new classes. You can modify the existing ones. Use
                      double quotes for all keys and values!';
		$ipn_url =  HTTPS_SERVER . DIR_WS_HTTPS_CATALOG . 'extras/twocheckout_api/ipn.php';

		$db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) 
        VALUES ('Test mode', 'MODULE_PAYMENT_TWOCHECKOUT_API_TEST', 'True', 'Place test orders!', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
		$db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) 
        VALUES ('Enable Twocheckout API', 'MODULE_PAYMENT_TWOCHECKOUT_API_STATUS', 'True', 'Pay with credit card using 2Checkout API?', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
		$db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
        VALUES ('Seller Id', 'MODULE_PAYMENT_TWOCHECKOUT_API_SELLER_ID', '123456789', 'Get your SELLER ID from your 2Checkout account!', '6', '1', now())");
		$db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
        VALUES ('Secret key', 'MODULE_PAYMENT_TWOCHECKOUT_API_SECRET_KEY', 'secret key', 'Get your SECRET KEY from your 2Checkout account!', '6', '2', now())");
		$db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
        VALUES ('Secret word', 'MODULE_PAYMENT_TWOCHECKOUT_API_SECRET_WORD', 'secret word', 'Get your SECRET WORD from your 2Checkout account!', '6', '3', now())");
		$db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) 
        VALUES ('Payment Zone', 'MODULE_PAYMENT_TWOCHECKOUT_API_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '4', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");
		$db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Set Order Status', 'MODULE_PAYMENT_TWOCHECKOUT_API_ORDER_STATUS_ID', '2', 'Set the status of orders made with this payment module that have completed payment to this value<br />(\'Processing\' recommended)', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
		$db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
        VALUES ('Sort order of display.', 'MODULE_PAYMENT_TWOCHECKOUT_API_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '5', '0', now())");
		$db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) 
        VALUES ('Use default style', 'MODULE_PAYMENT_TWOCHECKOUT_API_DEFAULT_STYLE', 'True', 'Yes, I like the default style', '7', '8', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
		$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) 
		VALUES ('Custom style', 'MODULE_PAYMENT_TWOCHECKOUT_API_STYLE', '$default_style', '$default_style_description', '6', '0', 'zen_cfg_textarea(', now())");
		$db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) 
        VALUES ('IPN url', 'MODULE_PAYMENT_TWOCHECKOUT_CONVERT_PLUS_IPN_URL', '$ipn_url', 'Enter this URL into your 2Checkout account (Integrations->Webhooks&Api->IPN settings)', '6', '1','tco_api_edit_readonly_text( ', now())");
	}


	function remove()
	{
		global $db;
		$db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
	}

	function keys()
	{
		return [
			'MODULE_PAYMENT_TWOCHECKOUT_API_SELLER_ID',
			'MODULE_PAYMENT_TWOCHECKOUT_API_SECRET_KEY',
			'MODULE_PAYMENT_TWOCHECKOUT_API_SECRET_WORD',
			'MODULE_PAYMENT_TWOCHECKOUT_API_IPN',
			'MODULE_PAYMENT_TWOCHECKOUT_API_DEFAULT_STYLE',
			'MODULE_PAYMENT_TWOCHECKOUT_API_STYLE',
			'MODULE_PAYMENT_TWOCHECKOUT_API_STATUS',
			'MODULE_PAYMENT_TWOCHECKOUT_API_ZONE',
			'MODULE_PAYMENT_TWOCHECKOUT_API_ORDER_STATUS_ID',
			'MODULE_PAYMENT_TWOCHECKOUT_API_TEST',
			'MODULE_PAYMENT_TWOCHECKOUT_API_SORT_ORDER',
			'MODULE_PAYMENT_TWOCHECKOUT_CONVERT_PLUS_IPN_URL'
		];
	}

	protected function createTable()
	{
		global $db, $sniffer;
		if (!$sniffer->table_exists(TABLE_2CHECKOUT_API)) {
			$sql = "CREATE TABLE `" . TABLE_2CHECKOUT_API . "` (
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

/**
 * @param        $text
 * @param string $key
 *
 * @return string
 */
function tco_api_edit_readonly_text($text, $key= ''){
	return tco_api_zen_draw_input_field($key, $text);
}

/**
 * @param        $name
 * @param string $value
 * @param string $parameters
 * @param string $type
 * @param bool   $reinsert_value
 * @param false  $required
 *
 * @return string
 */
function tco_api_zen_draw_input_field($name, $value = '', $parameters = '', $type = 'text', $reinsert_value = true, $required = false) {
	// -----
	// Give an observer the opportunity to **totally** override this function's operation.
	//
	$field = false;
	$GLOBALS['zco_notifier']->notify(
		'NOTIFY_ZEN_DRAW_INPUT_FIELD_OVERRIDE',
		array(
			'name' => $name,
			'value' => $value,
			'parameters' => $parameters,
			'type' => $type,
			'reinsert_value' => $reinsert_value,
			'required' => $required,
		),
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

	if (zen_not_null($parameters)) $field .= ' ' . $parameters;

	$field .= ' class="form-control" ';
	$field .= ' readonly = "readonly" />';

	// -----
	// Give an observer the opportunity to modify the just-rendered field.
	//
	$GLOBALS['zco_notifier']->notify(
		'NOTIFY_ZEN_DRAW_INPUT_FIELD',
		array(
			'name' => $name,
			'value' => $value,
			'parameters' => $parameters,
			'type' => $type,
			'reinsert_value' => $reinsert_value,
			'required' => $required,
		),
		$field
	);

	if ($required == true && !empty(TEXT_FIELD_REQUIRED)) {
		$field .= TEXT_FIELD_REQUIRED;
	}

	return $field;
}
