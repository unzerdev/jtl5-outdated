<?php 
/**
  * You are allowed to use this API in your web application.
 *
 * Copyright (C) 2018 by customweb GmbH
 *
 * This program is licenced under the customweb software licence. With the
 * purchase or the installation of the software in your application you
 * accept the licence agreement. The allowed usage is outlined in the
 * customweb software licence which can be found under
 * http://www.sellxed.com/en/software-license-agreement
 *
 * Any modification or distribution is strictly forbidden. The license
 * grants you the installation in one application. For multiuse you will need
 * to purchase further licences at http://www.sellxed.com/shop.
 *
 * See the customweb software licence agreement for more details.
 *
 */

require_once 'Customweb/Core/Stream/Input/File.php';

require_once 'UnzerCw/Language.php';
require_once 'UnzerCw/Util.php';
require_once 'UnzerCw/VersionHelper.php';


class UnzerCw_SettingApi {
	
	private $settingPrefix = '';
	private $paymentMethodMachineName = null;
	private static $settingsArray = null;
	private $pseudoPrefix = '';
	
	/**
	 * @var Plugin
	 */
	private $plugin = null;
	
	/**
	 * 
	 * @param string $paymentMethodMachineName (optional)
	 */
	public function __construct($paymentMethodMachineName = null) {
		$this->paymentMethodMachineName = $paymentMethodMachineName;
		if ($this->paymentMethodMachineName !== null) {
			$moduleId = UnzerCw_Util::getPaymentMethodModuleId($this->paymentMethodMachineName);
			$this->settingPrefix = $moduleId . '_';
			$this->pseudoPrefix = UnzerCw_Util::getPaymentMethodPseudoSettingPrefix($this->paymentMethodMachineName) . '_';
		}
		$this->plugin = UnzerCw_Util::getPluginObject();
		
	}
	
	public function isSettingPresent($key) {
		$definitons = $this->getSettingDefintions();
		if (isset($definitons[$key])) {
			return true;
		}
		else {
			return false;
		}
	}
	
	public function getSettingValue($key, $languageCode = null) {
		
		$definitons = $this->getSettingDefintions();
		
		if (!isset($definitons[$key])) {
			throw new Exception("Could not find setting with key '" . $key . "'.");
		}
		$type = strtolower($definitons[$key]['type']);
		$default = $definitons[$key]['default'];
		
		if ($type == 'file') {
			$value = $this->getRawSettingValue($key);
			$value = trim($value);
			if (!empty($value)) {
				$path = PFAD_ROOT . '/' . ltrim($value, '/');
				if (!file_exists($path)) {
					throw new Exception(UnzerCw_Language::_("Could not find file on path @path for setting @setting.", array('@path' => $path, '@setting' => $key)));
				}
				return new Customweb_Core_Stream_Input_File($path);
			}
			else {
				try {
					return UnzerCw_Util::getAssetResolver()->resolveAssetStream($default);
				}
				catch(Customweb_Asset_Exception_UnresolvableAssetException $e) {
					return null;
				}
			}
		}
		
		else if ($type == 'multiselect') {
			
			if (!isset($definitons[$key]['options'])) {
				throw new Exception("Could not load the options for multiselect field.");
			}
			
			$values = array();
			$foundAny = false;
			foreach ($definitons[$key]['options'] as $optionKey => $optionText) {
				$optionActive = $this->getRawSettingValue($key . '_' . strtolower($optionKey));
				if ($optionActive !== null) {
					$foundAny = true;
				}
				if ($optionActive == 'active') {
					$values[] = $optionKey;
				}
			}
			
			if ($foundAny) {
				return $values;
			}
			else {
				return explode(',', $default);
			}
		}
		else if ($type == 'multilangfield') {
			if ($languageCode === null) {
				throw new Exception("To query the '" . $key . "' setting,  a language code must be provided.");
			}
			
			$languageCode = (string)$languageCode;
			if (strlen($languageCode) >= 3) {
				$languageCode = strtolower(UnzerCw_VersionHelper::getInstance()->convertISO2ISO639($languageCode));
			}
			$key = $key . '_' . $languageCode;
			
			$value = $this->getRawSettingValue($key);
			if ($value !== null) {
				return $value;
			}
			else {
				return $default;
			}
		}
		else {
			$value = $this->getRawSettingValue($key);
			if ($value !== null) {
				return $value;
			}
			else {
				return $default;
			}
		}
	}
	
	/**
	 * This method returns the raw value for the given key. Multi selects, multi lang fields
	 * are not threaded.
	 * 
	 * @param string $key
	 */
	protected function getRawSettingValue($key) {
		$confs = UnzerCw_VersionHelper::getInstance()->getPluginConfigurations($this->plugin);
		
		// Check length and shorten if needed:
		$pseudoKey = $this->pseudoPrefix . $key;
		if (strlen($pseudoKey) > 64) {
			$key = substr($key, strlen($pseudoKey) - 64);
		}
		
		$key = $this->settingPrefix . $key;
		if (isset($confs[$key])) {
			return utf8_encode($confs[$key]);
		}
		else {
			return null;
		}
	}
	
	public function getSettingDefintions() {
		$array = self::getSettingsArray();
		if ($this->paymentMethodMachineName === null) {
			return $array['global_settings'];
		}
		else {
			return $array[strtolower($this->paymentMethodMachineName)];
		}
	}
	
	private static function getSettingsArray() {
		if (self::$settingsArray === null) {
			self::$settingsArray = self::getSettingsArrayRaw();
		}
		
		return self::$settingsArray;
	}
	
	private static function getSettingsArrayRaw() {
		return array(
		'global_settings' => array(
			'operating_mode' => array(
				'title' => UnzerCw_Language::_("Operation Mode"),
 				'description' => UnzerCw_Language::_("Operation mode of the shop"),
 				'type' => 'SELECT',
 				'options' => array(
					'test' => UnzerCw_Language::_("Test"),
 					'live' => UnzerCw_Language::_("Live"),
 				),
 				'default' => 'test',
 			),
 			'public_key_live' => array(
				'title' => UnzerCw_Language::_("Public Key Live"),
 				'description' => UnzerCw_Language::_("Public Key for live requests provided by Unzer"),
 				'type' => 'TEXTFIELD',
 				'default' => '',
 			),
 			'private_key_live' => array(
				'title' => UnzerCw_Language::_("Private Key Live"),
 				'description' => UnzerCw_Language::_("Private Key for live requests provided by Unzer"),
 				'type' => 'TEXTFIELD',
 				'default' => '',
 			),
 			'public_key_test' => array(
				'title' => UnzerCw_Language::_("Public Key Test"),
 				'description' => UnzerCw_Language::_("Public Key for test requests provided by Unzer"),
 				'type' => 'TEXTFIELD',
 				'default' => '',
 			),
 			'private_key_test' => array(
				'title' => UnzerCw_Language::_("Private Key Test"),
 				'description' => UnzerCw_Language::_("Private Key for test requests provided by Unzer"),
 				'type' => 'TEXTFIELD',
 				'default' => '',
 			),
 			'order_id_schema' => array(
				'title' => UnzerCw_Language::_("OrderId Schema"),
 				'description' => UnzerCw_Language::_("Here you can set a schema for the orderId parameter transmitted to identify the payment If left empty it is not transmitted The following placeholders can be used oid for the order id which may not be unique or set tid for the sellxed transaction id which"),
 				'type' => 'TEXTFIELD',
 				'default' => '{id}',
 			),
 			'payment_reference_schema' => array(
				'title' => UnzerCw_Language::_("PaymentReference Schema"),
 				'description' => UnzerCw_Language::_("Here you can set a schema for the paymentReference parameter transmitted to identify the payment If left empty it is not transmitted The following placeholders can be used oid for the order id which may not be unique or set tid for the sellxed transaction"),
 				'type' => 'TEXTFIELD',
 				'default' => '{id}',
 			),
 			'invoice_id_schema' => array(
				'title' => UnzerCw_Language::_("InvoiceID Schema"),
 				'description' => UnzerCw_Language::_("Here you can set a schema for the invoiceId parameter transmitted to identify the payment If left empty it is not transmitted The following placeholders can be used oid for the order id which may not be unique or set tid for the sellxed transaction id whi"),
 				'type' => 'TEXTFIELD',
 				'default' => '{id}',
 			),
 			'log_level' => array(
				'title' => UnzerCw_Language::_("Log Level"),
 				'description' => UnzerCw_Language::_("Messages of this or a higher level will be logged"),
 				'type' => 'SELECT',
 				'options' => array(
					'error' => UnzerCw_Language::_("Error"),
 					'info' => UnzerCw_Language::_("Info"),
 					'debug' => UnzerCw_Language::_("Debug"),
 				),
 				'default' => 'error',
 			),
 		),
 		'creditcard' => array(
			'placeholder_size' => array(
				'title' => UnzerCw_Language::_("Element Size"),
 				'description' => UnzerCw_Language::_("How should elements from Unzer be loaded With narrow elements the element label is displayed by the store with wide elements it is loaded via javascript by Unzer The input elements are always loade"),
 				'type' => 'SELECT',
 				'options' => array(
					'wide' => UnzerCw_Language::_("Wide label from Unzer"),
 					'narrow' => UnzerCw_Language::_("Narrow label from shop"),
 				),
 				'default' => 'narrow',
 			),
 			'send_basket' => array(
				'title' => UnzerCw_Language::_("Send Basket"),
 				'description' => UnzerCw_Language::_("Should the invoice items be transmitted to Unzer This slightly increases the processing time due to an additional request and may cause issues for certain quantity price combinations"),
 				'type' => 'SELECT',
 				'options' => array(
					'no' => UnzerCw_Language::_("Do not send"),
 					'yes' => UnzerCw_Language::_("Send Basket"),
 				),
 				'default' => 'no',
 			),
 			'capturing' => array(
				'title' => UnzerCw_Language::_("Capturing"),
 				'description' => UnzerCw_Language::_("Should the amount be captured automatically after the order Direct Charge or should the amount only be reserved Authorize"),
 				'type' => 'SELECT',
 				'options' => array(
					'direct' => UnzerCw_Language::_("Direct Charge"),
 					'deferred' => UnzerCw_Language::_("Authorize"),
 				),
 				'default' => 'direct',
 			),
 			'status_authorized' => array(
				'title' => UnzerCw_Language::_("Authorized Status"),
 				'description' => UnzerCw_Language::_("This status is set when the payment was successfull and it is authorized"),
 				'type' => 'ORDERSTATUSSELECT',
 				'default' => 'authorized',
 			),
 			'status_uncertain' => array(
				'title' => UnzerCw_Language::_("Uncertain Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status for new orders that have an uncertain authorisation status"),
 				'type' => 'ORDERSTATUSSELECT',
 				'default' => 'uncertain',
 			),
 			'status_cancelled' => array(
				'title' => UnzerCw_Language::_("Cancelled Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status when an order is cancelled"),
 				'type' => 'ORDERSTATUSSELECT',
 				'options' => array(
					'no_status_change' => UnzerCw_Language::_("Dont change order status"),
 				),
 				'default' => 'cancelled',
 			),
 			'status_captured' => array(
				'title' => UnzerCw_Language::_("Captured Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status for orders that are captured either directly after the order or manually in the backend"),
 				'type' => 'ORDERSTATUSSELECT',
 				'options' => array(
					'no_status_change' => UnzerCw_Language::_("Dont change order status"),
 				),
 				'default' => 'no_status_change',
 			),
 			'authorizationMethod' => array(
				'title' => UnzerCw_Language::_("Authorization Method"),
 				'description' => UnzerCw_Language::_("Select the authorization method to use for processing this payment method"),
 				'type' => 'SELECT',
 				'options' => array(
					'AjaxAuthorization' => UnzerCw_Language::_("Ajax Authorization"),
 				),
 				'default' => 'AjaxAuthorization',
 			),
 			'payment_receipt' => array(
				'title' => UnzerCw_Language::_("Payment Receipts"),
 				'description' => UnzerCw_Language::_("This setting controls when the payment receipts are created"),
 				'type' => 'SELECT',
 				'options' => array(
					'authorization' => UnzerCw_Language::_("Create the payment receipts during authorization"),
 					'capturing' => UnzerCw_Language::_("Create the payment receipts when the transaction is captured"),
 				),
 				'default' => 'authorization',
 			),
 			'error_display_page' => array(
				'title' => UnzerCw_Language::_("Error Page"),
 				'description' => UnzerCw_Language::_("This setting controls where the error message is shown to the customer"),
 				'type' => 'SELECT',
 				'options' => array(
					'overview' => UnzerCw_Language::_("Show the error message on the overview page"),
 					'separte' => UnzerCw_Language::_("Show the error message during checkout on a separate page"),
 				),
 				'default' => 'overview',
 			),
 		),
 		'directdebitssepa' => array(
			'status_authorized' => array(
				'title' => UnzerCw_Language::_("Authorized Status"),
 				'description' => UnzerCw_Language::_("This status is set when the payment was successfull and it is authorized"),
 				'type' => 'ORDERSTATUSSELECT',
 				'default' => 'authorized',
 			),
 			'status_uncertain' => array(
				'title' => UnzerCw_Language::_("Uncertain Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status for new orders that have an uncertain authorisation status"),
 				'type' => 'ORDERSTATUSSELECT',
 				'default' => 'uncertain',
 			),
 			'status_cancelled' => array(
				'title' => UnzerCw_Language::_("Cancelled Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status when an order is cancelled"),
 				'type' => 'ORDERSTATUSSELECT',
 				'options' => array(
					'no_status_change' => UnzerCw_Language::_("Dont change order status"),
 				),
 				'default' => 'cancelled',
 			),
 			'status_captured' => array(
				'title' => UnzerCw_Language::_("Captured Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status for orders that are captured either directly after the order or manually in the backend"),
 				'type' => 'ORDERSTATUSSELECT',
 				'options' => array(
					'no_status_change' => UnzerCw_Language::_("Dont change order status"),
 				),
 				'default' => 'no_status_change',
 			),
 			'placeholder_size' => array(
				'title' => UnzerCw_Language::_("Element Size"),
 				'description' => UnzerCw_Language::_("How should elements from Unzer be loaded With narrow elements the element label is displayed by the store with wide elements it is loaded via javascript by Unzer The input elements are always loade"),
 				'type' => 'SELECT',
 				'options' => array(
					'wide' => UnzerCw_Language::_("Wide label from Unzer"),
 					'narrow' => UnzerCw_Language::_("Narrow label from shop"),
 				),
 				'default' => 'narrow',
 			),
 			'send_basket' => array(
				'title' => UnzerCw_Language::_("Send Basket"),
 				'description' => UnzerCw_Language::_("Should the invoice items be transmitted to Unzer This slightly increases the processing time due to an additional request and may cause issues for certain quantity price combinations"),
 				'type' => 'SELECT',
 				'options' => array(
					'no' => UnzerCw_Language::_("Do not send"),
 					'yes' => UnzerCw_Language::_("Send Basket"),
 				),
 				'default' => 'no',
 			),
 			'send_customer' => array(
				'title' => UnzerCw_Language::_("Send Customer"),
 				'description' => UnzerCw_Language::_("Should customer data be transmitted to Unzer This slightly increases the processing time due to an additional request but may allow eg saving the payment method to the customer"),
 				'type' => 'SELECT',
 				'options' => array(
					'no' => UnzerCw_Language::_("Do not send"),
 					'yes' => UnzerCw_Language::_("Send Customer"),
 				),
 				'default' => 'no',
 			),
 			'merchant_name' => array(
				'title' => UnzerCw_Language::_("Merchant name"),
 				'description' => UnzerCw_Language::_("Here you can configure the merchant name which is displayed as part of the mandate text"),
 				'type' => 'TEXTFIELD',
 				'default' => '',
 			),
 			'authorizationMethod' => array(
				'title' => UnzerCw_Language::_("Authorization Method"),
 				'description' => UnzerCw_Language::_("Select the authorization method to use for processing this payment method"),
 				'type' => 'SELECT',
 				'options' => array(
					'AjaxAuthorization' => UnzerCw_Language::_("Ajax Authorization"),
 				),
 				'default' => 'AjaxAuthorization',
 			),
 			'payment_receipt' => array(
				'title' => UnzerCw_Language::_("Payment Receipts"),
 				'description' => UnzerCw_Language::_("This setting controls when the payment receipts are created"),
 				'type' => 'SELECT',
 				'options' => array(
					'authorization' => UnzerCw_Language::_("Create the payment receipts during authorization"),
 					'capturing' => UnzerCw_Language::_("Create the payment receipts when the transaction is captured"),
 				),
 				'default' => 'authorization',
 			),
 			'error_display_page' => array(
				'title' => UnzerCw_Language::_("Error Page"),
 				'description' => UnzerCw_Language::_("This setting controls where the error message is shown to the customer"),
 				'type' => 'SELECT',
 				'options' => array(
					'overview' => UnzerCw_Language::_("Show the error message on the overview page"),
 					'separte' => UnzerCw_Language::_("Show the error message during checkout on a separate page"),
 				),
 				'default' => 'overview',
 			),
 		),
 		'securesepa' => array(
			'merchant_name' => array(
				'title' => UnzerCw_Language::_("Merchant name"),
 				'description' => UnzerCw_Language::_("Here you can configure the merchant name which is displayed as part of the mandate text"),
 				'type' => 'TEXTFIELD',
 				'default' => '',
 			),
 			'status_authorized' => array(
				'title' => UnzerCw_Language::_("Authorized Status"),
 				'description' => UnzerCw_Language::_("This status is set when the payment was successfull and it is authorized"),
 				'type' => 'ORDERSTATUSSELECT',
 				'default' => 'authorized',
 			),
 			'status_uncertain' => array(
				'title' => UnzerCw_Language::_("Uncertain Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status for new orders that have an uncertain authorisation status"),
 				'type' => 'ORDERSTATUSSELECT',
 				'default' => 'uncertain',
 			),
 			'status_cancelled' => array(
				'title' => UnzerCw_Language::_("Cancelled Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status when an order is cancelled"),
 				'type' => 'ORDERSTATUSSELECT',
 				'options' => array(
					'no_status_change' => UnzerCw_Language::_("Dont change order status"),
 				),
 				'default' => 'cancelled',
 			),
 			'status_captured' => array(
				'title' => UnzerCw_Language::_("Captured Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status for orders that are captured either directly after the order or manually in the backend"),
 				'type' => 'ORDERSTATUSSELECT',
 				'options' => array(
					'no_status_change' => UnzerCw_Language::_("Dont change order status"),
 				),
 				'default' => 'no_status_change',
 			),
 			'placeholder_size' => array(
				'title' => UnzerCw_Language::_("Element Size"),
 				'description' => UnzerCw_Language::_("How should elements from Unzer be loaded With narrow elements the element label is displayed by the store with wide elements it is loaded via javascript by Unzer The input elements are always loade"),
 				'type' => 'SELECT',
 				'options' => array(
					'wide' => UnzerCw_Language::_("Wide label from Unzer"),
 					'narrow' => UnzerCw_Language::_("Narrow label from shop"),
 				),
 				'default' => 'narrow',
 			),
 			'authorizationMethod' => array(
				'title' => UnzerCw_Language::_("Authorization Method"),
 				'description' => UnzerCw_Language::_("Select the authorization method to use for processing this payment method"),
 				'type' => 'SELECT',
 				'options' => array(
					'AjaxAuthorization' => UnzerCw_Language::_("Ajax Authorization"),
 				),
 				'default' => 'AjaxAuthorization',
 			),
 			'payment_receipt' => array(
				'title' => UnzerCw_Language::_("Payment Receipts"),
 				'description' => UnzerCw_Language::_("This setting controls when the payment receipts are created"),
 				'type' => 'SELECT',
 				'options' => array(
					'authorization' => UnzerCw_Language::_("Create the payment receipts during authorization"),
 					'capturing' => UnzerCw_Language::_("Create the payment receipts when the transaction is captured"),
 				),
 				'default' => 'authorization',
 			),
 			'error_display_page' => array(
				'title' => UnzerCw_Language::_("Error Page"),
 				'description' => UnzerCw_Language::_("This setting controls where the error message is shown to the customer"),
 				'type' => 'SELECT',
 				'options' => array(
					'overview' => UnzerCw_Language::_("Show the error message on the overview page"),
 					'separte' => UnzerCw_Language::_("Show the error message during checkout on a separate page"),
 				),
 				'default' => 'overview',
 			),
 		),
 		'openinvoice' => array(
			'send_customer' => array(
				'title' => UnzerCw_Language::_("Send Customer"),
 				'description' => UnzerCw_Language::_("Should customer data be transmitted to Unzer This slightly increases the processing time due to an additional request but may allow eg saving the payment method to the customer"),
 				'type' => 'SELECT',
 				'options' => array(
					'no' => UnzerCw_Language::_("Do not send"),
 					'yes' => UnzerCw_Language::_("Send Customer"),
 				),
 				'default' => 'no',
 			),
 			'status_authorized' => array(
				'title' => UnzerCw_Language::_("Authorized Status"),
 				'description' => UnzerCw_Language::_("This status is set when the payment was successfull and it is authorized"),
 				'type' => 'ORDERSTATUSSELECT',
 				'default' => 'authorized',
 			),
 			'status_uncertain' => array(
				'title' => UnzerCw_Language::_("Uncertain Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status for new orders that have an uncertain authorisation status"),
 				'type' => 'ORDERSTATUSSELECT',
 				'default' => 'uncertain',
 			),
 			'status_cancelled' => array(
				'title' => UnzerCw_Language::_("Cancelled Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status when an order is cancelled"),
 				'type' => 'ORDERSTATUSSELECT',
 				'options' => array(
					'no_status_change' => UnzerCw_Language::_("Dont change order status"),
 				),
 				'default' => 'cancelled',
 			),
 			'status_captured' => array(
				'title' => UnzerCw_Language::_("Captured Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status for orders that are captured either directly after the order or manually in the backend"),
 				'type' => 'ORDERSTATUSSELECT',
 				'options' => array(
					'no_status_change' => UnzerCw_Language::_("Dont change order status"),
 				),
 				'default' => 'no_status_change',
 			),
 			'send_basket' => array(
				'title' => UnzerCw_Language::_("Send Basket"),
 				'description' => UnzerCw_Language::_("Should the invoice items be transmitted to Unzer This slightly increases the processing time due to an additional request and may cause issues for certain quantity price combinations"),
 				'type' => 'SELECT',
 				'options' => array(
					'no' => UnzerCw_Language::_("Do not send"),
 					'yes' => UnzerCw_Language::_("Send Basket"),
 				),
 				'default' => 'no',
 			),
 			'authorizationMethod' => array(
				'title' => UnzerCw_Language::_("Authorization Method"),
 				'description' => UnzerCw_Language::_("Select the authorization method to use for processing this payment method"),
 				'type' => 'SELECT',
 				'options' => array(
					'AjaxAuthorization' => UnzerCw_Language::_("Ajax Authorization"),
 				),
 				'default' => 'AjaxAuthorization',
 			),
 			'payment_receipt' => array(
				'title' => UnzerCw_Language::_("Payment Receipts"),
 				'description' => UnzerCw_Language::_("This setting controls when the payment receipts are created"),
 				'type' => 'SELECT',
 				'options' => array(
					'authorization' => UnzerCw_Language::_("Create the payment receipts during authorization"),
 					'capturing' => UnzerCw_Language::_("Create the payment receipts when the transaction is captured"),
 				),
 				'default' => 'authorization',
 			),
 			'error_display_page' => array(
				'title' => UnzerCw_Language::_("Error Page"),
 				'description' => UnzerCw_Language::_("This setting controls where the error message is shown to the customer"),
 				'type' => 'SELECT',
 				'options' => array(
					'overview' => UnzerCw_Language::_("Show the error message on the overview page"),
 					'separte' => UnzerCw_Language::_("Show the error message during checkout on a separate page"),
 				),
 				'default' => 'overview',
 			),
 		),
 		'secureinvoice' => array(
			'status_authorized' => array(
				'title' => UnzerCw_Language::_("Authorized Status"),
 				'description' => UnzerCw_Language::_("This status is set when the payment was successfull and it is authorized"),
 				'type' => 'ORDERSTATUSSELECT',
 				'default' => 'authorized',
 			),
 			'status_uncertain' => array(
				'title' => UnzerCw_Language::_("Uncertain Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status for new orders that have an uncertain authorisation status"),
 				'type' => 'ORDERSTATUSSELECT',
 				'default' => 'uncertain',
 			),
 			'status_cancelled' => array(
				'title' => UnzerCw_Language::_("Cancelled Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status when an order is cancelled"),
 				'type' => 'ORDERSTATUSSELECT',
 				'options' => array(
					'no_status_change' => UnzerCw_Language::_("Dont change order status"),
 				),
 				'default' => 'cancelled',
 			),
 			'status_captured' => array(
				'title' => UnzerCw_Language::_("Captured Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status for orders that are captured either directly after the order or manually in the backend"),
 				'type' => 'ORDERSTATUSSELECT',
 				'options' => array(
					'no_status_change' => UnzerCw_Language::_("Dont change order status"),
 				),
 				'default' => 'no_status_change',
 			),
 			'authorizationMethod' => array(
				'title' => UnzerCw_Language::_("Authorization Method"),
 				'description' => UnzerCw_Language::_("Select the authorization method to use for processing this payment method"),
 				'type' => 'SELECT',
 				'options' => array(
					'AjaxAuthorization' => UnzerCw_Language::_("Ajax Authorization"),
 				),
 				'default' => 'AjaxAuthorization',
 			),
 			'payment_receipt' => array(
				'title' => UnzerCw_Language::_("Payment Receipts"),
 				'description' => UnzerCw_Language::_("This setting controls when the payment receipts are created"),
 				'type' => 'SELECT',
 				'options' => array(
					'authorization' => UnzerCw_Language::_("Create the payment receipts during authorization"),
 					'capturing' => UnzerCw_Language::_("Create the payment receipts when the transaction is captured"),
 				),
 				'default' => 'authorization',
 			),
 			'error_display_page' => array(
				'title' => UnzerCw_Language::_("Error Page"),
 				'description' => UnzerCw_Language::_("This setting controls where the error message is shown to the customer"),
 				'type' => 'SELECT',
 				'options' => array(
					'overview' => UnzerCw_Language::_("Show the error message on the overview page"),
 					'separte' => UnzerCw_Language::_("Show the error message during checkout on a separate page"),
 				),
 				'default' => 'overview',
 			),
 		),
 		'paypal' => array(
			'status_authorized' => array(
				'title' => UnzerCw_Language::_("Authorized Status"),
 				'description' => UnzerCw_Language::_("This status is set when the payment was successfull and it is authorized"),
 				'type' => 'ORDERSTATUSSELECT',
 				'default' => 'authorized',
 			),
 			'status_uncertain' => array(
				'title' => UnzerCw_Language::_("Uncertain Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status for new orders that have an uncertain authorisation status"),
 				'type' => 'ORDERSTATUSSELECT',
 				'default' => 'uncertain',
 			),
 			'status_cancelled' => array(
				'title' => UnzerCw_Language::_("Cancelled Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status when an order is cancelled"),
 				'type' => 'ORDERSTATUSSELECT',
 				'options' => array(
					'no_status_change' => UnzerCw_Language::_("Dont change order status"),
 				),
 				'default' => 'cancelled',
 			),
 			'status_captured' => array(
				'title' => UnzerCw_Language::_("Captured Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status for orders that are captured either directly after the order or manually in the backend"),
 				'type' => 'ORDERSTATUSSELECT',
 				'options' => array(
					'no_status_change' => UnzerCw_Language::_("Dont change order status"),
 				),
 				'default' => 'no_status_change',
 			),
 			'send_basket' => array(
				'title' => UnzerCw_Language::_("Send Basket"),
 				'description' => UnzerCw_Language::_("Should the invoice items be transmitted to Unzer This slightly increases the processing time due to an additional request and may cause issues for certain quantity price combinations"),
 				'type' => 'SELECT',
 				'options' => array(
					'no' => UnzerCw_Language::_("Do not send"),
 					'yes' => UnzerCw_Language::_("Send Basket"),
 				),
 				'default' => 'no',
 			),
 			'send_customer' => array(
				'title' => UnzerCw_Language::_("Send Customer"),
 				'description' => UnzerCw_Language::_("Should customer data be transmitted to Unzer This slightly increases the processing time due to an additional request but may allow eg saving the payment method to the customer"),
 				'type' => 'SELECT',
 				'options' => array(
					'no' => UnzerCw_Language::_("Do not send"),
 					'yes' => UnzerCw_Language::_("Send Customer"),
 				),
 				'default' => 'no',
 			),
 			'capturing' => array(
				'title' => UnzerCw_Language::_("Capturing"),
 				'description' => UnzerCw_Language::_("Should the amount be captured automatically after the order Direct Charge or should the amount only be reserved Authorize"),
 				'type' => 'SELECT',
 				'options' => array(
					'direct' => UnzerCw_Language::_("Direct Charge"),
 					'deferred' => UnzerCw_Language::_("Authorize"),
 				),
 				'default' => 'direct',
 			),
 			'authorizationMethod' => array(
				'title' => UnzerCw_Language::_("Authorization Method"),
 				'description' => UnzerCw_Language::_("Select the authorization method to use for processing this payment method"),
 				'type' => 'SELECT',
 				'options' => array(
					'AjaxAuthorization' => UnzerCw_Language::_("Ajax Authorization"),
 				),
 				'default' => 'AjaxAuthorization',
 			),
 			'payment_receipt' => array(
				'title' => UnzerCw_Language::_("Payment Receipts"),
 				'description' => UnzerCw_Language::_("This setting controls when the payment receipts are created"),
 				'type' => 'SELECT',
 				'options' => array(
					'authorization' => UnzerCw_Language::_("Create the payment receipts during authorization"),
 					'capturing' => UnzerCw_Language::_("Create the payment receipts when the transaction is captured"),
 				),
 				'default' => 'authorization',
 			),
 			'error_display_page' => array(
				'title' => UnzerCw_Language::_("Error Page"),
 				'description' => UnzerCw_Language::_("This setting controls where the error message is shown to the customer"),
 				'type' => 'SELECT',
 				'options' => array(
					'overview' => UnzerCw_Language::_("Show the error message on the overview page"),
 					'separte' => UnzerCw_Language::_("Show the error message during checkout on a separate page"),
 				),
 				'default' => 'overview',
 			),
 		),
 		'sofortueberweisung' => array(
			'status_authorized' => array(
				'title' => UnzerCw_Language::_("Authorized Status"),
 				'description' => UnzerCw_Language::_("This status is set when the payment was successfull and it is authorized"),
 				'type' => 'ORDERSTATUSSELECT',
 				'default' => 'authorized',
 			),
 			'status_uncertain' => array(
				'title' => UnzerCw_Language::_("Uncertain Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status for new orders that have an uncertain authorisation status"),
 				'type' => 'ORDERSTATUSSELECT',
 				'default' => 'uncertain',
 			),
 			'status_cancelled' => array(
				'title' => UnzerCw_Language::_("Cancelled Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status when an order is cancelled"),
 				'type' => 'ORDERSTATUSSELECT',
 				'options' => array(
					'no_status_change' => UnzerCw_Language::_("Dont change order status"),
 				),
 				'default' => 'cancelled',
 			),
 			'status_captured' => array(
				'title' => UnzerCw_Language::_("Captured Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status for orders that are captured either directly after the order or manually in the backend"),
 				'type' => 'ORDERSTATUSSELECT',
 				'options' => array(
					'no_status_change' => UnzerCw_Language::_("Dont change order status"),
 				),
 				'default' => 'no_status_change',
 			),
 			'send_basket' => array(
				'title' => UnzerCw_Language::_("Send Basket"),
 				'description' => UnzerCw_Language::_("Should the invoice items be transmitted to Unzer This slightly increases the processing time due to an additional request and may cause issues for certain quantity price combinations"),
 				'type' => 'SELECT',
 				'options' => array(
					'no' => UnzerCw_Language::_("Do not send"),
 					'yes' => UnzerCw_Language::_("Send Basket"),
 				),
 				'default' => 'no',
 			),
 			'send_customer' => array(
				'title' => UnzerCw_Language::_("Send Customer"),
 				'description' => UnzerCw_Language::_("Should customer data be transmitted to Unzer This slightly increases the processing time due to an additional request but may allow eg saving the payment method to the customer"),
 				'type' => 'SELECT',
 				'options' => array(
					'no' => UnzerCw_Language::_("Do not send"),
 					'yes' => UnzerCw_Language::_("Send Customer"),
 				),
 				'default' => 'no',
 			),
 			'authorizationMethod' => array(
				'title' => UnzerCw_Language::_("Authorization Method"),
 				'description' => UnzerCw_Language::_("Select the authorization method to use for processing this payment method"),
 				'type' => 'SELECT',
 				'options' => array(
					'AjaxAuthorization' => UnzerCw_Language::_("Ajax Authorization"),
 				),
 				'default' => 'AjaxAuthorization',
 			),
 			'payment_receipt' => array(
				'title' => UnzerCw_Language::_("Payment Receipts"),
 				'description' => UnzerCw_Language::_("This setting controls when the payment receipts are created"),
 				'type' => 'SELECT',
 				'options' => array(
					'authorization' => UnzerCw_Language::_("Create the payment receipts during authorization"),
 					'capturing' => UnzerCw_Language::_("Create the payment receipts when the transaction is captured"),
 				),
 				'default' => 'authorization',
 			),
 			'error_display_page' => array(
				'title' => UnzerCw_Language::_("Error Page"),
 				'description' => UnzerCw_Language::_("This setting controls where the error message is shown to the customer"),
 				'type' => 'SELECT',
 				'options' => array(
					'overview' => UnzerCw_Language::_("Show the error message on the overview page"),
 					'separte' => UnzerCw_Language::_("Show the error message during checkout on a separate page"),
 				),
 				'default' => 'overview',
 			),
 		),
 		'giropay' => array(
			'status_authorized' => array(
				'title' => UnzerCw_Language::_("Authorized Status"),
 				'description' => UnzerCw_Language::_("This status is set when the payment was successfull and it is authorized"),
 				'type' => 'ORDERSTATUSSELECT',
 				'default' => 'authorized',
 			),
 			'status_uncertain' => array(
				'title' => UnzerCw_Language::_("Uncertain Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status for new orders that have an uncertain authorisation status"),
 				'type' => 'ORDERSTATUSSELECT',
 				'default' => 'uncertain',
 			),
 			'status_cancelled' => array(
				'title' => UnzerCw_Language::_("Cancelled Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status when an order is cancelled"),
 				'type' => 'ORDERSTATUSSELECT',
 				'options' => array(
					'no_status_change' => UnzerCw_Language::_("Dont change order status"),
 				),
 				'default' => 'cancelled',
 			),
 			'status_captured' => array(
				'title' => UnzerCw_Language::_("Captured Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status for orders that are captured either directly after the order or manually in the backend"),
 				'type' => 'ORDERSTATUSSELECT',
 				'options' => array(
					'no_status_change' => UnzerCw_Language::_("Dont change order status"),
 				),
 				'default' => 'no_status_change',
 			),
 			'send_basket' => array(
				'title' => UnzerCw_Language::_("Send Basket"),
 				'description' => UnzerCw_Language::_("Should the invoice items be transmitted to Unzer This slightly increases the processing time due to an additional request and may cause issues for certain quantity price combinations"),
 				'type' => 'SELECT',
 				'options' => array(
					'no' => UnzerCw_Language::_("Do not send"),
 					'yes' => UnzerCw_Language::_("Send Basket"),
 				),
 				'default' => 'no',
 			),
 			'send_customer' => array(
				'title' => UnzerCw_Language::_("Send Customer"),
 				'description' => UnzerCw_Language::_("Should customer data be transmitted to Unzer This slightly increases the processing time due to an additional request but may allow eg saving the payment method to the customer"),
 				'type' => 'SELECT',
 				'options' => array(
					'no' => UnzerCw_Language::_("Do not send"),
 					'yes' => UnzerCw_Language::_("Send Customer"),
 				),
 				'default' => 'no',
 			),
 			'authorizationMethod' => array(
				'title' => UnzerCw_Language::_("Authorization Method"),
 				'description' => UnzerCw_Language::_("Select the authorization method to use for processing this payment method"),
 				'type' => 'SELECT',
 				'options' => array(
					'AjaxAuthorization' => UnzerCw_Language::_("Ajax Authorization"),
 				),
 				'default' => 'AjaxAuthorization',
 			),
 			'payment_receipt' => array(
				'title' => UnzerCw_Language::_("Payment Receipts"),
 				'description' => UnzerCw_Language::_("This setting controls when the payment receipts are created"),
 				'type' => 'SELECT',
 				'options' => array(
					'authorization' => UnzerCw_Language::_("Create the payment receipts during authorization"),
 					'capturing' => UnzerCw_Language::_("Create the payment receipts when the transaction is captured"),
 				),
 				'default' => 'authorization',
 			),
 			'error_display_page' => array(
				'title' => UnzerCw_Language::_("Error Page"),
 				'description' => UnzerCw_Language::_("This setting controls where the error message is shown to the customer"),
 				'type' => 'SELECT',
 				'options' => array(
					'overview' => UnzerCw_Language::_("Show the error message on the overview page"),
 					'separte' => UnzerCw_Language::_("Show the error message during checkout on a separate page"),
 				),
 				'default' => 'overview',
 			),
 		),
 		'przelewy24' => array(
			'status_authorized' => array(
				'title' => UnzerCw_Language::_("Authorized Status"),
 				'description' => UnzerCw_Language::_("This status is set when the payment was successfull and it is authorized"),
 				'type' => 'ORDERSTATUSSELECT',
 				'default' => 'authorized',
 			),
 			'status_uncertain' => array(
				'title' => UnzerCw_Language::_("Uncertain Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status for new orders that have an uncertain authorisation status"),
 				'type' => 'ORDERSTATUSSELECT',
 				'default' => 'uncertain',
 			),
 			'status_cancelled' => array(
				'title' => UnzerCw_Language::_("Cancelled Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status when an order is cancelled"),
 				'type' => 'ORDERSTATUSSELECT',
 				'options' => array(
					'no_status_change' => UnzerCw_Language::_("Dont change order status"),
 				),
 				'default' => 'cancelled',
 			),
 			'status_captured' => array(
				'title' => UnzerCw_Language::_("Captured Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status for orders that are captured either directly after the order or manually in the backend"),
 				'type' => 'ORDERSTATUSSELECT',
 				'options' => array(
					'no_status_change' => UnzerCw_Language::_("Dont change order status"),
 				),
 				'default' => 'no_status_change',
 			),
 			'send_basket' => array(
				'title' => UnzerCw_Language::_("Send Basket"),
 				'description' => UnzerCw_Language::_("Should the invoice items be transmitted to Unzer This slightly increases the processing time due to an additional request and may cause issues for certain quantity price combinations"),
 				'type' => 'SELECT',
 				'options' => array(
					'no' => UnzerCw_Language::_("Do not send"),
 					'yes' => UnzerCw_Language::_("Send Basket"),
 				),
 				'default' => 'no',
 			),
 			'send_customer' => array(
				'title' => UnzerCw_Language::_("Send Customer"),
 				'description' => UnzerCw_Language::_("Should customer data be transmitted to Unzer This slightly increases the processing time due to an additional request but may allow eg saving the payment method to the customer"),
 				'type' => 'SELECT',
 				'options' => array(
					'no' => UnzerCw_Language::_("Do not send"),
 					'yes' => UnzerCw_Language::_("Send Customer"),
 				),
 				'default' => 'no',
 			),
 			'authorizationMethod' => array(
				'title' => UnzerCw_Language::_("Authorization Method"),
 				'description' => UnzerCw_Language::_("Select the authorization method to use for processing this payment method"),
 				'type' => 'SELECT',
 				'options' => array(
					'AjaxAuthorization' => UnzerCw_Language::_("Ajax Authorization"),
 				),
 				'default' => 'AjaxAuthorization',
 			),
 			'payment_receipt' => array(
				'title' => UnzerCw_Language::_("Payment Receipts"),
 				'description' => UnzerCw_Language::_("This setting controls when the payment receipts are created"),
 				'type' => 'SELECT',
 				'options' => array(
					'authorization' => UnzerCw_Language::_("Create the payment receipts during authorization"),
 					'capturing' => UnzerCw_Language::_("Create the payment receipts when the transaction is captured"),
 				),
 				'default' => 'authorization',
 			),
 			'error_display_page' => array(
				'title' => UnzerCw_Language::_("Error Page"),
 				'description' => UnzerCw_Language::_("This setting controls where the error message is shown to the customer"),
 				'type' => 'SELECT',
 				'options' => array(
					'overview' => UnzerCw_Language::_("Show the error message on the overview page"),
 					'separte' => UnzerCw_Language::_("Show the error message during checkout on a separate page"),
 				),
 				'default' => 'overview',
 			),
 		),
 		'ideal' => array(
			'status_authorized' => array(
				'title' => UnzerCw_Language::_("Authorized Status"),
 				'description' => UnzerCw_Language::_("This status is set when the payment was successfull and it is authorized"),
 				'type' => 'ORDERSTATUSSELECT',
 				'default' => 'authorized',
 			),
 			'status_uncertain' => array(
				'title' => UnzerCw_Language::_("Uncertain Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status for new orders that have an uncertain authorisation status"),
 				'type' => 'ORDERSTATUSSELECT',
 				'default' => 'uncertain',
 			),
 			'status_cancelled' => array(
				'title' => UnzerCw_Language::_("Cancelled Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status when an order is cancelled"),
 				'type' => 'ORDERSTATUSSELECT',
 				'options' => array(
					'no_status_change' => UnzerCw_Language::_("Dont change order status"),
 				),
 				'default' => 'cancelled',
 			),
 			'status_captured' => array(
				'title' => UnzerCw_Language::_("Captured Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status for orders that are captured either directly after the order or manually in the backend"),
 				'type' => 'ORDERSTATUSSELECT',
 				'options' => array(
					'no_status_change' => UnzerCw_Language::_("Dont change order status"),
 				),
 				'default' => 'no_status_change',
 			),
 			'placeholder_size' => array(
				'title' => UnzerCw_Language::_("Element Size"),
 				'description' => UnzerCw_Language::_("How should elements from Unzer be loaded With narrow elements the element label is displayed by the store with wide elements it is loaded via javascript by Unzer The input elements are always loade"),
 				'type' => 'SELECT',
 				'options' => array(
					'wide' => UnzerCw_Language::_("Wide label from Unzer"),
 					'narrow' => UnzerCw_Language::_("Narrow label from shop"),
 				),
 				'default' => 'narrow',
 			),
 			'send_basket' => array(
				'title' => UnzerCw_Language::_("Send Basket"),
 				'description' => UnzerCw_Language::_("Should the invoice items be transmitted to Unzer This slightly increases the processing time due to an additional request and may cause issues for certain quantity price combinations"),
 				'type' => 'SELECT',
 				'options' => array(
					'no' => UnzerCw_Language::_("Do not send"),
 					'yes' => UnzerCw_Language::_("Send Basket"),
 				),
 				'default' => 'no',
 			),
 			'send_customer' => array(
				'title' => UnzerCw_Language::_("Send Customer"),
 				'description' => UnzerCw_Language::_("Should customer data be transmitted to Unzer This slightly increases the processing time due to an additional request but may allow eg saving the payment method to the customer"),
 				'type' => 'SELECT',
 				'options' => array(
					'no' => UnzerCw_Language::_("Do not send"),
 					'yes' => UnzerCw_Language::_("Send Customer"),
 				),
 				'default' => 'no',
 			),
 			'authorizationMethod' => array(
				'title' => UnzerCw_Language::_("Authorization Method"),
 				'description' => UnzerCw_Language::_("Select the authorization method to use for processing this payment method"),
 				'type' => 'SELECT',
 				'options' => array(
					'AjaxAuthorization' => UnzerCw_Language::_("Ajax Authorization"),
 				),
 				'default' => 'AjaxAuthorization',
 			),
 			'payment_receipt' => array(
				'title' => UnzerCw_Language::_("Payment Receipts"),
 				'description' => UnzerCw_Language::_("This setting controls when the payment receipts are created"),
 				'type' => 'SELECT',
 				'options' => array(
					'authorization' => UnzerCw_Language::_("Create the payment receipts during authorization"),
 					'capturing' => UnzerCw_Language::_("Create the payment receipts when the transaction is captured"),
 				),
 				'default' => 'authorization',
 			),
 			'error_display_page' => array(
				'title' => UnzerCw_Language::_("Error Page"),
 				'description' => UnzerCw_Language::_("This setting controls where the error message is shown to the customer"),
 				'type' => 'SELECT',
 				'options' => array(
					'overview' => UnzerCw_Language::_("Show the error message on the overview page"),
 					'separte' => UnzerCw_Language::_("Show the error message during checkout on a separate page"),
 				),
 				'default' => 'overview',
 			),
 		),
 		'prepayment' => array(
			'status_authorized' => array(
				'title' => UnzerCw_Language::_("Authorized Status"),
 				'description' => UnzerCw_Language::_("This status is set when the payment was successfull and it is authorized"),
 				'type' => 'ORDERSTATUSSELECT',
 				'default' => 'authorized',
 			),
 			'status_uncertain' => array(
				'title' => UnzerCw_Language::_("Uncertain Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status for new orders that have an uncertain authorisation status"),
 				'type' => 'ORDERSTATUSSELECT',
 				'default' => 'uncertain',
 			),
 			'status_cancelled' => array(
				'title' => UnzerCw_Language::_("Cancelled Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status when an order is cancelled"),
 				'type' => 'ORDERSTATUSSELECT',
 				'options' => array(
					'no_status_change' => UnzerCw_Language::_("Dont change order status"),
 				),
 				'default' => 'cancelled',
 			),
 			'status_captured' => array(
				'title' => UnzerCw_Language::_("Captured Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status for orders that are captured either directly after the order or manually in the backend"),
 				'type' => 'ORDERSTATUSSELECT',
 				'options' => array(
					'no_status_change' => UnzerCw_Language::_("Dont change order status"),
 				),
 				'default' => 'no_status_change',
 			),
 			'send_basket' => array(
				'title' => UnzerCw_Language::_("Send Basket"),
 				'description' => UnzerCw_Language::_("Should the invoice items be transmitted to Unzer This slightly increases the processing time due to an additional request and may cause issues for certain quantity price combinations"),
 				'type' => 'SELECT',
 				'options' => array(
					'no' => UnzerCw_Language::_("Do not send"),
 					'yes' => UnzerCw_Language::_("Send Basket"),
 				),
 				'default' => 'no',
 			),
 			'send_customer' => array(
				'title' => UnzerCw_Language::_("Send Customer"),
 				'description' => UnzerCw_Language::_("Should customer data be transmitted to Unzer This slightly increases the processing time due to an additional request but may allow eg saving the payment method to the customer"),
 				'type' => 'SELECT',
 				'options' => array(
					'no' => UnzerCw_Language::_("Do not send"),
 					'yes' => UnzerCw_Language::_("Send Customer"),
 				),
 				'default' => 'no',
 			),
 			'authorizationMethod' => array(
				'title' => UnzerCw_Language::_("Authorization Method"),
 				'description' => UnzerCw_Language::_("Select the authorization method to use for processing this payment method"),
 				'type' => 'SELECT',
 				'options' => array(
					'AjaxAuthorization' => UnzerCw_Language::_("Ajax Authorization"),
 				),
 				'default' => 'AjaxAuthorization',
 			),
 			'payment_receipt' => array(
				'title' => UnzerCw_Language::_("Payment Receipts"),
 				'description' => UnzerCw_Language::_("This setting controls when the payment receipts are created"),
 				'type' => 'SELECT',
 				'options' => array(
					'authorization' => UnzerCw_Language::_("Create the payment receipts during authorization"),
 					'capturing' => UnzerCw_Language::_("Create the payment receipts when the transaction is captured"),
 				),
 				'default' => 'authorization',
 			),
 			'error_display_page' => array(
				'title' => UnzerCw_Language::_("Error Page"),
 				'description' => UnzerCw_Language::_("This setting controls where the error message is shown to the customer"),
 				'type' => 'SELECT',
 				'options' => array(
					'overview' => UnzerCw_Language::_("Show the error message on the overview page"),
 					'separte' => UnzerCw_Language::_("Show the error message during checkout on a separate page"),
 				),
 				'default' => 'overview',
 			),
 		),
 		'eps' => array(
			'status_authorized' => array(
				'title' => UnzerCw_Language::_("Authorized Status"),
 				'description' => UnzerCw_Language::_("This status is set when the payment was successfull and it is authorized"),
 				'type' => 'ORDERSTATUSSELECT',
 				'default' => 'authorized',
 			),
 			'status_uncertain' => array(
				'title' => UnzerCw_Language::_("Uncertain Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status for new orders that have an uncertain authorisation status"),
 				'type' => 'ORDERSTATUSSELECT',
 				'default' => 'uncertain',
 			),
 			'status_cancelled' => array(
				'title' => UnzerCw_Language::_("Cancelled Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status when an order is cancelled"),
 				'type' => 'ORDERSTATUSSELECT',
 				'options' => array(
					'no_status_change' => UnzerCw_Language::_("Dont change order status"),
 				),
 				'default' => 'cancelled',
 			),
 			'status_captured' => array(
				'title' => UnzerCw_Language::_("Captured Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status for orders that are captured either directly after the order or manually in the backend"),
 				'type' => 'ORDERSTATUSSELECT',
 				'options' => array(
					'no_status_change' => UnzerCw_Language::_("Dont change order status"),
 				),
 				'default' => 'no_status_change',
 			),
 			'send_basket' => array(
				'title' => UnzerCw_Language::_("Send Basket"),
 				'description' => UnzerCw_Language::_("Should the invoice items be transmitted to Unzer This slightly increases the processing time due to an additional request and may cause issues for certain quantity price combinations"),
 				'type' => 'SELECT',
 				'options' => array(
					'no' => UnzerCw_Language::_("Do not send"),
 					'yes' => UnzerCw_Language::_("Send Basket"),
 				),
 				'default' => 'no',
 			),
 			'send_customer' => array(
				'title' => UnzerCw_Language::_("Send Customer"),
 				'description' => UnzerCw_Language::_("Should customer data be transmitted to Unzer This slightly increases the processing time due to an additional request but may allow eg saving the payment method to the customer"),
 				'type' => 'SELECT',
 				'options' => array(
					'no' => UnzerCw_Language::_("Do not send"),
 					'yes' => UnzerCw_Language::_("Send Customer"),
 				),
 				'default' => 'no',
 			),
 			'authorizationMethod' => array(
				'title' => UnzerCw_Language::_("Authorization Method"),
 				'description' => UnzerCw_Language::_("Select the authorization method to use for processing this payment method"),
 				'type' => 'SELECT',
 				'options' => array(
					'AjaxAuthorization' => UnzerCw_Language::_("Ajax Authorization"),
 				),
 				'default' => 'AjaxAuthorization',
 			),
 			'payment_receipt' => array(
				'title' => UnzerCw_Language::_("Payment Receipts"),
 				'description' => UnzerCw_Language::_("This setting controls when the payment receipts are created"),
 				'type' => 'SELECT',
 				'options' => array(
					'authorization' => UnzerCw_Language::_("Create the payment receipts during authorization"),
 					'capturing' => UnzerCw_Language::_("Create the payment receipts when the transaction is captured"),
 				),
 				'default' => 'authorization',
 			),
 			'error_display_page' => array(
				'title' => UnzerCw_Language::_("Error Page"),
 				'description' => UnzerCw_Language::_("This setting controls where the error message is shown to the customer"),
 				'type' => 'SELECT',
 				'options' => array(
					'overview' => UnzerCw_Language::_("Show the error message on the overview page"),
 					'separte' => UnzerCw_Language::_("Show the error message during checkout on a separate page"),
 				),
 				'default' => 'overview',
 			),
 		),
 		'unzerbanktransfer' => array(
			'status_authorized' => array(
				'title' => UnzerCw_Language::_("Authorized Status"),
 				'description' => UnzerCw_Language::_("This status is set when the payment was successfull and it is authorized"),
 				'type' => 'ORDERSTATUSSELECT',
 				'default' => 'authorized',
 			),
 			'status_uncertain' => array(
				'title' => UnzerCw_Language::_("Uncertain Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status for new orders that have an uncertain authorisation status"),
 				'type' => 'ORDERSTATUSSELECT',
 				'default' => 'uncertain',
 			),
 			'status_cancelled' => array(
				'title' => UnzerCw_Language::_("Cancelled Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status when an order is cancelled"),
 				'type' => 'ORDERSTATUSSELECT',
 				'options' => array(
					'no_status_change' => UnzerCw_Language::_("Dont change order status"),
 				),
 				'default' => 'cancelled',
 			),
 			'status_captured' => array(
				'title' => UnzerCw_Language::_("Captured Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status for orders that are captured either directly after the order or manually in the backend"),
 				'type' => 'ORDERSTATUSSELECT',
 				'options' => array(
					'no_status_change' => UnzerCw_Language::_("Dont change order status"),
 				),
 				'default' => 'no_status_change',
 			),
 			'send_basket' => array(
				'title' => UnzerCw_Language::_("Send Basket"),
 				'description' => UnzerCw_Language::_("Should the invoice items be transmitted to Unzer This slightly increases the processing time due to an additional request and may cause issues for certain quantity price combinations"),
 				'type' => 'SELECT',
 				'options' => array(
					'no' => UnzerCw_Language::_("Do not send"),
 					'yes' => UnzerCw_Language::_("Send Basket"),
 				),
 				'default' => 'no',
 			),
 			'send_customer' => array(
				'title' => UnzerCw_Language::_("Send Customer"),
 				'description' => UnzerCw_Language::_("Should customer data be transmitted to Unzer This slightly increases the processing time due to an additional request but may allow eg saving the payment method to the customer"),
 				'type' => 'SELECT',
 				'options' => array(
					'no' => UnzerCw_Language::_("Do not send"),
 					'yes' => UnzerCw_Language::_("Send Customer"),
 				),
 				'default' => 'no',
 			),
 			'authorizationMethod' => array(
				'title' => UnzerCw_Language::_("Authorization Method"),
 				'description' => UnzerCw_Language::_("Select the authorization method to use for processing this payment method"),
 				'type' => 'SELECT',
 				'options' => array(
					'AjaxAuthorization' => UnzerCw_Language::_("Ajax Authorization"),
 				),
 				'default' => 'AjaxAuthorization',
 			),
 			'payment_receipt' => array(
				'title' => UnzerCw_Language::_("Payment Receipts"),
 				'description' => UnzerCw_Language::_("This setting controls when the payment receipts are created"),
 				'type' => 'SELECT',
 				'options' => array(
					'authorization' => UnzerCw_Language::_("Create the payment receipts during authorization"),
 					'capturing' => UnzerCw_Language::_("Create the payment receipts when the transaction is captured"),
 				),
 				'default' => 'authorization',
 			),
 			'error_display_page' => array(
				'title' => UnzerCw_Language::_("Error Page"),
 				'description' => UnzerCw_Language::_("This setting controls where the error message is shown to the customer"),
 				'type' => 'SELECT',
 				'options' => array(
					'overview' => UnzerCw_Language::_("Show the error message on the overview page"),
 					'separte' => UnzerCw_Language::_("Show the error message during checkout on a separate page"),
 				),
 				'default' => 'overview',
 			),
 		),
 		'unzerinstallment' => array(
			'effective_interest_rate' => array(
				'title' => UnzerCw_Language::_("Applied Interest Rate"),
 				'description' => UnzerCw_Language::_("The interest rate in percent that you enter here will be applied onto the instalment The rate must be above the amount that you have agreed up on with Unzer"),
 				'type' => 'TEXTFIELD',
 				'default' => '5.99',
 			),
 			'status_authorized' => array(
				'title' => UnzerCw_Language::_("Authorized Status"),
 				'description' => UnzerCw_Language::_("This status is set when the payment was successfull and it is authorized"),
 				'type' => 'ORDERSTATUSSELECT',
 				'default' => 'authorized',
 			),
 			'status_uncertain' => array(
				'title' => UnzerCw_Language::_("Uncertain Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status for new orders that have an uncertain authorisation status"),
 				'type' => 'ORDERSTATUSSELECT',
 				'default' => 'uncertain',
 			),
 			'status_cancelled' => array(
				'title' => UnzerCw_Language::_("Cancelled Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status when an order is cancelled"),
 				'type' => 'ORDERSTATUSSELECT',
 				'options' => array(
					'no_status_change' => UnzerCw_Language::_("Dont change order status"),
 				),
 				'default' => 'cancelled',
 			),
 			'status_captured' => array(
				'title' => UnzerCw_Language::_("Captured Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status for orders that are captured either directly after the order or manually in the backend"),
 				'type' => 'ORDERSTATUSSELECT',
 				'options' => array(
					'no_status_change' => UnzerCw_Language::_("Dont change order status"),
 				),
 				'default' => 'no_status_change',
 			),
 			'authorizationMethod' => array(
				'title' => UnzerCw_Language::_("Authorization Method"),
 				'description' => UnzerCw_Language::_("Select the authorization method to use for processing this payment method"),
 				'type' => 'SELECT',
 				'options' => array(
					'AjaxAuthorization' => UnzerCw_Language::_("Ajax Authorization"),
 				),
 				'default' => 'AjaxAuthorization',
 			),
 			'payment_receipt' => array(
				'title' => UnzerCw_Language::_("Payment Receipts"),
 				'description' => UnzerCw_Language::_("This setting controls when the payment receipts are created"),
 				'type' => 'SELECT',
 				'options' => array(
					'authorization' => UnzerCw_Language::_("Create the payment receipts during authorization"),
 					'capturing' => UnzerCw_Language::_("Create the payment receipts when the transaction is captured"),
 				),
 				'default' => 'authorization',
 			),
 			'error_display_page' => array(
				'title' => UnzerCw_Language::_("Error Page"),
 				'description' => UnzerCw_Language::_("This setting controls where the error message is shown to the customer"),
 				'type' => 'SELECT',
 				'options' => array(
					'overview' => UnzerCw_Language::_("Show the error message on the overview page"),
 					'separte' => UnzerCw_Language::_("Show the error message during checkout on a separate page"),
 				),
 				'default' => 'overview',
 			),
 		),
 		'alipay' => array(
			'status_authorized' => array(
				'title' => UnzerCw_Language::_("Authorized Status"),
 				'description' => UnzerCw_Language::_("This status is set when the payment was successfull and it is authorized"),
 				'type' => 'ORDERSTATUSSELECT',
 				'default' => 'authorized',
 			),
 			'status_uncertain' => array(
				'title' => UnzerCw_Language::_("Uncertain Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status for new orders that have an uncertain authorisation status"),
 				'type' => 'ORDERSTATUSSELECT',
 				'default' => 'uncertain',
 			),
 			'status_cancelled' => array(
				'title' => UnzerCw_Language::_("Cancelled Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status when an order is cancelled"),
 				'type' => 'ORDERSTATUSSELECT',
 				'options' => array(
					'no_status_change' => UnzerCw_Language::_("Dont change order status"),
 				),
 				'default' => 'cancelled',
 			),
 			'status_captured' => array(
				'title' => UnzerCw_Language::_("Captured Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status for orders that are captured either directly after the order or manually in the backend"),
 				'type' => 'ORDERSTATUSSELECT',
 				'options' => array(
					'no_status_change' => UnzerCw_Language::_("Dont change order status"),
 				),
 				'default' => 'no_status_change',
 			),
 			'send_basket' => array(
				'title' => UnzerCw_Language::_("Send Basket"),
 				'description' => UnzerCw_Language::_("Should the invoice items be transmitted to Unzer This slightly increases the processing time due to an additional request and may cause issues for certain quantity price combinations"),
 				'type' => 'SELECT',
 				'options' => array(
					'no' => UnzerCw_Language::_("Do not send"),
 					'yes' => UnzerCw_Language::_("Send Basket"),
 				),
 				'default' => 'no',
 			),
 			'send_customer' => array(
				'title' => UnzerCw_Language::_("Send Customer"),
 				'description' => UnzerCw_Language::_("Should customer data be transmitted to Unzer This slightly increases the processing time due to an additional request but may allow eg saving the payment method to the customer"),
 				'type' => 'SELECT',
 				'options' => array(
					'no' => UnzerCw_Language::_("Do not send"),
 					'yes' => UnzerCw_Language::_("Send Customer"),
 				),
 				'default' => 'no',
 			),
 			'authorizationMethod' => array(
				'title' => UnzerCw_Language::_("Authorization Method"),
 				'description' => UnzerCw_Language::_("Select the authorization method to use for processing this payment method"),
 				'type' => 'SELECT',
 				'options' => array(
					'AjaxAuthorization' => UnzerCw_Language::_("Ajax Authorization"),
 				),
 				'default' => 'AjaxAuthorization',
 			),
 			'payment_receipt' => array(
				'title' => UnzerCw_Language::_("Payment Receipts"),
 				'description' => UnzerCw_Language::_("This setting controls when the payment receipts are created"),
 				'type' => 'SELECT',
 				'options' => array(
					'authorization' => UnzerCw_Language::_("Create the payment receipts during authorization"),
 					'capturing' => UnzerCw_Language::_("Create the payment receipts when the transaction is captured"),
 				),
 				'default' => 'authorization',
 			),
 			'error_display_page' => array(
				'title' => UnzerCw_Language::_("Error Page"),
 				'description' => UnzerCw_Language::_("This setting controls where the error message is shown to the customer"),
 				'type' => 'SELECT',
 				'options' => array(
					'overview' => UnzerCw_Language::_("Show the error message on the overview page"),
 					'separte' => UnzerCw_Language::_("Show the error message during checkout on a separate page"),
 				),
 				'default' => 'overview',
 			),
 		),
 		'wechatpay' => array(
			'status_authorized' => array(
				'title' => UnzerCw_Language::_("Authorized Status"),
 				'description' => UnzerCw_Language::_("This status is set when the payment was successfull and it is authorized"),
 				'type' => 'ORDERSTATUSSELECT',
 				'default' => 'authorized',
 			),
 			'status_uncertain' => array(
				'title' => UnzerCw_Language::_("Uncertain Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status for new orders that have an uncertain authorisation status"),
 				'type' => 'ORDERSTATUSSELECT',
 				'default' => 'uncertain',
 			),
 			'status_cancelled' => array(
				'title' => UnzerCw_Language::_("Cancelled Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status when an order is cancelled"),
 				'type' => 'ORDERSTATUSSELECT',
 				'options' => array(
					'no_status_change' => UnzerCw_Language::_("Dont change order status"),
 				),
 				'default' => 'cancelled',
 			),
 			'status_captured' => array(
				'title' => UnzerCw_Language::_("Captured Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status for orders that are captured either directly after the order or manually in the backend"),
 				'type' => 'ORDERSTATUSSELECT',
 				'options' => array(
					'no_status_change' => UnzerCw_Language::_("Dont change order status"),
 				),
 				'default' => 'no_status_change',
 			),
 			'send_basket' => array(
				'title' => UnzerCw_Language::_("Send Basket"),
 				'description' => UnzerCw_Language::_("Should the invoice items be transmitted to Unzer This slightly increases the processing time due to an additional request and may cause issues for certain quantity price combinations"),
 				'type' => 'SELECT',
 				'options' => array(
					'no' => UnzerCw_Language::_("Do not send"),
 					'yes' => UnzerCw_Language::_("Send Basket"),
 				),
 				'default' => 'no',
 			),
 			'send_customer' => array(
				'title' => UnzerCw_Language::_("Send Customer"),
 				'description' => UnzerCw_Language::_("Should customer data be transmitted to Unzer This slightly increases the processing time due to an additional request but may allow eg saving the payment method to the customer"),
 				'type' => 'SELECT',
 				'options' => array(
					'no' => UnzerCw_Language::_("Do not send"),
 					'yes' => UnzerCw_Language::_("Send Customer"),
 				),
 				'default' => 'no',
 			),
 			'authorizationMethod' => array(
				'title' => UnzerCw_Language::_("Authorization Method"),
 				'description' => UnzerCw_Language::_("Select the authorization method to use for processing this payment method"),
 				'type' => 'SELECT',
 				'options' => array(
					'AjaxAuthorization' => UnzerCw_Language::_("Ajax Authorization"),
 				),
 				'default' => 'AjaxAuthorization',
 			),
 			'payment_receipt' => array(
				'title' => UnzerCw_Language::_("Payment Receipts"),
 				'description' => UnzerCw_Language::_("This setting controls when the payment receipts are created"),
 				'type' => 'SELECT',
 				'options' => array(
					'authorization' => UnzerCw_Language::_("Create the payment receipts during authorization"),
 					'capturing' => UnzerCw_Language::_("Create the payment receipts when the transaction is captured"),
 				),
 				'default' => 'authorization',
 			),
 			'error_display_page' => array(
				'title' => UnzerCw_Language::_("Error Page"),
 				'description' => UnzerCw_Language::_("This setting controls where the error message is shown to the customer"),
 				'type' => 'SELECT',
 				'options' => array(
					'overview' => UnzerCw_Language::_("Show the error message on the overview page"),
 					'separte' => UnzerCw_Language::_("Show the error message during checkout on a separate page"),
 				),
 				'default' => 'overview',
 			),
 		),
 		'bcmc' => array(
			'status_authorized' => array(
				'title' => UnzerCw_Language::_("Authorized Status"),
 				'description' => UnzerCw_Language::_("This status is set when the payment was successfull and it is authorized"),
 				'type' => 'ORDERSTATUSSELECT',
 				'default' => 'authorized',
 			),
 			'status_uncertain' => array(
				'title' => UnzerCw_Language::_("Uncertain Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status for new orders that have an uncertain authorisation status"),
 				'type' => 'ORDERSTATUSSELECT',
 				'default' => 'uncertain',
 			),
 			'status_cancelled' => array(
				'title' => UnzerCw_Language::_("Cancelled Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status when an order is cancelled"),
 				'type' => 'ORDERSTATUSSELECT',
 				'options' => array(
					'no_status_change' => UnzerCw_Language::_("Dont change order status"),
 				),
 				'default' => 'cancelled',
 			),
 			'status_captured' => array(
				'title' => UnzerCw_Language::_("Captured Status"),
 				'description' => UnzerCw_Language::_("You can specify the order status for orders that are captured either directly after the order or manually in the backend"),
 				'type' => 'ORDERSTATUSSELECT',
 				'options' => array(
					'no_status_change' => UnzerCw_Language::_("Dont change order status"),
 				),
 				'default' => 'no_status_change',
 			),
 			'placeholder_size' => array(
				'title' => UnzerCw_Language::_("Element Size"),
 				'description' => UnzerCw_Language::_("How should elements from Unzer be loaded With narrow elements the element label is displayed by the store with wide elements it is loaded via javascript by Unzer The input elements are always loade"),
 				'type' => 'SELECT',
 				'options' => array(
					'wide' => UnzerCw_Language::_("Wide label from Unzer"),
 					'narrow' => UnzerCw_Language::_("Narrow label from shop"),
 				),
 				'default' => 'narrow',
 			),
 			'send_basket' => array(
				'title' => UnzerCw_Language::_("Send Basket"),
 				'description' => UnzerCw_Language::_("Should the invoice items be transmitted to Unzer This slightly increases the processing time due to an additional request and may cause issues for certain quantity price combinations"),
 				'type' => 'SELECT',
 				'options' => array(
					'no' => UnzerCw_Language::_("Do not send"),
 					'yes' => UnzerCw_Language::_("Send Basket"),
 				),
 				'default' => 'no',
 			),
 			'send_customer' => array(
				'title' => UnzerCw_Language::_("Send Customer"),
 				'description' => UnzerCw_Language::_("Should customer data be transmitted to Unzer This slightly increases the processing time due to an additional request but may allow eg saving the payment method to the customer"),
 				'type' => 'SELECT',
 				'options' => array(
					'no' => UnzerCw_Language::_("Do not send"),
 					'yes' => UnzerCw_Language::_("Send Customer"),
 				),
 				'default' => 'no',
 			),
 			'authorizationMethod' => array(
				'title' => UnzerCw_Language::_("Authorization Method"),
 				'description' => UnzerCw_Language::_("Select the authorization method to use for processing this payment method"),
 				'type' => 'SELECT',
 				'options' => array(
					'AjaxAuthorization' => UnzerCw_Language::_("Ajax Authorization"),
 				),
 				'default' => 'AjaxAuthorization',
 			),
 			'payment_receipt' => array(
				'title' => UnzerCw_Language::_("Payment Receipts"),
 				'description' => UnzerCw_Language::_("This setting controls when the payment receipts are created"),
 				'type' => 'SELECT',
 				'options' => array(
					'authorization' => UnzerCw_Language::_("Create the payment receipts during authorization"),
 					'capturing' => UnzerCw_Language::_("Create the payment receipts when the transaction is captured"),
 				),
 				'default' => 'authorization',
 			),
 			'error_display_page' => array(
				'title' => UnzerCw_Language::_("Error Page"),
 				'description' => UnzerCw_Language::_("This setting controls where the error message is shown to the customer"),
 				'type' => 'SELECT',
 				'options' => array(
					'overview' => UnzerCw_Language::_("Show the error message on the overview page"),
 					'separte' => UnzerCw_Language::_("Show the error message during checkout on a separate page"),
 				),
 				'default' => 'overview',
 			),
 		),
 	);
	}
	
	
}